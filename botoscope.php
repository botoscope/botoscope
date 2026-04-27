<?php
/*
  Plugin Name: Botoscope
  Plugin URI: https://botoscope.com/
  Description: Connect your WooCommerce store to Telegram: product catalog, orders, booking and payments via Telegram bot
  Requires at least: 6.0
  Tested up to: 7.0
  Author: botoscope
  Author URI: https://botoscope.com/about
  Version: 1.0.0
  Requires PHP: 8.3
  Tags: woocommerce, telegram, ecommerce, shop, chatbot
  Text Domain: botoscope
  Domain Path: /languages
  WC requires at least: 9.0
  WC tested up to: 10.7
  Requires Plugins: woocommerce
  License: GPLv2 or later
  Forum URI: https://pluginus.net/support/forum/botoscope/
 */

//22-04-2026
if (!defined('ABSPATH')) {
    exit;
}

include_once 'lib/helper.php';
include_once 'lib/storage.php';
include_once 'lib/hooks.php';
include_once 'classes/app.php';
include_once 'classes/extensions.php';
include_once 'classes/currency.php';
include_once 'classes/payment.php';
include_once 'classes/controls.php';
include_once 'classes/translations.php';
include_once 'classes/taxonomies.php';
include_once 'classes/users.php';
include_once 'rest/orders.php';
include_once 'rest/allrest.php';

define('BOTOSCOPE_PLUGIN_NAME', plugin_basename(__FILE__));
define('BOTOSCOPE_VERSION', '1.0.0');
define('BOTOSCOPE_PATH', plugin_dir_path(__FILE__));
define('BOTOSCOPE_LINK', plugin_dir_url(__FILE__));
define('BOTOSCOPE_ASSETS_LINK', BOTOSCOPE_LINK . 'assets/');
define('BOTOSCOPE_EXT_PATH', BOTOSCOPE_PATH . 'exts/');
define('BOTOSCOPE_EXT_LINK', BOTOSCOPE_LINK . 'exts/');
define('BOTOSCOPE_CUSTOM_EXT_PATH', WP_CONTENT_DIR . '/botoscope/exts/');
define('BOTOSCOPE_CUSTOM_EXT_LINK', content_url('/botoscope/exts/'));

define('BOTOSCOPE_LOCALE_MAP', [
    'pl_PL' => 'pl',
    'es_ES' => 'es',
    'pt_PT' => 'pt',
    'pt_BR' => 'pt',
    'uk' => 'uk',
    'ru_RU' => 'ru',
    'kk' => 'kk',
]);

if (!defined('BOTOSCOPE_CLIENT_API_KEY')) {
    add_filter('plugin_row_meta', function ($links, $file) {
        if (strpos($file, 'botoscope/botoscope.php') !== false) {
            $locale = get_locale();
            $lang_prefix = isset(BOTOSCOPE_LOCALE_MAP[$locale]) ? BOTOSCOPE_LOCALE_MAP[$locale] . '/' : '';

            $links[] = '<br><br><a href="https://botoscope.com/' . $lang_prefix . 'start/" target="_blank" style="color: #d54e21; font-weight: bold;">' . esc_html__('Read Setup Instructions', 'botoscope') . '</a>';
        }
        return $links;
    }, 10, 2);
}

add_action('before_woocommerce_init', function () {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

class Botoscope {

    private $slug = 'botoscope';
    public $apps;
    public $storage;
    public $debug;
    //apps
    public $extensions;
    public $payment;
    public $options;
    public $controls;
    public $taxonomies;
    public $allrest;
    public $rest_orders;
    public $languages;
    public $translations;
    //exts
    public $sizecharts; //todo
    public $shipping;
    public $pickup_points; //todo
    public $coupons;
    public $support;
    public $elogios;
    public $advertising;
    public $product_attributes;
    public $interface_translations;
    public $currency;
    public $products;
    public $b2b;
    public $users;
    public $broadcast;
    public $booking;
    public $business_in_pocket;
    public $marketing;
    public $no_bot;
    public $shopify_sync;
    public static $allowed_slugs = ['botoscope-filter', 'botoscope-product-details', 'botoscope-thank-you', 'botoscope-variation-gallery',
        'botoscope-media-casting', 'botoscope-refund-policy', 'botoscope-privacy-policy', 'botoscope-shipping-policy', 'botoscope-chat'];

    public function __construct() {

        register_activation_hook(__FILE__, function () {
            flush_rewrite_rules();
        });

        $this->debug = apply_filters('botoscope_debug', 0);
        $this->no_bot = !defined('BOTOSCOPE_BOT_TOKEN');
        $this->storage = new BOTOSCOPE_STORAGE();

        $this->allrest = new BOTOSCOPE_REST_ALLREST($this);
        $this->rest_orders = new BOTOSCOPE_REST_ORDERS($this);

        $this->apps = ['extensions', 'currency', 'payment', 'controls', 'taxonomies', 'users'];

        foreach ($this->apps as $app) {
            $class_name = 'BOTOSCOPE_' . strtoupper($app);
            if (class_exists($class_name)) {
                $this->$app = new $class_name(['botoscope' => $this]);
            }
        }

        $this->languages = include 'data/supported_languages.php';
        $this->translations = new BOTOSCOPE_TRANSLATIONS(['botoscope' => $this]); //this is service for all apps

        $this->set_ajax_actions();
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
    }

    public function is_botoscope_page(): bool {
        if (!is_page()) {
            return false;
        }

        $obj = get_queried_object();
        $slug = is_object($obj) ? ($obj->post_name ?? '') : '';

        return $slug && in_array($slug, Botoscope::$allowed_slugs, true);
    }

    public function init() {
        //Intercepting rendering and outputting a "minimal" template
        add_action('template_redirect', function () {
            if (!$this->is_botoscope_page()) {
                return;
            }

            status_header(200);
            //nocache_headers();
            ?>
            <!DOCTYPE html>
            <html <?php language_attributes(); ?>>
                <head>
                    <meta charset="<?php bloginfo('charset'); ?>">
                    <meta name="viewport" content="width=device-width, initial-scale=1">
                    <meta name="robots" content="noindex,nofollow" />
                    <?php wp_enqueue_style('botoscope-page', apply_filters('botoscope_page_styles_link', BOTOSCOPE_ASSETS_LINK . 'css/page.css'), [], BOTOSCOPE_VERSION); ?>
                    <?php wp_head(); ?>
                </head>
                <body <?php body_class(); ?>>
                    <main>
                        <article>
                            <?php
                            while (have_posts()) {
                                the_post();
                                the_content();
                            }
                            ?>
                        </article>
                    </main>
                    <?php wp_footer(); ?>
                </body>
            </html>
            <?php
            exit;
        }, 999);

        //+++

        if (!class_exists('WooCommerce') || !current_user_can('manage_woocommerce') || !is_admin()) {
            return;
        }

        add_filter('admin_body_class', function ($classes) {
            if (isset($_GET['page']) && sanitize_key($_GET['page']) === $this->slug) {
                if ($this->no_bot) {
                    $classes .= ' botoscope-no-bot';
                }

                if (!wp_is_mobile()) {
                    $classes .= ' botoscope-desktop';
                } else {
                    $classes .= ' botoscope-mobile';
                }
            }

            return $classes;
        });

        add_filter('plugin_action_links_' . BOTOSCOPE_PLUGIN_NAME, function ($links) {
            $locale = get_locale();
            $lang_prefix = isset(BOTOSCOPE_LOCALE_MAP[$locale]) ? BOTOSCOPE_LOCALE_MAP[$locale] . '/' : '';

            $buttons = array(
                '<a href="' . admin_url("edit.php?post_type=product&page={$this->slug}") . '">Botoscope</a>',
                '<a target="_blank" href="https://botoscope.com/' . $lang_prefix . 'documentation"><span class="icon-book"></span>&nbsp;' . esc_html__('Documentation', 'botoscope') . '</a>'
            );
            return array_merge($buttons, $links);
        }, 50);

        add_action('admin_menu', function () {
            if (!current_user_can('manage_woocommerce')) {
                return;
            }

            add_submenu_page('edit.php?post_type=product', 'Botoscope', 'Botoscope', 'manage_woocommerce', $this->slug, function () {
                $args = [];

                $args['woocommerce_product_attributes'] = $this->get_woocommerce_product_attributes();
                $args['botoscope'] = $this;
                $args['languages'] = $this->languages;

                BOTOSCOPE_HELPER::render_html_e(BOTOSCOPE_PATH . 'views/options.php', $args);
            });
        }, 99);

        if (intval($this->controls->get_option('show_botoscope_button_on_top_menu'))) {
            add_action('admin_bar_menu', function ($admin_bar) {
                if (!current_user_can('manage_woocommerce')) {
                    return;
                }

                $admin_bar->add_menu([
                    'id' => 'botoscope_top_link',
                    'title' => 'Botoscope',
                    'href' => admin_url('edit.php?post_type=product&page=botoscope'),
                    'meta' => [
                        'title' => esc_html__('Botoscope: Products Dashboard', 'botoscope'),
                    ],
                ]);
            }, 100);
        }

        add_filter('attachment_fields_to_edit', function ($form_fields, $post) {
            if (strpos($post->post_mime_type, 'image/') === 0) {
                $custom_field = ['botoscope_video_link' =>
                    [
                        'label' => esc_html__('Botoscope telegram video link', 'botoscope'),
                        'input' => 'text',
                        'value' => get_post_meta($post->ID, 'botoscope_video_link', true),
                        'helps' => esc_html__('You can use this field to set a thumbnail preview for your videos in the admin area. This is especially useful for links to videos shared in your dedicated Telegram channel when the videos exceed 25 seconds in length and/or are too large for the Telegram API.', 'botoscope')
                    ]
                ];

                $form_fields = $custom_field + $form_fields; //to make dit first field of the custom ones
            }
            return $form_fields;
        }, 10, 2);

        add_filter('attachment_fields_to_save', function ($post, $attachment) {
            if (isset($attachment['botoscope_video_link'])) {
                update_post_meta($post['ID'], 'botoscope_video_link', sanitize_text_field($attachment['botoscope_video_link']));
            }
            return $post;
        }, 10, 2);
    }

    private function set_ajax_actions() {
        add_action('wp_ajax_botoscope_reset_cache', function () {
            if ($this->is_ajax_request_valid()) {
                $this->reset_cache(sanitize_textarea_field($_REQUEST['cache_name']));
                die('done');
            }

            die('0');
        }, 1);

        add_action('wp_ajax_botoscope_check_nonce', function () {
            if ($this->is_ajax_request_valid()) {
                die('1');
            }

            die('0');
        }, 1);

        add_action('wp_ajax_botoscope_add_row', array($this, 'botoscope_add_row'), 1);
        add_action('wp_ajax_botoscope_edit_cell', array($this, 'botoscope_edit_cell'), 1);
        add_action('wp_ajax_botoscope_get_parent_cell_data', array($this, 'botoscope_get_parent_cell_data'), 1);
        add_action('wp_ajax_botoscope_delete_row', array($this, 'botoscope_delete_row'), 1);
        add_action('wp_ajax_botoscope_search_orders', array($this, 'botoscope_search_orders'), 1);
        add_action('wp_ajax_botoscope_get_translations', array($this, 'botoscope_get_translations'), 1);
        add_action('wp_ajax_botoscope_get_page_data', function () {
            if ($this->is_ajax_request_valid()) {
                $page_num = intval($_REQUEST['page_num']);
                $page_num = $page_num < 0 ? 0 : $page_num;
                $search = map_deep(wp_unslash($_REQUEST['search'] ?? []), 'sanitize_text_field');

                die(wp_json_encode($this->get_data(sanitize_text_field($_REQUEST['what']), $page_num, sanitize_text_field($_REQUEST['order_by']), sanitize_text_field($_REQUEST['order']), $search)));
            }

            die('0');
        }, 1);
        add_action('wp_ajax_botoscope_translate_string', array($this, 'botoscope_translate_string'), 1);
        add_action('wp_ajax_botoscope_get_sidebar_html', function () {
            if ($this->is_ajax_request_valid()) {
                Botoscope_Hooks::apply_action('botoscope_get_sidebar_html', [], [
                    sanitize_text_field($_REQUEST['what']),
                    sanitize_text_field($_REQUEST['template_name']),
                    intval($_REQUEST['id']),
                ]);
                exit;
            }

            die('0');
        }, 1);

        add_action('wp_ajax_botoscope_edit_row', function () {
            if ($this->is_ajax_request_valid()) {
                $data = [];

                if (isset($_REQUEST['data']) && is_array($_REQUEST['data'])) {
                    foreach (wp_unslash($_REQUEST['data']) as $key => $value) {
                        $data[sanitize_key($key)] = is_array($value) ? array_map('wp_kses_post', $value) : (is_string($value) ? wp_kses_post($value) : $value);
                    }
                }

                Botoscope_Hooks::apply_action('botoscope_edit_row', [], [
                    sanitize_text_field($_REQUEST['what']),
                    sanitize_text_field($_REQUEST['id']),
                    $data
                ]);
                exit;
            }

            die('0');
        }, 1);

        add_action('wp_ajax_botoscope_set_table_col_positions', function () {
            if ($this->is_ajax_request_valid()) {
                $what = sanitize_text_field($_REQUEST['what']);
                $keys = sanitize_text_field($_REQUEST['keys']);
                update_option("botoscope_{$what}_table_col_positions", $keys);
                exit;
            }

            die('0');
        }, 1);

        add_action('wp_ajax_botoscope_get_table_col_positions', function () {
            if ($this->is_ajax_request_valid()) {
                $what = sanitize_text_field($_REQUEST['what']);
                echo esc_html(get_option("botoscope_{$what}_table_col_positions", ''));
                exit;
            }

            die('0');
        }, 1);
    }

    public function admin_enqueue_scripts() {
        if (isset($_GET['page']) && sanitize_key($_GET['page']) === $this->slug) {

            add_filter('admin_body_class', function ($classes) {
                $classes .= ' folded';
                if (botoscope_is_no_cart()) {
                    $classes .= ' botoscope_is_no_cart';
                }
                return $classes;
            });

            wp_enqueue_media();
            wp_enqueue_script('media-upload');
            wp_enqueue_style('thickbox');
            wp_enqueue_script('thickbox');
            wp_enqueue_script('editor');
            wp_enqueue_script('quicktags');
            wp_enqueue_script('wp-tinymce');
            wp_enqueue_style('editor-style');

            add_filter('script_loader_tag', function ($tag, $handle, $src) {
                if ($handle === $this->slug) {
                    // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
                    $tag = '<script type="module" src="' . esc_url($src) . '"></script>';
                }
                return $tag;
            }, 10, 3);

            wp_enqueue_style('dashicons');
            wp_enqueue_script('jquery-ui-sortable');
            wp_enqueue_style(
                    'woocommerce-jquery-ui',
                    plugins_url('assets/css/jquery-ui/jquery-ui.min.css', WC_PLUGIN_FILE),
                    [],
                    BOTOSCOPE_VERSION
            );

            wp_enqueue_style('select2-css', WC()->plugin_url() . '/assets/css/select2.css', [], BOTOSCOPE_VERSION, true);
            wp_enqueue_script('select2-js', WC()->plugin_url() . '/assets/js/select2/select2.full.min.js', ['jquery'], BOTOSCOPE_VERSION, true);

            wp_enqueue_script('botoscope_general', BOTOSCOPE_ASSETS_LINK . 'js/general.js', [], BOTOSCOPE_VERSION, false);
            wp_enqueue_script($this->slug, BOTOSCOPE_ASSETS_LINK . 'js/' . $this->slug . '.js', [], BOTOSCOPE_VERSION, true);
            wp_enqueue_script("{$this->slug}_tabs", BOTOSCOPE_ASSETS_LINK . 'js/tabs.js', [], BOTOSCOPE_VERSION, false);
            wp_enqueue_script("{$this->slug}_perfect-scrollbar", BOTOSCOPE_ASSETS_LINK . 'js/vendor/perfect-scrollbar.js', [], BOTOSCOPE_VERSION, true);

            $css = [
                'css/vendor/perfect-scrollbar.css',
                'css/lib/popup-23.css',
                'css/lib/calendar23.css',
                'css/lib/selectm23.css',
                'css/lib/sidebar.css',
                'css/vendor/fontello.css',
                'css/vendor/bootstrap.min.css',
                'css/vendor/growls.css',
                'css/botoscope.css',
                'css/botoscope-mobile.css',
                'css/botoscope-theme.css'
            ];

            foreach ($css as $num => $path) {
                wp_enqueue_style("{$this->slug}_asset_{$num}", BOTOSCOPE_ASSETS_LINK . $path, [], BOTOSCOPE_VERSION);
            }

            // --- Runtime variables (depend on PHP constants and functions) ---
            add_action('admin_print_scripts', function () {
                wp_print_inline_script_tag(
                        'var botoscope_no_bot = ' . ($this->no_bot ? 1 : 0) . ';' .
                        'var botoscope_url = "' . esc_url(BOTOSCOPE_LINK) . '";' .
                        'var botoscope_custom_ext_url = "' . esc_url(BOTOSCOPE_CUSTOM_EXT_LINK) . '";' .
                        'var botoscope_bot_name = "' . esc_js(defined('BOTOSCOPE_BOT_NAME') ? BOTOSCOPE_BOT_NAME : '') . '";' .
                        'var botoscope_locale = "' . esc_js(get_locale()) . '";' .
                        'var botoscope_default_language = "' . esc_js($this->get_default_language()) . '";' .
                        'var botoscope_is_no_cart = ' . (botoscope_is_no_cart() ? 1 : 0) . ';' .
                        'var botoscope_is_mobile = ' . (wp_is_mobile() ? 1 : 0) . ';' .
                        'var botoscope_default_image = "' . esc_url(BOTOSCOPE_ASSETS_LINK) . 'img/no-image.webp";' .
                        'var botoscope_delete_product_without_ask = ' . intval($this->controls->get_option('delete_product_without_ask') ?? 0) . ';' .
                        'var botoscope_site_url = "' . esc_url(get_site_url()) . '/";' .
                        'var is_botoscope_connected = ' . (is_botoscope_connected() ? 1 : 0) . ';' .
                        'var botoscope_lang = {};'
                );
            });

            // --- Translated strings via wp_localize_script ---
            $botoscope_lang = [
                'botoscope' => esc_html__('Botoscope — Your Partner for Telegram Sales Success', 'botoscope'),
                'after_bear_edit' => esc_html__('After performing bulk operations, go to the Botoscope system controls and click the "Syncing products with Telegram" button to synchronize with the bot.', 'botoscope'),
                'type' => esc_html__('Type', 'botoscope'),
                'topic' => esc_html__('Topic', 'botoscope'),
                'interaction' => esc_html__('Interaction', 'botoscope'),
                'unanswered' => esc_html__('Unanswered', 'botoscope'),
                'updated' => esc_html__('Updated', 'botoscope'),
                'messages' => esc_html__('Messages', 'botoscope'),
                'search_orders' => esc_html__('Search orders', 'botoscope'),
                'close' => esc_html__('Close', 'botoscope'),
                'enter_order_num' => esc_html__('Please enter order number ...', 'botoscope'),
                'status' => esc_html__('Status', 'botoscope'),
                'total' => esc_html__('Total', 'botoscope'),
                'date' => esc_html__('Date', 'botoscope'),
                'ticket' => esc_html__('Ticket', 'botoscope'),
                'messages_for' => esc_html__('Messages for', 'botoscope'),
                'messages_for_order' => esc_html__('Messages for order', 'botoscope'),
                'role' => esc_html__('Role', 'botoscope'),
                'content' => esc_html__('Content', 'botoscope'),
                'time' => esc_html__('Time', 'botoscope'),
                'are_you_sure' => esc_html__('Are you sure?', 'botoscope'),
                'manage' => esc_html__('Manage', 'botoscope'),
                'manage_products' => esc_html__('Manage products', 'botoscope'),
                'default' => esc_html__('Default', 'botoscope'),
                'title' => esc_html__('Title', 'botoscope'),
                'description' => esc_html__('Description', 'botoscope'),
                'table' => esc_html__('Table', 'botoscope'),
                'active' => esc_html__('Active', 'botoscope'),
                'delete' => esc_html__('Delete', 'botoscope'),
                'cancel' => esc_html__('Cancel', 'botoscope'),
                'charts_for' => esc_html__('Charts for', 'botoscope'),
                'height' => esc_html__('Height', 'botoscope'),
                'neck' => esc_html__('Neck', 'botoscope'),
                'shoulder' => esc_html__('Shoulder', 'botoscope'),
                'breast' => esc_html__('Breast', 'botoscope'),
                'waist' => esc_html__('Waist', 'botoscope'),
                'hip' => esc_html__('Hip', 'botoscope'),
                'arm' => esc_html__('Arm', 'botoscope'),
                'leg_length_from_waist' => esc_html__('Leg length from waist', 'botoscope'),
                'append_new_row' => esc_html__('append new row', 'botoscope'),
                'order' => esc_html__('Order', 'botoscope'),
                'price' => esc_html__('Price', 'botoscope'),
                'minimum' => esc_html__('Minimum', 'botoscope'),
                'product' => esc_html__('Product', 'botoscope'),
                'product_downloads' => esc_html__('Product downloads', 'botoscope'),
                'terms' => esc_html__('Terms', 'botoscope'),
                'search_by_title_or_sku' => esc_html__('search by title or sku or ID ...', 'botoscope'),
                'add' => esc_html__('Add', 'botoscope'),
                'select_media' => esc_html__('Select media for the product', 'botoscope'),
                'media' => esc_html__('Media', 'botoscope'),
                'sale' => esc_html__('Sale', 'botoscope'),
                'sku' => esc_html__('SKU', 'botoscope'),
                'category' => esc_html__('Category', 'botoscope'),
                'select_category' => esc_html__('select product category ...', 'botoscope'),
                'select_terms' => esc_html__('select terms', 'botoscope'),
                'select_attribute' => esc_html__('Select attribute', 'botoscope'),
                'attribute' => esc_html__('Attribute', 'botoscope'),
                'display' => esc_html__('Display', 'botoscope'),
                'inline' => esc_html__('Inline', 'botoscope'),
                'formula' => esc_html__('Formula', 'botoscope'),
                'icon' => esc_html__('Icon', 'botoscope'),
                'name' => esc_html__('Name', 'botoscope'),
                'address' => esc_html__('Address', 'botoscope'),
                'details' => esc_html__('Details', 'botoscope'),
                'shipping' => esc_html__('Shipping', 'botoscope'),
                'formulas' => esc_html__('Formulas', 'botoscope'),
                'formulas_for' => esc_html__('Formulas for', 'botoscope'),
                'mar_ext_should_active' => esc_html__('Marketing strategies ext should be activated!', 'botoscope'),
                'products' => esc_html__('Products', 'botoscope'),
                'excluded_products' => esc_html__('Excluded Products', 'botoscope'),
                'products_for' => esc_html__('Products for', 'botoscope'),
                'groups_for' => esc_html__('Groups for', 'botoscope'),
                'select_strategia' => esc_html__('Select strategia', 'botoscope'),
                'select_date' => esc_html__('select date', 'botoscope'),
                'strategia_id' => esc_html__('Strategia', 'botoscope'),
                'time_start' => esc_html__('Start', 'botoscope'),
                'time_finish' => esc_html__('Finish', 'botoscope'),
                'key' => esc_html__('Key', 'botoscope'),
                'original' => esc_html__('Original', 'botoscope'),
                'customized' => esc_html__('Customized', 'botoscope'),
                'code' => esc_html__('Code', 'botoscope'),
                'amount' => esc_html__('Amount', 'botoscope'),
                'usage_limit' => esc_html__('Limit', 'botoscope'),
                'maximum' => esc_html__('Maximum', 'botoscope'),
                'expiry' => esc_html__('Expiry', 'botoscope'),
                'enter3_to_start_search' => esc_html__('Please enter 3 or more characters to start searching, or type an product ID (e.g. 123 or v123(for variations of variable product))...', 'botoscope'),
                'child_terms' => esc_html__('Child terms', 'botoscope'),
                'translated' => esc_html__('Translated', 'botoscope'),
                'settings' => esc_html__('Settings', 'botoscope'),
                'settings_for' => esc_html__('Settings for', 'botoscope'),
                'value' => esc_html__('Value', 'botoscope'),
                'video' => esc_html__('Video', 'botoscope'),
                'bot_languages' => esc_html__('Bot Languages', 'botoscope'),
                /* translators: %s: max products number */
                'reset_products_cache️' => is_botoscope_free() ? sprintf(esc_html__('Syncing products with Telegram (max. %s products)', 'botoscope'), 9) : esc_html__('Syncing products with Telegram', 'botoscope'),
                'reset_system_cache️' => esc_html__('Full data synchronization with Telegram', 'botoscope'),
                'reset_options_cache️' => esc_html__('Syncing site settings with Telegram', 'botoscope'),
                'percent_to_cart' => esc_html__('percent to cart', 'botoscope'),
                'fixed_to_cart' => esc_html__('fixed to cart', 'botoscope'),
                'percent_to_selected_product' => esc_html__('percent to selected product', 'botoscope'),
                'fixed_to_selected_product' => esc_html__('fixed to selected product', 'botoscope'),
                'send_message' => esc_html__('send message', 'botoscope'),
                'in_stock' => esc_html__('in stock', 'botoscope'),
                'out_of_stock' => esc_html__('out of stock', 'botoscope'),
                'delivery_methods' => esc_html__('delivery methods', 'botoscope'),
                'wrong_sale_price' => esc_html__('Sale price should be lower than the regular price!', 'botoscope'),
                'provide_some_text' => esc_html__('You should provide some text', 'botoscope'),
                'saved' => esc_html__('Saved', 'botoscope'),
                'saving' => esc_html__('Saving ...', 'botoscope'),
                'file_url' => esc_html__('File URL', 'botoscope'),
                'no_data' => esc_html__('No data', 'botoscope'),
                'loading' => esc_html__('Loading ...', 'botoscope'),
                'done' => esc_html__('Done', 'botoscope'),
                'variation' => esc_html__('Variation', 'botoscope'),
                'variations' => esc_html__('Variations', 'botoscope'),
                'variations_of' => esc_html__('Variations of product', 'botoscope'),
                'variations_for' => esc_html__('Variations for product', 'botoscope'),
                'variation_of' => esc_html__('Variation of product', 'botoscope'),
                'select_possible_variation' => esc_html__('Select possible variation', 'botoscope'),
                'no_free_possible_variations' => esc_html__('There are no free possible combinations', 'botoscope'),
                'select' => esc_html__('Select', 'botoscope'),
                'combination' => esc_html__('Combination', 'botoscope'),
                'wrong_combination' => esc_html__('Combination of attributes should be unique', 'botoscope'),
                'attributes' => esc_html__('Attributes', 'botoscope'),
                'not_selected' => esc_html__('not selected', 'botoscope'),
                'new_attribute_taxonomy' => esc_html__('New attribute', 'botoscope'),
                'new_taxonomy' => esc_html__('New taxonomy', 'botoscope'),
                'translating' => esc_html__('Translating ...', 'botoscope'),
                'require_openai_key' => esc_html__('Place into settings openai API key!', 'botoscope'),
                'append_formula' => esc_html__('append new formula', 'botoscope'),
                'select_attributes_and_terms' => esc_html__('Kindly select the product attributes and their corresponding terms first, and then click save button', 'botoscope'),
                'select_prod_type' => esc_html__('Select product type', 'botoscope'),
                'select_prod_category' => esc_html__('Select product category', 'botoscope'),
                'simple' => esc_html__('Simple', 'botoscope'),
                'simple_virtual' => esc_html__('Simple virtual', 'botoscope'),
                'simple_virtual_downloadable' => esc_html__('Simple virtual downloadable', 'botoscope'),
                'simple_media_casting' => esc_html__('Simple media casting', 'botoscope'),
                'external' => esc_html__('External', 'botoscope'),
                'grouped' => esc_html__('Grouped', 'botoscope'),
                'variable' => esc_html__('Variable', 'botoscope'),
                'button' => esc_html__('Button', 'botoscope'),
                'switcher' => esc_html__('Switcher', 'botoscope'),
                /* translators: 1: max files count, 2: added files count */
                'max_files_count' => esc_html__('The maximum allowed number of files is %1$s. Only %2$s of the selected files have been added.', 'botoscope'),
                /* translators: %s: max files number */
                'max_files_count_no_added' => esc_html__('You\'ve reached the maximum file limit of %s. No more files can be added.', 'botoscope'),
                'orders' => esc_html__('Orders', 'botoscope'),
                'reports' => esc_html__('Reports', 'botoscope'),
                'gallery' => esc_html__('Gallery', 'botoscope'),
                'bulk_editing' => esc_html__('Bulk Editing', 'botoscope'),
                'currency' => esc_html__('Currency', 'botoscope'),
                'filter' => esc_html__('Filter', 'botoscope'),
                'log_out' => esc_html__('Log out', 'botoscope'),
                'enter_products_count_to_create' => esc_html__('Enter the number of products you want to create', 'botoscope'),
                'append_to_group' => esc_html__('to group', 'botoscope'),
                'enter_group_product_id' => esc_html__('Enter the ID of the group product. Enter 0 to detach', 'botoscope'),
                'select_audio' => esc_html__('Select audio file', 'botoscope'),
                'add_audio' => esc_html__('Add audio', 'botoscope'),
                'select_image' => esc_html__('Select image', 'botoscope'),
                'add_image' => esc_html__('Add image', 'botoscope'),
                'yes' => esc_html__('Yes', 'botoscope'),
                'no' => esc_html__('No', 'botoscope'),
                'insert_html' => esc_html__('Insert HTML', 'botoscope'),
                'enter_html_code' => esc_html__('Enter HTML code', 'botoscope'),
                'add_all' => esc_html__('Add all', 'botoscope'),
                'composition' => esc_html__('Composition', 'botoscope'),
                'create' => esc_html__('Create', 'botoscope'),
                'clone' => esc_html__('Clone', 'botoscope'),
                'create_meta_field' => esc_html__('Create meta field', 'botoscope'),
                'append_meta_field' => esc_html__('Append meta field', 'botoscope'),
                'product_meta' => esc_html__('Product meta', 'botoscope'),
                'products_meta_gallery' => esc_html__('Products meta gallery', 'botoscope'),
                'unit_of_measurement' => esc_html__('Measure', 'botoscope'),
                'append' => esc_html__('Append', 'botoscope'),
                'create_meta_pack' => esc_html__('Create meta pack', 'botoscope'),
                'load_meta_pack' => esc_html__('Load meta pack', 'botoscope'),
                'meta_packs' => esc_html__('Meta packs', 'botoscope'),
                'apply' => esc_html__('Apply', 'botoscope'),
                'meta_position_media' => esc_html__('Show on media gallery', 'botoscope'),
                'meta_position_description' => esc_html__('Show under description', 'botoscope'),
                'added' => esc_html__('Added', 'botoscope'),
                'analytics' => esc_html__('Analytics', 'botoscope'),
                'pages' => esc_html__('Pages', 'botoscope'),
                'refund_policy' => esc_html__('Refund policy', 'botoscope'),
                'privacy_policy' => esc_html__('Privacy policy', 'botoscope'),
                'shipping_policy' => esc_html__('Shipping policy', 'botoscope'),
                'edit' => esc_html__('Edit', 'botoscope'),
                'meta_delete' => esc_html__('Are you sure? All metadata attached to products will also be deleted. This action cannot be undone!', 'botoscope'),
                'message' => esc_html__('Message', 'botoscope'),
                'sent' => esc_html__('Sent', 'botoscope'),
                'tools' => esc_html__('Tools', 'botoscope'),
                'products_import' => esc_html__('Products import', 'botoscope'),
                'products_export' => esc_html__('Products export', 'botoscope'),
                'set_start' => esc_html__('set start', 'botoscope'),
                'set_finish' => esc_html__('set finish', 'botoscope'),
                'test_mode' => esc_html__('Test mode', 'botoscope'),
                'deactivated' => esc_html__('Deactivated', 'botoscope'),
                'help' => esc_html__('Help', 'botoscope'),
                'make_products_visible' => esc_html__('Make all published products visible in your Telegram store?', 'botoscope'),
                'make_products_hidden' => esc_html__('Hide all products in your Telegram store?', 'botoscope'),
                'make_product_published' => esc_html__('Please publish the product first!', 'botoscope'),
                'works_better_on' => esc_html__('The dashboard works best on larger screens. Try opening the site from a laptop, desktop, or tablet — the interface will be more convenient.', 'botoscope'),
                'month_names' => [
                    esc_html__('January', 'botoscope'),
                    esc_html__('February', 'botoscope'),
                    esc_html__('March', 'botoscope'),
                    esc_html__('April', 'botoscope'),
                    esc_html__('May', 'botoscope'),
                    esc_html__('June', 'botoscope'),
                    esc_html__('July', 'botoscope'),
                    esc_html__('August', 'botoscope'),
                    esc_html__('September', 'botoscope'),
                    esc_html__('October', 'botoscope'),
                    esc_html__('November', 'botoscope'),
                    esc_html__('December', 'botoscope'),
                ],
                'month_names_short' => [
                    esc_html__('Jan', 'botoscope'),
                    esc_html__('Feb', 'botoscope'),
                    esc_html__('Mar', 'botoscope'),
                    esc_html__('Apr', 'botoscope'),
                    esc_html__('May', 'botoscope'),
                    esc_html__('Jun', 'botoscope'),
                    esc_html__('Jul', 'botoscope'),
                    esc_html__('Aug', 'botoscope'),
                    esc_html__('Sep', 'botoscope'),
                    esc_html__('Oct', 'botoscope'),
                    esc_html__('Nov', 'botoscope'),
                    esc_html__('Dec', 'botoscope'),
                ],
                'day_names' => [
                    esc_html__('Mo', 'botoscope'),
                    esc_html__('Tu', 'botoscope'),
                    esc_html__('We', 'botoscope'),
                    esc_html__('Th', 'botoscope'),
                    esc_html__('Fr', 'botoscope'),
                    esc_html__('Sa', 'botoscope'),
                    esc_html__('Su', 'botoscope'),
                ],
            ];

            wp_localize_script($this->slug, 'botoscope_lang', apply_filters('botoscope_lang', $botoscope_lang));
        }
    }

    //+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

    public function get_default_language() {
        return $this->controls->get_default_language() ?? substr(get_locale(), 0, 2);
    }

    private function get_data($what, $page_num = 0, $order_by = '', $order = '', $search = []) {
        $res = [];

        if ($order_by === 'oid') {
            $order_by = 'id';
        }

        if (property_exists($this, $what) && $this->$what && method_exists($this->$what, 'get')) {
            $this->$what->search = $search;
            $res = $this->$what->get($page_num, compact('order_by', 'order', 'search'));
            if (property_exists($this->$what, 'found_posts') && $this->$what->found_posts !== -1) {
                $res = ['posts' => $res, 'found_posts' => $this->$what->found_posts];
            }
        } else {
            $res = Botoscope_Hooks::apply_action('botoscope_get_page_data', [], [$what, $page_num, $order_by, $order, $search]);
        }

        return $res;
    }

    //for botoscope bridge plugin
    public function get_active($what, $more_data = []) {
        $data = $this->get_data($what);

        if (property_exists($this, $what) && $this->$what && method_exists($this->$what, 'get_active')) {
            $res = $this->$what->get_active($more_data);
        } else {
            $res = array_values(array_filter($data, function ($item) {
                        return isset($item['is_active']) && intval($item['is_active']) === 1;
                    }));
        }

        return $res;
    }

    //ajax
    public function botoscope_add_row() {
        if ($this->is_ajax_request_valid()) {
            $what = sanitize_text_field($_REQUEST['what']);
            $parent_row_id = isset($_REQUEST['additional_params']['parent_row_id']) ? intval($_REQUEST['additional_params']['parent_row_id']) : 0;
            $content = isset($_REQUEST['additional_params']['content']) ? sanitize_textarea_field($_REQUEST['additional_params']['content']) : '';

            if (property_exists($this, $what) && $this->$what && method_exists($this->$what, 'create')) {
                $res = $this->$what->create();
            } else {
                $res = Botoscope_Hooks::apply_action('botoscope_add_row', [], [$what, $parent_row_id, $content]);
            }

            die(wp_json_encode($res));
        }
    }

    //ajax
    public function botoscope_edit_cell() {
        if ($this->is_ajax_request_valid()) {

            $key = sanitize_key($_REQUEST['key'] ?? '');
            $id = isset($_REQUEST['id']) ? is_numeric($_REQUEST['id']) ? intval($_REQUEST['id']) : sanitize_text_field($_REQUEST['id']) : 0;
            $value = wp_kses_post(wp_unslash($_REQUEST['value'] ?? ''));
            $what = sanitize_text_field($_REQUEST['what']);
            $request_data = [];
            
            if (property_exists($this, $what) && $this->$what && method_exists($this->$what, 'update')) {

                //!!fix
                if ($value === 'null') {
                    $value = null;
                }
                
                foreach (wp_unslash($_REQUEST) as $k => $v) {
                    $request_data[sanitize_key($k)] = is_array($v) ? array_map('wp_kses_post', $v) : (is_string($v) ? wp_kses_post($v) : $v);
                }

                $this->$what->update($id, $key, $value, $request_data);

                if ($this->$what->synhronize_cache) {
                    $this->reset_cache($what);
                }
            }

            foreach (wp_unslash($_REQUEST) as $k => $v) {
                $request_data[sanitize_key($k)] = is_array($v) ? array_map('wp_kses_post', $v) : (is_string($v) ? wp_kses_post($v) : $v);
            }

            Botoscope_Hooks::apply_action('botoscope_edit_cell', [], [$what, $id, $key, $value, $request_data]);

            die('done');
        }
    }

    //ajax
    public function botoscope_get_parent_cell_data() {
        if ($this->is_ajax_request_valid()) {
            $parent_app = sanitize_text_field($_REQUEST['parent_app']);
            $parent_cell_name = sanitize_text_field($_REQUEST['parent_cell_name']);
            $parent_row_id = is_numeric($_REQUEST['parent_row_id']) ? intval($_REQUEST['parent_row_id']) : sanitize_text_field($_REQUEST['parent_row_id']);

            $res = Botoscope_Hooks::apply_action('botoscope_get_parent_cell_data', [], [$parent_app, $parent_row_id, $parent_cell_name]);
            die(wp_json_encode($res));
        }
    }

    //ajax
    public function botoscope_delete_row() {
        if ($this->is_ajax_request_valid()) {

            $what = sanitize_text_field($_REQUEST['what']);
            $row_id = intval($_REQUEST['row_id']);

            if (isset($_REQUEST['additional_params']['parent_row_id'])) {
                $parent_row_id = intval($_REQUEST['additional_params']['parent_row_id'] ?? 0);
            } else {
                $parent_row_id = intval($_REQUEST['parent_row_id'] ?? 0);
            }

            if (property_exists($this, $what) && $this->$what && method_exists($this->$what, 'delete')) {
                $this->$what->delete($row_id);
                if ($this->$what->synhronize_cache) {
                    $this->reset_cache($what);
                }
            }

            Botoscope_Hooks::apply_action('botoscope_delete_row', [], [$what, $row_id, $parent_row_id]);

            die('done');
        }
    }

    //ajax
    public function botoscope_get_translations() {
        if ($this->is_ajax_request_valid()) {
            $res = [];

            $parent_app = sanitize_text_field($_REQUEST['parent_app']);
            $parent_cell_name = sanitize_text_field($_REQUEST['parent_cell_name']);
            $parent_row_id = is_numeric($_REQUEST['parent_row_id']) ? intval($_REQUEST['parent_row_id']) : sanitize_text_field($_REQUEST['parent_row_id']);

            $active_languages = $this->controls->get_active_languages();

            if (!empty($active_languages)) {
                foreach ($active_languages as $language) {
                    $translation = $this->translations->get_translation($language, $parent_app, $parent_row_id, $parent_cell_name);

                    $res[] = [
                        'id' => $translation['id'],
                        'language' => $language,
                        'value' => strval($translation['value'])
                    ];
                }
            }


            die(wp_json_encode($res));
        }
    }

    public function get_woocommerce_product_attributes() {
        $attributes = wc_get_attribute_taxonomies();
        $attributes_array = [];

        foreach ($attributes as $attribute) {
            $attribute = (array) $attribute;
            $taxonomy = 'pa_' . $attribute['attribute_name'];

            //if (taxonomy_exists($taxonomy)) {
            $attributes_array[] = [
                'name' => wp_strip_all_tags($attribute['attribute_label']),
                'slug' => $taxonomy
            ];
            //}
        }

        return $attributes_array;
    }

    public function do_command($chat_id, $command, $params) {

        if ($this->no_bot) {
            return;
        }

        $data = [];
        $data['chat_id'] = $chat_id;
        $data['command'] = $command;
        $data['data'] = $params;
        $data['encrypted_pass'] = defined('BOTOSCOPE_CLIENT_PASS') ? BOTOSCOPE_HELPER::encrypt_value(BOTOSCOPE_CLIENT_PASS, BOTOSCOPE_CLIENT_API_KEY) : '';

        $client_api_key = defined('BOTOSCOPE_CLIENT_API_KEY') ? BOTOSCOPE_CLIENT_API_KEY : '';
        if (defined('BOTOSCOPE_PROXY_SERVER')) {
            return BOTOSCOPE_HELPER::http_request(BOTOSCOPE_PROXY_SERVER, [
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'Client-Api-Key' => $client_api_key
                        ],
                        'method' => 'POST',
                        'body' => wp_json_encode($data)
            ]);
        }
    }

    public function reset_cache($cache_name) {
        $this->do_command(-1, 'reset_cache', [
            'cache_name' => $cache_name
        ]);
    }

    public function botoscope_search_orders() {
        if ($this->is_ajax_request_valid()) {
            $res = [];
            $order_id = intval($_REQUEST['value']);
            $order = wc_get_order($order_id);
            if ($order) {
                $res[] = [
                    'id' => $order->get_id(),
                    'status' => wc_get_order_status_name($order->get_status()),
                    'total' => $order->get_total(),
                    'date' => $order->get_date_created()->date('Y-m-d H:i:s')
                ];
            }
            die(wp_json_encode($res));
        }
    }

    //ajax
    public function botoscope_translate_string() {

        $api_key = $this->controls->get_option('openai_api_key');

        $text = sanitize_textarea_field($_REQUEST['value']);
        $target_language = trim(sanitize_text_field($_REQUEST['selected_language']));
        $url = "https://api.openai.com/v1/chat/completions";

        $headers = [
            "Content-Type: application/json",
            "Authorization: Bearer $api_key"
        ];

        $data = [
            "model" => "gpt-4-turbo",
            "messages" => [
                [
                    "role" => "system",
                    "content" => "You are a professional translator. Translate the provided text into {$target_language}. Return only the translated text with exactly the same formatting as the original, including line breaks, empty lines, and paragraph spacing. Ensure that the first letter of each sentence is properly capitalized. Keep the original syntax and context of emoji symbols, if present."
                ],
                [
                    "role" => "user",
                    "content" => $text
                ]
            ],
            "temperature" => 0.5
        ];

        $wp_response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body' => wp_json_encode($data),
            'timeout' => 30,
        ]);
        $response = wp_remote_retrieve_body($wp_response);
        $response_data = json_decode($response, true);

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- OpenAI API response passed directly to wp_die() as plain text content for AJAX handler
        wp_die($response_data["choices"][0]["message"]["content"] ?? "-1");
    }

    public function is_ajax_request_valid() {
        return !(!current_user_can('manage_woocommerce') || !isset($_REQUEST['botoscope_form_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['botoscope_form_nonce'])), 'botoscope_form_nonce'));
    }
}

//!!
add_filter('woocommerce_currencies', function ($currencies) {
    $currencies['XTR'] = esc_html__('Telegram Stars', 'botoscope');
    return $currencies;
});

add_filter('woocommerce_currency_symbol', function ($symbol, $currency) {
    if ($currency === 'XTR') {
        return '⭐';
    }
    return $symbol;
}, 10, 2);

add_action('woocommerce_init', function () {
    global $WOOCS;
    if ($WOOCS) {
        //fix to avoid currency change in botoscope admin panel!!
        $WOOCS->set_currency($WOOCS->default_currency);
    }

    botoscope_init_product_types();

    $GLOBALS['Botoscope'] = $Botoscope = new Botoscope();
    add_action('init', array($Botoscope, 'init'), 99999);
    include_once 'wp_hooks.php';
});

//lets register system taxonomies
add_action('init', function () {
    botoscope_check_pages();
    $existing_taxonomies = get_option('botoscope_taxonomies', []);
    if (!empty($existing_taxonomies)) {
        foreach ($existing_taxonomies as $data) {
            register_taxonomy($data['slug'], 'product', [
                'label' => $data['name'],
                'hierarchical' => true,
                'public' => true,
                'show_ui' => true,
                'show_in_rest' => true,
                'rewrite' => ['slug' => $data['slug']],
            ]);
        }
    }
});

//+++
//important to let next pages always exists
function botoscope_check_pages() {

    $page_slug = 'botoscope-refund-policy';
    $page = get_page_by_path($page_slug, OBJECT, 'page');
    if (!$page) {
        wp_insert_post([
            'post_title' => esc_html__('Botoscope refund policy', 'botoscope'),
            'post_name' => $page_slug,
            'post_content' => esc_html__('Here you can specify the text of your return policy', 'botoscope'),
            'post_status' => 'publish',
            'post_type' => 'page'
        ]);
    }

    //+++

    $page_slug = 'botoscope-privacy-policy';
    $page = get_page_by_path($page_slug, OBJECT, 'page');
    if (!$page) {
        wp_insert_post([
            'post_title' => esc_html__('Botoscope privacy policy', 'botoscope'),
            'post_name' => $page_slug,
            'post_content' => esc_html__('Here you can specify the text of your privacy policy', 'botoscope'),
            'post_status' => 'publish',
            'post_type' => 'page'
        ]);
    }

    //+++

    $page_slug = 'botoscope-shipping-policy';
    $page = get_page_by_path($page_slug, OBJECT, 'page');
    if (!$page) {
        wp_insert_post([
            'post_title' => esc_html__('Botoscope shipping policy', 'botoscope'),
            'post_name' => $page_slug,
            'post_content' => esc_html__('Here you can specify the text of your shipping policy', 'botoscope'),
            'post_status' => 'publish',
            'post_type' => 'page'
        ]);
    }

    //+++

    $page_slug = 'botoscope-media-casting';
    $page = get_page_by_path($page_slug, OBJECT, 'page');
    if (!$page) {
        wp_insert_post([
            'post_title' => esc_html__('Botoscope media casting', 'botoscope'),
            'post_name' => $page_slug,
            'post_content' => '[botoscope_media_casting]',
            'post_status' => 'publish',
            'post_type' => 'page'
        ]);
    }

    //+++

    $page_slug = 'botoscope-product-details';
    $page = get_page_by_path($page_slug, OBJECT, 'page');
    if (!$page) {
        wp_insert_post([
            'post_title' => esc_html__('Botoscope product details', 'botoscope'),
            'post_name' => $page_slug,
            'post_content' => '[botoscope_product_details]',
            'post_status' => 'publish',
            'post_type' => 'page'
        ]);
    }

    //+++

    $page_slug = 'botoscope-filter';
    $page = get_page_by_path($page_slug, OBJECT, 'page');
    if (!$page) {
        wp_insert_post([
            'post_title' => esc_html__('Botoscope filter', 'botoscope'),
            'post_name' => $page_slug,
            'post_content' => '[woof autosubmit=0 redirect="' . get_site_url(null, $page_slug) . '" btn_position=tb]',
            'post_status' => 'publish',
            'post_type' => 'page'
        ]);
    }

    //+++

    $page_slug = 'botoscope-variation-gallery';
    $page = get_page_by_path($page_slug, OBJECT, 'page');
    if (!$page) {
        wp_insert_post([
            'post_title' => esc_html__('Botoscope variation gallery', 'botoscope'),
            'post_name' => $page_slug,
            'post_content' => '[botoscope_variation_gallery]',
            'post_status' => 'publish',
            'post_type' => 'page'
        ]);
    }

    //+++

    $page_slug = 'botoscope-thank-you';
    $page = get_page_by_path($page_slug, OBJECT, 'page');
    if (!$page) {
        wp_insert_post([
            'post_title' => esc_html__('Thank you', 'botoscope'),
            'post_name' => $page_slug,
            'post_content' => '🙏😊🎉💖👍',
            'post_status' => 'publish',
            'post_type' => 'page'
        ]);
    }


    //+++
    /*
      $page_slug = 'products-in-telegram';
      $page = get_page_by_path($page_slug, OBJECT, 'page');
      if (!$page) {
      wp_insert_post([
      'post_title' => esc_html__('Products in Telegram', 'botoscope'),
      'post_name' => $page_slug,
      'post_content' => '[botoscope_telegram_products]',
      'post_status' => 'publish',
      'post_type' => 'page'
      ]);
      }
     *
     */
}

function botoscope_is_no_cart() {
    return intval(get_option('botoscope_disable_cart_checkout', 0)) ? 1 : 0;
}

function botoscope_init_product_types() {
    add_filter('product_type_selector', function ($types) {
        $types['botoscope_simple_virtual'] = esc_html__('Botoscope Simple Virtual', 'botoscope');
        $types['botoscope_simple_virtual_downloadable'] = esc_html__('Botoscope Simple Virtual Downloadable', 'botoscope');
        $types['botoscope_simple_media_casting'] = esc_html__('Botoscope Simple Media Casting', 'botoscope');

        return $types;
    });

    if (!class_exists('Botoscope_Product_Simple_Virtual')) {

        class Botoscope_Product_Simple_Virtual extends WC_Product_Simple {

            protected $product_type = 'botoscope_simple_virtual';
            protected $virtual = 'yes';

            public function __construct($product = 0) {
                parent::__construct($product);
                $this->product_type = 'botoscope_simple_virtual';
                $this->virtual = 'yes';
            }

            public function get_type() {
                return 'botoscope_simple_virtual';
            }

            public function is_virtual() {
                return true;
            }

            public function is_downloadable($context = 'view') {
                return false;
            }
        }

        class Botoscope_Product_Simple_Virtual_Downloadable extends WC_Product_Simple {

            protected $product_type = 'botoscope_simple_virtual_downloadable';
            protected $virtual = 'yes';
            protected $downloadable = 'yes';

            public function __construct($product = 0) {
                parent::__construct($product);
                $this->product_type = 'botoscope_simple_virtual_downloadable';
                $this->virtual = 'yes';
                $this->downloadable = 'yes';
            }

            public function get_type() {
                return 'botoscope_simple_virtual_downloadable';
            }

            public function is_virtual() {
                return true;
            }

            public function is_downloadable() {
                return true;
            }
        }

        class Botoscope_Product_Simple_Media_Casting extends WC_Product_Simple {

            protected $product_type = 'botoscope_simple_media_casting';

            public function __construct($product = 0) {
                parent::__construct($product);
                $this->product_type = 'botoscope_simple_media_casting';
            }

            public function get_type() {
                return 'botoscope_simple_media_casting';
            }

            public function is_virtual($context = 'view') {
                return true;
            }

            public function is_downloadable($context = 'view') {
                return true;
            }
        }

    }

    add_filter('woocommerce_product_class', function ($classname, $product_type) {
        switch ($product_type) {
            case 'botoscope_simple_virtual':
                $classname = 'Botoscope_Product_Simple_Virtual';
                break;
            case 'botoscope_simple_virtual_downloadable':
                $classname = 'Botoscope_Product_Simple_Virtual_Downloadable';
                break;
            case 'botoscope_simple_media_casting':
                $classname = 'Botoscope_Product_Simple_Media_Casting';
                break;
        }

        return $classname;
    }, 10, 2);
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Function name contains plugin prefix "botoscope"
function is_botoscope_connected() {
    return defined('BOTOSCOPE_CLIENT_API_KEY');
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Function name contains plugin prefix "botoscope"
function is_botoscope_free() {

    if (!is_botoscope_connected()) {
        return true;
    }

    return strpos(BOTOSCOPE_CLIENT_API_KEY, '-free-') !== false;
}
