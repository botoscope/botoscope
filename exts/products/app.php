<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

include_once BOTOSCOPE_PATH . 'classes/products_translations.php';
include_once 'classes/meta.php';

//11-05-2026
final class BOTOSCOPE_PRODUCTS extends BOTOSCOPE_APP {

    protected $botoscope;
    protected $controls;
    protected $translations;
    protected $table_name = 'botoscope_products';
    protected $slug = 'products';
    protected $data_structure = [
        'title' => 'click to edit ...',
        'price' => 0,
        'sale_price' => 0,
        'is_published' => 0
    ];
    private $admin_per_page = 10;
    public $synhronize_cache = false; //!!
    public $found_posts = -1;
    private $allowed_product_types = ['simple', 'external', 'grouped', 'variable', 'botoscope_simple_virtual', 'botoscope_simple_virtual_downloadable', 'botoscope_simple_media_casting'];
    public $types = [];
    protected $meta;
    private static $synced_products = [];

    public function __construct($args = []) {
        parent::__construct($args);

        $this->controls = new BOTOSCOPE_CONTROLS($args);
        $this->translations = new BOTOSCOPE_PRODUCTS_TRANSLATIONS($args);

        $this->types = [
            'simple' => esc_html__('Simple', 'botoscope'),
            'botoscope_simple_virtual' => esc_html__('Simple Virtual', 'botoscope'),
            'botoscope_simple_virtual_downloadable' => esc_html__('Simple Virtual Downloadable', 'botoscope'),
            'botoscope_simple_media_casting' => esc_html__('Simple Media Casting', 'botoscope'),
            'external' => esc_html__('External', 'botoscope'),
            'grouped' => esc_html__('Grouped', 'botoscope'),
            'variable' => esc_html__('Variable', 'botoscope'),
        ];

        $this->meta = new BOTOSCOPE_PRODUCTS_META($this, $this->botoscope);

        Botoscope_Hooks::add_action('botoscope_panel_tabs', function ($tabs) {
            $tabs[$this->slug] = esc_html__('Products', 'botoscope');
            return $tabs;
        });

        add_action("botoscope_{$this->slug}_tab_icon", function () {
            return 'shop';
        });

        add_filter('woof_dynamic_count_attr', function ($args, $custom_type) {
            if (!is_page('botoscope-filter')) {
                return $args;
            }

            $args['post__in'] = $this->get_woof_products_ids();
            return $args;
        }, 9999, 2);

        //Trigger bot cache delete when product is trashed or permanently deleted
        add_action('wp_trash_post', function ($product_id) {
            if (get_post_type($product_id) === 'product') {
                update_post_meta($product_id, '_botoscope_is_hidden', 1);
                $this->update_product_bot_cache($product_id);
            }
        });
        add_action('before_delete_post', function ($product_id) {
            if (get_post_type($product_id) === 'product' && get_post_status($product_id) !== 'trash') {
                update_post_meta($product_id, '_botoscope_is_hidden', 1);
                $this->update_product_bot_cache($product_id);
            }
        });

        //do not heritate parent product SKU
        add_action('woocommerce_new_product_variation', function ($variation_id) {
            $variation = wc_get_product($variation_id);

            if ($variation && $variation->is_type('variation')) {
                $variation->set_sku('');
                $variation->save();
            }
        });

        // Set custom sort order for Botoscope products - not need
        add_filter('botoscope_products_args', function ($args) {
            if (!empty($args['order_by'])) {
                return $args; //!!
            }

            $args['orderby'] = ['menu_order' => 'ASC', 'ID' => 'DESC'];
            $args['order'] = 'ASC';
            return $args;
        });

        Botoscope_Hooks::add_action('botoscope_get_sidebar_html', function ($what, $template_name, $product_id) {
            if ($what === $this->slug && in_array($_REQUEST['template_name'], ['single-product', 'single-product-downloads',
                        'single-product-products', 'single-product-variations', 'single-product-variation'])) {

                $product = wc_get_product($product_id);
                $data = $this->get_product_fields($product);
                $data['brands'] = $this->botoscope->taxonomies->get_brands();

                if ($product->is_type('variation')) {
                    $data['description'] = $product->get_description();
                }

                BOTOSCOPE_HELPER::render_html_e(BOTOSCOPE_EXT_PATH . "{$this->slug}/views/{$template_name}.php", [
                    'product_id' => $product_id,
                    'data' => $data,
                    'obj' => $this,
                    'meta_position' => $product->get_meta('_botoscope_meta_position') ? 1 : 0
                ]);
            }
        });

        Botoscope_Hooks::add_action('botoscope_edit_row', function ($what, $product_id, $data) {
            if ($what === $this->slug) {
                if (!empty($data) && $product_id > 0) {
                    $product = wc_get_product($product_id);

                    //+++

                    $audio_array = [];
                    foreach ($data as $field_key => $value) {
                        if (strpos($field_key, 'audio_') === 0) {
                            $lang = str_replace('audio_', '', $field_key);

                            if (!empty($value)) {
                                $audio_array[$lang] = $value;
                            }

                            unset($data[$field_key]);
                        }
                    }

                    if (!empty($audio_array)) {
                        $data['audio'] = $audio_array;
                    } else {
                        $data['audio'] = [];
                    }

                    //+++
                    self::$synced_products[$product_id] = true; //fix to avoid synhro of the product before all the data save
                    foreach ($data as $field_key => $value) {
                        $this->update($product, $field_key, $value);
                    }

                    wc_delete_product_transients($product_id);
                    //delete_transient('wc_term_counts');
                    delete_transient('woocommerce_products');
                    wp_cache_flush();

                    unset(self::$synced_products[$product_id]);
                    $non_type_keys = array_diff(array_keys($data), ['type', 'audio']); //fix to avoid synhro of the product before all the data save
                    if (!empty($non_type_keys)) {
                        $this->update_product_bot_cache($product_id);
                    }

                    wp_send_json_success(['message' => 'Product data is saved successfully!']);
                }
            }
        });

        Botoscope_Hooks::add_action('botoscope_search_products_not_in', function ($what, $product_id, $search_term) {
            if ($what === 'product_products') {
                $product = wc_get_product($product_id);

                if ($product && method_exists($product, 'get_children')) {
                    $res = $product->get_children();

                    $grouped_product_ids = wc_get_products([
                        'limit' => -1,
                        'type' => 'grouped',
                        'return' => 'ids'
                    ]);

                    return array_merge($res, $grouped_product_ids);
                }

                return [];
            }

            return [];
        });

        Botoscope_Hooks::add_action('botoscope_edit_cell', function ($what, $product_id, $key, $value) {
            if ($what === 'product_products') {
                switch ($key) {
                    case 'child_ids':

                        $product = wc_get_product(intval($product_id));

                        if ($product) {
                            $child_ids = array_map('intval', explode(',', sanitize_text_field($value)));

                            $product->set_children($child_ids);
                            $product->save();

                            //+++

                            if (!empty($child_ids)) {
                                $childs_array = [];

                                foreach ($child_ids as $pid) {
                                    $product = wc_get_product($pid);

                                    if ($product) {
                                        $childs_array[] = [
                                            'id' => $pid,
                                            'oid' => $pid,
                                            'title' => $product->get_name()
                                        ];
                                    }
                                }

                                $this->update_product_bot_cache($product_id);
                                wp_die(wp_json_encode($childs_array, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG));
                            }
                        }

                        break;
                }

                if (!$product->is_type('grouped')) {
                    $this->update_product_bot_cache($product_id);
                }
            }

            //+++

            if ($what === 'product_downloads') {
                switch ($key) {
                    case 'downloads_order':
                        $product = wc_get_product(intval($product_id));
                        $new_order = $value;

                        $downloads = $product->get_downloads(); //Get current downloads
                        $ordered_downloads = [];

                        foreach ($new_order as $download_id) {
                            foreach ($downloads as $key => $download) {
                                if ($download->get_id() === $download_id) {
                                    $ordered_downloads[] = $download;
                                    unset($downloads[$key]); //We delete it to avoid duplication
                                    break;
                                }
                            }
                        }

                        if (!empty($downloads)) {
                            $ordered_downloads = array_merge($ordered_downloads, $downloads);
                        }

                        $product->set_downloads($ordered_downloads);
                        $product->save();

                        break;

                    case 'title':
                        $product_id = intval($_REQUEST['additional_params']['product_id']);
                        $download_uuid = sanitize_text_field($_REQUEST['id']);
                        $new_title = sanitize_text_field($value);
                        $product = wc_get_product($product_id);

                        $downloads = $product->get_downloads();
                        $file_url = null;
                        $updated_downloads = array();

                        foreach ($downloads as $download_id => $download) {
                            if ($download->get_id() === $download_uuid) {
                                $file_url = $download->get_file();
                                // Create a new download object
                                $updated_download = new WC_Product_Download();
                                $updated_download->set_id($download->get_id());
                                $updated_download->set_name($new_title);
                                $updated_download->set_file($download->get_file());
                                $updated_downloads[$download_id] = $updated_download;
                            } else {
                                $updated_downloads[$download_id] = $download;
                            }
                        }

                        if (!$file_url) {
                            wp_send_json_error(['message' => 'File not found']);
                            exit;
                        }

                        $product->set_downloads($updated_downloads);
                        $product->save();

                        global $wpdb;
                        $uploads_baseurl = wp_get_upload_dir()['baseurl'] . '/';
                        $relative_path = str_replace($uploads_baseurl, '', $file_url);
                        $attachment_id = $wpdb->get_var($wpdb->prepare(
                                        "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_value = %s LIMIT 1",
                                        $relative_path
                                ));

                        if ($attachment_id) {
                            wp_update_post([
                                'ID' => $attachment_id,
                                'post_title' => $new_title,
                            ]);
                        }

                        break;
                    case 'file_url':
                        $product_id = intval($_REQUEST['additional_params']['product_id']);
                        $download_uuid = sanitize_text_field($_REQUEST['id']);
                        $file_url = sanitize_text_field($value);
                        $product = wc_get_product($product_id);

                        $downloads = $product->get_downloads();
                        $updated_downloads = array();

                        foreach ($downloads as $download_id => $download) {
                            if ($download->get_id() === $download_uuid) {
                                // Create a new download object
                                $updated_download = new WC_Product_Download();
                                $updated_download->set_id($download->get_id());
                                $updated_download->set_name($download->get_name());
                                $updated_download->set_file($file_url);
                                $updated_downloads[$download_id] = $updated_download;
                            } else {
                                $updated_downloads[$download_id] = $download;
                            }
                        }

                        $product->set_downloads($updated_downloads);
                        $product->save();
                        break;
                }

                $this->update_product_bot_cache($product_id);
            }

            //+++

            if ($what === 'product_group') {
                $groupes_ids = array_values(array_unique((array) $value));

                if (!empty($groupes_ids)) {
                    foreach ($groupes_ids as $group_id) {
                        $grouped_product = wc_get_product($group_id);
                        $children = $grouped_product->get_children();
                        if (!in_array($product_id, $children)) {
                            $children[] = $product_id;
                            $grouped_product->set_children($children);
                            $grouped_product->save();
                        }
                    }


                    $res = [];

                    foreach ($groupes_ids as $pid) {
                        $product = wc_get_product($pid);

                        if ($product) {
                            $res[] = [
                                'id' => $pid,
                                'oid' => $pid,
                                'title' => $product->get_name()
                            ];
                        }
                    }

                    wp_die(wp_json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG));
                }

                $this->update_product_bot_cache($product_id);
            }


            if ($what === 'products') {
                $this->update_product_bot_cache($product_id);
            }
        });

        add_action('woocommerce_order_refunded', [$this, 'woocommerce_order_refunded'], 10, 2);
        add_action('woocommerce_reduce_order_stock', [$this, 'on_re_order_stock']);
        add_action('woocommerce_restore_order_stock', [$this, 'on_re_order_stock']);

        // Automatically mark all parent categories when saving a product
        add_action('save_post_product', function ($product_id) {
            $this->mark_all_parent_categories($product_id);
        }, 10, 1);

        add_action('woocommerce_update_product', function ($product_id) {
            if (is_admin() && !defined('DOING_CRON') && !defined('REST_REQUEST')) {
                if (isset(self::$synced_products[$product_id])) {
                    return;
                }

                $this->update_product_bot_cache($product_id);
            }
        }, 10, 1);

        $this->register_routes();
        $this->init_hooks();
        $this->init_ajax();
    }

    private function init_ajax() {

        add_action('wp_ajax_botoscope_products_make_all_visible', function () {
            if ($this->botoscope->is_ajax_request_valid()) {
                global $wpdb;

                $product_ids = $wpdb->get_col("
            SELECT ID FROM {$wpdb->posts}
            WHERE post_type = 'product'
            AND post_status IN ('publish', 'draft', 'future')
        ");

                if (!empty($product_ids)) {
                    $ids_placeholder = implode(',', array_map('intval', $product_ids));

                    $wpdb->query("
                DELETE FROM {$wpdb->postmeta}
                WHERE post_id IN ({$ids_placeholder})
                AND meta_key = '_botoscope_is_hidden'
            ");

                    $this->botoscope->reset_cache('products');
                }

                wp_send_json_success(['message' => 'All products are now visible', 'count' => count($product_ids)]);
            }
        }, 1);

        add_action('wp_ajax_botoscope_products_make_all_hidden', function () {
            if ($this->botoscope->is_ajax_request_valid()) {
                global $wpdb;

                $product_ids = $wpdb->get_col("
            SELECT ID FROM {$wpdb->posts}
            WHERE post_type = 'product'
            AND post_status IN ('publish', 'draft', 'future')
        ");

                if (!empty($product_ids)) {
                    $ids_placeholder = implode(',', array_map('intval', $product_ids));

                    $wpdb->query("
                DELETE FROM {$wpdb->postmeta}
                WHERE post_id IN ({$ids_placeholder})
                AND meta_key = '_botoscope_is_hidden'
            ");

                    $values = implode(',', array_map(function ($id) use ($wpdb) {
                                return $wpdb->prepare("(%d, '_botoscope_is_hidden', '1')", intval($id));
                            }, $product_ids));

                    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $values contains individually prepared rows via wpdb->prepare()
                    $wpdb->query("INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES " . $values);

                    $this->botoscope->reset_cache('products');
                }

                wp_send_json_success(['message' => 'All products are now hidden', 'count' => count($product_ids)]);
            }
        }, 1);

        add_action('wp_ajax_botoscope_is_product_published', function () {
            if ($this->botoscope->is_ajax_request_valid()) {
                $product_id = intval($_REQUEST['product_id']);

                if (!$product_id) {
                    die("0");
                }

                $product = wc_get_product($product_id);

                if (!$product) {
                    die("0");
                }

                die($product->get_status() === 'publish' ? "1" : "0");
            }
        }, 1);

        add_action('wp_ajax_botoscope_products_save_gallery', function () {
            if ($this->botoscope->is_ajax_request_valid()) {

                $product_id = intval($_REQUEST['product_id']);

                if (!$product_id) {
                    wp_send_json_error(['message' => 'Wrong data']);
                }

                $attachment_ids = array_map('intval', explode(',', sanitize_text_field($_REQUEST['attachment_ids'])));
                $this->save_product_medias($product_id, $attachment_ids);

                $this->update_product_bot_cache($product_id);

                wp_send_json_success(['message' => 'Gallery order saved successfully!']);
            }
        }, 1);

        add_action('wp_ajax_botoscope_products_addto_gallery', function () {
            if ($this->botoscope->is_ajax_request_valid()) {

                $product_id = intval($_REQUEST['product_id']);
                $attachment_ids = array_map('intval', explode(',', sanitize_text_field($_REQUEST['attachment_ids'])));

                if (!$product_id || empty($attachment_ids)) {
                    wp_send_json_error(['message' => 'Wrong data']);
                }

                $attachment_ids = array_unique(array_merge($this->get_media_gallery_ids($product_id), $attachment_ids));
                $this->save_product_medias($product_id, $attachment_ids);

                $this->update_product_bot_cache($product_id);

                wp_send_json_success(['message' => 'Gallery order saved successfully!']);
            }
        }, 1);

        //for coupons and marketing companies
        add_action('wp_ajax_botoscope_search_products', function () {
            if ($this->botoscope->is_ajax_request_valid()) {
                $res = [];
                $search_term = esc_html(sanitize_text_field($_REQUEST['value']));
                $what = esc_html(sanitize_text_field($_REQUEST['what'] ?? ''));
                $parent_row_id = intval($_REQUEST['parent_row_id'] ?? 0);

                $not_in = Botoscope_Hooks::apply_action('botoscope_search_products_not_in', [], [$what, $parent_row_id, $search_term]);

                if (isset($_REQUEST['more']['exclude'])) {
                    $not_in = array_merge($not_in, array_map('intval', $_REQUEST['more']['exclude']));
                }

                // If the query consists only of an integer
                if (ctype_digit($search_term)) {
                    $product_id = intval($search_term);

                    // Trying to get a product by ID
                    $product = wc_get_product($product_id);

                    if ($product) {
                        $res[] = [
                            'title' => $product->get_name(),
                            'id' => $product_id,
                        ];
                    }
                }
                // If the value starts with the letter 'v' and is followed by numbers (variable product ID)
                elseif (preg_match('/^v(\d+)$/i', $search_term, $matches)) {
                    $parent_product_id = intval($matches[1]);

                    // We receive variations of the specified product
                    $args = [
                        'post_type' => 'product_variation',
                        'posts_per_page' => 100,
                        'post_status' => ['publish', 'draft', 'future'],
                        'post_parent' => $parent_product_id,
                        'post__not_in' => $not_in,
                    ];

                    $query = new WP_Query($args);

                    if ($query->have_posts()) {
                        while ($query->have_posts()) {
                            $query->the_post();

                            $variation_id = get_the_ID();
                            $parent_id = $parent_product_id;
                            $variation = wc_get_product($variation_id);

                            if ($variation && $variation->is_type('variation')) {
                                $attributes = $variation->get_attributes();
                                $attributes_string = implode(' | ', array_map(function ($key, $value) {
                                            $term = get_term_by('slug', $value, $key);
                                            $term_name = $term ? $term->name : $value;
                                            return $term_name;
                                        }, array_keys($attributes), $attributes));

                                $title = get_the_title($parent_id) . ': ' . $attributes_string;

                                $res[] = [
                                    'title' => $title,
                                    'id' => $variation_id,
                                    'parent_id' => $parent_id
                                ];
                            }
                        }

                        wp_reset_postdata();
                    }
                } else {
                    $product_types = $this->allowed_product_types;

                    /*
                      if (isset($_REQUEST['more']['see_in_groups'])) {
                      $product_types = ['grouped'];
                      }
                     *
                     */

                    $args = [
                        'post_type' => 'product',
                        's' => $search_term,
                        'posts_per_page' => 30,
                        'post_status' => ['publish', 'draft', 'future'],
                        'post__not_in' => $not_in,
                        'tax_query' => [
                            [
                                'taxonomy' => 'product_type',
                                'field' => 'slug',
                                'terms' => $product_types
                            ],
                        ],
                    ];

                    //+++

                    add_filter('posts_search', function ($search, $query) use ($search_term) {
                        global $wpdb;

                        if (!empty($search_term) && $query->is_main_query() === false) {
                            $search = preg_replace('/\)\s*$/', '', $search);
                            $search .= " OR ({$wpdb->postmeta}.meta_key = '_sku' AND {$wpdb->postmeta}.meta_value LIKE '%" . esc_sql($wpdb->esc_like($search_term)) . "%'))";
                        }

                        return $search;
                    }, 10, 2);

                    add_filter('posts_join', function ($join, $query) use ($search_term) {
                        global $wpdb;

                        if (!empty($search_term) && $query->is_main_query() === false) {
                            $join .= " LEFT JOIN {$wpdb->postmeta} ON {$wpdb->posts}.ID = {$wpdb->postmeta}.post_id";
                        }

                        return $join;
                    }, 10, 2);

                    //+++

                    $query = new WP_Query($args);

                    if ($query->have_posts()) {
                        while ($query->have_posts()) {
                            $query->the_post();

                            $res[] = [
                                'title' => get_the_title(),
                                'id' => get_the_ID()
                            ];
                        }

                        wp_reset_postdata();
                    }
                }

                die(wp_json_encode($res));
            }
        }, 1);

        add_action('wp_ajax_botoscope_products_get_single_product', function () {
            if ($this->botoscope->is_ajax_request_valid()) {
                die(wp_json_encode($this->get_single_product(intval($_REQUEST['product_id']))));
            }
        }, 1);

        add_action('wp_ajax_botoscope_products_get_medias', function () {
            if ($this->botoscope->is_ajax_request_valid()) {

                $attachment_ids = array_map('intval', explode(',', sanitize_text_field($_REQUEST['ids'])));
                $media_gallery = [];

                foreach ($attachment_ids as $attachment_id) {
                    $attachment_url = wp_get_attachment_url($attachment_id);

                    if ($attachment_url) {
                        $media_gallery[] = array(
                            'aid' => intval($attachment_id),
                            'type' => preg_match('/^[^\/]+/', get_post_mime_type($attachment_id), $matches) ? $matches[0] : null,
                            'media' => $attachment_url
                        );
                    }
                }

                die(wp_json_encode($media_gallery));
            }
        }, 1);

        add_action('wp_ajax_botoscope_products_process_text_by_ai', function () {
            if ($this->botoscope->is_ajax_request_valid()) {
                $api_key = $this->controls->get_option('openai_api_key');

                $text = wp_kses_post(wp_unslash($_REQUEST['value']));
                $command = sanitize_text_field($_REQUEST['command']);

                if (isset($_REQUEST['command_text'])) {
                    $command_text = sanitize_text_field($_REQUEST['command_text']);
                } else {
                    $command_text = '';
                }

                if (empty($command_text)) {
                    switch ($command) {
                        case 'fix_description':
                            $command_text = 'You are a text corrector. Preserve the original language of the text. Correct only grammar and lexical errors. IMPORTANT: preserve ALL HTML tags, attributes, entities and structure exactly as they are - do not modify, remove or reformat any HTML markup. Return only the corrected text without any explanations.';
                            break;

                        default:
                            $command_text = 'Generate a concise product description, no longer than 3 sentences, based on the provided text. Maintain the original language of the input. Optimize the description for Telegram by making it engaging and adding relevant emojis where appropriate. Return only the generated description without any explanations.';
                            break;
                    }
                }

                $headers = [
                    "Content-Type: application/json",
                    "Authorization: Bearer $api_key"
                ];

                $data = [
                    "model" => "gpt-4-turbo",
                    "messages" => [
                        [
                            "role" => "system",
                            "content" => $command_text
                        ],
                        [
                            "role" => "user",
                            "content" => $text
                        ]
                    ],
                    "temperature" => 0.9
                ];

                $wp_response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
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
        }, 1);

        add_action('wp_ajax_botoscope_delete_product_download', function () {
            if ($this->botoscope->is_ajax_request_valid()) {

                $product_id = intval($_REQUEST['product_id']);
                $download_id = sanitize_text_field($_REQUEST['download_id']);
                $product = wc_get_product($product_id);

                if (!$product->is_downloadable()) {
                    $product->set_downloadable(true);
                    $product->save();
                    $product = wc_get_product($product_id);
                }

                $downloads = $product->get_downloads();

                if (isset($downloads[$download_id])) {
                    unset($downloads[$download_id]);

                    $product->set_downloads($downloads);
                    $product->save();

                    $this->update_product_bot_cache($product_id);
                    return true;
                }

                wp_send_json_error('Download with the specified ID not found');
            }
        }, 1);

        add_action('wp_ajax_botoscope_products_add_product_downloads', function () {
            if ($this->botoscope->is_ajax_request_valid()) {

                $product_id = intval($_REQUEST['product_id']);
                $attachment_ids = array_map('intval', explode(',', sanitize_text_field($_REQUEST['attachment_ids'])));

                if ($product_id <= 0 || empty($attachment_ids)) {
                    wp_send_json_error('Invalid product or attachments');
                }

                $product = wc_get_product($product_id);

                if (!$product->is_downloadable()) {
                    $product->set_downloadable(true);
                    $product->save();
                    $product = wc_get_product($product_id);
                }

                $downloads = $product->get_downloads();
                $existing_urls = array_map(function ($download) {
                    return $download->get_file();
                }, $downloads);

                foreach ($attachment_ids as $attachment_id) {
                    $file_url = wp_get_attachment_url($attachment_id);
                    $file_title = get_the_title($attachment_id) ?: 'Attachment ' . $attachment_id;

                    if ($file_url && !in_array($file_url, $existing_urls)) {
                        $unique_id = 'attachment_' . $attachment_id;

                        $downloads[$unique_id] = [
                            'id' => $unique_id,
                            'name' => $file_title,
                            'file' => $file_url,
                        ];
                    }
                }

                $product->set_downloads($downloads);
                $product->save();

                $updated_downloads = array_map(function ($download) {
                    return [
                        'id' => $download->get_id(),
                        'title' => $download->get_name(),
                        'file_url' => $download->get_file(),
                    ];
                }, $product->get_downloads());

                $this->update_product_bot_cache($product_id);

                wp_send_json_success(array_values($updated_downloads));
            }
        }, 1);

        add_action('wp_ajax_botoscope_delete_product_child', function () {
            if ($this->botoscope->is_ajax_request_valid()) {

                $product_id = intval($_REQUEST['product_id']);
                $child_id = intval($_REQUEST['child_id']);
                $the_product = wc_get_product($product_id);

                if ($the_product && ($the_product->is_type('grouped') || $the_product->is_type('variable'))) {
                    $current_children = $the_product->get_children();

                    $updated_children = array_filter($current_children, function ($id) use ($child_id) {
                        return intval($id) !== intval($child_id);
                    });

                    $the_product->set_children($updated_children);
                    $the_product->save();

                    if ($the_product->is_type('variable')) {
                        wp_delete_post($child_id, true);
                        $this->update_product_bot_cache($product_id);
                    }

                    wp_send_json_success("Product with ID {$child_id} successfully removed from group/variative product with ID {$product_id}");
                }
            }
        }, 1);

        add_action('wp_ajax_botoscope_products_get_variations', function () {
            if ($this->botoscope->is_ajax_request_valid()) {

                $product_id = intval($_REQUEST['product_id']);
                $product = wc_get_product($product_id);
                $res = $this->get_variations_by_ids($product->get_children());

                if (isset($_REQUEST['order_by'])) {
                    $order_by = sanitize_text_field($_REQUEST['order_by']);
                    $order = sanitize_text_field($_REQUEST['order']);

                    usort($res, function ($a, $b) use ($order_by, $order) {
                        $valueA = $a[$order_by] ?? 0;
                        $valueB = $b[$order_by] ?? 0;

                        if ($valueA === $valueB) {
                            return 0;
                        }

                        $result = $valueA <=> $valueB; // 1 for ASC, -1 for DESC
                        return $order === 'asc' ? $result : -$result;
                    });
                }

                wp_die(wp_json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG));
            }
        }, 1);

        add_action('wp_ajax_botoscope_products_variable_get_possible_combinations', function () {
            if ($this->botoscope->is_ajax_request_valid()) {
                $product_id = intval($_REQUEST['product_id']);
                $combinations = $this->get_possible_free_combinations($product_id);
                wp_die(wp_json_encode($combinations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG));
            }
        }, 1);

        add_action('wp_ajax_botoscope_product_create_variation', function () {
            if ($this->botoscope->is_ajax_request_valid()) {
                $product_id = intval($_REQUEST['product_id']);
                $combination = map_deep(wp_unslash($_REQUEST['combination'] ?? []), 'sanitize_text_field');
                $this->create_variation($product_id, $combination);
                wc_delete_product_transients($product_id);
                wp_cache_flush();
                do_action('woocommerce_delete_product_transients', $product_id);
                $product = wc_get_product($product_id);
                $this->update_product_bot_cache($product_id);

                wp_die(wp_json_encode($this->get_variations_by_ids($product->get_children()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG));
            }
        }, 1);

        add_action('wp_ajax_botoscope_product_get_allowed_attributes', function () {
            if ($this->botoscope->is_ajax_request_valid()) {
                $product_id = intval($_REQUEST['product_id']);
                $attributes = $this->get_product_allowed_attributes($product_id);

                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode produces safe output
                wp_die(wp_json_encode($attributes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG));
            }
        }, 1);

        add_action('wp_ajax_botoscope_product_get_all_attributes', function () {
            if ($this->botoscope->is_ajax_request_valid()) {
                $attributes = $this->get_all_woocommerce_attributes();
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode produces safe output
                wp_die(wp_json_encode($attributes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG));
            }
        }, 1);

        add_action('wp_ajax_botoscope_products_set_variation_combination', function () {
            if (!$this->botoscope->is_ajax_request_valid()) {
                wp_die('Error: Invalid request');
            }

            $variation_id = intval($_REQUEST['variation_id']);
            $combination = map_deep(wp_unslash($_REQUEST['combination'] ?? []), 'sanitize_text_field');

            $variation = wc_get_product($variation_id);
            if (!$variation || !$variation->is_type('variation')) {
                wp_die('Error: Invalid variation ID');
            }
            if (empty($combination) || !is_array($combination)) {
                wp_die('Error: Invalid combination data');
            }

            global $wpdb;
            $new_attributes = [];

            foreach ($combination as $attribute_key => $attribute_value) {
                if ($attribute_value === null || $attribute_value === 'null' || $attribute_value === '') {
                    $new_attributes[urldecode($attribute_key)] = '';
                    continue;
                }

                $term_id = intval($attribute_value);
                $row = $wpdb->get_row($wpdb->prepare(
                                "SELECT t.slug, tt.taxonomy
                                FROM {$wpdb->terms} t
                                JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                                WHERE t.term_id = %d AND tt.taxonomy LIKE %s",
                                $term_id,
                                $wpdb->esc_like('pa_') . '%'
                        ));

                if (!$row) {
                    wp_send_json_error(['message' => "Term ID {$term_id} not found", 'term_id' => $term_id]);
                    wp_die();
                }

                $new_attributes[$row->taxonomy] = $row->slug;
            }

            foreach ($new_attributes as $taxonomy => $slug) {
                update_post_meta($variation_id, 'attribute_' . sanitize_title($taxonomy), wp_slash($slug));
            }

            clean_post_cache($variation_id);
            wc_delete_product_transients($variation->get_parent_id());
            $this->update_product_bot_cache($variation_id);

            wp_send_json_success([
                'message' => 'Variation successfully updated',
                'variation_id' => $variation_id,
                'updated_attributes' => $new_attributes,
            ]);
            wp_die();
        }, 1);

        add_action('wp_ajax_botoscope_update_product_bot_cache', function () {
            if ($this->botoscope->is_ajax_request_valid()) {
                $product_id = intval($_REQUEST['product_id']);
                if ($product_id > 0) {
                    $this->update_product_bot_cache($product_id);
                }

                wp_die('done');
            }
        }, 1);

        add_action('wp_ajax_botoscope_create_dummy_products', function () {
            if ($this->botoscope->is_ajax_request_valid()) {
                $count = intval($_REQUEST['count']);

                if ($count) {
                    for ($i = 0; $i < $count; $i++) {
                        $this->create();
                    }
                }

                wp_die('done');
            }
        }, 1);

        add_action('wp_ajax_botoscope_draw_progress_bar', function () {
            if ($this->botoscope->is_ajax_request_valid()) {
                $this->draw_progress_bar();
                exit;
            }
        }, 1);
    }

    public function get($page_num = 0, $params = []) {
        // Support custom limit (for REST), default is admin_per_page
        // posts_per_page passed directly from products_filter_rest has priority
        if (isset($params['posts_per_page'])) {
            $limit = intval($params['posts_per_page']);
        } elseif (isset($params['limit'])) {
            $limit = intval($params['limit']);
        } else {
            $limit = $this->admin_per_page;
        }

        // offset: paged (from products_filter_rest) has priority over offset_val
        if (isset($params['paged']) && intval($params['paged']) > 0) {
            $offset = (intval($params['paged']) - 1) * $limit;
        } elseif (isset($params['offset_val'])) {
            $offset = intval($params['offset_val']);
        } else {
            $offset = $page_num * $limit;
        }

        $order_by = $params['order_by'] ?? null;
        $order = $params['order'] ?? null;
        $search = $params['search'] ?? [];

        // Support custom post_type and post_status (for REST)
        $post_type = $params['post_type'] ?? ['product'];
        $post_status = $params['post_status'] ?? ['publish', 'draft', 'future'];

        $args = array_merge([
            'post_type' => $post_type,
            'post_status' => $post_status,
            'posts_per_page' => $limit,
            'offset' => $offset,
            'tax_query' => [['relation' => 'AND']],
            'meta_query' => []
                ], $params);

        // Exclude hidden products if requested (for REST)
        if (!empty($params['exclude_hidden'])) {
            $args['meta_query'][] = [
                'relation' => 'OR',
                [
                    'key' => '_botoscope_is_hidden',
                    'compare' => 'NOT EXISTS'
                ],
                [
                    'key' => '_botoscope_is_hidden',
                    'value' => '1',
                    'compare' => '!='
                ]
            ];
        }

        // Sorting
        if ($order_by) {
            $args['orderby'] = $order_by;
            switch ($order_by) {
                case 'oid':
                case 'id':
                    $args['orderby'] = 'ID';
                    break;
                case 'price':
                case 'sale_price':
                    $args['meta_key'] = $order_by === 'price' ? '_regular_price' : '_sale_price';
                    $args['orderby'] = 'meta_value_num';
                    break;
                case 'menu_order':
                    $args['orderby'] = ['menu_order' => 'ASC', 'ID' => 'DESC'];
                    unset($args['order']);
                    break;
                default:
                    $args['orderby'] = $order_by;
                    break;
            }

            if ($order) {
                $args['order'] = strtoupper($order);
            }
        } else {
            // Default sort: menu_order ASC, ID DESC — same as WooCommerce admin
            $args['orderby'] = ['menu_order' => 'ASC', 'ID' => 'DESC'];
            unset($args['order']);
        }

        // Create subqueries for search
        $search_meta_queries = [];
        $using_search = false;

        // Add a condition for the product type if it is selected
        if (isset($search['product_type']) && !empty($search['product_type'])) {
            $args['tax_query'][] = [
                'taxonomy' => 'product_type',
                'field' => 'slug',
                'terms' => [$search['product_type']],
                'operator' => 'IN'
            ];
        } elseif (in_array('product', (array) $post_type) && !in_array('product_variation', (array) $post_type)) {
            // Only apply allowed_product_types filter for product post type
            $args['tax_query'][] = [
                'taxonomy' => 'product_type',
                'field' => 'slug',
                'terms' => $this->allowed_product_types
            ];
        }

        //+++

        if (isset($search['product_category']) && !empty($search['product_category'])) {
            $args['tax_query'][] = [
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => [intval($search['product_category'])],
                'operator' => 'IN'
            ];
        }

        // if there is a search query
        if (isset($search['title']) && !empty($search['title'])) {
            $using_search = true;
            $search_term = $search['title'];
            $args['custom_search'] = $search_term; // Flag for our custom search
            // Add filters for search
            add_filter('posts_where', function ($where, $wp_query) {
                global $wpdb;

                if ($search_term = $wp_query->get('custom_search')) {
                    $search_term_clean = trim($search_term);
                    $search_term_like = '%' . $wpdb->esc_like($search_term_clean) . '%';

                    $conditions = [];

                    // If a pure number is entered, we add a search by ID
                    if (ctype_digit($search_term_clean)) {
                        $conditions[] = $wpdb->prepare(" {$wpdb->posts}.ID = %d ", intval($search_term_clean));

                        // Find parent product by variation ID
                        $conditions[] = $wpdb->prepare("
                            EXISTS (
                                SELECT 1 FROM {$wpdb->posts} AS var_p
                                WHERE var_p.ID = %d
                                AND var_p.post_parent = {$wpdb->posts}.ID
                                AND var_p.post_type = 'product_variation'
                            )
                        ", intval($search_term_clean));
                    }

                    // Search by title
                    $conditions[] = $wpdb->prepare(" {$wpdb->posts}.post_title LIKE %s ", $search_term_like);

                    //search by SKU (including variation SKUs — find parent product)
                    $conditions[] = $wpdb->prepare("
                        EXISTS (
                            SELECT 1 FROM {$wpdb->postmeta}
                            WHERE {$wpdb->postmeta}.post_id = {$wpdb->posts}.ID
                            AND {$wpdb->postmeta}.meta_key = '_sku'
                            AND {$wpdb->postmeta}.meta_value LIKE %s
                        )
                    ", $search_term_like);

                    //search by variation SKU — find parent product via post_parent
                    $conditions[] = $wpdb->prepare("
                        EXISTS (
                            SELECT 1 FROM {$wpdb->posts} AS var_posts
                            INNER JOIN {$wpdb->postmeta} AS var_meta ON var_meta.post_id = var_posts.ID
                            WHERE var_posts.post_parent = {$wpdb->posts}.ID
                            AND var_posts.post_type = 'product_variation'
                            AND var_meta.meta_key = '_sku'
                            AND var_meta.meta_value LIKE %s
                        )
                    ", $search_term_like);

                    // We combine all conditions using OR
                    $where = " AND (" . implode(' OR ', $conditions) . ") " . $where;
                }

                return $where;
            }, 10, 2);
        }

        // Combine all search conditions meta_query
        if (!empty($search_meta_queries)) {
            $args['meta_query'] = $search_meta_queries;
        }

        //+++
        $ignore_language = defined('REST_REQUEST') && REST_REQUEST ? 1 : 0;
        $query = new WP_Query(apply_filters('botoscope_products_args', $args));

        // Remove filters after query execution
        if ($using_search) {
            remove_all_filters('posts_where');
        }

        $products = [];
        $this->found_posts = $query->found_posts;
        $language = $this->get_current_language();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                global $product;
                $title = $product->get_title();
                $description = $this->convert_html_list_to_telegram_text($this->get_product_description($product));
                if (!$ignore_language && $this->get_current_language() !== $this->get_default_language()) {
                    $translation = $this->translations->get_translation($product->get_id(), $language);
                    $title = $translation['title'] ?: "<ta></ta>" . $title;
                    $description = $translation['description'] ?: "<ta></ta>" . $description;
                }
                $products_data = $this->get_product_fields($product);
                $products_data['title'] = $title;

                if (defined('REST_REQUEST') && REST_REQUEST) {
                    $description = strip_tags($this->convert_html_list_to_telegram_text($description), '<strong><em><code><u>');
                } else {
                    $description = wpautop($description);
                }

                $products_data['description'] = $description;
                $products[] = $products_data;
            }
            wp_reset_postdata();
        }

        return $products ?: [];
    }

    private function get_products_count($post_statuses = ['publish'], $need_variation = true) {
        $count1 = (new WP_Query([
                    'post_type' => 'product',
                    'post_status' => $post_statuses,
                    'posts_per_page' => -1,
                    'fields' => 'ids',
                    'no_found_rows' => true,
                    'tax_query' => [
                        array(
                            'taxonomy' => 'product_type',
                            'field' => 'slug',
                            'terms' => $this->allowed_product_types
                        )
                    ]]))->post_count;

        $count2 = 0;
        if ($need_variation) {
            $count2 = (new WP_Query([
                        'post_type' => 'product_variation',
                        'post_status' => $post_statuses,
                        'posts_per_page' => -1,
                        'fields' => 'ids',
                        'no_found_rows' => true
                            ]))->post_count;
        }

        return $count1 + $count2;
    }

    public function get_single_product($product_id) {
        $product_data = $this->get(0, [
            'p' => $product_id
        ]);

        return !empty($product_data) ? $product_data[0] : null;
    }

    public function create($data = []) {
        $product = new WC_Product_Simple();
        $product->set_name(esc_html__('New product', 'botoscope'));
        $product->set_regular_price(0);
        $product->set_status('draft');

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $min_order = (int) $wpdb->get_var("SELECT MIN(menu_order) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status != 'trash'");
        $product->set_menu_order($min_order - 1);

        $product_id = $product->save();

        update_post_meta($product_id, '_downloadable', 'no');
        update_post_meta($product_id, '_virtual', 'no');

        //lets also apply existed meta fields for this new product
        $this->meta->apply_for_new_product($product_id);

        return [
            'title' => esc_html__('New product', 'botoscope'),
            'id' => $product_id,
            'oid' => $product_id,
            'is_published' => 0
        ];
    }

    public function update($product, $field_key, $value, $all_sent_data = []) {

        if (is_object($product)) {
            $product_id = $product->get_id();
        } else {
            $product_id = intval($product);
            $product = wc_get_product($product_id);
        }

        remove_filter('content_save_pre', 'wp_filter_post_kses');
        remove_filter('content_filtered_save_pre', 'wp_filter_post_kses');

        if ($this->get_current_language() !== $this->get_default_language()) {
            $this->translations->update($product_id, $field_key, wp_unslash($value), $this->get_current_language());
        } else {
            if ($product_id) {
                //do not use $product->save() as it avoid of saving fields, order of items in html form matter
                switch ($field_key) {
                    case 'title':
                        wp_update_post([
                            'ID' => $product_id,
                            'post_title' => wp_unslash(wp_strip_all_tags($value))
                        ]);
                        break;
                    case 'description':
                        $meta_key = apply_filters('botoscope_use_meta_for_description', false);
                        $clean_value = wp_unslash($value);

                        if (!empty($meta_key)) {
                            update_post_meta($product_id, $meta_key, $clean_value);
                        } else {
                            if ($product->is_type('variation')) {
                                $product->set_description($clean_value);
                                $product->save();
                            } else {
                                wp_update_post([
                                    'ID' => $product_id,
                                    'post_excerpt' => $clean_value
                                ]);
                            }
                        }
                        break;

                    case 'product_details':
                        $meta_key = apply_filters('botoscope_use_meta_for_product_details', false);
                        $clean_value = wp_unslash($value);

                        if (!empty($meta_key)) {
                            update_post_meta($product_id, $meta_key, $clean_value);
                        } else {
                            if (!$product->is_type('variation')) {
                                wp_update_post([
                                    'ID' => $product_id,
                                    'post_content' => $clean_value
                                ]);
                            }
                        }
                        break;

                    case 'sku':
                        try {
                            $product->set_sku($value);
                            $product->save();
                        } catch (Exception $ex) {
                            wp_send_json_error(esc_html__('Such sku already attached to another product', 'botoscope'));
                        }

                        break;
                    case 'price':
                        update_post_meta($product_id, '_regular_price', $value);
                        update_post_meta($product_id, '_price', $value);
                        //synhro save
                        update_post_meta($product_id, '_botoscope_regular_price', $value);
                        update_post_meta($product_id, '_botoscope_price', $value);

                        //lets check sale price if it more than regular price
                        $sale_price = floatval(get_post_meta($product_id, '_sale_price', true));
                        if ($sale_price >= floatval($value)) {
                            update_post_meta($product_id, '_sale_price', '');
                            update_post_meta($product_id, '_botoscope_sale_price', '');
                        } else {
                            if ($sale_price > 0) {
                                update_post_meta($product_id, '_price', $sale_price);
                                update_post_meta($product_id, '_sale_price', $sale_price);
                                update_post_meta($product_id, '_botoscope_sale_price', $sale_price);
                                update_post_meta($product_id, '_botoscope_price', $sale_price);
                            }
                        }

                        break;
                    case 'sale_price':

                        $regular_price = get_post_meta($product_id, '_regular_price', true);
                        $value = floatval($value);

                        if ($regular_price <= $value) {
                            //Sale price should be lower than the regular price!
                            update_post_meta($product_id, '_sale_price', '');
                            update_post_meta($product_id, '_botoscope_sale_price', '');
                            update_post_meta($product_id, '_price', $regular_price);
                        } else {
                            if ($value <= 0) {
                                $value = '';
                            }
                            update_post_meta($product_id, '_sale_price', $value);
                            if (floatval($value > 0)) {
                                update_post_meta($product_id, '_price', $value);
                            } else {
                                update_post_meta($product_id, '_price', $regular_price);
                            }
                            //synhro save
                            update_post_meta($product_id, '_botoscope_sale_price', $value);
                            update_post_meta($product_id, '_botoscope_price', $value);
                        }

                        break;
                    case 'is_hidden':

                        update_post_meta($product_id, '_botoscope_is_hidden', $value ? 1 : 0);

                        break;

                    case 'is_published':

                        wp_update_post([
                            'ID' => $product_id,
                            'post_status' => intval($value) ? 'publish' : 'draft'
                        ]);

                        break;

                    case 'category':
                        $new_term_ids = array_map('intval', explode(',', sanitize_text_field($value))); // "18,23"
                        $product_id = intval($_POST['id']);

                        if (get_post_type($product_id) !== 'product') {
                            wp_send_json_error('Invalid product ID');
                        }

                        $current_terms = wp_get_object_terms($product_id, 'product_cat', ['fields' => 'ids']);
                        if (!is_wp_error($current_terms) && !empty($current_terms)) {
                            wp_remove_object_terms($product_id, $current_terms, 'product_cat');
                        }

                        if ($new_term_ids) {
                            $result = wp_set_object_terms($product_id, $new_term_ids, 'product_cat');
                        }

                        if (is_wp_error($result)) {
                            wp_send_json_error(esc_html__('Failed to update categories', 'botoscope'));
                        }

                        $this->mark_all_parent_categories($product_id);

                        //wp_send_json_success('Product data updated');//do not do it here, because code after this function doesn work

                        break;

                    case 'media':
                        //single product form
                        $attachment_ids = array_map('intval', explode(',', sanitize_text_field($value))); // "18,23"
                        $this->save_product_medias($product_id, $attachment_ids);
                        break;

                    case 'type':

                        switch ($value) {
                            case 'simple':
                                wp_set_object_terms($product_id, [$value], 'product_type');
                                update_post_meta($product_id, '_downloadable', 'no');
                                update_post_meta($product_id, '_virtual', 'no');
                                break;
                            case 'botoscope_simple_virtual':
                                wp_set_object_terms($product_id, [$value], 'product_type');
                                update_post_meta($product_id, '_virtual', 'yes');
                                update_post_meta($product_id, '_downloadable', 'no');
                                break;
                            case 'botoscope_simple_virtual_downloadable':
                            case 'botoscope_simple_media_casting':
                                wp_set_object_terms($product_id, [$value], 'product_type');
                                update_post_meta($product_id, '_virtual', 'yes');
                                update_post_meta($product_id, '_downloadable', 'yes');
                                break;
                            case 'variation_physical':
                                $product->set_virtual(false);
                                $product->set_downloadable(false);
                                $product->update_meta_data('_botoscope_variation_type', $value);
                                $product->save();
                                break;
                            case 'variation_virtual':
                                $product->set_virtual(true);
                                $product->set_downloadable(false);
                                $product->update_meta_data('_botoscope_variation_type', $value);
                                $product->save();
                                break;
                            case 'variation_virtual_downloadable':
                            case 'variation_media_casting':
                                $product->set_virtual(true);
                                $product->set_downloadable(true);
                                $product->update_meta_data('_botoscope_variation_type', $value);
                                $product->save();
                                break;
                            default:
                                wp_set_object_terms($product_id, [$value], 'product_type');
                                break;
                        }

                        break;

                    case 'external_link':
                        update_post_meta($product_id, '_product_url', esc_url_raw($value));
                        break;

                    case 'product_attributes':
                        $attributes = explode(',', $value);
                        $this->set_variable_product_attributes($product_id, $attributes);
                        break;

                    case 'product_attributes_terms':
                        $terms = explode(',', $value);
                        $this->set_variable_product_terms($product_id, $terms);
                        break;

                    case 'product_brand':
                        $brand_id = intval($value);

                        if ($brand_id === 0) {
                            wp_set_object_terms($product_id, [], 'product_brand');
                        } else {
                            $result = wp_set_object_terms($product_id, [$brand_id], 'product_brand');

                            if (is_wp_error($result)) {
                                wp_send_json_error('Failed to update brand');
                            }
                        }
                        break;

                    case 'publish_date':
                        update_post_meta($product_id, '_botoscope_publish_date', $value);
                        break;

                    case 'audio':
                        update_post_meta($product_id, '_botoscope_audio', $value);
                        break;

                    case 'quantity_step':
                        update_post_meta($product_id, 'botoscope_quantity_step', intval($value));
                        break;

                    case 'min_cart_count':
                        update_post_meta($product_id, 'botoscope_min_cart_count', intval($value));
                        break;

                    case 'meta_position':
                        update_post_meta($product_id, '_botoscope_meta_position', intval($value));
                        break;

                    case 'hide_price_below_media':
                        update_post_meta($product_id, '_botoscope_hide_price_below_media', intval($value));
                        break;
                    case 'manage_stock':
                        update_post_meta($product_id, '_manage_stock', intval($value) === 1 ? 'yes' : 'no');
                        break;
                    case 'stock_quantity':
                        update_post_meta($product_id, '_stock', intval($value));
                        if (intval($value)) {
                            update_post_meta($product_id, '_stock_status', 'instock');
                            if ($product->is_type('variation')) {
                                //update_post_meta($product_id, '_stock_status_var', 1);
                            }
                        } else {
                            update_post_meta($product_id, '_stock_status', 'outofstock');
                            if ($product->is_type('variation')) {
                                //update_post_meta($product_id, '_stock_status_var', 0);
                            }
                        }
                        break;
                    case 'is_in_stock':
                        update_post_meta($product_id, '_stock_status', $value); //do not use intval instock/outofstock
                        if ($product->is_type('variation')) {
                            //update_post_meta($product_id, '_stock_status_var', $value === 'instock' ? 1 : 0);
                        }
                        break;
                    case 'ignore_stock_for_collection':
                        //for grouped product only
                        update_post_meta($product_id, 'ignore_stock_for_collection', intval($value));
                        break;
                    case 'access_days':
                        //for botoscope_simple_virtual_downloadable and botoscope_simple_media_casting products
                        update_post_meta($product_id, 'botoscope_access_days', intval($value));
                        break;
                    case 'is_active':
                        if (intval($value)) {
                            wp_update_post([
                                'ID' => $product_id,
                                'post_status' => 'publish'
                            ]);
                            update_post_meta($product_id, '_botoscope_is_hidden', 0);
                        } else {
                            update_post_meta($product_id, '_botoscope_is_hidden', 1);
                        }
                        break;
                }
            }
        }
    }

    public function delete($product_id, $conditions = []) {
        $product = wc_get_product($product_id);

        if ($product) {
            $product->delete(false);
        }

        $this->translations->delete(0, ['product_id' => intval($product_id)]);
    }

    //===========================================================================

    public function register_routes() {

        $instance = $this;
        $this->botoscope->allrest->add_rest_route('/products/(?P<offset>\d+)/(?P<limit>\d+)', function (WP_REST_Request $request) use ($instance) {
            return $instance->get_products_for_rest($request);
        });

        $this->botoscope->allrest->add_rest_route('/products_count', function (WP_REST_Request $request) use ($instance) {
            return ['products_count' => $this->get_products_count(['publish'], false)];
        });

        $this->botoscope->allrest->add_rest_route('/products_filter/(?P<ids>[0-9,]+)', function (WP_REST_Request $request) use ($instance) {
            return $instance->products_filter_rest($request);
        });
    }

    private function init_hooks() {
        add_action('wp_enqueue_scripts', function () {
            if ((is_page('botoscope-filter') || is_page('botoscope-media-casting')) && function_exists('woof')) {
                wp_enqueue_style('botoscope-filter', BOTOSCOPE_EXT_LINK . 'products/assets/css/filter.css', [], BOTOSCOPE_VERSION);
                wp_enqueue_script('botoscope-telegram-sdk', 'https://telegram.org/js/telegram-web-app.js', [], BOTOSCOPE_VERSION, false);
                wp_enqueue_script('botoscope-filter', BOTOSCOPE_EXT_LINK . 'products/assets/js/filter.js', ['botoscope-telegram-sdk'], BOTOSCOPE_VERSION, true);
            }
        });

        add_action('wp_footer', function () {
            if ((is_page('botoscope-filter') || is_page('botoscope-media-casting')) && function_exists('woof')) {
                global $WOOF;
                $chat_id = 0;
                $parsed_url = wp_parse_url(home_url(sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']))));
                if (isset($parsed_url['query'])) {
                    $query_params = [];
                    parse_str($parsed_url['query'], $query_params);
                    $chat_id = intval($query_params['chat_id'] ?? 0);
                }
                $should_close = false;
                $woof_data = woof()->get_request_data();
                if ($chat_id && is_object($WOOF) && isset($woof_data['swoof'])) {
                    woof()->woof_products_ids_prediction(true);
                    $ids = array_map('intval', $_REQUEST['woof_wp_query_ids'] ?? []);
                    $possible_ids = $this->get_woof_products_ids();
                    $ids = array_values(array_intersect($ids, $possible_ids));
                    $should_close = true;
                    $this->botoscope->do_command($chat_id, 'filtered_products_ids', ['ids' => $ids]);
                }
                wp_add_inline_script('botoscope-filter', 'var botoscope_filter_data = ' . wp_json_encode(['should_close' => $should_close]) . ';', 'before');
            }
        }, 5);
    }

    public function get_products_for_rest(WP_REST_Request $request, $params = []) {
        $offset = intval($request['offset']);
        $limit = intval($request['limit']);

        $get_params = array_merge([
            'post_type' => ['product'],
            'post_status' => ['publish'],
            'limit' => $limit > 0 ? $limit : $this->admin_per_page,
            'offset_val' => $offset,
            'exclude_hidden' => true,
                ], $params);

        $products = $this->get(0, $get_params);

        // Collect IDs of variable products to fetch their variations
        $variable_ids = [];
        foreach ($products as $p) {
            if ($p['type'] === 'variable' && !empty($p['child_ids'])) {
                foreach ($p['child_ids'] as $child_id) {
                    $variable_ids[] = intval($child_id);
                }
            }
        }

        // Fetch variations and append to result
        if (!empty($variable_ids)) {
            $variations_query = new WP_Query([
                'post_type' => 'product_variation',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'post__in' => $variable_ids,
                'orderby' => 'post__in', // Keep original order
            ]);

            if ($variations_query->have_posts()) {
                while ($variations_query->have_posts()) {
                    $variations_query->the_post();
                    global $product;
                    $products[$product->get_id()] = $this->get_product_fields($product);
                }
                wp_reset_postdata();
            }
        }

        return $products;
    }

    public function products_filter_rest(WP_REST_Request $request) {
        $params = [];
        $ids = $request->get_param('ids');

        if (!empty($ids)) {
            $ids = explode(',', $ids);

            if (!empty($ids)) {
                $params['post__in'] = $ids;
                $params['posts_per_page'] = $request->get_param('per_page');
                $params['paged'] = $request->get_param('page');
                //maybe for products filter should exclude product_variation
                $params['post_type'] = ['product', 'product_variation'];
            } else {
                $params['post__in'] = -1;
            }

            $request->set_param('offset', 0);
            $request->set_param('limit', -1);

            return $this->get_products_for_rest($request, $params);
        }
    }

    private function get_min_cart_count($product_id) {
        $min_cart_count = intval(get_post_meta($product_id, 'botoscope_min_cart_count', true));
        return $min_cart_count ? $min_cart_count : 0;
    }

    public function get_access_days($product_id) {
        $access_days = intval(get_post_meta($product_id, 'botoscope_access_days', true));
        return $access_days ? $access_days : 0;
    }

    private function get_max_cart_count($product_id) {
        $max_cart_count = intval(get_post_meta($product_id, 'botoscope_max_cart_count', true));
        return $max_cart_count;
    }

    private function get_quantity_step($product_id) {
        $quantity_step = intval(get_post_meta($product_id, 'botoscope_quantity_step', true));
        return $quantity_step;
    }

    public function get_product_media_gallery($product) {
        $media_gallery = [];

        if (!is_object($product)) {
            return [];
        }

        $attachment_ids = $this->get_media_gallery_ids($product->get_id());
        foreach ($attachment_ids as $attachment_id) {
            $attachment_url = wp_get_attachment_url($attachment_id);
            $type = preg_match('/^[^\/]+/', get_post_mime_type($attachment_id), $matches) ? $matches[0] : null;

            if (defined('REST_REQUEST') && REST_REQUEST) {
                $botoscope_video_link = get_post_meta($attachment_id, 'botoscope_video_link', true);
                if (!empty($botoscope_video_link)) {
                    //for big and long files user should upload them to special channel then paste link in media gallery
                    $attachment_url = $botoscope_video_link;
                    $type = 'video';
                }
            }

            if ($attachment_url) {
                $media_gallery[] = array(
                    'aid' => intval($attachment_id),
                    'type' => $type,
                    'media' => $attachment_url
                );
            }
        }

        if (empty($media_gallery)) {
            $image_url = BOTOSCOPE_ASSETS_LINK . "img/no-image.webp";
            $image_id = $product->get_image_id();

            if ($image_id) {
                $image_url = wp_get_attachment_url($image_id);
            }

            $media_gallery[] = array(
                'type' => 'image',
                'media' => $image_url
            );
        }

        return $media_gallery;
    }

    private function get_product_categories($product_id) {
        $terms = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
        return $terms ? $terms : array();
    }

    private function save_product_medias($product_id, $attachment_ids) {
        $ids = implode(',', array_unique($attachment_ids));
        update_post_meta($product_id, '_product_image_gallery', $ids);
    }

    public function get_media_gallery_ids($product_id) {
        $ids = explode(',', strval(get_post_meta($product_id, '_product_image_gallery', true)));
        return array_unique($ids);
    }

    public function get_variations_by_ids($child_ids) {
        $res = [];

        if (!empty($child_ids)) {
            foreach ($child_ids as $pid) {
                $variation = wc_get_product($pid);

                if ($variation && $variation->is_type('variation')) {
                    $attributes = $variation->get_attributes();

                    $attributes_string = implode(' | ', array_map(function ($key, $value) {
                                $decoded_key = urldecode($key);
                                $attribute_label = wc_attribute_label($decoded_key);
                                $term = get_term_by('slug', urldecode($value), $decoded_key);
                                if (!$term) {
                                    $term = get_term_by('slug', $value, $decoded_key);
                                }
                                $term_name = $term ? $term->name : $value;
                                return "{$attribute_label}: {$term_name}";
                            }, array_keys($attributes), $attributes));

                    $var = $this->get_product_fields($variation);
                    $var['title'] = $attributes_string;
                    $var['oid'] = $pid;

                    $attributes_with_ids = [];
                    foreach ($attributes as $key => $value) {
                        $decoded_key = urldecode($key);
                        $term = get_term_by('slug', urldecode($value), $decoded_key);
                        if (!$term) {
                            $term = get_term_by('slug', $value, $decoded_key);
                        }
                        $attributes_with_ids[$decoded_key] = $term ? $term->term_id : null;
                    }

                    $var['combination'] = $attributes_with_ids;
                    $res[] = $var;
                }
            }

            usort($res, function ($a, $b) {
                return $b['id'] <=> $a['id'];
            });
        }

        return $res;
    }

    private function get_possible_free_combinations($product_id) {
        $res = [];
        $attributes_info = [];
        $product = wc_get_product($product_id);

        if ($product && $product->is_type('variable')) {
            $attributes = $product->get_attributes();

            // We collect only permitted attributes and terms
            foreach ($attributes as $attribute_name => $attribute) {
                if ($attribute->get_variation()) {
                    $taxonomy = $attribute->get_name();

                    // We get the permitted terms
                    $terms = get_terms([
                        'taxonomy' => $taxonomy,
                        'include' => $attribute->get_options(), // Only permitted terms
                        'hide_empty' => false
                    ]);

                    foreach ($terms as $term) {
                        if (!isset($attributes_info[$taxonomy])) {
                            $attributes_info[$taxonomy] = [
                                'title' => wc_attribute_label($taxonomy),
                                'terms' => []
                            ];
                        }

                        $attributes_info[$taxonomy]['terms'][$term->term_id] = $term->name;
                    }
                }
            }

            // Generate all possible combinations based on allowed attributes
            $keys = array_keys($attributes_info);
            $values = array_map(function ($info) {
                return array_keys($info['terms']);
            }, $attributes_info);

            $all_combinations = $this->generate_combinations(array_combine($keys, $values));

            // We obtain already existing combinations (attributes of existing variations)
            $existing_variations = [];
            foreach ($product->get_children() as $variation_id) {
                $variation = wc_get_product($variation_id);

                if ($variation && $variation->is_type('variation')) {
                    $existing_attributes = [];
                    foreach ($variation->get_attributes() as $key => $value) {
                        $term = get_term_by('slug', $value, $key);
                        if ($term) {
                            $existing_attributes[$key] = (string) $term->term_id;
                        }
                    }
                    $existing_variations[] = $existing_attributes;
                }
            }

            // We exclude already existing combinations
            foreach ($all_combinations as $combination) {
                $is_existing = false;

                foreach ($existing_variations as $existing) {
                    if ($this->are_combinations_equal($existing, $combination)) {
                        $is_existing = true;
                        break;
                    }
                }

                if (!$is_existing) {
                    $res[] = $combination; // We add only unique combinations
                }
            }
        }

        return [
            'combinations' => $res,
            'info' => $attributes_info
        ];
    }

    // Helper function for comparing combinations
    private function are_combinations_equal($existing, $combination) {
        if (count($existing) !== count($combination)) {
            return false; // Number of attributes does not match
        }

        foreach ($existing as $key => $value) {
            if (!isset($combination[$key]) || (string) $combination[$key] !== (string) $value) {
                return false; // If the key is missing or the values ​​do not match
            }
        }

        return true;
    }

    // Helper function for generating combinations
    private function generate_combinations($arrays) {
        $result = [[]];

        foreach ($arrays as $key => $values) {
            $append = [];

            foreach ($result as $product) {
                foreach ($values as $value) {
                    $append[] = array_merge($product, [$key => $value]);
                }
            }

            $result = $append;
        }

        return $result;
    }

    private function create_variation($product_id, $attributes) {
        $product = wc_get_product($product_id);

        if (!$product || !$product->is_type('variable')) {
            return new WP_Error('invalid_product', 'Product not found or not variable');
        }

        $variation = new WC_Product_Variation();

        $variation->set_parent_id($product_id);

        $variation_attributes = [];
        foreach ($attributes as $attribute_name => $term_id) {
            $term = get_term($term_id);
            if ($term) {
                $variation_attributes[$attribute_name] = $term->slug;
            }
        }
        $variation->set_attributes($variation_attributes);

        $variation->set_regular_price('0');
        $variation->set_stock_status('instock');
        $variation->save();

        $parent_product = wc_get_product($product_id);
        $parent_sku = $parent_product->get_sku();
        if (empty($parent_sku)) {
            $parent_sku = 'sku';
        }
        $variation->set_sku($parent_sku . '-' . $variation->get_id());
        $variation->save();

        return $variation->get_id();
    }

    public function get_product_allowed_attributes($product_id) {
        $blocks = [];
        $taxonomies = [];
        $product = wc_get_product($product_id);
        $attributes = $product->get_attributes();

        foreach ($attributes as $attribute_name => $attribute) {
            if (!$attribute->get_variation()) {
                continue; // Skip attributes not used for variations
            }

            $taxonomy = $attribute->get_name();

            if (!isset($taxonomies[$taxonomy])) {
                $taxonomies[$taxonomy] = wc_attribute_label($taxonomy);
            }

            // Get attribute values ​​allowed for this product
            $allowed_terms_ids = $attribute->get_options();

            $terms = get_terms([
                'taxonomy' => $taxonomy,
                'hide_empty' => false
            ]);

            if (!empty($terms) && !is_wp_error($terms)) {
                $blocks[$taxonomy] = [];

                usort($terms, function ($a, $b) {
                    $a_name = mb_strtolower($a->name, 'UTF-8');
                    $b_name = mb_strtolower($b->name, 'UTF-8');
                    return strcmp($a_name, $b_name);
                });

                foreach ($terms as $term) {
                    // We add only those terms that are allowed for this product
                    if (in_array($term->term_id, $allowed_terms_ids)) {
                        $blocks[$taxonomy][$term->term_id] = $term->name;
                    }
                }
            }
        }

        return [
            'blocks' => $blocks,
            'taxonomies' => $taxonomies
        ];
    }

    public function get_all_woocommerce_attributes() {
        $blocks = [];
        $taxonomies = [];

        $global_attributes = wc_get_attribute_taxonomies();

        foreach ($global_attributes as $attribute) {
            $taxonomy = wc_attribute_taxonomy_name($attribute->attribute_name);

            $taxonomies[$taxonomy] = $attribute->attribute_label;

            $terms = get_terms([
                'taxonomy' => $taxonomy,
                'hide_empty' => false
            ]);

            if (!empty($terms) && !is_wp_error($terms)) {
                $blocks[$taxonomy] = [];

                usort($terms, function ($a, $b) {
                    $a_name = mb_strtolower($a->name, 'UTF-8');
                    $b_name = mb_strtolower($b->name, 'UTF-8');
                    return strcmp($a_name, $b_name);
                });

                foreach ($terms as $term) {
                    $blocks[$taxonomy][$term->term_id] = $term->name;
                }
            }
        }

        return [
            'blocks' => $blocks,
            'taxonomies' => $taxonomies
        ];
    }

    private function set_variable_product_attributes($product_id, $attributes) {
        $product = wc_get_product($product_id);

        if (!$product || $product->get_type() !== 'variable') {
            return false;
        }

        $product_attributes = [];

        foreach ($attributes as $attribute) {
            $product_attributes[$attribute] = [
                'name' => $attribute,
                'value' => '',
                'is_visible' => 1,
                'is_variation' => 1,
                'is_taxonomy' => 1
            ];
        }

        $product->set_attributes($product_attributes);
        $product->save();
    }

    private function set_variable_product_terms($product_id, $terms) {
        $product = wc_get_product($product_id);

        if (!$product || $product->get_type() !== 'variable') {
            return false;
        }

        $attributes = [];

        foreach ($terms as $term_id) {
            $term = get_term($term_id);

            if (!$term || is_wp_error($term)) {
                continue;
            }

            $taxonomy = $term->taxonomy;

            if (!isset($attributes[$taxonomy])) {
                $attributes[$taxonomy] = [];
            }

            $attributes[$taxonomy][] = $term->term_id;
        }

        foreach ($attributes as $taxonomy => $terms) {
            $this->set_product_attributes($product, $taxonomy, $terms);
        }
    }

    private function set_product_attributes($product, $field_key, $value) {
        if (!is_array($value)) {
            $value = array(intval($value));
        } else {
            foreach ($value as $k => $tid) {
                $value[$k] = intval($tid);
            }
        }

        $attributes = array();
        $product_attributes = $product->get_attributes();

        //*** fix for empty value
        if (count($value) === 1) {
            if (intval($value[0]) === 0) {
                $value = array();
            }
        }

        if (!empty($product_attributes)) {
            //wp-content\plugins\woocommerce\includes\admin\meta-boxes\class-wc-meta-box-product-data.php
            //public static function prepare_attributes
            foreach ($product_attributes as $pa_key => $a) {

                if (is_object($a)) {
                    $attribute = new WC_Product_Attribute();
                    $attribute->set_id($a->get_id());
                    $attribute->set_name($a->get_name());

                    if ($a->get_name() === $field_key) {

                        //detach attributes if there is no selected terms!!
                        if (empty($value)) {
                            continue;
                        }

                        $attribute->set_options($value);
                    } else {
                        $attribute->set_options($a->get_options());
                    }
                    $attribute->set_position($a->get_position());
                    $attribute->set_visible(true);
                    $attribute->set_variation(true);

                    $attributes[] = $attribute;
                }
            }
        }

        //***
        //if such attribute not applied in the product
        if (!isset($product_attributes[$field_key]) AND !isset($product_attributes[strtolower((string) urlencode((string) $field_key))])) {
            $attribute = new WC_Product_Attribute();
            $attribute_taxonomies = wc_get_attribute_taxonomies();
            foreach ($attribute_taxonomies as $a) {
                if ('pa_' . $a->attribute_name == $field_key) {

                    if (!empty($value)) {
                        $attribute->set_id($a->attribute_id);
                        $attribute->set_name('pa_' . $a->attribute_name);
                        $attribute->set_options($value);
                        $attribute->set_position(count($attributes));
                        $attribute->set_visible(1);
                        $attribute->set_variation(true);
                        $attributes[] = $attribute;
                    }

                    break;
                }
            }
        }

        $product->set_attributes($attributes);
        $product->save();
    }

    private function get_variation_combination($child_id) {
        $combination = [];

        if (!empty($child_id)) {
            $variation = wc_get_product($child_id);

            if ($variation && $variation->is_type('variation')) {
                $attributes = $variation->get_attributes();

                foreach ($attributes as $key => $value) {
                    $decoded_key = urldecode($key);
                    $term = get_term_by('slug', urldecode($value), $decoded_key);
                    if (!$term) {
                        $term = get_term_by('slug', $value, $decoded_key);
                    }
                    $combination[$decoded_key] = $term ? $term->term_id : null;
                }
            }
        }

        return $combination;
    }

    public function update_product_bot_cache($product_id) {

        if (isset(self::$synced_products[$product_id])) {
            return;
        }

        self::$synced_products[$product_id] = true;

        $this->botoscope->do_command(-1, 'update_product_cache', [
            'product_id' => $product_id
        ]);
    }

    public function get_product_brand_id($product) {
        if (is_numeric($product)) {
            $product_id = $product;
        } elseif ($product instanceof WC_Product) {
            $product_id = $product->get_id();
        } else {
            return 0;
        }

        $brands = wp_get_post_terms($product_id, 'product_brand');

        return (!empty($brands) && !is_wp_error($brands)) ? $brands[0]->term_id : 0;
    }

    public function get_product_fields($product) {
        $regular_price = floatval($product->get_regular_price());
        $sale_price = floatval($product->get_sale_price()) ?: 0;

        if ($product->get_type() === 'grouped') {
            $regular_price = $product->get_meta('_botoscope_regular_price');
            $sale_price = $product->get_meta('_botoscope_sale_price');
        }

        if ($sale_price >= $regular_price) {
            $sale_price = 0; //!!
        }

        $manage_stock = $product->get_manage_stock() ? 1 : 0;
        $stock_quantity = $product->get_manage_stock() ? $product->get_stock_quantity() : 0;
        $botoscope_type = $product->get_type();

        if ($product->get_type() === 'variation') {
            $botoscope_type = $product->get_meta('_botoscope_variation_type', true) ?: 'variation_physical';
        }

        if (!in_array($botoscope_type, ['simple', 'variable', 'variation_physical'])) {
            $manage_stock = $stock_quantity = 0;
        }

        if ($botoscope_type === 'variation_physical') {
            $manage_stock = $product->get_manage_stock() ? 1 : 0;
        }

        $is_in_stock = $product->is_in_stock() ? 1 : 0;

        if ($product->get_type() === 'variable') {
            $is_in_stock = $product->get_stock_status() === 'instock' ? 1 : 0;
        }

        if ($manage_stock && $stock_quantity > 0) {
            $is_in_stock = 1;
        }

        if ($manage_stock && $stock_quantity === 0) {
            $is_in_stock = 0;
        }

        if (defined('REST_REQUEST') && REST_REQUEST) {
            $description = strip_tags($this->convert_html_list_to_telegram_text($this->get_product_description($product)), '<strong><em><code><u>');
        } else {
            $description = wpautop($this->get_product_description($product));
        }

        $is_hidden = $product->get_meta('_botoscope_is_hidden') ? 1 : 0;
        $is_published = $product->get_status() === 'publish' ? 1 : 0;

        $product_data = [
            'id' => $product->get_id(),
            'type' => $product->get_type(),
            'botoscope_type' => $botoscope_type,
            'title' => $product->get_title(),
            'description' => $description,
            'product_details' => $this->get_product_product_details($product),
            'sku' => $product->get_sku(),
            'is_in_stock' => $is_in_stock,
            'manage_stock' => $manage_stock,
            'stock_quantity' => $stock_quantity,
            'language' => $this->botoscope->get_default_language(),
            'translations' => $this->translations->get_product_translations($product->get_id()),
            'price' => $regular_price,
            'sale_price' => $sale_price,
            'category' => $this->get_product_categories($product->get_id()),
            'audio' => $this->get_product_audio($product),
            //'audio_caption' => $this->get_product_audio_caption($product->get_id()),
            'min_cart_count' => $this->get_min_cart_count($product->get_id()),
            'max_cart_count' => $this->get_max_cart_count($product->get_id()),
            'access_days' => $this->get_access_days($product->get_id()),
            'quantity_step' => $this->get_quantity_step($product->get_id()),
            'media' => $this->get_product_media_gallery($product),
            'child_ids' => [],
            'downloads' => [],
            'external_link' => '',
            'attributes' => [],
            'attributes_terms' => [],
            'combination' => [],
            'parent_id' => 0,
            'product_brand' => $this->get_product_brand_id($product),
            'is_hidden' => $is_hidden,
            'is_published' => $is_published,
            'is_active' => ($is_published && !$is_hidden) ? 1 : 0,
            'is_in_group_of' => (array) $this->get_product_group_ids($product->get_id(), $product->get_type()),
            'meta' => $this->meta->get_product_meta($product->get_id()),
            'meta_position' => $product->get_meta('_botoscope_meta_position') ? 1 : 0,
            'hide_price_below_media' => intval($product->get_meta('_botoscope_hide_price_below_media') ?: 0),
            'publish_date' => intval($product->get_meta('_botoscope_publish_date') ?: 0),
            'menu_order' => $product->get_menu_order()
        ];

        if ($this->botoscope->shopify_sync) {
            //Shopify source fields: used by the bot to show "Buy on Shopify" link instead of WC checkout.
            $product_data['shopify_product_id'] = intval($product->get_meta('_botoscope_shopify_product_id') ?: 0);
            $product_data['shopify_product_url'] = (string) ($product->get_meta('_botoscope_shopify_product_url') ?: '');
        }


        if (method_exists($product, 'get_product_url')) {
            $product_data['external_link'] = $product->get_product_url();
        }

        if (method_exists($product, 'get_downloads')) {
            $downloads = [];
            foreach ($product->get_downloads() as $download_id => $download) {
                $downloads[] = [
                    'id' => $download_id,
                    'title' => $download->get_name(),
                    'file_url' => $download->get_file()
                ];
            }

            $product_data['downloads'] = $downloads;
        }

        if ($product->is_type('grouped') || $product->is_type('variable')) {
            $product_data['child_ids'] = array_values($product->get_children());
            $product_data['attributes'] = array_keys($this->get_product_allowed_attributes($product->get_id())['taxonomies']);
            $blocks = (array) $this->get_product_allowed_attributes($product->get_id())['blocks'];

            $keys = [];

            foreach ($blocks as $block) {
                if (is_array($block)) {
                    $keys = array_merge($keys, array_keys($block));
                }
            }

            $product_data['attributes_terms'] = array_map('intval', $keys);
            $product_data['ignore_stock_for_collection'] = intval($product->get_meta('ignore_stock_for_collection') ?: 0);

            if ($product->is_type('grouped')) {
                if ($product_data['ignore_stock_for_collection'] === 0 && $product_data['is_in_stock'] && !empty($product_data['child_ids'])) {
                    $is_in_stock = 1;

                    foreach ($product_data['child_ids'] as $child_id) {
                        $f = $this->get_product_fields(wc_get_product($child_id));
                        if ($f['is_in_stock'] === 0) {
                            $is_in_stock = 0;
                            break;
                        }
                    }

                    $product_data['is_in_stock'] = $is_in_stock;
                }
            }
        }

        if ($product->is_type('variation')) {
            $product_data['parent_id'] = $product->get_parent_id();
            $product_data['combination'] = $this->get_variation_combination($product->get_id());
        }

        return $product_data;
    }

    private function get_product_description($product) {
        $description = $product->get_short_description();

        if ($product->is_type('variation')) {
            $description = $product->get_description();
            if (empty($description)) {
                $parent_product = wc_get_product($product->get_parent_id());
                $description = $this->get_product_description($parent_product);
            }
        }

        $meta_key = apply_filters('botoscope_use_meta_for_description', false);
        if (!empty($meta_key)) {
            $description = $product->get_meta($meta_key);
        }

        return $description;
    }

    private function get_product_product_details($product) {
        $product_details = $product->get_description();

        if ($product->is_type('variation')) {
            $parent_product = wc_get_product($product->get_parent_id());
            $product_details = $parent_product->get_description();
        }

        $meta_key = apply_filters('botoscope_use_meta_for_product_details', false);
        if (!empty($meta_key)) {
            $product_details = $product->get_meta($meta_key);
        }

        return $product_details;
    }

    protected function reset_wc_product_cache($product_id) {
        wc_delete_product_transients($product_id);
        wp_cache_delete($product_id, 'products');
        wp_cache_delete('product_' . $product_id, 'products');
        wp_cache_flush();
    }

    public function get_product_group_ids($product_id, $type = '') {
        static $res = [];

        if (!array_key_exists($product_id, $res)) {
            if ($type === 'grouped') {
                return [];
            }

            if (!$product_id) {
                return [];
            }

            global $wpdb;

            $container_ids = $wpdb->get_col($wpdb->prepare("
        SELECT p.ID FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE p.post_type = 'product'
        AND pm.meta_key = '_children'
        AND pm.meta_value LIKE %s", '%' . $wpdb->esc_like($product_id) . '%'));

            $res[$product_id] = !empty($container_ids) ? array_map('intval', $container_ids) : [];
        }

        return $res[$product_id];
    }

    private function get_product_audio($product) {
        $res = [];

        if ($product) {
            $res = $product->get_meta('_botoscope_audio');
        }

        return $res ? $res : [];
    }

    public function on_re_order_stock($order) {
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product && $product->managing_stock()) {
                $this->update_product_bot_cache($product->get_id());
            }
        }
    }

    public function woocommerce_order_refunded($order_id, $refund_id = 0) {
        $order = wc_get_order($order_id);

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $product_id = $product->get_id();

            if ($product && $product->managing_stock()) {
                $this->update_product_bot_cache($product_id);
            }
        }
    }

    public function mark_all_parent_categories($product_id) {
        clean_object_term_cache($product_id, 'product_cat');
        $terms = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);

        if (!is_wp_error($terms) && !empty($terms)) {
            $all_terms = [];

            foreach ($terms as $term_id) {
                $all_terms[] = $term_id;

                $ancestors = get_ancestors($term_id, 'product_cat', 'taxonomy');
                if (!empty($ancestors)) {
                    $all_terms = array_merge($all_terms, $ancestors);
                }
            }

            wp_set_object_terms($product_id, array_unique($all_terms), 'product_cat');

            clean_post_cache($product_id);
            clean_object_term_cache($product_id, 'product_cat');

            if (function_exists('wc_delete_product_transients')) {
                wc_delete_product_transients($product_id);
            }
        }
    }

    public function get_woof_products_ids() {
        static $res = null;

        if ($res === null) {
            $params = [
                'post_type' => ['product'],
                'post_status' => ['publish'],
                'limit' => is_botoscope_free() ? 9 : 1000,
                'offset_val' => 0,
                'exclude_hidden' => true,
            ];

            $res = array_column($this->get(0, $params), 'id');
        }

        return $res;
    }

    public function draw_progress_bar() {
        $max_products = is_botoscope_free() ? 9 : 1000;
        $now_products = count($this->get(0, [
                    'post_status' => ['publish'],
                    'exclude_hidden' => true,
                    'posts_per_page' => -1,
                    'fields' => 'ids',
        ]));

        $percent = $max_products > 0 ? round($now_products / $max_products * 100, 1) : 0;
        ?>
        <div class="botoscope_progress_wrap">
            <div class="botoscope_progress_bar" style="--botoscope-progress: <?php echo esc_attr($percent) ?>%">
                <span class="botoscope_progress_label"><?php
                    /* translators: %s: products count fraction e.g. "5 / 9" */
                    printf(esc_html__('%s products', 'botoscope'), intval($now_products) . ' / ' . intval($max_products))
                    ?></span>
            </div>
        </div>
        <?php
    }

    public function draw_content($counter) {
        $default_lang = $this->controls->get_default_language();
        $active_langs = $this->controls->get_active_languages();
        $langs = array_intersect_key($this->botoscope->languages, array_flip(array_merge($active_langs, [$default_lang])));
        ?>
        <section id="botoscope-<?php echo esc_attr($this->slug) ?>" <?php if ($counter === 0): ?>class="content-current"<?php endif; ?>>

            <div class="botoscope-table-tools-panel">
                <div class="botoscope-products-panel-buttons">
                    <div>
                        <a href="javascript: void(0);" id="botoscope_create_products" class="botoscope-button"><span><div class="svg_wrap relative" style="display:inline-block;width:30px"><svg viewBox="0 0 315.1 315.1">
                                        <path style="fill:#fff" class="st0" d="M303.4,21L5.5,135.9c-7.3,2.8-7.3,13.2,0.1,16L78.2,179l28.1,90.4c1.8,5.8,8.9,7.9,13.6,4.1l40.5-33  c4.2-3.5,10.3-3.6,14.7-0.4l73,53c5,3.7,12.1,0.9,13.4-5.2L315,30.8C316.2,24,309.7,18.5,303.4,21z M246.5,81.1l-117,108.8  c-4.1,3.8-6.8,9-7.5,14.5l-4,29.6c-0.5,3.9-6.1,4.3-7.2,0.5l-15.3-53.9c-1.8-6.1,0.8-12.7,6.2-16.1l141.9-87.4  C246.1,75.6,248.7,79.1,246.5,81.1z"></path>
                                    </svg></div></span><?php esc_html_e('Create product', 'botoscope') ?></a><br>
                    </div>

                    <div>
                        <a href="javascript: void(0);" id="botoscope_create_dummy_products" class="botoscope-button">🖋 <?php esc_html_e('Generate multiple', 'botoscope') ?></a><br>
                    </div>

                    <?php if (is_botoscope_connected()): ?>
                        <div>

                            <a href="#" class="button wc-action-button bs-invert-button" id="botoscope-products-all-visible" title="<?php esc_html_e('Make all published products visible in your Telegram store?', 'botoscope') ?>"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="56" height="56">
                                    <!-- Background circle -->
                                    <circle cx="32" cy="32" r="30" fill="#2AABEE"/>

                                    <!-- Box bottom -->
                                    <rect x="12" y="32" width="40" height="22" rx="3" fill="white"/>
                                    <!-- Box stripe -->
                                    <rect x="12" y="38" width="40" height="5" fill="#2AABEE" opacity="0.3"/>

                                    <!-- Box left flap open -->
                                    <path d="M12 32 L12 18 L26 24 L26 32 Z" fill="white" opacity="0.85"/>
                                    <!-- Box right flap open -->
                                    <path d="M52 32 L52 18 L38 24 L38 32 Z" fill="white" opacity="0.85"/>
                                    <!-- Box back -->
                                    <rect x="12" y="20" width="40" height="13" rx="2" fill="white" opacity="0.5"/>

                                    <!-- Products inside box - small squares -->
                                    <rect x="18" y="42" width="8" height="8" rx="1.5" fill="#2AABEE"/>
                                    <rect x="28" y="42" width="8" height="8" rx="1.5" fill="#2AABEE"/>
                                    <rect x="38" y="42" width="8" height="8" rx="1.5" fill="#2AABEE"/>

                                    <!-- Checkmark top right -->
                                    <circle cx="47" cy="17" r="11" fill="#29A84E"/>
                                    <polyline points="41,17 45,21 53,11" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg></a>
                            <a href="#" class="button wc-action-button bs-invert-button" id="botoscope-products-all-hidden" title="<?php esc_html_e('Hide all products in your Telegram store?', 'botoscope') ?>"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="56" height="56">
                                    <!-- Background circle -->
                                    <circle cx="32" cy="32" r="30" fill="#7B8EA0"/>

                                    <!-- Box body -->
                                    <rect x="12" y="28" width="40" height="26" rx="3" fill="white" opacity="0.9"/>
                                    <!-- Box lid -->
                                    <rect x="10" y="22" width="44" height="10" rx="3" fill="white"/>
                                    <!-- Box stripe -->
                                    <rect x="10" y="29" width="44" height="4" fill="#7B8EA0" opacity="0.2"/>
                                    <!-- Ribbon vertical -->
                                    <rect x="29" y="22" width="6" height="32" rx="1" fill="#7B8EA0" opacity="0.25"/>
                                    <!-- Ribbon horizontal -->
                                    <rect x="10" y="31" width="44" height="5" rx="1" fill="#7B8EA0" opacity="0.25"/>

                                    <!-- Lock -->
                                    <rect x="25" y="36" width="14" height="11" rx="2.5" fill="#7B8EA0"/>
                                    <path d="M27 36 L27 32 Q32 27 37 32 L37 36" fill="none" stroke="#7B8EA0" stroke-width="3" stroke-linecap="round"/>
                                    <circle cx="32" cy="41" r="2" fill="white"/>
                                    <rect x="31" y="41" width="2" height="3" rx="1" fill="white"/>
                                </svg></a>

                        </div>
                    <?php endif; ?>

                </div>

                <?php if (is_botoscope_connected()): ?>
                    <div id="botoscope_progress_wrap_container"><?php $this->draw_progress_bar() ?></div>
                <?php endif; ?>

                <div>

                    <select id="botoscope-<?php echo esc_attr($this->slug) ?>-lang-selector" class="botoscope-lang-selector" data-default-language="<?php echo esc_attr($default_lang) ?>">
                        <?php foreach ($langs as $lang_key => $lang_title) : ?>
                            <option value="<?php echo esc_attr($lang_key) ?>" <?php selected($this->get_current_language(), $lang_key) ?>><?php echo esc_attr($lang_title) ?></option>
                        <?php endforeach; ?>
                    </select>

                </div>



            </div>

            <div data-per-page="<?php echo esc_attr($this->admin_per_page) ?>" data-items-count="<?php echo intval($this->get_products_count(['publish', 'draft'], false)) ?>" id="botoscope-<?php echo esc_attr($this->slug) ?>-w"><?php echo wp_json_encode($this->get(), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></div>
            <br>
                <a href="<?php echo esc_url(admin_url('edit.php?post_status=trash&post_type=product')) ?>" class="button" style="float: right; clear: both;"><?php esc_html_e('Deleted Products', 'botoscope') ?></a><br>

                    <template id="botoscope-product-types"><?php echo wp_json_encode($this->types, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></template>

                    </section>
                    <?php
                }
            }
            