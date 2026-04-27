<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

include_once 'meta_pack.php';

//17-03-2026
final class BOTOSCOPE_PRODUCTS_META {

    protected $db;
    protected $table = 'botoscope_meta';
    protected $table_products = 'botoscope_meta_products';
    protected $slug = 'product_meta';
    public $products;
    public $botoscope;
    protected $meta_pack;
    protected $meta_types = ['string', 'number', 'boolean', 'taxonomy', 'calendar'];

    public function __construct($products, $botoscope) {
        global $wpdb;
        $this->db = $wpdb;
        $this->table = $wpdb->prefix . $this->table;
        $this->table_products = $wpdb->prefix . $this->table_products;
        $this->products = $products;
        $this->botoscope = $botoscope;

        if ($this->botoscope->no_bot) {
            $this->meta_types = array_values(array_diff($this->meta_types, ['taxonomy', 'calendar'])); // ['string','number','boolean'];
        }

        $this->meta_pack = new BOTOSCOPE_PRODUCTS_META_PACK($this, $this->botoscope);

        $this->install();

        $this->register_routes();

        Botoscope_Hooks::add_action('botoscope_get_sidebar_html', function ($what, $template_name, $product_id) {
            if ($what === $this->slug) {
                $default_lang = $this->get_default_language();
                $active_langs = $this->botoscope->controls->get_active_languages();

                BOTOSCOPE_HELPER::render_html_e(BOTOSCOPE_EXT_PATH . "products/views/{$template_name}.php", [
                    'product_id' => $product_id,
                    'gallery' => $this->get(),
                    'exclude' => $this->get_product_meta_ids($product_id),
                    'meta_types' => $this->meta_types,
                    'default_lang' => $default_lang,
                    'active_langs' => $active_langs,
                    'current_language' => $this->get_current_language(),
                    'langs' => array_intersect_key($this->botoscope->languages, array_flip(array_merge($active_langs, [$default_lang])))
                ]);
            }
        });

        Botoscope_Hooks::add_action('botoscope_edit_cell', function ($what, $id, $field_key, $value) {
            if ($what === 'products_meta_gallery') {
                if ($this->get_current_language() !== $this->get_default_language()) {
                    $this->botoscope->translations->update_app_field($id, $field_key, $value, $this->get_current_language());
                    $this->update_bot_cache();
                } else {
                    $this->update($id, $field_key, $value);
                }
            }

            if ($what === 'product_meta') {
                $this->update_product_meta($id, $field_key, $value);
            }

            if ($what === 'product_meta_menu_order') {
                $mp_ids = explode(',', $value);
                $mp_ids = array_map('intval', $mp_ids);

                if (!empty($mp_ids)) {
                    foreach ($mp_ids as $pos => $mpid) {
                        $this->db->update($this->table_products, ['menu_order' => $pos], ['id' => intval($mpid)]);
                    }

                    $this->products->update_product_bot_cache($id);
                }
            }
        });

        Botoscope_Hooks::add_action('botoscope_add_row', function ($what, $parent_row_id, $content) {
            $res = null;

            if ($what === $this->slug) {
                $res = $this->create();
            }

            return $res;
        });

        Botoscope_Hooks::add_action('botoscope_get_page_data', function ($what, $page_num, $order_by, $order, $search) {
            $res = [];

            if ($what === $this->slug) {
                $res = $this->get_product_meta(intval($_REQUEST['more']['product_id']));
            }

            if ($what === 'products_meta_gallery') {
                $exclude = [];
                if (isset($_REQUEST['more'])) {
                    //fix 21-03-2025 - show all rows
                    //$exclude = $this->get_product_meta_ids(intval($_REQUEST['more']['product_id']));
                }

                $res = $this->get($exclude);
            }

            return $res;
        });

        Botoscope_Hooks::add_action('botoscope_delete_row', function ($what, $row_id, $parent_row_id) {
            if ($what === $this->slug) {
                $this->delete_product_meta($row_id, $parent_row_id, intval($_REQUEST['meta_id']));
                $this->products->update_product_bot_cache($parent_row_id);
            }
        });

        add_action('wp_ajax_botoscope_product_append_meta', function () {
            if ($this->botoscope->is_ajax_request_valid()) {
                $product_id = intval($_REQUEST['product_id']);
                $meta_id = intval($_REQUEST['meta_id']);

                if (!$product_id || !$meta_id) {
                    wp_send_json_error(['message' => 'Wrong data']);
                }

                $meta = $this->get_meta($meta_id);
                $value = $meta['default_value'] ?? '';

                $this->insert($product_id, [
                    'meta_id' => $meta_id,
                    'value' => $value,
                    'icon' => $meta['icon']
                        ], $meta['type']);

                $this->products->update_product_bot_cache($product_id);
                wp_send_json_success(['message' => 'Meta added successfully']);
            }
        }, 1);

        add_action('wp_ajax_botoscope_product_get_meta', function () {
            if ($this->botoscope->is_ajax_request_valid()) {
                $product_id = intval($_REQUEST['product_id']);

                if (!$product_id) {
                    wp_send_json_error(['message' => 'Wrong data']);
                }

                $res = $this->get_product_meta($product_id);
                die(wp_json_encode($res));
            }
        }, 1);

        add_action('wp_ajax_botoscope_product_create_meta', function () {
            if ($this->botoscope->is_ajax_request_valid()) {
                $this->create();
                wp_send_json_success(['message' => 'Meta created successfully']);
            }
        }, 1);

        add_action("wp_ajax_botoscope_products_meta_gallery_set_current_language", function () {
            if ($this->botoscope->is_ajax_request_valid()) {
                $this->botoscope->storage->set_val("products_meta_gallery_selected_language", sanitize_text_field($_REQUEST['language']));
            }
        }, 1);

        add_action("wp_ajax_botoscope_delete_meta", function () {
            if ($this->botoscope->is_ajax_request_valid()) {
                $meta_id = intval($_REQUEST['meta_id']);
                if ($meta_id > 0) {
                    global $wpdb;
                    $this->db->delete($this->table, ['id' => $meta_id]);
                    $this->db->delete($this->table_products, ['meta_id' => $meta_id]);
                    $this->db->delete($wpdb->prefix . 'postmeta', ['meta_key' => 'botoscope_meta_' . $meta_id]);
                }
            }
        }, 1);
    }

    public function register_routes() {
        $instance = $this;
        $this->botoscope->allrest->add_rest_route('/meta', function (WP_REST_Request $request) use ($instance) {
            return array_map(function ($item) {
                $item['id'] = (int) $item['id'];
                $item['unit_of_measurement'] = $item['unit_of_measurement'] ?? '';

                if ($item['type'] === 'number') {
                    $item['default_value'] = intval($item['default_value']);
                }

                //+++

                $translations = [];
                $fields = ['title', 'unit_of_measurement'];
                if (!empty($this->botoscope->controls->get_active_languages())) {
                    foreach ($this->botoscope->controls->get_active_languages() as $language) {

                        if (!isset($translations[$language])) {
                            $translations[$language] = [];
                        }

                        foreach ($fields as $cell_name) {
                            $t = $this->botoscope->translations->get_row($language, 'products_meta_gallery', $item['id'], $cell_name);

                            if ($t) {
                                $value = $t['value'] ?: null;

                                if ($value) {
                                    $translations[$language][$cell_name] = $value;
                                }
                            }
                        }

                        if (empty($translations[$language])) {
                            unset($translations[$language]);
                        }
                    }
                }

                $item['translations'] = $translations;

                //+++

                return $item;
            }, $this->get());
        });
    }

    //+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

    public function get_product_meta_ids($product_id) {
        $sql = "SELECT meta_id FROM `{$this->table_products}` WHERE product_id = %d";
        return $this->db->get_col($this->db->prepare($sql, $product_id));
    }

    public function get_product_meta($product_id) {
        static $products = [];

        if (!array_key_exists($product_id, $products)) {


            $sql = "
        SELECT
            p.id AS id,
            p.meta_id,
            p.value,
            p.icon,
            p.menu_order,
            m.title,
            m.type,
            m.unit_of_measurement
        FROM `{$this->table_products}` AS p
        INNER JOIN `{$this->table}` AS m ON p.meta_id = m.id
        WHERE p.product_id = %d
        ORDER BY p.menu_order ASC
    ";

            $res = $this->db->get_results($this->db->prepare($sql, $product_id), ARRAY_A);

            if (!empty($res)) {
                foreach ($res as $key => $m) {
                    if ($m['type'] === 'taxonomy' && !empty($m['value'])) {
                        $m['value'] = stripslashes($m['value']);
                    }

                    if ($m['type'] === 'calendar') {
                        $m['value'] = intval($m['value']);
                    }

                    $m['id'] = intval($m['id']);
                    $m['meta_id'] = intval($m['meta_id']);
                    $m['menu_order'] = intval($m['menu_order']);

                    if (defined('REST_REQUEST') && REST_REQUEST) {
                        $m['id'] = intval($m['meta_id']);
                        unset($m['meta_id']);
                        unset($m['title']);
                        unset($m['type']);
                        unset($m['unit_of_measurement']);
                    }

                    $res[$key] = $m;
                }
            }

            $products[$product_id] = $res;
        }

        return $products[$product_id];
    }

    public function update_product_meta($id, $field_key, $value) {
        if (empty($id) || empty($field_key)) {
            return false;
        }

        $sql = "SELECT product_id,meta_id FROM `{$this->table_products}` WHERE id = %d LIMIT 1";
        $meta_row = $this->db->get_row($this->db->prepare($sql, $id), ARRAY_A);
        update_post_meta($meta_row['product_id'], "botoscope_meta_{$meta_row['meta_id']}", $value);
        $this->db->update($this->table_products, [$field_key => $value], ['id' => intval($id)]);

        $meta = $this->get_meta($meta_row['meta_id']);
        if ($meta['type'] === 'taxonomy') {
            $taxonomy_data = json_decode(stripslashes($value), true);
            wp_set_object_terms(intval($meta_row['product_id']), null, $taxonomy_data['taxonomy']);
            if (!empty($taxonomy_data['terms'])) {
                wp_set_object_terms($meta_row['product_id'], $taxonomy_data['terms'], $taxonomy_data['taxonomy']);
            }
        }

        $this->products->update_product_bot_cache($meta_row['product_id']);
    }

    public function delete_product_meta($id, $product_id, $meta_id) {
        delete_post_meta($product_id, "botoscope_meta_{$meta_id}");
        return $this->db->delete($this->table_products, ['id' => $id]);
    }

    //+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

    public function create() {
        $data = [
            'title' => esc_html__('new meta field', 'botoscope'),
            'default_value' => '',
            'icon' => '✔️'
        ];

        $this->db->insert($this->table, $data);
        $data['id'] = $this->db->insert_id;

        return $data;
    }

    public function get_meta($meta_id) {
        $sql = "SELECT * FROM `{$this->table}` WHERE id = %d LIMIT 1";
        return $this->db->get_row($this->db->prepare($sql, $meta_id), ARRAY_A);
    }

    public function get($except_ids = []) {
        if (empty($except_ids)) {
            $sql = "SELECT * FROM `{$this->table}` ORDER BY id DESC";
        } else {
            $except_ids = implode(',', array_map('intval', $except_ids));
            $sql = "SELECT * FROM `{$this->table}` WHERE id NOT IN ({$except_ids}) ORDER BY id DESC";
        }

        $res = $this->db->get_results($sql, ARRAY_A);

        //+++

        $ignore_language = defined('REST_REQUEST') && REST_REQUEST ? 1 : 0;
        $default_lang = $this->get_default_language();
        $language = $this->get_current_language();

        if (!$ignore_language && $language !== $default_lang) {
            $related_app = 'products_meta_gallery';

            if (!empty($res)) {
                foreach ($res as $key => $value) {
                    $res[$key]['title'] = $this->botoscope->translations->get_translation($language, $related_app, $value['id'], 'title')['value'] ?: "<ta></ta>" . $value['title'];
                    $res[$key]['unit_of_measurement'] = $this->botoscope->translations->get_translation($language, $related_app, $value['id'], 'unit_of_measurement')['value'] ?: "<ta></ta>" . $value['unit_of_measurement'];
                }
            }
        }

        //+++

        if (!empty($res)) {
            foreach ($res as $key => $m) {
                if ($m['type'] === 'taxonomy' && !empty($m['default_value'])) {
                    $res[$key]['default_value'] = stripslashes($m['default_value']);
                }
            }
        }

        //+++

        return $res;
    }

    //insert meta to product
    public function insert($product_id, $data, $type = '') {
        $data['product_id'] = $product_id;
        $this->db->insert($this->table_products, $data);
        update_post_meta($product_id, "botoscope_meta_{$data['meta_id']}", $data['value']);

        if ($type === 'taxonomy') {
            $taxonomy_data = json_decode(stripslashes($data['value']), true);
            if (!empty($taxonomy_data['terms'])) {
                wp_set_object_terms($product_id, null, $taxonomy_data['taxonomy']);
                wp_set_object_terms($product_id, $taxonomy_data['terms'], $taxonomy_data['taxonomy']);
            }
        }
    }

    public function get_default_language() {
        return $this->botoscope->controls->get_default_language();
    }

    public function get_current_language() {
        $language = $this->botoscope->storage->get_val("products_meta_gallery_selected_language") ?: $this->get_default_language();
        if (!in_array($language, $this->botoscope->controls->get_active_languages())) {
            $language = $this->get_default_language();
        }
        return $language;
    }

    public function update($id, $field_key, $value) {
        if (empty($id) || empty($field_key)) {
            return false;
        }

        $this->db->update($this->table, [$field_key => $value], ['id' => intval($id)]);
        $this->update_bot_cache();
    }

    public function apply_for_new_product($product_id) {
        //maybe not need, postponed
    }

    //todo
    public function get_column_value($id, $column) {
        if (empty($id) || empty($column)) {
            return null;
        }

        return $this->db->get_var($this->db->prepare("SELECT `{$column}` FROM `{$this->table}` WHERE `id` = %d", $id));
    }

    //todo
    public function delete($id, $conditions = []) {
        if (empty($conditions)) {
            $conditions = ['id' => $id];
        }

        return $this->db->delete($this->table, $conditions);
    }

    public function update_bot_cache() {
        $this->botoscope->reset_cache('meta');
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
        `type` varchar(16) NOT NULL DEFAULT 'string',
        `default_value` text DEFAULT NULL,
        `icon` varchar(8) DEFAULT NULL,
        `unit_of_measurement` varchar(32) DEFAULT NULL,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB {$charset_collate};";
        dbDelta($sql);

        // Additional safety measure: convert table after creation
        if ($supports_utf8mb4) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name is an internal class constant, not user input. wpdb->prepare() does not support table name placeholders.
            $wpdb->query("ALTER TABLE `" . esc_sql($this->table) . "` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_products} (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `product_id` int(11) NOT NULL,
        `meta_id` int(11) NOT NULL,
        `value` text DEFAULT NULL,
        `icon` varchar(8) DEFAULT NULL,
        `menu_order` int(4) NOT NULL DEFAULT 9999,
        PRIMARY KEY (id),
        KEY product_id (product_id),
        KEY meta_id (meta_id)
    ) ENGINE=InnoDB {$charset_collate};";
        dbDelta($sql);

        // Additional safety measure: convert table after creation
        if ($supports_utf8mb4) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name is an internal class constant, not user input. wpdb->prepare() does not support table name placeholders.
            $wpdb->query("ALTER TABLE `" . esc_sql($this->table_products) . "` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }

        add_option("botoscope_{$this->table}_is_installed", 1);
    }
}
