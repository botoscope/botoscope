<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

//23-01-2026
final class BOTOSCOPE_BOOKING_SLOTS_TARGETED extends BOTOSCOPE_APP {

    protected $botoscope;
    protected $table_name = 'botoscope_booking_slots_targeted';
    protected $slug = 'booking_slots_targeted';
    protected $data_structure = [
        'start_h' => 23,
        'start_m' => 59,
        'finish_h' => 23,
        'finish_m' => 59,
    ];

    public function __construct($args = []) {
        parent::__construct($args);

        $this->botoscope->allrest->add_rest_route($this->slug, [$this, 'register_route']);

        Botoscope_Hooks::add_action('botoscope_edit_cell', function ($what, $id, $key, $value) {
            if ($what === $this->slug) {
                $hash_key = sanitize_key($_REQUEST['additional_params']['hash_key']);
                $timestamp = intval($_REQUEST['additional_params']['timestamp']);

                $product_id = 0;
                if (isset($_REQUEST['additional_params']['product_id'])) {
                    $product_id = intval($_REQUEST['additional_params']['product_id']);
                }

                $this->update_slot($id, $key, $value, $timestamp, $product_id, $hash_key);
            }
        });

        add_action('wp_ajax_botoscope_booking_create_disposable_slot', function () {
            if ($this->botoscope->is_ajax_request_valid()) {
                $product_id = intval($_REQUEST['product_id']);

                if (!$product_id) {
                    die('not created');
                }

                $timestamp = intval($_REQUEST['timestamp']);
                $data = [];

                $dt = new DateTime('@' . $timestamp);
                $dt->setTimezone(new DateTimeZone('UTC')); //UTC 0

                $data['target_year'] = $dt->format('Y');
                $data['target_month'] = $dt->format('m');
                $data['target_day'] = $dt->format('d');
                $data['product_id'] = $product_id;
                $data['hash_key'] = md5(time());
                $data['is_disposal'] = 1;

                $this->create($data);
                wp_send_json_success($data);
            }
        }, 1);

        add_action('wp_ajax_botoscope_booking_get_all_virtual_products', function () {
            if ($this->botoscope->is_ajax_request_valid()) {
                $args = [
                    'post_type' => 'product',
                    'post_status' => 'publish',
                    'posts_per_page' => -1,
                    'tax_query' => [
                        [
                            'taxonomy' => 'product_type',
                            'field' => 'slug',
                            'terms' => ['botoscope_simple_virtual'],
                            'operator' => 'IN'
                        ],
                    ],
                ];

                $query = new WP_Query($args);
                $products = [];

                if ($query->have_posts()) {
                    while ($query->have_posts()) {
                        $query->the_post();
                        $product_id = get_the_ID();
                        $title = html_entity_decode(get_the_title($product_id), ENT_QUOTES, 'UTF-8');
                        ;

                        $products[] = [
                            'id' => $product_id,
                            'title' => $title,
                        ];
                    }
                    wp_reset_postdata();
                }

                wp_send_json_success(array_values($products));
            }
        }, 1);
    }

    public function register_route(WP_REST_Request $request) {
        return $this->get_active();
    }

    public function get_slot($slot_id, $target_year, $target_month, $target_day) {
        return $this->db->get_row($this->db->prepare(
                                "SELECT * FROM {$this->table_name} WHERE slot_id = %d AND target_year = %d "
                                . "AND target_month = %d AND target_day = %d LIMIT 1",
                                $slot_id,
                                $target_year,
                                $target_month,
                                $target_day
                        ), ARRAY_A);
    }

    public function update($id, $field_key, $value, $all_sent_data = []) {
        parent::update($id, $field_key, $value);
    }

    public function get_active() {
        $res = [];
        //todo
        return $res;
    }

    public function delete($id, $conditions = []) {
        $this->update($id, 'is_deleted', 1);
    }

    public function get_slot_by_hash($hash_key) {
        return $this->db->get_row($this->db->prepare(
                                "SELECT * FROM {$this->table_name} WHERE hash_key = %s LIMIT 1",
                                $hash_key
                        ), ARRAY_A);
    }

    public function get_disposable_slots($product_id, $target_year, $target_month, $target_day) {
        return $this->db->get_results($this->db->prepare(
                                "SELECT * FROM {$this->table_name} WHERE product_id = %d AND target_year = %d "
                                . "AND target_month = %d AND target_day = %d AND is_disposal=1",
                                $product_id,
                                $target_year,
                                $target_month,
                                $target_day
                        ), ARRAY_A);
    }

    public function update_slot($slot_id, $field_key, $field_value, $timestamp, $product_id, $hash_key) {

        $allowed_keys = ['start_h', 'start_m', 'finish_h', 'finish_m', 'is_active', 'price', 'capacity'];
        if (!in_array($field_key, $allowed_keys, true)) {
            return false;
        }

        usleep(2000);

        $dt = new DateTime('@' . $timestamp);
        $dt->setTimezone(new DateTimeZone('UTC')); //UTC 0

        $target_year = $dt->format('Y');
        $target_month = $dt->format('m');
        $target_day = $dt->format('d');

        if ($hash_key) {
            $this->get_slot_by_hash($hash_key);
        } else {
            $hash_key = md5(time());
            $this->db->insert(
                    $this->table_name,
                    [
                        'slot_id' => $slot_id,
                        'product_id' => $product_id,
                        'hash_key' => $hash_key,
                        'timestamp' => $timestamp,
                        'target_year' => $target_year,
                        'target_month' => $target_month,
                        'target_day' => $target_day,
                    ],
                    [
                        '%d', '%d', '%s', '%d', '%d', '%d', '%d'
                    ]
            );

            $row_id = $this->db->insert_id;
        }

        // Let's update the specified field
        $this->db->update(
                $this->table_name,
                [$field_key => $field_value],
                ['hash_key' => $hash_key],
                [is_numeric($field_value) ? '%f' : '%s'],
                ['%s']
        );

        return $hash_key;
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
        product_id int(11) DEFAULT NULL,
        slot_id int(11) NOT NULL,
        hash_key varchar(32) NOT NULL,
        timestamp bigint(20) DEFAULT NULL,
        target_day tinyint(2) NOT NULL,
        target_month tinyint(2) NOT NULL,
        target_year int(4) NOT NULL,
        start_h bigint(20) DEFAULT NULL,
        start_m bigint(20) DEFAULT NULL,
        finish_h bigint(20) DEFAULT NULL,
        finish_m bigint(20) DEFAULT NULL,
        is_active tinyint(1) DEFAULT NULL,
        is_disposal tinyint(1) NOT NULL DEFAULT 0,
        price double DEFAULT NULL,
        capacity int(11) NOT NULL DEFAULT 1,
        PRIMARY KEY (id),
        UNIQUE KEY hash_key (hash_key),
        KEY slot_id (slot_id),
        KEY product_id (product_id),
        KEY is_active (is_active)
    ) ENGINE=InnoDB {$charset_collate} COMMENT='targeted to specific day';";

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
