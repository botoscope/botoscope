<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

//20-03-2026
final class BOTOSCOPE_PRODUCTS_META_PACK {

    protected $db;
    protected $table = 'botoscope_meta_pack';
    protected $slug = 'product_meta_pack';
    public $botoscope;
    public $meta;

    public function __construct($meta, $botoscope) {
        global $wpdb;
        $this->db = $wpdb;
        $this->table = $wpdb->prefix . $this->table;
        $this->meta = $meta;
        $this->botoscope = $botoscope;

        $this->install();

        Botoscope_Hooks::add_action('botoscope_get_sidebar_html', function ($what, $template_name, $product_id) {
            if ($what === $this->slug) {
                BOTOSCOPE_HELPER::render_html_e(BOTOSCOPE_EXT_PATH . "products/views/{$template_name}.php", [
                    'packs' => $this->get()
                ]);
            }
        });

        Botoscope_Hooks::add_action('botoscope_edit_cell', function ($what, $id, $field_key, $value) {
            if ($what === $this->slug) {
                $this->update($id, $field_key, $value);
            }
        });

        Botoscope_Hooks::add_action('botoscope_delete_row', function ($what, $row_id, $parent_row_id) {
            if ($what === $this->slug) {
                $this->delete(intval($_REQUEST['row_id']));
            }
        });

        add_action('wp_ajax_botoscope_product_create_meta_pack', function () {
            if ($this->botoscope->is_ajax_request_valid()) {
                $product_id = intval($_REQUEST['product_id']);

                if (!$product_id) {
                    wp_send_json_error(['message' => 'Wrong data']);
                }

                $res = $this->create($product_id);
                die(wp_json_encode($res));
            }
        }, 1);

        add_action('wp_ajax_botoscope_product_apply_pack', function () {
            if ($this->botoscope->is_ajax_request_valid()) {
                $pack_id = intval($_REQUEST['pack_id']);
                $product_id = intval($_REQUEST['product_id']);

                if (!$product_id || !$pack_id) {
                    wp_send_json_error(['message' => 'Wrong data']);
                }

                $res = $this->apply($pack_id, $product_id);
                die(wp_json_encode($res));
            }
        }, 1);
    }

    public function create($product_id) {
        $meta = $this->meta->get_product_meta($product_id);

        if (!empty($meta)) {
            $data = [
                'title' => /* translators: 1: product ID, 2: creation date */sprintf(esc_html__('Pack has been created from product #%1$s (%2$s)', 'botoscope'), $product_id, gmdate('Y-m-d H:i:s')),
                'content' => wp_json_encode($meta)
            ];

            $this->db->insert($this->table, $data);
            $data['id'] = $this->db->insert_id;
        }

        return $data;
    }

    public function get() {
        $sql = "SELECT * FROM `{$this->table}` ORDER BY id DESC";
        $res = $this->db->get_results($sql, ARRAY_A);
        return $res;
    }

    public function get_pack($pack_id) {
        $sql = $this->db->prepare("SELECT * FROM `{$this->table}` WHERE id = %d LIMIT 1;", intval($pack_id));
        return $this->db->get_row($sql, ARRAY_A);
    }

    public function update($id, $field_key, $value) {
        if (empty($id) || empty($field_key)) {
            return false;
        }

        return $this->db->update($this->table, [$field_key => $value], ['id' => intval($id)]);
    }

    public function delete($id) {
        return $this->db->delete($this->table, ['id' => $id]);
    }

    public function apply($pack_id, $product_id) {
        $pack = $this->get_pack($pack_id);
        $content = json_decode($pack['content'], true);

        if (!empty($content)) {
            $product_meta = $this->meta->get_product_meta($product_id);

            foreach ($content as $mp) {
                $mid = intval($mp['meta_id']);
                $exists = array_search($mid, array_column($product_meta, 'meta_id')) !== false;
                if (!$exists) {
                    $allowed_keys = ['meta_id', 'value', 'icon', 'menu_order'];
                    $this->meta->insert($product_id, array_intersect_key($mp, array_flip($allowed_keys)), $mp['type']);
                }
            }

            $this->meta->products->update_product_bot_cache($product_id);
        }

        return $content;
    }

    protected function install() {
        global $wpdb;

        if (get_option("botoscope_{$this->table}_is_installed")) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Check MySQL version for utf8mb4 support
        $mysql_version = $wpdb->db_version();
        $supports_utf8mb4 = version_compare($mysql_version, '5.5.3', '>=');

        // Force utf8mb4 if MySQL supports it
        if ($supports_utf8mb4) {
            $charset_collate = 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        } else {
            $charset_collate = $wpdb->get_charset_collate();
        }

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `title` varchar(64) NOT NULL,
        `content` text NOT NULL,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB {$charset_collate};";
        dbDelta($sql);

        // Additional safety measure: convert table after creation
        if ($supports_utf8mb4) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name is an internal class constant, not user input. wpdb->prepare() does not support table name placeholders.
            $wpdb->query("ALTER TABLE `" . esc_sql($this->table) . "` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }

        add_option("botoscope_{$this->table}_is_installed", 1);
    }
}
