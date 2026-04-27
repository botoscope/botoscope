<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

include_once 'slots_targeted.php';

/*
 * hash_key + slot_id - is edited regular slot
 * hash_key + (slot_id === 0) - is disposable slot
 */

//23-01-2026
final class BOTOSCOPE_BOOKING_SLOTS extends BOTOSCOPE_APP {

    protected $botoscope;
    protected $table_name = 'botoscope_booking_slots';
    protected $slug = 'booking_slots';
    public $slots_targeted;
    protected $data_structure = [
        'weekday' => 1,
        'start_h' => 23,
        'start_m' => 59,
        'finish_h' => 23,
        'finish_m' => 59,
    ];

    public function __construct($args = []) {
        parent::__construct($args);

        $this->slots_targeted = new BOTOSCOPE_BOOKING_SLOTS_TARGETED($args);
        $this->botoscope->allrest->add_rest_route($this->slug . '/(?P<product_id>\d+)/(?P<year>\d+)/(?P<month>\d+)/(?P<day>\d+)', [$this, 'register_route']);

        Botoscope_Hooks::add_action('botoscope_edit_cell', function ($what, $id, $key, $value) {
            if ($what === $this->slug) {
                $this->update(intval($_REQUEST['id']), sanitize_key($_REQUEST['key']), floatval($_REQUEST['value']));
            }
        });

        add_action('wp_ajax_botoscope_booking_create_slot', function () {
            if ($this->botoscope->is_ajax_request_valid()) {
                $product_id = intval($_REQUEST['product_id']);
                $weekday = intval($_REQUEST['weekday']);

                $this->create([
                    'product_id' => $product_id,
                    'weekday' => $weekday
                ]);

                $slots = $this->get_slots($product_id, $weekday);
                wp_send_json_success(array_values($slots));
            }
        }, 1);

        add_action('wp_ajax_botoscope_booking_clone_slots', function () {
            if ($this->botoscope->is_ajax_request_valid()) {
                $product_id = intval($_REQUEST['product_id']);
                $weekday = intval($_REQUEST['weekday']);
                $copy_from = intval($_REQUEST['copy_from']);

                $sql = $this->db->prepare("
            INSERT INTO {$this->table_name} 
            (product_id, weekday, start_h, start_m, finish_h, finish_m, 
             active_from, active_to, is_active, is_deleted, price, capacity)
            SELECT 
                product_id,
                %d AS weekday,
                start_h,
                start_m,
                finish_h,
                finish_m,
                active_from,
                active_to,
                is_active,
                is_deleted,
                price,
                capacity
            FROM {$this->table_name}
            WHERE product_id = %d 
              AND weekday = %d
              AND is_deleted = 0
        ", $weekday, $product_id, $copy_from);

                $this->db->query($sql);

                $slots = $this->get_slots($product_id, $weekday);
                wp_send_json_success(array_values($slots));
            }
        }, 1);

        add_action('wp_ajax_botoscope_booking_get_slots', function () {
            if ($this->botoscope->is_ajax_request_valid()) {
                $product_id = intval($_REQUEST['product_id']);
                $weekday = intval($_REQUEST['weekday']);
                $slots = $this->get_slots($product_id, $weekday);
                wp_send_json_success(array_values($slots));
            }
        }, 1);

        add_action('wp_ajax_botoscope_booking_delete_slot', function () {
            if ($this->botoscope->is_ajax_request_valid()) {
                $this->delete(intval($_REQUEST['id']));
                die('done');
            }
        }, 1);

        Botoscope_Hooks::add_action('botoscope_get_sidebar_html', function ($what, $template_name, $id) {
            if ($what === 'products' && $_REQUEST['template_name'] === 'single-product-booking-slots') {
                $data = [];
                $product_id = intval($_REQUEST['id']);
                $data['slots'] = $this->get_slots($product_id, 1);
                BOTOSCOPE_HELPER::render_html_e(BOTOSCOPE_EXT_PATH . "booking/views/{$template_name}.php", $data);
            }
        });

        add_action('wp_ajax_botoscope_booking_job_slots', function () {
            if ($this->botoscope->is_ajax_request_valid()) {
                $product_id = intval($_REQUEST['product_id']);
                $start_time = intval($_REQUEST['start_time']);
                $end_time = intval($_REQUEST['end_time']);

                $dt = new DateTime('@' . $start_time);
                $dt->setTimezone(new DateTimeZone('UTC'));
                $weekday = $dt->format('N'); // 1 (monday) ... 7 (sunday)
                $year = $dt->format('Y');
                $month = $dt->format('m');
                $day = $dt->format('d');

                $slots = $this->get_slots($product_id, $weekday, intval($year), intval($month), intval($day));
                wp_send_json_success(array_values($slots));
            }
        }, 1);
    }

    public function register_route(WP_REST_Request $request) {
        $product_id = intval($request['product_id']);
        $year = intval($request['year']);
        $month = intval($request['month']);
        $day = intval($request['day']);

        $dt = new DateTime();
        $dt->setDate($year, $month, $day);
        $weekday = intval($dt->format('N')); // 1 (Mon) - 7 (Sun)

        return $this->get_active($product_id, $weekday, $year, $month, $day);
    }

    public function is_slot_reserved(array $slot, int $year, int $month, int $day) {
        static $cache = [];
        $res = 0;
        $cache_index = "{$year}-{$month}-{$day}";

        if (!array_key_exists($cache_index, $cache)) {
            $cache[$cache_index] = $reservations = $this->botoscope->booking->get_reservations($year, $month, $day);
        } else {
            $reservations = $cache[$cache_index];
        }

        if (!empty($reservations)) {
            foreach ($reservations as $reservation) {
                if ((isset($slot['hash_key']) && isset($reservation['hash_key']) && $slot['hash_key'] === $reservation['hash_key']) && intval($slot['slot_id']) === 0) {
                    $res = 1;
                    break;
                } else {
                    if (intval($reservation['day']) === $day && intval($reservation['month']) === $month && intval($reservation['year']) === $year) {
                        if (intval($reservation['slot_id']) === intval($slot['id'])) {
                            if (intval($reservation['start_h']) === intval($slot['start_h']) && intval($reservation['start_m']) === intval($slot['start_m']) && intval($reservation['finish_h']) === intval($slot['finish_h']) && intval($reservation['finish_m']) === intval($slot['finish_m'])) {
                                $res = 1;
                                break;
                            }
                        }
                    }
                }
            }
        }

        return $res;
    }

    public function get_slot_reservation_capacity($slot, $year, $month, $day) {
        static $cache = [];
        $res = 0;
        $cache_index = "{$year}-{$month}-{$day}";

        if (!array_key_exists($cache_index, $cache)) {
            $cache[$cache_index] = $reservations = $this->botoscope->booking->get_reservations($year, $month, $day);
        } else {
            $reservations = $cache[$cache_index];
        }

        if (!empty($reservations)) {
            foreach ($reservations as $reservation) {
                if ($slot['hash_key'] === $reservation['hash_key'] && intval($slot['slot_id']) === 0) {
                    $res += 1;
                } else {
                    if (intval($reservation['day']) === intval($day) && intval($reservation['month']) === intval($month) && intval($reservation['year']) === intval($year)) {
                        if (intval($reservation['slot_id']) === intval($slot['id'])) {
                            if (intval($reservation['start_h']) === intval($slot['start_h']) && intval($reservation['start_m']) === intval($slot['start_m']) && intval($reservation['finish_h']) === intval($slot['finish_h']) && intval($reservation['finish_m']) === intval($slot['finish_m'])) {
                                $res += 1;
                            }
                        }
                    }
                }
            }
        }

        return $res;
    }

    public function get_reservation($slot, $year, $month, $day) {
        static $cache = [];
        $res = null;
        $cache_index = "{$year}-{$month}-{$day}";

        if (!array_key_exists($cache_index, $cache)) {
            $cache[$cache_index] = $reservations = $this->botoscope->booking->get_reservations($year, $month, $day);
        } else {
            $reservations = $cache[$cache_index];
        }

        if (!empty($reservations)) {
            foreach ($reservations as $reservation) {
                if ((array_key_exists('hash_key', $slot) && $slot['hash_key'] === $reservation['hash_key']) && intval($slot['slot_id']) === 0) {
                    $res = $reservation;
                    break;
                } else {
                    if (intval($reservation['day']) === intval($day) && intval($reservation['month']) === intval($month) && intval($reservation['year']) === intval($year)) {
                        if (intval($reservation['slot_id']) === intval($slot['id'])) {
                            if (intval($reservation['start_h']) === intval($slot['start_h']) && intval($reservation['start_m']) === intval($slot['start_m']) && intval($reservation['finish_h']) === intval($slot['finish_h']) && intval($reservation['finish_m']) === intval($slot['finish_m'])) {
                                $res = $reservation;
                                break;
                            }
                        }
                    }
                }
            }
        }

        return $res;
    }

    public function get_slots($product_id, $weekday, $year = 0, $month = 0, $day = 1) {
        $sql = $this->db->prepare(
                "SELECT * FROM `{$this->table_name}` WHERE product_id = %d AND weekday = %d AND is_deleted=0 ORDER BY start_h, start_m ASC",
                $product_id,
                $weekday
        );

        $res = $this->db->get_results($sql, ARRAY_A) ?: [];

        //job slots
        if ($year && $month && !empty($res)) {
            foreach ($res as $k => $slot) {
                $slot_id = intval($slot['id']);
                $targeted_slot = $this->slots_targeted->get_slot($slot_id, $year, $month, $day);

                if (!empty($targeted_slot)) {
                    foreach ($targeted_slot as $key => $value) {
                        if (in_array($key, ['start_h', 'start_m', 'finish_h', 'finish_m', 'is_active', 'hash_key', 'price', 'capacity'])) {
                            if (!is_null($value)) {
                                $res[$k][$key] = $value;
                            }
                        }
                    }
                }
            }

            //+++

            $disposable_slots = $this->slots_targeted->get_disposable_slots($product_id, $year, $month, $day);
            $res = array_merge($res, $disposable_slots);
        }

        //+++

        if (!empty($res) && $year && $month && $day) {
            foreach ($res as $skey => $slot) {

                $capacity = intval($slot['capacity']);

                if ($capacity > 1) {
                    $res[$skey]['capacity_used'] = $capacity_used = $this->get_slot_reservation_capacity($slot, $year, $month, $day);

                    if ($capacity_used >= $capacity) {
                        $res[$skey]['is_reserved'] = 1;
                    }
                } else {
                    $res[$skey]['is_reserved'] = $this->is_slot_reserved($slot, $year, $month, $day);

                    if ($res[$skey]['is_reserved']) {
                        $r = $this->get_reservation($slot, $year, $month, $day);
                        $res[$skey]['order_id'] = $r['order_id'] ?? 0;
                    }
                }
            }
        }

        return $this->sort_by_time($res);
    }

    public function get_slot_by_id($slot_id) {
        return $this->db->get_row($this->db->prepare(
                                "SELECT * FROM {$this->table_name} WHERE id = %d LIMIT 1",
                                $slot_id
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

    public function update($id, $field_key, $value, $all_sent_data = []) {
        parent::update($id, $field_key, $value);
    }

    public function get_active($product_id, $weekday, $year, $month, $day) {
        $slots = $this->get_slots($product_id, $weekday, intval($year), intval($month), intval($day));

        $active_slots = array_filter($slots, function ($slot) {
            return intval($slot['is_active']) && !intval($slot['is_deleted'] ?? 0);
        });

        return array_values($active_slots);
    }

    public function delete($id, $conditions = []) {
        $this->update($id, 'is_deleted', 1);
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
        weekday smallint(1) NOT NULL,
        start_h tinyint(2) NOT NULL,
        start_m tinyint(2) NOT NULL,
        finish_h tinyint(2) NOT NULL,
        finish_m tinyint(2) NOT NULL,
        active_from bigint(20) DEFAULT NULL COMMENT 'for season works',
        active_to bigint(20) DEFAULT NULL COMMENT 'for season works',
        is_active tinyint(1) NOT NULL DEFAULT 1,
        is_deleted tinyint(4) NOT NULL DEFAULT 0,
        price double NOT NULL DEFAULT -1,
        capacity int(11) NOT NULL DEFAULT 1,
        PRIMARY KEY (id),
        KEY product_id (product_id),
        KEY weekday (weekday)
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
