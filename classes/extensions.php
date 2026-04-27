<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

//22-04-2026
final class BOTOSCOPE_EXTENSIONS extends BOTOSCOPE_APP {

    protected $botoscope;
    protected $table_name = 'botoscope_extensions';
    protected $slug = 'extensions';
    protected $data_structure = [];
    protected $exts = [];

    public function __construct($args = []) {
        parent::__construct($args);

        $this->exts = [
            'products' => [
                'title' => '🛍 ' . esc_html__('Products', 'botoscope'),
                'description' => esc_html__('Products manager', 'botoscope'),
                'menu_order' => -1,
                'help' => 'https://botoscope.com/docs/adding-a-new-product/',
                'settings' => [],
                'nonswitchable' => 1,
                'need_bot' => false
            ],
            /*
              'sizecharts' => [
              'title' => '📏 ' . esc_html__('Size charts', 'botoscope'),
              'description' => esc_html__('Allows your customers find their wear size through wizard', 'botoscope'),
              'menu_order' => 9999,
              'help' => '',
              'settings' => [
              'size_param_slug' => 'pa_size',
              ]
              ],
             * 
             */
            'shipping' => [
                'title' => '🚚 ' . esc_html__('Shipping', 'botoscope'),
                'description' => esc_html__('Products delivering', 'botoscope'),
                'menu_order' => 6,
                'help' => 'https://botoscope.com/docs/setting-up-shipping-methods/',
                'settings' => [],
                'need_cart' => 1,
                'need_bot' => true
            ],
            /*
              'pickup_points' => [
              'title' => '📍 ' . esc_html__('Pickup points', 'botoscope'),
              'description' => esc_html__('Places where your customers can pick up the goods they have purchased. Use with Shipping extension.', 'botoscope'),
              'menu_order' => 9999,
              'help' => '',
              'settings' => [],
              'need_cart' => 1
              ],
             * 
             */
            'coupons' => [
                'title' => '🏷 ' . esc_html__('Coupons', 'botoscope'),
                'description' => esc_html__('Offer discounts to your customers', 'botoscope'),
                'menu_order' => 1,
                'help' => 'https://botoscope.com/docs/creating-and-managing-coupons/',
                'settings' => [],
                'need_cart' => 1,
                'need_bot' => false
            ],
            'marketing' => [
                'title' => '💸 ' . esc_html__('Marketing', 'botoscope'),
                'description' => esc_html__('Marketing companies and strategies', 'botoscope'),
                'menu_order' => 9999,
                'help' => 'https://botoscope.com/docs/marketing-strategies-and-campaigns/',
                'settings' => [],
                'need_cart' => 1,
                'need_bot' => true
            ],
            'support' => [
                'title' => '✨ ' . esc_html__('Support', 'botoscope'),
                'description' => esc_html__('Interaction with your customers', 'botoscope'),
                'menu_order' => 9999,
                'help' => 'https://botoscope.com/docs/using-the-built-in-support-chat/',
                'settings' => [],
                'need_bot' => true
            ],
            'elogios' => [
                'title' => '❤️ ' . esc_html__('Elogios', 'botoscope'),
                'description' => esc_html__('Compliments bring comfort to your customers', 'botoscope'),
                'menu_order' => 2,
                'help' => 'https://botoscope.com/docs/compliments-system-elogios/',
                'settings' => [],
                'need_cart' => 1,
                'need_bot' => true
            ],
            'advertising' => [
                'title' => 'ℹ️ ' . esc_html__('Advertising', 'botoscope'),
                'description' => esc_html__('Broadcast your own advertising to your customers', 'botoscope'),
                'menu_order' => 3,
                'help' => 'https://botoscope.com/docs/advertising-inside-your-bot/',
                'settings' => [],
                'need_bot' => true
            ],
            'product_attributes' => [
                'title' => '🎨️ ' . esc_html__('Product attributes', 'botoscope'),
                'description' => esc_html__('Manage the display of your product attributes in telegram shop', 'botoscope'),
                'menu_order' => 4,
                'help' => 'https://botoscope.com/docs/using-attributes-and-variations/',
                'settings' => [],
                'need_cart' => 1,
                'nonswitchable' => botoscope_is_no_cart() ? 0 : 1,
                'need_bot' => true
            ],
            'interface_translations' => [
                'title' => '📝️ ' . esc_html__('Interface translations', 'botoscope'),
                'description' => esc_html__('Managing telegram shop text variables', 'botoscope'),
                'menu_order' => 5,
                'help' => 'https://botoscope.com/docs/translating-bot-interface-and-content/',
                'settings' => [],
                'need_bot' => true
            ],
            'b2b' => [
                'title' => '🏢️ B2B',
                'description' => esc_html__('Tailored options for B2B enterprises', 'botoscope'),
                'menu_order' => 9999,
                'help' => 'https://botoscope.com/docs/b2b-features/',
                'settings' => [],
                'need_cart' => 1,
                'need_bot' => true
            ],
            'broadcast' => [
                'title' => '📣️ ' . esc_html__('Broadcast', 'botoscope'),
                'description' => esc_html__('Deliver your message. Engage your audience. Instantly.', 'botoscope'),
                'menu_order' => 9999,
                'help' => 'https://botoscope.com/docs/broadcasting-messages-to-customers/',
                'settings' => [],
                'need_bot' => true
            ],
            'booking' => [
                'title' => '📅️ ' . esc_html__('Booking', 'botoscope'),
                'description' => esc_html__('Turn your virtual products into bookable services by assigning time slots — perfect for coaches, consultants, and service providers', 'botoscope'),
                'menu_order' => 9999,
                'help' => 'https://botoscope.com/docs/booking/',
                'settings' => [],
                'need_cart' => 1,
                'need_bot' => true
            ],
            'shopify_sync' => [
                'title' => '🛒 ' . esc_html__('Shopify Sync', 'botoscope'),
                'description' => esc_html__('Sync products from your Shopify store into WooCommerce. Orders from the bot are pushed back to Shopify automatically.', 'botoscope'),
                'menu_order' => 9999,
                'help' => 'https://botoscope.com/docs/shopify-sync/',
                'settings' => [
                    'shopify_store_url' => '',
                    'shopify_client_id' => '',
                    'shopify_client_secret' => '',
                    'api_access_token' => ''
                ],
                'need_bot' => false
            ],
            'business_in_pocket' => [
                'title' => '💼📲🧠️ ' . esc_html__('Business in your pocket', 'botoscope'),
                'description' => esc_html__('Feel in control — instant shop notifications', 'botoscope'),
                'menu_order' => 9999,
                'help' => 'https://botoscope.com/docs/business-in-pocket/',
                'settings' => [
                    'bot_name' => '',
                    'bot_token' => '',
                    'admin_chat_id' => ''
                ],
                'need_bot' => false
            ]
        ];

        $this->merge_custom_exts();

        //https://emojidb.org/interface-emojis
        //+++
        //lets add this ext settings into globals ones to send them to bot server
        Botoscope_Hooks::add_action('botoscope_controls', function ($controls) {
            foreach (array_keys($this->exts) as $gateway) {

                if (!intval($this->get_option($gateway, 'is_active'))) {
                    continue;
                }

                //+++

                $settings = $this->get_option($gateway, 'settings');
                if (!empty($settings)) {
                    $settings = json_decode($settings, true);
                } else {
                    $settings = [];
                }

                if (!empty($settings)) {
                    foreach ($settings as $key => $value) {
                        $controls[] = [
                            'id' => $key,
                            'title' => $key,
                            'value' => $value,
                            'is_active' => 1,
                            'extension' => $key
                        ];
                    }
                }
            }

            return $controls;
        });

        //ajax
        Botoscope_Hooks::add_action('botoscope_edit_cell', function ($what, $id, $key, $value) {
            if ($what === 'extensions_menu_order') {
                $new_values = map_deep(wp_unslash($_REQUEST['new_values'] ?? []), 'sanitize_text_field');

                if (!empty($new_values)) {
                    foreach ($new_values as $nv) {
                        $this->update_option(sanitize_text_field($nv['gateway']), 'menu_order', intval($nv['menu_order']));
                    }
                }
            }

            //+++

            if ($what === 'extensions_settings_table') {
                $gateway = sanitize_text_field(wp_strip_all_tags($_REQUEST['additional_params']['parent_row_id']));
                $field_key = $id;

                $this->update_setting($gateway, $field_key, $value);
                $this->botoscope->reset_cache('controls'); //!!
            }
        });

        Botoscope_Hooks::add_action('botoscope_get_parent_cell_data', function ($parent_app, $parent_row_id, $parent_cell_name) {
            $res = [];
            if ($parent_app === $this->slug) {
                switch ($parent_cell_name) {
                    case 'settings':
                        $res = [];
                        //$parent_row_id is gateway here
                        $settings = $this->get_settings($parent_row_id);

                        if (!empty($settings)) {
                            foreach ($settings as $key => $value) {
                                $res[] = [
                                    'title' => $key,
                                    'value' => $value,
                                    'id' => $key
                                ];
                            }
                        }

                        break;
                }
            }

            return $res;
        });

        //+++
        $active_exts = $this->get_active();
        if (!empty($active_exts)) {
            foreach ($active_exts as $ext_key) {
                $this->put_ext_to_system($ext_key);
            }
        }

        add_action("botoscope_{$this->slug}_tab_icon", function () {
            return 'puzzle';
        });

        add_action('admin_enqueue_scripts', function () {
            if (!isset($_GET['page']) || sanitize_key($_GET['page']) !== 'botoscope') {
                return;
            }

            wp_add_inline_script(
                    'botoscope_general',
                    'var botoscope_active_extensions = ' . wp_json_encode($this->get_active()) . ';',
                    'before'
            );

            //+++

            $items_in_visible_menu = 7;

            if (wp_is_mobile()) {
                $items_in_visible_menu = 2;
            }

            $botoscope_tabs_count = count(Botoscope_Hooks::apply_action('botoscope_panel_tabs', [])) + 4;
            if (botoscope_is_no_cart()) {
                $botoscope_tabs_count--;
            }

            wp_add_inline_script(
                    'botoscope_general',
                    'var botoscope_tabs_count = ' . $botoscope_tabs_count . ';
                     var botoscope_menu_limit = ' . intval($items_in_visible_menu) . ';',
                    'before'
            );

            //+++

            if (!get_option('botoscope_first_run_completed', false)) {
                update_option('botoscope_first_run_completed', true);
                wp_add_inline_script(
                        'botoscope_general',
                        'alert("⚠️ " + ' . wp_json_encode(__("Welcome to Botoscope! 🎉\n\nIMPORTANT FIRST STEP:\n\n1️⃣ Configure wp-config.php with your Botoscope credentials\n2️⃣ Go to System Controls tab\n3️⃣ Click [Full Reset System Data] button\n\nThis will synchronize your Telegram shop with your WordPress site.", 'botoscope')) . ');',
                        'after'
                );
            }
        }, 20);

        $this->botoscope->allrest->add_rest_route($this->slug, [$this, 'register_route']);
    }

    public function register_route(WP_REST_Request $request) {
        return $this->get_active();
    }

    private function put_ext_to_system($ext_key) {
        $ext_data = $this->exts[$ext_key];
        $class_name = 'BOTOSCOPE_' . strtoupper($ext_key);

        if ($this->botoscope->no_bot) {
            if ($ext_data['need_bot']) {
                return false;
            }
        }

        if (isset($ext_data['is_custom'])) {
            include_once $ext_data['ext_directory'] . "/app.php";
        } else {
            include_once BOTOSCOPE_PATH . "exts/{$ext_key}/app.php";
        }

        if (class_exists($class_name)) {
            $this->botoscope->$ext_key = new $class_name(['botoscope' => $this->botoscope]);
        }
    }

    public function get($page_num = 0) {
        $res = [];

        foreach ($this->exts as $gateway => $data) {
            $settings = $this->get_option($gateway, 'settings');
            if (!empty($settings)) {
                $settings = json_decode($settings, true);
            } else {
                $settings = $this->exts[$gateway]['settings'];
            }

            $is_active = intval($this->get_option($gateway, 'is_active'));
            if ($data['nonswitchable'] ?? 0) {
                $is_active = 1;
            }

            $res[] = [
                'id' => $gateway,
                'title' => $data['title'],
                'description' => $data['description'],
                'help' => $data['help'],
                'menu_order' => intval($this->get_option($gateway, 'menu_order')),
                'settings' => $settings,
                'is_active' => $is_active,
                'nonswitchable' => intval($data['nonswitchable'] ?? 0),
                'need_cart' => intval($data['need_cart'] ?? 0),
                'need_bot' => intval($data['need_bot'] ?? 0)
            ];
        }

        //+++
        // Filter active and inactive elements
        $activeItems = array_filter($res, fn($item) => intval($item['is_active']) === 1);
        $inactiveItems = array_filter($res, fn($item) => intval($item['is_active']) === 0);

        // Function to sort by menu_order
        $sortByMenuOrder = fn($a, $b) => intval($a['menu_order']) <=> intval($b['menu_order']);

        // We sort both arrays
        usort($activeItems, $sortByMenuOrder);
        usort($inactiveItems, $sortByMenuOrder);

        return array_merge($activeItems, $inactiveItems);
    }

    public function update($id, $key, $value, $all_sent_data = []) {
        $this->update_option($id, $key, $value);

        //+++

        if ($key === 'is_active') {
            $active_gateways = $this->db->get_results(
                    $this->db->prepare("SELECT `gateway` FROM `{$this->table_name}` WHERE `is_active` = %d", 1),
                    ARRAY_A
            );

            $active_exts = wp_list_pluck($active_gateways, 'gateway');

            //19-12-2024 deactivated, use js var
            //file_put_contents(BOTOSCOPE_PATH . 'data/extensions.json', wp_json_encode($active_exts));
            //+++

            if (intval($value) === 1) {
                //if ext just enabled lets reset its cache on the bot side
                $this->put_ext_to_system($id);
                if (isset($this->botoscope->$id)) {
                    usleep(3500000); // Pause
                    $this->botoscope->reset_cache($id);
                }
            }
        }
    }

    private function update_option($gateway, $field_key, $value) {
        $row = $this->get_row($gateway);

        if (empty($row)) {
            $this->db->insert($this->table_name, [
                'gateway' => $gateway,
            ]);

            $id = $this->db->insert_id;
        } else {
            $id = intval($row['id']);
        }


        $this->db->update($this->table_name, [$field_key => $value], ['id' => $id]);
    }

    private function get_option($ext_key, $field_key) {
        $row = $this->get_row($ext_key);

        if (empty($row)) {
            $row[$field_key] = "";
        }

        return $row[$field_key];
    }

    public function get_settings($ext_key) {
        $option = $this->get_option($ext_key, 'settings');
        $need_data = $this->exts[$ext_key]['settings'];

        if (!empty($option)) {
            $option = json_decode($option, true);
        } else {
            $option = $need_data;
        }


        if (!empty($need_data)) {
            foreach (array_keys($need_data) as $key) {
                if (isset($option[$key])) {
                    $need_data[$key] = $option[$key];
                }
            }
        }

        return $need_data;
    }

    public function update_setting($gateway, $key, $value) {
        $settings = $this->get_settings($gateway);
        $settings[$key] = $value;
        $this->update_option($gateway, 'settings', wp_json_encode($settings));
    }

    private function get_row($gateway) {

        static $res = [];

        if (!array_key_exists($gateway, $res)) {

            $res[$gateway] = $this->db->get_row(
                    $this->db->prepare("SELECT * FROM `{$this->table_name}` WHERE gateway = %s", $gateway),
                    ARRAY_A
            );
        }

        return $res[$gateway];
    }

    public function get_active() {
        $res = [];
        $rows = $this->get();

        if (!empty($rows)) {
            foreach ($rows as $r) {
                if (intval($r['is_active'])) {
                    $res[] = $r['id'];
                }
            }
        }

        return $res;
    }

    private function merge_custom_exts() {
        $directories = glob(BOTOSCOPE_CUSTOM_EXT_PATH . '*', GLOB_ONLYDIR);
        $new_extensions = [];

        foreach ($directories as $directory) {
            $data = [];
            $gateway = basename($directory);

            $json_file = $directory . '/data.json';

            if (file_exists($json_file)) {
                global $wp_filesystem;
                if (empty($wp_filesystem)) {
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                    WP_Filesystem();
                }
                $data = json_decode($wp_filesystem->get_contents($json_file), true);
            }

            $new_extensions[$gateway] = [
                'title' => isset($data['title']) ? esc_html($data['title']) : $gateway,
                'description' => isset($data['description']) ? esc_html($data['description']) : esc_html__('No description', 'botoscope'),
                'menu_order' => isset($data['menu_order']) ? (int) $data['menu_order'] : 9999,
                'help' => isset($data['help']) ? esc_html($data['help']) : '',
                'settings' => isset($data['settings']) ? $data['settings'] : [],
                'is_custom' => 1,
                'ext_directory' => $directory,
                'need_cart' => isset($data['need_cart']) ? $data['need_cart'] : 0,
                'ext_link' => BOTOSCOPE_CUSTOM_EXT_LINK . $gateway
            ];
        }

        $this->exts = array_merge($this->exts, $new_extensions);
    }

    protected function install() {
        global $wpdb;

        // Checking if the table has already been created
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
        gateway varchar(32) NOT NULL,
        title varchar(32) DEFAULT NULL,
        description text DEFAULT NULL,
        settings mediumtext DEFAULT NULL,
        menu_order smallint(2) NOT NULL DEFAULT 9999,
        is_active smallint(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        UNIQUE KEY gateway (gateway)
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
                'gateway' => 'support',
                'title' => null,
                'description' => null,
                'settings' => null,
                'menu_order' => 0,
                'is_active' => 1,
            ],
            [
                'gateway' => 'elogios',
                'title' => null,
                'description' => null,
                'settings' => null,
                'menu_order' => 4,
                'is_active' => 1,
            ],
            [
                'gateway' => 'advertising',
                'title' => null,
                'description' => null,
                'settings' => null,
                'menu_order' => 9,
                'is_active' => 0,
            ],
            [
                'gateway' => 'coupons',
                'title' => null,
                'description' => null,
                'settings' => null,
                'menu_order' => 1,
                'is_active' => 1,
            ],
            [
                'gateway' => 'product_attributes',
                'title' => null,
                'description' => null,
                'settings' => null,
                'menu_order' => 5,
                'is_active' => 0,
            ],
            [
                'gateway' => 'interface_translations',
                'title' => null,
                'description' => null,
                'settings' => null,
                'menu_order' => 10,
                'is_active' => 0,
            ],
        ];

        foreach ($default_data as $data) {
            $wpdb->insert($this->table_name, $data);
        }
    }

    public function draw_content($counter) {
        $exts = $this->get();

        foreach ($exts as $key => $value) {
            if (botoscope_is_no_cart() && isset($value['need_cart']) && intval($value['need_cart']) === 1) {
                unset($exts[$key]);
            }

            if ($this->botoscope->no_bot) {
                if ($value['need_bot']) {
                    unset($exts[$key]);
                }
            }
        }

        $exts = array_values($exts);
        ?>

        <section id="botoscope-<?php echo esc_attr($this->slug) ?>" <?php if ($counter === 0): ?>class="content-current"<?php endif; ?>>
            <div id="botoscope-<?php echo esc_attr($this->slug) ?>-w"><?php echo wp_json_encode($exts, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></div>
        </section>

        <?php
    }
}
