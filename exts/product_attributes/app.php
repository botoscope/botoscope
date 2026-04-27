<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

//14-04-2026
final class BOTOSCOPE_PRODUCT_ATTRIBUTES extends BOTOSCOPE_APP {

    protected $botoscope;
    protected $controls;
    protected $translations;
    protected $table_name = 'botoscope_product_attributes';
    protected $slug = 'product_attributes';
    protected $data_structure = [
        'menu_order' => 9999,
        'title' => '',
        'taxonomy_slug' => '',
        'display_as' => 'button',
        'cols_in_row' => 2,
        'formula' => '',
        'icon' => '',
        'is_active' => 0
    ];

    public function __construct($args = []) {
        parent::__construct($args);

        if (botoscope_is_no_cart()) {
            return false;
        }

        $this->controls = new BOTOSCOPE_CONTROLS($args);
        $this->translations = new BOTOSCOPE_TRANSLATIONS($args);
        $this->botoscope->allrest->add_rest_route($this->slug, [$this, 'register_route']);

        Botoscope_Hooks::add_action('botoscope_panel_tabs', function ($tabs) {
            $tabs[$this->slug] = esc_html__('Product attributes', 'botoscope');
            return $tabs;
        });

        //+++
        //ajax
        Botoscope_Hooks::add_action('botoscope_edit_cell', function ($what, $id, $key, $value) {
            if ($what === "{$this->slug}_menu_order") {
                $new_values = map_deep(wp_unslash($_REQUEST['new_values'] ?? []), 'sanitize_text_field');

                if (!empty($new_values)) {
                    foreach ($new_values as $key => $val) {
                        $new_values[$key]['id'] = intval($val['id']);
                        $new_values[$key]['value'] = intval($val['value']);
                    }
                }

                $this->set_order($new_values);
                $this->botoscope->reset_cache($this->slug);
            }
        });

        Botoscope_Hooks::add_action('botoscope_edit_cell', function ($what, $id, $key, $value) {
            if ($what === 'controls' && in_array($id, ['default_language', 'languages'])) {
                $this->botoscope->reset_cache($this->slug);
            }
        });

        add_action("botoscope_{$this->slug}_tab_icon", function () {
            return 'tag';
        });
    }

    public function register_route(WP_REST_Request $request) {
        return $this->get_active();
    }

    public function get($page_num = 0) {
        $res = parent::get();

        $ignore_language = defined('REST_REQUEST') && REST_REQUEST ? 1 : 0;

        if (!$ignore_language && $this->get_current_language() !== $this->get_default_language()) {
            $language = $this->get_current_language();
            $related_app = $this->slug;

            if (!empty($res)) {
                foreach ($res as $key => $value) {
                    $res[$key]['title'] = $this->translations->get_translation($language, $related_app, $value['id'], 'title')['value'] ?: "<ta></ta>" . $value['title'];
                }
            }
        }

        //+++

        if (!empty($res)) {
            $order_field = 'menu_order';
            $compare = function ($a, $b) use ($order_field) {
                return strcmp($a[$order_field], $b[$order_field]);
            };

            usort($res, $compare);
        }

        return $res;
    }

    private function set_order($new_order) {

        if (!empty($new_order)) {
            foreach ($new_order as $data) {
                $this->update(intval($data['id']), 'menu_order', intval($data['value']));
            }
        }
    }

    public function get_active() {

        $res = [];
        $rows = $this->get(0, true);

        if (!empty($rows)) {

            $controls = new BOTOSCOPE_CONTROLS(['botoscope' => $this->botoscope]);
            $languages = $controls->get_active_languages();
            $default_language = $controls->get_default_language();
            $translations = new BOTOSCOPE_TRANSLATIONS(['botoscope' => $this->botoscope]);

            foreach ($rows as $r) {

                if (!intval($r['is_active'])) {
                    continue;
                }

                if (empty($r['title']) || empty($r['taxonomy_slug'])) {
                    continue;
                }

                $st = [
                    'display_title' => $r['title'],
                    'display_as' => $r['display_as'],
                    'cols_in_row' => intval($r['cols_in_row']),
                    'formula' => $r['formula'],
                    'taxonomy_slug' => $r['taxonomy_slug'],
                    'icon' => $r['icon'],
                    'order' => intval($r['menu_order'])
                ];

                //+++

                $st['translations'] = [];

                if (!empty($languages)) {
                    foreach ($languages as $language) {

                        if (!isset($st['translations'][$language])) {
                            $st['translations'][$language] = [];
                        }

                        $st['translations'][$language]['display_title'] = $translations->get_translation($language, $this->slug, $r['id'], 'title')['value'];
                    }
                }

                //+++

                $res[$r['taxonomy_slug']] = $st;
            }
        }

        return $res;
    }

    protected function process_value_before_upfate($value, $field_key) {

        if ($field_key === 'icon') {
            //$value = substr($value, 0, 1);
        }

        return $value;
    }

    public function update($id, $field_key, $value, $all_sent_data = []) {
        if ($this->get_current_language() !== $this->get_default_language()) {
            $tr = $this->translations->get_translation($this->get_current_language(), $this->slug, $id, $field_key);
            $this->translations->update($tr['id'], $field_key, $value);
        } else {
            parent::update($id, $field_key, $value);
        }
    }

    public function delete($id, $conditions = []) {
        parent::delete($id);
        $this->translations->delete(0, ['related_app' => $this->slug, 'related_row_id' => intval($id)]);
    }

    public function draw_content($counter) {
        $default_lang = $this->controls->get_default_language();
        $active_langs = $this->controls->get_active_languages();
        $langs = array_intersect_key($this->botoscope->languages, array_flip(array_merge($active_langs, [$default_lang])));
        ?>

        <section id="botoscope-<?php echo esc_attr($this->slug) ?>" <?php if ($counter === 0): ?>class="content-current"<?php endif; ?>>

            <select id="botoscope-<?php echo esc_attr($this->slug) ?>-lang-selector" class="botoscope-lang-selector" data-default-language="<?php echo esc_attr($default_lang) ?>">
                <?php foreach ($langs as $lang_key => $lang_title) : ?>
                    <option value="<?php echo esc_attr($lang_key) ?>" <?php selected($this->get_current_language(), $lang_key) ?>><?php echo esc_attr($lang_title) ?></option>
                <?php endforeach; ?>
            </select>

            <div id="botoscope-<?php echo esc_attr($this->slug) ?>-w"><?php echo wp_json_encode($this->get(), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></div>
            <br>
            <a href="javascript: void(0);" id="botoscope_create_<?php echo esc_attr($this->slug) ?>" class="button button-primary"><?php esc_html_e('New product attribute', 'botoscope') ?></a>

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
        menu_order int(4) NOT NULL DEFAULT 9999,
        title varchar(64) DEFAULT NULL,
        taxonomy_slug varchar(64) DEFAULT NULL,
        display_as varchar(16) NOT NULL DEFAULT 'button',
        cols_in_row int(2) NOT NULL DEFAULT 2,
        formula varchar(32) DEFAULT NULL,
        icon varchar(1) DEFAULT NULL,
        is_active tinyint(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Additional safety measure: convert table after creation
        if ($supports_utf8mb4) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name is an internal class constant, not user input. wpdb->prepare() does not support table name placeholders.
            $wpdb->query("ALTER TABLE `" . esc_sql($this->table_name) . "` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }

        add_option("botoscope_{$this->table_name}_is_installed", 1);

        // Insert default data
        $atts = $this->botoscope->get_woocommerce_product_attributes();
        $default_data = [];

        if (!empty($atts)) {
            $display_as = ['button', 'switcher'];

            foreach ($atts as $order => $a) {
                $display = $display_as[array_rand($display_as)];
                $default_data[] = [
                    'title' => $a['name'],
                    'taxonomy_slug' => $a['slug'],
                    'display_as' => $display,
                    'cols_in_row' => 2,
                    'formula' => $display === 'button' ? 'cl4=b' : 'cg6=s',
                    'icon' => '🎨',
                    'menu_order' => $order,
                    'is_active' => 1,
                ];
            }

            foreach ($default_data as $data) {
                $wpdb->insert($this->table_name, $data);
            }
        }
    }
}
