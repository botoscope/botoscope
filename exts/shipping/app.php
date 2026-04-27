<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

//17-12-2025
final class BOTOSCOPE_SHIPPING extends BOTOSCOPE_APP {

    protected $botoscope;
    protected $controls;
    protected $translations;
    protected $table_name = 'botoscope_shipping';
    protected $slug = 'shipping';
    protected $data_structure = [
        'title' => 'click to edit ...',
        'price' => 0,
        'min_amount' => 0,
        'description' => '',
        'is_active' => 0,
        'menu_order' => 9999,
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
            $tabs[$this->slug] = esc_html__('Shipping', 'botoscope');
            return $tabs;
        });

        add_action("wp_ajax_botoscope_shipping_get", function () {
            if ($this->botoscope->is_ajax_request_valid()) {
                die(wp_json_encode($this->get()));
            }
        }, 1);

        add_action("botoscope_{$this->slug}_tab_icon", function () {
            return 'truck';
        });
    }

    public function register_route(WP_REST_Request $request) {
        return $this->get_active();
    }

    public function get($page_num = 0) {
        $res = parent::get();

        if (!empty($res)) {
            $order_field = 'menu_order';
            $compare = function ($a, $b) use ($order_field) {
                return strcmp($a[$order_field], $b[$order_field]);
            };

            usort($res, $compare);
        }

        //+++

        $ignore_language = defined('REST_REQUEST') && REST_REQUEST ? 1 : 0;

        if (!$ignore_language && $this->get_current_language() !== $this->get_default_language()) {
            $language = $this->get_current_language();
            $related_app = $this->slug;

            if (!empty($res)) {
                foreach ($res as $key => $value) {
                    $res[$key]['title'] = $this->translations->get_translation($language, $related_app, $value['id'], 'title')['value'] ?: ("<ta></ta>" . $value['title']);
                    $res[$key]['description'] = $this->translations->get_translation($language, $related_app, $value['id'], 'description')['value'] ?: ("<ta></ta>" . $value['description']);
                }
            }
        }

        return $res;
    }

    public function get_active() {
        $res = [
            'default' => $this->get_default(),
            'how_to_display' => 'switcher',
            'ask_about_address' => esc_html__('enter your address and phone', 'botoscope'),
            'ways' => []
        ];

        $ways = $this->get(0, true);

        if (!empty($ways)) {
            foreach ($ways as $way) {
                if ($way['is_active']) {

                    $data = [
                        'title' => $way['title'],
                        'price' => floatval($way['price']),
                        'min_amount' => floatval($way['min_amount']),
                        'description' => $way['description'],
                    ];

                    $pickups = $this->get_pickups($way['id']);

                    if (!empty($pickups)) {
                        $data['pickups'] = $pickups;
                    }

                    //+++

                    $translations = [];
                    $fields = ['title', 'description'];
                    if (!empty($this->controls->get_active_languages())) {
                        foreach ($this->controls->get_active_languages() as $language) {

                            if (!isset($translations[$language])) {
                                $translations[$language] = [];
                            }

                            foreach ($fields as $cell_name) {
                                $value = $this->translations->get_row($language, $this->slug, intval($way['id']), $cell_name)['value'] ?? null;

                                if ($value) {
                                    $translations[$language][$cell_name] = $value;
                                }
                            }

                            if (empty($translations[$language])) {
                                unset($translations[$language]);
                            }
                        }
                    }

                    $data['translations'] = $translations;
                    $res['ways'][$way['id']] = $data;
                }
            }
        }

        return $res;
    }

    private function get_default() {
        $res = 0;
        $ways = $this->get();
        $first = [];

        if (!empty($ways)) {
            foreach ($ways as $counter => $way) {
                if ($way['is_active']) {
                    if ($counter === 0) {
                        $first = $way;
                    }

                    if ($way['is_default']) {
                        $res = $way['id'];
                        break;
                    }
                }
            }

            if ($res === 0 && !empty($first)) {
                $res = $first['id'];
            }
        }

        return intval($res);
    }

    private function get_pickups($way_id) {
        $res = [];

        if (isset($this->botoscope->pickup_points)) {
            $all_pickups = $this->botoscope->pickup_points->get(0, true);
            if (!empty($all_pickups)) {
                foreach ($all_pickups as $p) {
                    if ($p['is_active']) {
                        $shipping_ways = explode(',', $p['shipping_ways']);
                        if (in_array($way_id, $shipping_ways)) {
                            array_push($res, intval($p['id']));
                        }
                    }
                }
            }
        }

        return $res;
    }

    public function update($id, $field_key, $value, $all_sent_data = []) {
        if (empty($field_key)) {
            return false;
        }

        switch ($field_key) {
            case 'is_default':
                $all = $this->get();

                foreach ($all as $sch) {
                    $val = intval($sch['id']) === intval($id) ? 1 : 0;
                    $this->db->update($this->table_name, [$field_key => $val], ['id' => intval($sch['id'])]);
                }

                break;

            case 'menu_order':

                if (!empty($all_sent_data['new_values'])) {
                    foreach ($all_sent_data['new_values'] as $d) {
                        $this->update(intval($d['id']), 'menu_order', intval($d['value']));
                    }
                } else {
                    $this->db->update($this->table_name, [$field_key => $value], ['id' => intval($id)]);
                }

                break;

            default:

                //$this->db->update($this->table_name, [$field_key => $value], ['id' => intval($id)]);

                if ($this->get_current_language() !== $this->get_default_language()) {
                    $tr = $this->translations->get_translation($this->get_current_language(), $this->slug, $id, $field_key);
                    $this->translations->update($tr['id'], $field_key, $value);
                } else {
                    parent::update($id, $field_key, $value);
                }

                break;
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
            <a href="javascript: void(0);" id="botoscope_create_<?php echo esc_attr($this->slug) ?>" class="button button-primary"><?php esc_html_e('New shipping method', 'botoscope') ?></a><br>

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
        title varchar(32) NOT NULL,
        price double NOT NULL DEFAULT 0,
        min_amount double NOT NULL DEFAULT 0,
        description text DEFAULT NULL,
        is_active tinyint(1) NOT NULL DEFAULT 0,
        is_default tinyint(1) NOT NULL DEFAULT 0,
        menu_order smallint(4) NOT NULL DEFAULT 9999,
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
        $default_data = [
            [
                'title' => esc_html__('Pickup', 'botoscope'),
                'price' => 0,
                'min_amount' => 0,
                'is_active' => 1,
                'is_default' => 1,
                'description' => esc_html__('Self-pickup from our warehouse', 'botoscope'),
            ],
            [
                'title' => esc_html__('Delivery by courier', 'botoscope'),
                'price' => 20,
                'min_amount' => 0,
                'description' => esc_html__('Our personal courier service', 'botoscope'),
            ],
            [
                'title' => esc_html__('Free shipping', 'botoscope'),
                'price' => 0,
                'min_amount' => 100,
                'description' => esc_html__('Free shipping', 'botoscope'),
            ],
        ];

        foreach ($default_data as $data) {
            $wpdb->insert($this->table_name, $data);
        }
    }
}
