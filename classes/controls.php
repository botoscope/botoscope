<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

//03-04-2026
final class BOTOSCOPE_CONTROLS extends BOTOSCOPE_APP {

    protected $table_name = 'botoscope_controls';
    protected $slug = 'controls';
    protected $data_structure = [];
    public $controls = [];

    public function __construct($args) {
        parent::__construct($args);

        $timezone = wp_timezone();
        $offset = $timezone->getOffset(new DateTime) / 3600; // In hours

        $this->controls = [
            'default_language' => [
                'title' => esc_html__('Default language', 'botoscope'),
                'value' => 'en',
                'help' => esc_html__('Default system language', 'botoscope')
            ],
            'languages' => [
                'title' => esc_html__('Languages', 'botoscope'),
                'value' => [],
                'help' => esc_html__('Languages available in your Telegram shop', 'botoscope')
            ],
            'about' => [
                'title' => esc_html__('About shop', 'botoscope'),
                'value' => '',
                'type' => 'textarea',
                'help' => esc_html__('Information displayed in the Help section of your Telegram shop', 'botoscope')
            ],
            'shop_time_zone' => [
                'title' => esc_html__('Shop time zone', 'botoscope'),
                'value' => $offset,
                'hide' => 1,
            ],
            'order_logo' => [
                'title' => esc_html__('Order logo', 'botoscope'),
                'value' => '',
                'help' => esc_html__('You can set a logo image for your orders here', 'botoscope')
            ],
            'order_shop_title' => [
                'title' => esc_html__('Shop name in order', 'botoscope'),
                'value' => '',
                'help' => esc_html__('Enter your shop name here to display it in your shop orders', 'botoscope')
            ],
            'order_header' => [
                'title' => esc_html__('Order header', 'botoscope'),
                'value' => '',
                'type' => 'textarea',
                'help' => esc_html__('Custom text to display in the order header', 'botoscope')
            ],
            'order_footer' => [
                'title' => esc_html__('Order footer', 'botoscope'),
                'value' => '',
                'help' => esc_html__('Custom text to display in the order footer', 'botoscope')
            ],
            'show_catalog_on_main' => [
                'title' => esc_html__('Show catalog on start', 'botoscope'),
                'value' => 0,
                'type' => 'switcher',
                'help' => esc_html__('Display all products when the bot starts, instead of category navigation.', 'botoscope')
            ],
            'disable_cart_checkout' => [
                'title' => esc_html__('Disable cart and checkout', 'botoscope'),
                'value' => 0,
                'type' => 'switcher',
                'help' => esc_html__('Use your Telegram shop as a catalog only (no checkout)', 'botoscope')
            ],
            'show_site_product_link_in_catalog_mode' => [
                'title' => esc_html__('Show View on Website button', 'botoscope'),
                'value' => 0,
                'type' => 'switcher',
                'help' => esc_html__('Add a button to view the product on your website. Useful for custom checkouts, headless commerce, B2B orders, or when using Telegram bot as a product catalog only.', 'botoscope')
            ],
            'show_refund_policy_button' => [
                'title' => esc_html__('Show refund policy button', 'botoscope'),
                'value' => 0,
                'type' => 'switcher',
                'help' => esc_html__('Display a Refund Policy button during checkout and in the Help section', 'botoscope')
            ],
            'show_privacy_policy_button' => [
                'title' => esc_html__('Show privacy policy button', 'botoscope'),
                'value' => 0,
                'type' => 'switcher',
                'help' => esc_html__('Display a Privacy Policy button during checkout and in the Help section', 'botoscope')
            ],
            'show_shipping_policy_button' => [
                'title' => esc_html__('Show shipping policy button', 'botoscope'),
                'value' => 0,
                'type' => 'switcher',
                'help' => esc_html__('Display a Shipping Policy button during checkout and in the Help section', 'botoscope')
            ],
            'disable_on_checkout_step_comments' => [
                'title' => esc_html__('Disable comments on checkout', 'botoscope'),
                'value' => 0,
                'type' => 'switcher',
                'help' => esc_html__('Disable the comments step during checkout', 'botoscope')
            ],
            'categories_per_row' => [
                'title' => esc_html__('Categories buttons per row', 'botoscope'),
                'value' => 2,
                'help' => esc_html__('How many buttons to display in the categories section of your Telegram shop', 'botoscope')
            ],
            'use_private_access' => [
                'title' => esc_html__('Use private access key', 'botoscope'),
                'value' => 0,
                'type' => 'switcher',
                'help' => esc_html__('Set a password to access your bot and share it with your customers. This makes your Telegram shop more private', 'botoscope')
            ],
            'private_access_key' => [
                'title' => esc_html__('Private access key', 'botoscope'),
                'value' => ''
            ],
            'show_botoscope_button_on_top_menu' => [
                'title' => esc_html__('Button in admin menu', 'botoscope'),
                'value' => 1,
                'type' => 'switcher',
                'help' => esc_html__('Display botoscope button in the top admin menu', 'botoscope')
            ],
            'delete_product_without_ask' => [
                'title' => esc_html__('Delete products without confirmation', 'botoscope'),
                'value' => 0,
                'help' => esc_html__('Delete products without confirmation in the admin panel', 'botoscope')
            ],
            'openai_api_key' => [
                'title' => esc_html__('Openai API key', 'botoscope'),
                'value' => '',
                'help' => esc_html__('Harness the power of AI to generate product descriptions and translations', 'botoscope')
            ],
            'booking_on_off_state' => [
                'title' => '',
                'value' => 0,
                'hide' => 1,
                'help' => esc_html__('Toggle booking feature on or off for your Telegram shop', 'botoscope')
            ],
            'botoscope_marketing_test_mode' => [
                'title' => '',
                'value' => 0,
                'hide' => 1,
                'help' => esc_html__('This mode allows you to test marketing campaigns visible only to the admin chat defined in BOTOSCOPE_ADMIN_CHAT_ID', 'botoscope')
            ],
            'support_username' => [
                'title' => '',
                'value' => '',
                'hide' => 1
            ],
            'support_web_site' => [
                'title' => '',
                'value' => '',
                'hide' => 1
            ],
            'support_mode' => [
                'title' => '',
                'value' => 'username',
                'hide' => 1
            ]
        ];

        //if (!class_exists('WPO_WCPDF')) {
        //we do not it here, let user manage it from invoice plugin options
        unset($this->controls['order_logo']);
        unset($this->controls['order_shop_title']);
        unset($this->controls['order_header']);
        unset($this->controls['order_footer']);
        //}

        add_action("botoscope_{$this->slug}_tab_icon", function () {
            return 'cogs';
        });

        if ($this->botoscope->no_bot) {
            $this->controls = [
                'show_botoscope_button_on_top_menu' => [
                    'title' => esc_html__('Button in admin menu', 'botoscope'),
                    'value' => 1,
                    'type' => 'switcher',
                    'help' => esc_html__('Display botoscope button in the top admin menu', 'botoscope')
                ],
                'openai_api_key' => [
                    'title' => esc_html__('Openai API key', 'botoscope'),
                    'value' => '',
                    'help' => esc_html__('Harness the power of AI to generate product descriptions and translations', 'botoscope')
                ],
            ];
        }

        //+++

        if (botoscope_is_no_cart()) {
            unset($this->controls['categories_per_row']);
            unset($this->controls['disable_on_checkout_step_comments']);
            unset($this->controls['categories_per_row']);
            unset($this->controls['categories_per_row']);
        } else {
            unset($this->controls['show_site_product_link_in_catalog_mode']);
        }

        //+++

        $this->controls = apply_filters('botoscope_controls', $this->controls);

        if (!$this->botoscope->no_bot) {

            $this->controls['options_full_reset'] = [
                'title' => esc_html__('Syncing site settings with Telegram', 'botoscope'),
                'value' => '',
                'help' => esc_html__('Synchronize all store parameters with the store in Telegram', 'botoscope'),
            ];

            $this->controls['products_full_reset'] = [
                'title' => esc_html__('Syncing products with Telegram', 'botoscope'),
                'value' => '',
                'help' => esc_html__('Use this if you have problems syncing products with your Telegram store', 'botoscope'),
            ];

            $this->controls['system_full_reset'] = [
                'title' => esc_html__('Full data synchronization with Telegram', 'botoscope'),
                'value' => '',
                'help' => esc_html__('Run this after a fresh system installation', 'botoscope'),
            ];
        }

        $this->botoscope->allrest->add_rest_route($this->slug, [$this, 'register_route']);
    }

    public function register_route(WP_REST_Request $request) {
        return $this->get_active();
    }

    public function get($exept_exts = 0, $print = false) {
        $res = [];

        foreach ($this->controls as $key => $o) {

            if ($print) {
                if (isset($o['hide'])) {
                    continue;
                }
            }

            $value = $this->get_option($key) ?? $o['value'];

            $res[] = [
                'id' => $key,
                'title' => $o['title'],
                'type' => $o['type'] ?? '',
                'value' => $value,
                'help' => $o['help'] ?? '',
                'is_active' => 1
            ];

            //for order logo
            if ($key === 'order_logo') {
                $res[] = [
                    'id' => 'order_logo_url',
                    'title' => 'order_logo_url',
                    'value' => intval($value) ? wp_get_attachment_url($value) : '',
                    'is_active' => 0
                ];
            }
        }

        if ($exept_exts) {
            return $res;
        }

        return Botoscope_Hooks::apply_action('botoscope_controls', $res, [$res]); //!!
    }

    public function update($id, $key, $value, $all_sent_data = []) {

        if ($id === 'default_language') {
            $lang = botoscope_convert_language_code_to_locale($value);
            update_option('WPLANG', $lang);

            $user_id = get_current_user_id();
            if ($user_id) {
                update_user_meta($user_id, 'locale', $lang);
            }

            switch_to_locale($lang);
        }

        $this->update_option($id, $key, $value);
    }

    public function update_option($key, $field_key, $value) {
        $row = $this->get_row($key);

        if (empty($row)) {
            $this->db->insert($this->table_name, [
                'control_key' => $key,
            ]);

            $id = $this->db->insert_id;
        } else {
            $id = intval($row['id']);
        }

        //***

        if ($key === 'disable_cart_checkout') {
            update_option('botoscope_disable_cart_checkout', $value);
        }

        //***
        $order_fields = ['order_logo', 'order_shop_title', 'order_header', 'order_footer'];
        if (in_array($key, $order_fields)) {
            $general_settings = get_option('wpo_wcpdf_settings_general');

            switch ($key) {
                case 'order_logo':
                    $general_settings['header_logo'] = [];
                    $general_settings['header_logo']['default'] = intval($value);
                    break;
                case 'order_shop_title':
                    $general_settings['shop_name'] = [];
                    $general_settings['shop_name']['default'] = esc_html($value);
                    break;
                case 'order_header':
                    $general_settings['shop_address'] = [];
                    $general_settings['shop_address']['default'] = esc_html($value);
                    break;
                case 'order_footer':
                    $general_settings['footer'] = [];
                    $general_settings['footer']['default'] = esc_html($value);
                    break;
            }

            update_option('wpo_wcpdf_settings_general', $general_settings);
        }


        $this->db->update($this->table_name, [$field_key => $value], ['id' => $id]);
    }

    public function get_option($key, $field_key = 'value') {
        $row = $this->get_row($key);

        if (empty($row) && array_key_exists($key, $this->controls)) {
            $row[$field_key] = $this->controls[$key]['value'];
        }

        return $row[$field_key] ?? 0;
    }

    private function get_row($key) {
        static $res = []; //cache

        if (!array_key_exists($key, $res)) {
            $res[$key] = $this->db->get_row(
                    $this->db->prepare("SELECT * FROM `{$this->table_name}` WHERE control_key = %s", $key),
                    ARRAY_A
            );
        }

        return $res[$key];
    }

    public function get_active() {

        $res = [];
        $rows = $this->get();

        if (!empty($rows)) {
            foreach ($rows as $v) {
                $res[$v['id']] = $v['value'];
            }
        }

        $res['filter_page_url'] = home_url('botoscope-filter');
        $res['variation_gallery_url'] = home_url('botoscope-variation-gallery');
        $res['site_domain'] = wp_parse_url(home_url(), PHP_URL_HOST);
        $res['filter_is_enabled'] = function_exists('woof') ? 1 : 0;
        $res['invoice_is_enabled'] = class_exists('WPO_WCPDF');
        unset($res['products_full_reset']);

        return $res;
    }

    public function get_active_languages() {
        $res = [];
        $option = $this->get_option('languages');
        $default_language = $this->get_option('default_language');

        if (!empty($option)) {
            $res = explode(',', $option);

            foreach ($res as $key => $value) {
                if ($value === $default_language) {
                    unset($res[$key]);
                }
            }
        }

        return $res;
    }

    public function get_default_language() {
        return $this->get_option('default_language');
    }

    public function draw_content($counter) {
        ?>

        <section id="botoscope-<?php echo esc_attr($this->slug) ?>" <?php if ($counter === 0): ?>class="content-current"<?php endif; ?>>
            <?php if (is_botoscope_free() && is_botoscope_connected()): ?>

                <div class="bs-warning-box"><p><?php
                        /* translators: 1: max products free, 2: max visitors, 3: max booking slots, 4: reconnect days, 5: max products paid */
                        printf(esc_html__('Free version includes: up to %1$s products, up to %2$s unique visitors per month, up to %3$s booking slots, "Powered by Botoscope" branding in your Telegram store, and manual reconnection required every %4$s days. Upgrade to the full version for up to %5$s products, unlimited visitors, unlimited booking slots, no branding, and uninterrupted connection.', 'botoscope'), 9, 200, 4, 14, 1000)
                        ?></p></div>
                <div class="bs-warning-box"><p><?php
                        /* translators: %s: product limit number */
                        printf(esc_html__('Grouped products on free version: Products included in this group may not sync to the bot if they exceed the %s-product limit.', 'botoscope'), 9)
                        ?></p></div>

        <?php endif; ?>

            <div id="botoscope-<?php echo esc_attr($this->slug) ?>-w"><?php echo wp_json_encode($this->get(true, true), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></div>
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
        control_key varchar(64) DEFAULT NULL,
        value text DEFAULT NULL,
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
                'control_key' => 'languages',
                'value' => 'es'
            ],
            [
                'control_key' => 'default_language',
                'value' => 'en'
            ],
            [
                'control_key' => 'size_param_slug',
                'value' => 'pa_size'
            ]
        ];

        foreach ($default_data as $data) {
            $wpdb->insert($this->table_name, $data);
        }
    }
}
