<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

include_once 'classes/slots.php';

//08-04-2026
final class BOTOSCOPE_BOOKING extends BOTOSCOPE_APP {

    protected $botoscope;
    public $slots;
    protected $table_name = 'botoscope_booking_reservations';
    protected $slug = 'booking';
    protected $data_structure = [
        'product_id' => 0,
        'slot_id' => 0,
        'start_h' => 23,
        'start_m' => 59,
        'finish_h' => 23,
        'finish_m' => 59,
        'day' => 0,
        'month' => 0,
        'year' => 0,
        'hash_key' => 0,
        'order_id' => 0,
    ];

    public function __construct($args = []) {
        parent::__construct($args);

        if (botoscope_is_no_cart()) {
            return false;
        }

        $this->slots = new BOTOSCOPE_BOOKING_SLOTS($args);
        $this->botoscope->allrest->add_rest_route($this->slug . '/(?P<year>\d+)/(?P<month>\d+)', [$this, 'register_route']);

        Botoscope_Hooks::add_action('botoscope_panel_tabs', function ($tabs) {
            $tabs[$this->slug] = esc_html__('Booking', 'botoscope');
            return $tabs;
        });

        add_action("botoscope_{$this->slug}_tab_icon", function () {
            return 'calendar';
        });

        add_action('wp_ajax_botoscope_booking_get_reservations', function () {
            if ($this->botoscope->is_ajax_request_valid()) {
                $timestamp = intval($_REQUEST['start_time']);

                $dt = new DateTime('@' . $timestamp);
                $dt->setTimezone(new DateTimeZone('UTC')); //UTC 0

                $year = $dt->format('Y');
                $month = $dt->format('m');
                $day = $dt->format('d');

                $reservations = $this->get_reservations($year, $month, $day);
                wp_send_json_success(array_values($reservations));
            }
        }, 1);

        add_action('wp_ajax_botoscope_booking_get_reservation_counts', function () {
            if ($this->botoscope->is_ajax_request_valid()) {
                $timestamp = intval($_REQUEST['start_time']);

                $dt = new DateTime('@' . $timestamp);
                $dt->setTimezone(new DateTimeZone('UTC')); //UTC 0

                $year = $dt->format('Y');
                $month = $dt->format('m');

                $count_data = $this->get_reservation_counts($year, $month);
                wp_send_json_success($count_data);
            }
        }, 1);

        add_action('wp_ajax_botoscope_booking_get_users', function () {
            if ($this->botoscope->is_ajax_request_valid()) {

                $users = $this->botoscope->users->get_bot_users();
                if (!empty($users)) {
                    foreach ($users as $key => $u) {
                        $users[$key]['id'] = intval($u['ID']);
                        $users[$key]['oid'] = intval($u['ID']);
                        unset($users[$key]['ID']);
                    }
                }

                wp_send_json_success($users);
            }
        }, 1);

        add_action('botoscope_booking_make_reservation', function ($data) {
            $this->create($data);
        }, 10, 1);

        add_action('woocommerce_before_order_itemmeta', function ($item_id, $item, $product) {
            if ($product) {
                if ($product->get_type() === 'botoscope_simple_virtual') {
                    $order_id = $item->get_order_id();
                    $reservation_id = get_post_meta($order_id, '_botoscope_reservation_id', true);

                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML built internally from WP date formatting functions
                    echo $this->get_order_item_timeslot($reservation_id);
                }
            }
        }, 10, 3);

        add_action('wp_ajax_botoscope_booking_on_off_get_state', function () {
            if ($this->botoscope->is_ajax_request_valid()) {
                $state = $this->botoscope->controls->get_option('booking_on_off_state');
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- AJAX response, plain text value from plugin options
                wp_die($state);
            }
        }, 1);

        add_action('wp_ajax_botoscope_booking_on_off_set_state', function () {
            if ($this->botoscope->is_ajax_request_valid()) {
                $value = intval($_REQUEST['value']);
                $this->botoscope->controls->update('booking_on_off_state', 'value', $value);
                $this->botoscope->reset_cache('controls');
                wp_die(intval($value));
            }
        }, 1);

        add_action('wp_ajax_botoscope_booking_reserve_slot', function () {
            if ($this->botoscope->is_ajax_request_valid()) {
                global $WOOCS;

                $currency = get_woocommerce_currency();

                if ($WOOCS) {
                    $currency = $WOOCS->default_currency;
                }

                //$data['start_h'] = intval($_REQUEST['start_h']);
                //$data['start_m'] = intval($_REQUEST['start_m']);
                //$data['finish_h'] = intval($_REQUEST['finish_h']);
                //$data['finish_m'] = intval($_REQUEST['finish_m']);

                $user_id = intval($_REQUEST['user_id']);
                $product_id = intval($_REQUEST['product_id']);
                $slot_id = intval($_REQUEST['slot_id']);
                $hash_key = sanitize_key($_REQUEST['hash_key']);
                $timestamp = intval($_REQUEST['start_time']);
                $dt = new DateTime('@' . $timestamp);
                $dt->setTimezone(new DateTimeZone('UTC')); //UTC 0
                $year = $dt->format('Y');
                $month = $dt->format('m');
                $day = $dt->format('d');

                $first_name = get_user_meta($user_id, 'first_name', true);
                $last_name = get_user_meta($user_id, 'last_name', true);
                $chat_id = get_user_meta($user_id, 'chat_id', true);

                $slot_data = $this->slots->get_slot_by_id($slot_id);

                if (!empty($hash_key)) {
                    $hashed_slot_data = $this->slots->slots_targeted->get_slot_by_hash($hash_key);

                    if ($hashed_slot_data) {
                        $fields = ['start_h', 'start_m', 'finish_h', 'finish_m', 'price'];

                        foreach ($fields as $field) {
                            if (!empty($hashed_slot_data[$field]) || strval($hashed_slot_data[$field]) === '0') {
                                $slot_data[$field] = $hashed_slot_data[$field];
                            }
                        }
                    }
                }

                //+++

                $product = wc_get_product($product_id);
                $total_amount = $product->get_price();

                $custom_price = 0;
                if (floatval($slot_data['price']) > 0) {
                    $custom_price = floatval($slot_data['price']);
                    $total_amount = $custom_price;
                }

                //+++

                $data = [
                    'payment_method_title' => 'swift',
                    'payment_method' => 'swift',
                    'billing' => [
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'phone' => '0',
                    ],
                    'items' => [
                        $product_id => [
                            'id' => $product_id,
                            'quantity' => 1,
                            'additional' => [
                                'year' => $year,
                                'month' => $month,
                                'day' => $day,
                                'slot_id' => $slot_id,
                                'hash_key' => $hash_key,
                                'custom_price' => $custom_price,
                            ],
                        ],
                    ],
                    'coupon_code' => '',
                    'shipping_address' => '',
                    'shipping_way' => '',
                    'shipping_amount' => 0,
                    'chat_id' => $chat_id,
                    'total_amount' => $total_amount,
                    'currency' => $currency,
                    'discount_amount' => 0,
                    'bot_name' => 'botoscope',
                    'discount_reason' => '',
                    'order_note' => esc_html__('Booking created manually by administrator', 'botoscope'),
                    'user_id' => $user_id
                ];

                $this->botoscope->rest_orders->make_order($data);
                wp_send_json_success($data);
            }
        }, 1);

        //+++

        $cleanup = function (int $order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                $reservation_id = (int) $order->get_meta('_botoscope_reservation_id', true);
                if ($reservation_id > 0) {
                    parent::delete($reservation_id);
                }
            }
        };

        add_action('woocommerce_order_status_changed', function ($order_id, $old_status, $new_status, $order) use ($cleanup) {
            if ($new_status === 'cancelled') {
                $cleanup((int) $order_id);
            }
        }, 10, 4);

        add_action('wp_trash_post', function ($post_id) use ($cleanup) {
            if (get_post_type($post_id) !== 'shop_order') {
                return;
            }

            $cleanup((int) $post_id);
        }, 10, 1);

        add_action('before_delete_post', function ($post_id) use ($cleanup) {
            if (get_post_type($post_id) !== 'shop_order') {
                return;
            }

            $cleanup((int) $post_id);
        }, 10, 1);

        add_filter('botoscope_lang', function ($lang) {
            $lang['booking_slots_for'] = esc_html__('Booking slots for product', 'botoscope');
            $lang['start_h'] = esc_html__('Start hour', 'botoscope');
            $lang['start_m'] = esc_html__('Start minute', 'botoscope');
            $lang['finish_h'] = esc_html__('Finish hour', 'botoscope');
            $lang['finish_m'] = esc_html__('Finish minute', 'botoscope');
            $lang['mon'] = esc_html__('Mon', 'botoscope');
            $lang['tue'] = esc_html__('Tue', 'botoscope');
            $lang['wed'] = esc_html__('Wed', 'botoscope');
            $lang['thu'] = esc_html__('Thu', 'botoscope');
            $lang['fri'] = esc_html__('Fri', 'botoscope');
            $lang['sat'] = esc_html__('Sat', 'botoscope');
            $lang['sun'] = esc_html__('Sun', 'botoscope');
            $lang['hold'] = esc_html__('Hold', 'botoscope');
            $lang['hold_customer'] = esc_html__('Hold customer to slot', 'botoscope');
            $lang['booking_is_on'] = esc_html__('Booking system is on', 'botoscope');
            $lang['booking_is_off'] = esc_html__('Booking system is off', 'botoscope');
            $lang['capacity'] = esc_html__('Capacity', 'botoscope');
            return $lang;
        });
    }

    public function register_route(WP_REST_Request $request) {
        $year = intval($request['year']);
        $month = intval($request['month']);
        return $this->get_active($year, $month);
    }

    public function get_reservations($year, $month, $day) {
        $sql = $this->db->prepare(
                "SELECT * FROM `{$this->table_name}` WHERE day = %d AND month = %d AND year = %d ORDER BY start_h, start_m ASC",
                $day,
                $month,
                $year
        );

        $res = $this->db->get_results($sql, ARRAY_A) ?: [];
        $res = $this->sort_by_time($res);

        if (!empty($res)) {
            foreach ($res as $k => $item) {
                $item['product_title'] = get_post_field('post_title', $item['product_id']);
                $res[$k] = $item;
            }
        }

        return $res;
    }

    public function get_reservation($id) {
        return $this->db->get_row($this->db->prepare(
                                "SELECT * FROM {$this->table_name} WHERE id = %d LIMIT 1",
                                $id
                        ), ARRAY_A);
    }

    private function sort_by_time($res) {
        usort($res, function ($a, $b) {
            $a_time = intval($a['start_h']) * 60 + intval($a['start_m']);
            $b_time = intval($b['start_h']) * 60 + intval($b['start_m']);
            return $a_time <=> $b_time;
        });

        return $res;
    }

    public function get_reservation_counts($year, $month) {

        $sql = $this->db->prepare(
                "SELECT * FROM `{$this->table_name}` WHERE year = %d AND month = %d",
                $year,
                $month
        );

        $res = [];
        $reservations = $this->db->get_results($sql, ARRAY_A) ?: [];

        if (!empty($reservations)) {
            foreach ($reservations as $r) {
                if (!isset($res[$r['day']])) {
                    $res[$r['day']] = 0;
                }

                $res[$r['day']] += 1;
            }
        }

        return $res;
    }

    public function get_order_item_timeslot($reservation_id) {
        if ($reservation_id) {
            $reservation = $this->get_reservation($reservation_id);
            if ($reservation) {
                $date_format = get_option('date_format');
                $time_format = get_option('time_format');

                $start = DateTime::createFromFormat(
                        'Y-n-j H:i',
                        sprintf('%04d-%02d-%02d %02d:%02d',
                                $reservation['year'],
                                $reservation['month'],
                                $reservation['day'],
                                $reservation['start_h'],
                                $reservation['start_m']
                        )
                );

                $end = DateTime::createFromFormat(
                        'Y-n-j H:i',
                        sprintf('%04d-%02d-%02d %02d:%02d',
                                $reservation['year'],
                                $reservation['month'],
                                $reservation['day'],
                                $reservation['finish_h'],
                                $reservation['finish_m']
                        )
                );

                if ($start && $end) {
                    $date_str = $start->format($date_format);
                    $time_str = $start->format($time_format) . '–' . $end->format($time_format);

                    return "<div style='color: #0073aa; font-style: italic;'>📅 {$date_str} 🕓 {$time_str}</div>";
                }
            }
        }
    }

    public function update($id, $field_key, $value, $all_sent_data = []) {
        parent::update($id, $field_key, $value);
    }

    public function create($data = []) {
        return parent::create($data)['id'];
    }

    public function get_active($year, $month) {
        $sql = $this->db->prepare(
                "SELECT * FROM `{$this->table_name}` WHERE year = %d AND month = %d ORDER BY day ASC, start_h, start_m ASC",
                $year,
                $month
        );

        $res = $this->db->get_results($sql, ARRAY_A) ?: [];
        return $this->sort_by_time($res);
    }

    public function delete($id, $conditions = []) {
        $reservation = $this->get_reservation($id);
        $order_id = $reservation['order_id'];
        if ($order_id > 0) {
            $order = wc_get_order($order_id);
            if ($order) {
                $order->update_status('cancelled', esc_html__('cancelled by botoscope', 'botoscope'));
            }
        }
        parent::delete($id);
    }

    public function draw_content($counter) {
        if (botoscope_is_no_cart()) {
            return false;
        }
        ?>
        <section id="botoscope-<?php echo esc_attr($this->slug) ?>" <?php if ($counter === 0): ?>class="content-current"<?php endif; ?>>

            <ul id="botoscope-booking-tabs" class="botoscope-tabs">
                <li><a href="#" data-tab="reservations" class="botoscope-button selected"><?php esc_html_e('Reservations', 'botoscope') ?></a></li>
                <li><a href="#" data-tab="slots" class="botoscope-button"><?php esc_html_e('Runtime slots', 'botoscope') ?></a></li>
                <li style="margin-left: auto;" id="botoscope-booking-on-off-container"></li>
            </ul>

            <?php if (is_botoscope_free() && is_botoscope_connected()): ?>

                <div class="bs-warning-box"><p><?php
                        /* translators: %s: number of booking slots */
                        printf(esc_html__('Free version: only the first %s booking slots are available in your Telegram store', 'botoscope'), 4)
                        ?></p></div>

            <?php endif; ?>

            <div class="form-body mt-4 botoscope-tab-container">

                <div class="row">
                    <div class="col-lg-4">
                        <div class="border border-3 p-4 rounded product-sidebar">

                            <div id="booking_calendar_nav_reservations" class="booking-calendar"></div>

                        </div>
                    </div>

                    <div class="col-lg-8">
                        <div class="border border-3 p-4 rounded product-sidebar">

                            <h3><?php esc_html_e('Reservations', 'botoscope') ?></h3>
                            <input type="search" value="" style="width: 100%; box-sizing: border-box;" id="botoscope-booking-reservations-search" /><br>
                            <div id="botoscope-<?php echo esc_attr($this->slug) ?>-w"></div>

                        </div>
                    </div>
                </div>
            </div>

            <div class="form-body mt-4 botoscope-tab-container" style="display: none;">
                <div class="row">
                    <div class="col-lg-4">
                        <div class="border border-3 p-4 rounded product-sidebar">

                            <div id="booking_calendar_nav_slots" class="booking-calendar"></div>

                        </div>
                    </div>

                    <div class="col-lg-8">
                        <div class="border border-3 p-4 rounded product-sidebar">

                            <h3><?php esc_html_e('Slots', 'botoscope') ?></h3>

                            <div id="botoscope-booking_slots_targeted-w"></div>
                            <br>
                            <a href="javascript: void(0);" id="botoscope_create_booking_slots_targeted_disposable" class="button button-primary"><?php esc_html_e('Create disposable slot', 'botoscope') ?></a><br>
                        </div>
                    </div>
                </div>
            </div>

        </section>
        <?php
    }

    protected function install() {
        global $wpdb;

        if (get_option("botoscope_{$this->table_name}_is_installed")) {
            return;
        }

        // Check MySQL version for utf8mb4 support
        $mysql_version = $wpdb->db_version();
        $supports_utf8mb4 = version_compare($mysql_version, '5.5.3', '>=');

        // Force utf8mb4 if MySQL supports it
        if ($supports_utf8mb4) {
            $charset_collate = 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        } else {
            $charset_collate = $wpdb->get_charset_collate();
        }

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
        id int(11) NOT NULL AUTO_INCREMENT,
        product_id int(11) NOT NULL,
        slot_id int(11) NOT NULL,
        start_h tinyint(2) NOT NULL,
        start_m tinyint(2) NOT NULL,
        finish_h tinyint(2) NOT NULL,
        finish_m tinyint(2) NOT NULL,
        day tinyint(2) NOT NULL,
        month tinyint(2) NOT NULL,
        year int(4) NOT NULL,
        hash_key varchar(32) NOT NULL,
        order_id int(11) DEFAULT NULL,
        PRIMARY KEY (id),
        KEY product_id (product_id),
        KEY slot_id (slot_id),
        KEY year (year),
        KEY month (month),
        KEY day (day)
    ) ENGINE=InnoDB {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Additional safety measure: convert table after creation
        if ($supports_utf8mb4) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name is an internal class constant, not user input. wpdb->prepare() does not support table name placeholders.
            $wpdb->query("ALTER TABLE `" . esc_sql($this->table_name) . "` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }

        add_option("botoscope_{$this->table_name}_is_installed", 1);
    }
}
