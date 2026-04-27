<?php
if (!defined('ABSPATH')) {
    exit;
}

//14-04-2026
final class BOTOSCOPE_SHOPIFY_SYNC extends BOTOSCOPE_APP {

    protected $botoscope;
    protected $table_name = '';
    protected $slug = 'shopify_sync';
    private $shopify_api_ver = '2026-01';
    private $token_cache_key = 'botoscope_shopify_access_token_cache';
    //Per-request cache to avoid duplicate creation within one sync run
    private static $attr_cache = [];
    private static $importing_from_shopify = false;
    private $errors = [];

    public function __construct($args = []) {
        parent::__construct($args);

        $this->botoscope->allrest->add_rest_route('shopify_sync/webhook', [$this, 'route_webhook'], 'POST', true);

        //debug only
        /*
          $this->botoscope->allrest->add_rest_route('shopify_sync/run/(?P<offset>\d+)/(?P<limit>\d+)', [$this, 'route_run_sync']);
          $this->botoscope->allrest->add_rest_route('shopify_sync', [$this, 'route_status']);
          //$this->botoscope->allrest->add_rest_route('shopify_sync/product/(?P<shopify_id>\d+)', [$this, 'route_sync_single']);
          $this->botoscope->allrest->add_rest_route('shopify_sync/count', [$this, 'route_products_count']);
          $this->botoscope->allrest->add_rest_route('shopify_sync/webhooks_check', [$this, 'route_webhooks_check']);
          $this->botoscope->allrest->add_rest_route('shopify_sync/flush', [$this, 'route_flush_cache']);

          $this->botoscope->allrest->add_rest_route('shopify_sync/webhooks_list', function () {
          return $this->shopify_get('webhooks.json');
          });
         * 
         */
        add_action('wp_ajax_botoscope_shopify_register_webhooks', [$this, 'ajax_register_webhooks']);
        add_action('wp_ajax_botoscope_shopify_run_sync', [$this, 'ajax_run_sync']);
        add_action('wp_ajax_botoscope_shopify_count', [$this, 'ajax_products_count']);
        add_action('wp_ajax_botoscope_shopify_flush', [$this, 'ajax_flush_cache']);
        add_action('wp_ajax_botoscope_shopify_webhooks_check', [$this, 'ajax_webhooks_check']);

        //add_action('woocommerce_checkout_order_created', [$this, 'push_order_to_shopify']);
        //add_action('woocommerce_order_status_processing', [$this, 'on_order_processing']);
        add_action('botoscope_order_created', [$this, 'on_order_created']);

        Botoscope_Hooks::add_action('botoscope_panel_tabs', function ($tabs) {
            $tabs[$this->slug] = esc_html__('Shopify Sync', 'botoscope');
            return $tabs;
        });

        add_action("botoscope_{$this->slug}_tab_icon", function () {
            return 'star';
        });

        add_action('admin_enqueue_scripts', function () {
            if (isset($_GET['page']) && sanitize_key($_GET['page']) === 'botoscope') {
                wp_enqueue_style(
                        "botoscope-{$this->slug}",
                        BOTOSCOPE_LINK . 'exts/shopify_sync/assets/css/app.css',
                        [],
                        BOTOSCOPE_VERSION
                );
            }
        });

        add_filter('botoscope_lang', function ($lang) {
            $lang['shopify_sync_ch_webhooks'] = esc_html__('Checking webhooks...', 'botoscope');
            $lang['shopify_sync_reg_webhooks'] = esc_html__('Registering webhooks...', 'botoscope');
            $lang['shopify_sync_get_prod_count'] = esc_html__('Getting product count...', 'botoscope');
            /* translators: %s: products count */
            $lang['shopify_sync_found_products'] = esc_html__('Found %s products. Syncing...', 'botoscope');
            /* translators: 1: synced count, 2: total count */
            $lang['shopify_sync_synced_of'] = esc_html__('Synced %1$s of %2$s...', 'botoscope');
            /* translators: 1: synced count, 2: total count */
            $lang['shopify_sync_done'] = esc_html__('Done! Synced %1$s of %2$s products.', 'botoscope');
            return $lang;
        });
    }

    public function ajax_register_webhooks() {
        if (!$this->botoscope->is_ajax_request_valid()) {
            wp_die();
        }

        $topics = ['products/create', 'products/update', 'products/delete', 'orders/create', 'orders/updated'];
        $results = [];

        foreach ($topics as $topic) {
            $res = $this->shopify_post('webhooks.json', [
                'webhook' => [
                    'topic' => $topic,
                    'address' => rest_url('botoscope/v3/shopify_sync/webhook'),
                    'format' => 'json',
                ]
            ]);

            if (isset($res['webhook']['id'])) {
                $results[$topic] = 'ok';
            } elseif (isset($res['errors']['address']) &&
                    str_contains(implode('', (array) $res['errors']['address']), 'already been taken')) {
                $results[$topic] = 'ok'; //already registered - we consider it a success
            } else {
                $results[$topic] = $res['errors'] ?? 'error';
            }
        }

        wp_send_json_success($results);
        wp_die();
    }

    public function ajax_run_sync() {
        if (!$this->botoscope->is_ajax_request_valid()) {
            wp_die();
        }

        $offset = intval($_REQUEST['offset'] ?? 0);
        $limit = intval($_REQUEST['limit'] ?? 5);
        $result = $this->sync_products($offset, $limit);
        wp_send_json_success($result);
        wp_die();
    }

    public function ajax_products_count() {
        if (!$this->botoscope->is_ajax_request_valid()) {
            wp_die();
        }

        $response = $this->shopify_get('products/count.json', ['status' => 'active']);
        if (is_wp_error($response)) {
            wp_send_json_error(['error' => $response->get_error_message()]);
        }
        wp_send_json_success(['count' => intval($response['count'] ?? 0)]);
        wp_die();
    }

    public function ajax_flush_cache() {
        if (!$this->botoscope->is_ajax_request_valid()) {
            wp_die();
        }

        $total = intval($_REQUEST['total_synced'] ?? 0);
        if ($total > 0) {
            update_option('botoscope_shopify_last_sync_count', $total);
            update_option('botoscope_shopify_last_sync', time());
        }

        $this->flush_cache();
        wp_send_json_success(['flushed' => 1]);
        wp_die();
    }

    public function ajax_webhooks_check() {
        if (!$this->botoscope->is_ajax_request_valid()) {
            wp_die();
        }

        $response = $this->shopify_get('webhooks.json');
        if (is_wp_error($response)) {
            wp_send_json_success(['registered' => false]);
            wp_die();
        }
        $topics = array_column($response['webhooks'] ?? [], 'topic');
        $required = ['products/create', 'products/update', 'products/delete', 'orders/create', 'orders/updated'];
        wp_send_json_success(['registered' => count(array_intersect($required, $topics)) === count($required)]);
        wp_die();
    }

    private function get_settings() {
        return $this->botoscope->extensions->get_settings($this->slug);
    }

    // -------------------------------------------------------------------------
    // Get a valid Shopify access token.
    // Exchanges Client ID + Secret for a token via OAuth client credentials grant.
    // Caches the token in wp_options for 23 hours (token lives 24h).
    // -------------------------------------------------------------------------
    private function get_access_token() {
        $s = $this->get_settings();

        // If a static API access token is configured (legacy custom app) — use it directly.
        if (!empty($s['api_access_token'])) {
            return sanitize_text_field($s['api_access_token']);
        }

        //Otherwise fall back to client credentials grant (OAuth app via Partner Dashboard).
        //Return cached token if still valid
        $cached = get_option($this->token_cache_key, null);
        if ($cached && is_array($cached) && !empty($cached['token']) && $cached['expires_at'] > time()) {
            return $cached['token'];
        }

        if (empty($s['shopify_client_id']) || empty($s['shopify_client_secret']) || empty($s['shopify_store_url'])) {
            return new WP_Error('shopify_no_credentials', 'Shopify Client ID, Secret or Store URL not configured');
        }

        $url = "https://{$s['shopify_store_url']}/admin/oauth/access_token";

        $response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode([
                'client_id' => $s['shopify_client_id'],
                'client_secret' => $s['shopify_client_secret'],
                'grant_type' => 'client_credentials',
            ]),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['access_token'])) {
            $error = $body['error_description'] ?? $body['error'] ?? 'Unknown error';
            return new WP_Error('shopify_token_error', "Failed to get Shopify token: {$error}");
        }

        // Cache for 23 hours (token lives 24h, give 1h buffer)
        update_option($this->token_cache_key, [
            'token' => $body['access_token'],
            'expires_at' => time() + (23 * 3600),
                ], false);

        return $body['access_token'];
    }

    // -------------------------------------------------------------------------
    // REST GET /botoscope/v3/shopify_sync
    // -------------------------------------------------------------------------
    public function route_status(WP_REST_Request $request) {
        $s = $this->get_settings();
        return [
            'connected' => !empty($s['shopify_client_id']) && !empty($s['shopify_client_secret']) && !empty($s['shopify_store_url']),
            'sync_interval' => intval($s['sync_interval'] ?? 1),
            'last_sync_at' => get_option('botoscope_shopify_last_sync', 0),
            'last_sync_count' => get_option('botoscope_shopify_last_sync_count', 0),
        ];
    }

    // -------------------------------------------------------------------------
    // REST GET /botoscope/v3/shopify_sync/run
    // -------------------------------------------------------------------------
    public function route_run_sync(WP_REST_Request $request) {
        $offset = intval($request->get_param('offset') ?? 0);
        $limit = intval($request->get_param('limit') ?? 0);

        $result = $this->sync_products($offset, $limit);
        update_option('botoscope_shopify_last_sync', time());
        update_option('botoscope_shopify_last_sync_count', $result['synced'] ?? 0);

        return $result;
    }

    // -------------------------------------------------------------------------
    // Pull active products from Shopify, upsert into WooCommerce.
    // -------------------------------------------------------------------------
    public function sync_products($offset = 0, $limit = 0) {
        $s = $this->get_settings();

        if (empty($s['shopify_client_id']) || empty($s['shopify_client_secret']) || empty($s['shopify_store_url'])) {
            return ['error' => 'Shopify credentials not configured'];
        }

        $synced = 0;
        $page_info = null;
        $processed = 0; // Total products seen so far across all pages

        do {
            $params = $page_info ? ['limit' => 50, 'page_info' => $page_info] : ['limit' => 50, 'status' => 'active'];

            $response = $this->shopify_get('products.json', $params);

            if (is_wp_error($response)) {
                $this->errors[] = $response->get_error_message();
                break;
            }

            $products = $response['products'] ?? [];

            foreach ($products as $sp) {
                // Skip products before offset
                if ($offset > 0 && $processed < $offset) {
                    $processed++;
                    continue;
                }

                // Stop if we've processed enough
                if ($limit > 0 && ($processed - $offset) >= $limit) {
                    return ['synced' => $synced, 'errors' => $this->errors];
                }

                if ($this->upsert_wc_product($sp)) {
                    $synced++;
                } else {
                    $this->errors[] = 'Failed to sync: ' . ($sp['title'] ?? 'unknown');
                }

                $processed++;
            }

            $page_info = $response['_next_page_info'] ?? null;
        } while ($page_info);

        return ['synced' => $synced, 'errors' => $this->errors];
    }

    // -------------------------------------------------------------------------
    // REST GET /botoscope/v3/shopify_sync/product/{shopify_id}
    // -------------------------------------------------------------------------
    public function route_sync_single(WP_REST_Request $request) {
        $shopify_id = intval($request->get_param('shopify_id'));
        $result = $this->sync_single_product($shopify_id);

        if (is_wp_error($result)) {
            $code = $result->get_error_code() === 'not_found' ? 404 : 500;
            return new WP_REST_Response(['error' => $result->get_error_message()], $code);
        }

        $this->botoscope->reset_cache('taxonomies');
        $this->botoscope->reset_cache('product_attributes');
        $this->botoscope->do_command(-1, 'update_product_cache', ['product_id' => $result['wc_id']]);

        return $result;
    }

    public function route_webhook(WP_REST_Request $request) {
        // HMAC verification
        $hmac_header = $request->get_header('x-shopify-hmac-sha256');
        $raw_body = $request->get_body();
        $s = $this->get_settings();
        $expected = base64_encode(hash_hmac('sha256', $raw_body, $s['shopify_client_secret'], true));

        if (!hash_equals($expected, $hmac_header ?? '')) {
            return new WP_REST_Response(['error' => 'Invalid signature'], 403);
        }

        $topic = $request->get_header('x-shopify-topic');
        $body = $request->get_json_params();
        $shopify_id = intval($body['id'] ?? 0);

        if (!$shopify_id) {
            return new WP_REST_Response(['error' => 'No product ID'], 400);
        }

        if ($topic === 'products/delete') {
            // When deleting, simply find the WC product and delete it.
            $wc_id = $this->find_wc_product_by_shopify_id($shopify_id);
            if ($wc_id) {
                wp_delete_post($wc_id, true);
            }
            return ['deleted' => 1, 'shopify_id' => $shopify_id];
        }

        if ($topic === 'orders/create') {
            $this->import_shopify_order($body);
            return ['imported' => 1, 'shopify_order_id' => $shopify_id];
        }

        if ($topic === 'orders/updated') {
            $wc_order_id = $this->find_wc_order_by_shopify_id($shopify_id);
            if ($wc_order_id) {
                $order = wc_get_order($wc_order_id);
                $new_status = $this->map_shopify_status_to_wc($body);
                if ($new_status) {
                    $order->update_status($new_status, 'Updated from Shopify');
                }
            }
            return ['updated' => 1];
        }

        $wc_id = $this->upsert_wc_product($body);

        if (!$wc_id) {
            return new WP_REST_Response(['error' => 'Sync failed'], 500);
        }

        $this->botoscope->reset_cache('taxonomies');
        $this->botoscope->reset_cache('product_attributes');
        $this->botoscope->do_command(-1, 'update_product_cache', ['product_id' => $wc_id]);

        return ['synced' => 1, 'wc_id' => $wc_id, 'shopify_id' => $shopify_id];
    }

    public function route_webhooks_check(WP_REST_Request $request) {
        $response = $this->shopify_get('webhooks.json');
        if (is_wp_error($response)) {
            return new WP_REST_Response(['registered' => false], 200);
        }
        $topics = array_column($response['webhooks'] ?? [], 'topic');
        $required = ['products/create', 'products/update', 'products/delete', 'orders/create', 'orders/updated'];
        $all_registered = count(array_intersect($required, $topics)) === count($required);
        return ['registered' => $all_registered];
    }

    public function route_products_count(WP_REST_Request $request) {
        $response = $this->shopify_get('products/count.json', ['status' => 'active']);
        if (is_wp_error($response)) {
            return new WP_REST_Response(['error' => $response->get_error_message()], 500);
        }
        return ['count' => intval($response['count'] ?? 0)];
    }

    public function route_flush_cache(WP_REST_Request $request) {
        $this->flush_cache();
        return ['flushed' => 1];
    }

    public function flush_cache() {
        $this->botoscope->reset_cache('taxonomies');
        $this->botoscope->reset_cache('product_attributes');
        $this->botoscope->reset_cache('products');
    }

    // -------------------------------------------------------------------------
    // Create or update a WooCommerce product from a Shopify product object.
    // -------------------------------------------------------------------------
    private function upsert_wc_product($sp) {
        $shopify_id = intval($sp['id']);
        $is_variable = $this->is_shopify_variable($sp);
        $existing_id = $this->find_wc_product_by_shopify_id($shopify_id);
        $s = $this->get_settings();

        if ($existing_id) {
            $product = wc_get_product($existing_id);
            if ($product && (($is_variable && !$product->is_type('variable')) || (!$is_variable && $product->is_type('variable')))) {
                $product->delete(true);
                $product = null;
            }
        }

        if (!$existing_id || !$product) {
            $product = $is_variable ? new WC_Product_Variable() : new WC_Product_Simple();
        }

        $product->set_name(sanitize_text_field($sp['title'] ?? ''));
        $product->set_description(wp_kses_post($sp['body_html'] ?? ''));
        $product->set_status($sp['status'] === 'active' ? 'publish' : 'draft');
        $product->set_catalog_visibility('visible');

        if (!empty($sp['tags'])) {
            $tag_ids = [];
            foreach (array_map('trim', explode(',', $sp['tags'])) as $tag_name) {
                $term = term_exists($tag_name, 'product_tag');

                if (!$term) {
                    $term = wp_insert_term($tag_name, 'product_tag');
                }

                if (!is_wp_error($term)) {
                    $tid = intval($term['term_id'] ?? $term);
                    update_term_meta($tid, 'is_active', 1);
                    $tag_ids[] = $tid;
                }
            }
            $product->set_tag_ids($tag_ids);
        }

        $product->update_meta_data('_botoscope_shopify_product_id', $shopify_id);
        $product->update_meta_data('_botoscope_is_hidden', 0);

        // Save public Shopify product URL for catalog-only / "Buy on Shopify" button in the bot
        if (!empty($sp['handle']) && !empty($s['shopify_store_url'])) {
            $shopify_product_url = 'https://' . rtrim($s['shopify_store_url'] ?? '', '/') . '/products/' . $sp['handle'];
            $product->update_meta_data('_botoscope_shopify_product_url', esc_url_raw($shopify_product_url));
        }

        if (!empty($sp['vendor'])) {
            $product->update_meta_data('_botoscope_shopify_vendor', sanitize_text_field($sp['vendor']));
        }

        // Create WC category from Shopify product_type field
        if (!empty($sp['product_type'])) {
            $cat_name = sanitize_text_field($sp['product_type']);
            $term = term_exists($cat_name, 'product_cat');

            if (!$term) {
                $term = wp_insert_term($cat_name, 'product_cat');
            }

            if (!is_wp_error($term) && is_array($term)) {
                update_term_meta(intval($term['term_id']), 'is_active', 1);
                $product->set_category_ids([intval($term['term_id'])]);
            }
        }

        if ($is_variable) {
            $this->set_variable_data($product, $sp);
        } else {
            $v = $sp['variants'][0] ?? [];
            $product->set_regular_price($v['price'] ?? '0');
            try {
                $product->set_sku(sanitize_text_field($v['sku'] ?? ''));
            } catch (WC_Data_Exception $e) {
                $this->errors[] = 'SKU conflict: "' . sanitize_text_field($v['sku'] ?? '') . '" — ' . $e->getMessage();
            }

            if (isset($v['inventory_quantity'])) {
                $product->set_manage_stock(true);
                $product->set_stock_quantity(intval($v['inventory_quantity']));
                $product->set_stock_status($v['inventory_quantity'] > 0 ? 'instock' : 'outofstock');
            }
        }

        $wc_id = $product->save();

        if (!$wc_id) {
            return false;
        }

        $this->sync_images($wc_id, $sp['images'] ?? []);

        return $wc_id;
    }

    // -------------------------------------------------------------------------
    // Map Shopify options → WC attributes, Shopify variants → WC variations.
    // -------------------------------------------------------------------------
    private function set_variable_data(WC_Product_Variable $product, $sp) {
        $attributes = [];

        foreach ($sp['options'] as $option) {
            $attr_name = sanitize_text_field($option['name']);
            $attr_slug = 'pa_' . wc_sanitize_taxonomy_name($attr_name);
            $attr_id = $this->ensure_attribute($attr_slug, $attr_name);

            if (!$attr_id) {
                continue; // Skip if attribute could not be created
            }

            $term_ids = [];
            foreach (array_map('sanitize_text_field', $option['values']) as $val) {
                if (empty($val)) {
                    continue;
                }
                $existing_term = get_term_by('name', $val, $attr_slug);
                if ($existing_term) {
                    $term_ids[] = $existing_term->term_id;
                    update_term_meta($existing_term->term_id, 'is_active', 1);
                } else {
                    $new_term = wp_insert_term($val, $attr_slug);
                    if (is_wp_error($new_term)) {
                        //error_log("SHOPIFY SYNC: wp_insert_term failed — val={$val}, taxonomy={$attr_slug}, error=" . $new_term->get_error_message());
                    } else {
                        $term_ids[] = intval($new_term['term_id']);
                        update_term_meta(intval($new_term['term_id']), 'is_active', 1);
                    }
                }
            }

            if (empty($term_ids)) {
                continue;
            }

            $wc_attr = new WC_Product_Attribute();
            $wc_attr->set_id($attr_id);
            $wc_attr->set_name($attr_slug);
            $wc_attr->set_options($term_ids);
            $wc_attr->set_visible(true);
            $wc_attr->set_variation(true);
            $attributes[] = $wc_attr;
        }

        $product->set_attributes($attributes);
        $product->save();

        // Remove WC variations that no longer exist in Shopify
        $shopify_variant_ids = array_map(fn($v) => intval($v['id']), $sp['variants']);
        foreach ($product->get_children() as $var_post_id) {
            $shopify_vid = get_post_meta($var_post_id, '_botoscope_shopify_variant_id', true);
            if ($shopify_vid && !in_array(intval($shopify_vid), $shopify_variant_ids)) {
                wp_delete_post($var_post_id, true);
            }
        }

        foreach ($sp['variants'] as $variant) {
            $this->upsert_variation($product->get_id(), $variant, $sp['options']);
        }

        WC_Product_Variable::sync_stock_status($product->get_id());
        $product->get_data_store()->read($product);
    }

    // -------------------------------------------------------------------------
    // Create or update a single WC variation from a Shopify variant.
    // -------------------------------------------------------------------------
    private function upsert_variation($wc_product_id, $variant, $options) {
        $shopify_vid = intval($variant['id']);
        $existing_id = $this->find_wc_variation_by_shopify_id($shopify_vid);

        $variation = $existing_id ? wc_get_product($existing_id) : new WC_Product_Variation();
        if (!$variation) {
            return false;
        }

        if (!$existing_id) {
            $variation->set_parent_id($wc_product_id);
        }

        $variation->set_regular_price($variant['price'] ?? '0');
        try {
            $variation->set_sku(sanitize_text_field($variant['sku'] ?? ''));
        } catch (WC_Data_Exception $e) {
            $this->errors[] = 'SKU conflict: "' . sanitize_text_field($variant['sku'] ?? '') . '" — ' . $e->getMessage();
        }
        $variation->update_meta_data('_botoscope_shopify_variant_id', $shopify_vid);

        if (isset($variant['inventory_quantity'])) {
            $variation->set_manage_stock(true);
            $variation->set_stock_quantity(intval($variant['inventory_quantity']));
            $variation->set_stock_status($variant['inventory_quantity'] > 0 ? 'instock' : 'outofstock');
        }

        $attrs = [];
        foreach ($options as $i => $option) {
            $val = sanitize_text_field($variant['option' . ($i + 1)] ?? '');
            if (empty($val)) {
                continue;
            }

            $attr_slug = 'pa_' . wc_sanitize_taxonomy_name($option['name']);

            $term = get_term_by('name', $val, $attr_slug);
            if ($term) {
                $attrs[$attr_slug] = $term->slug;
            } else {
                $slug_try = mb_strtolower($val, 'UTF-8');
                $term2 = get_term_by('slug', $slug_try, $attr_slug);
                if ($term2) {
                    $attrs[$attr_slug] = $term2->slug;
                } else {
                    $attrs[$attr_slug] = $slug_try;
                }
            }
        }

        // Save variation (price, status etc.) — WITHOUT set_attributes()
        $variation_id = $variation->save();

        // Write attributes directly — same approach as botoscope_products_set_variation_combination
        // sanitize_title() matches how WC reads in wc_get_product_variation_attributes()
        foreach ($attrs as $taxonomy => $slug) {
            update_post_meta($variation_id, 'attribute_' . sanitize_title($taxonomy), wp_slash($slug));
        }

        clean_post_cache($variation_id);
        wc_delete_product_transients($variation->get_parent_id());

        return $variation_id;
    }

    // -------------------------------------------------------------------------
    // Download Shopify images to WP media library.
    // -------------------------------------------------------------------------
    private function sync_images($wc_id, $images) {
        if (!function_exists('media_sideload_image')) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        // Always clear existing assignments first — even if Shopify sent no images
        delete_post_thumbnail($wc_id);
        update_post_meta($wc_id, '_product_image_gallery', '');

        if (empty($images)) {
            return;
        }

        $gallery = [];

        foreach ($images as $img) {
            $src = $img['src'] ?? '';
            $sid = intval($img['id']);

            if (empty($src)) {
                continue;
            }

            $att_id = $this->find_attachment_by_shopify_image_id($sid);

            if (!$att_id) {
                $clean_src = strtok($src, '?') . '?width=800';
                $att_id = media_sideload_image($clean_src, $wc_id, null, 'id');
                if (is_wp_error($att_id)) {
                    continue;
                }
                update_post_meta($att_id, '_botoscope_shopify_image_id', $sid);
            }

            // All images go into gallery
            $gallery[] = $att_id;
        }

        if (!empty($gallery)) {
            // First image becomes thumbnail
            set_post_thumbnail($wc_id, $gallery[0]);
            // All images in gallery (including first)
            update_post_meta($wc_id, '_product_image_gallery', implode(',', $gallery));
        }
    }

    // -------------------------------------------------------------------------
    // Push bot-created WC order to Shopify.
    // -------------------------------------------------------------------------
    public function push_order_to_shopify($order) {
        $s = $this->get_settings();

        if (empty($s['shopify_client_id']) || empty($s['shopify_store_url'])) {
            return;
        }

        // Skip orders that contain no Shopify products
        if (!$this->order_has_shopify_products($order)) {
            return;
        }

        $line_items = [];
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $vid = $product ? get_post_meta($product->get_id(), '_botoscope_shopify_variant_id', true) : null;
            $qty = max(1, $item->get_quantity());

            $entry = [
                'title' => $item->get_name(),
                'quantity' => $qty,
                'price' => number_format($item->get_subtotal() / $qty, 2, '.', ''),
            ];
            if ($vid) {
                $entry['variant_id'] = intval($vid);
            }
            $line_items[] = $entry;
        }

        //+++

        $chat_id = intval($order->get_meta('_botoscope_chat_id'));
        $email = $order->get_billing_email() ?: "{$chat_id}@telegram.botoscope";
        $first_name = $order->get_billing_first_name() ?: 'Botoscope';
        $last_name = $order->get_billing_last_name() ?: "User {$chat_id}";

        $shipping_lines = [];
        foreach ($order->get_items('shipping') as $shipping_item) {
            $shipping_lines[] = [
                'title' => $shipping_item->get_name() ?: 'Shipping',
                'price' => number_format(floatval($shipping_item->get_total()), 2, '.', ''),
                'code' => $shipping_item->get_method_id() ?: 'custom',
            ];
        }

        $payload = ['order' => [
                'line_items' => $line_items,
                'shipping_lines' => $shipping_lines,
                'financial_status' => $this->get_shopify_financial_status($order),
                'fulfillment_status' => null,
                'email' => $email,
                'customer' => [
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'email' => $email,
                ],
                'note' => "Order via Botoscope. WC Order #{$order->get_id()}"
                . "\nPayment: " . $order->get_payment_method_title()
                . ($order->get_meta('_botoscope_shipping_address') ? "\nShipping: " . $order->get_meta('_botoscope_shipping_address') : '')
                . ($order->get_customer_note() ? "\nNote: " . $order->get_customer_note() : ''),
            /*
              'shipping_address' => [
              'first_name' => $first_name,
              'last_name' => $last_name,
              'address1' => $order->get_meta('_botoscope_shipping_address') ?: '',
              'city' => $order->get_billing_city(),
              'country' => $order->get_billing_country() ?: WC()->countries->get_base_country(),//!! we do not have it, so total block is ignored
              'zip' => $order->get_billing_postcode() ?: '',
              'phone' => $order->get_billing_phone() ?: '',
              ],
             * 
             */
        ]];

        $response = $this->shopify_post('orders.json', $payload);

        if (!is_wp_error($response) && !empty($response['order']['id'])) {
            $order->update_meta_data('_botoscope_shopify_order_id', intval($response['order']['id']));
            $order->save_meta_data();
        }
    }

    // -------------------------------------------------------------------------
    // Shopify API: GET
    // -------------------------------------------------------------------------
    private function shopify_get($endpoint, $params = []) {
        $token = $this->get_access_token();
        if (is_wp_error($token)) {
            return $token;
        }

        $s = $this->get_settings();
        $url = "https://{$s['shopify_store_url']}/admin/api/{$this->shopify_api_ver}/{$endpoint}";

        if ($params) {
            $url .= '?' . http_build_query($params);
        }

        $response = wp_remote_get($url, [
            'headers' => [
                'X-Shopify-Access-Token' => $token,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        $link = wp_remote_retrieve_header($response, 'link');
        if ($link && preg_match('/<[^>]+page_info=([^&>]+)[^>]*>;\s*rel="next"/', $link, $m)) {
            $body['_next_page_info'] = $m[1];
        }

        return $body;
    }

    // -------------------------------------------------------------------------
    // Shopify API: POST
    // -------------------------------------------------------------------------
    private function shopify_post($endpoint, $data) {
        $token = $this->get_access_token();
        if (is_wp_error($token)) {
            return $token;
        }

        $s = $this->get_settings();
        $url = "https://{$s['shopify_store_url']}/admin/api/{$this->shopify_api_ver}/{$endpoint}";

        $response = wp_remote_post($url, [
            'headers' => [
                'X-Shopify-Access-Token' => $token,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($data),
            'timeout' => 30,
        ]);

        return is_wp_error($response) ? $response : json_decode(wp_remote_retrieve_body($response), true);
    }

    // -------------------------------------------------------------------------
    // DB lookup helpers
    // -------------------------------------------------------------------------
    private function find_wc_product_by_shopify_id($id) {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
                                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_botoscope_shopify_product_id' "
                                . "AND meta_value=%d LIMIT 1", $id
                        ));
    }

    private function find_wc_variation_by_shopify_id($id) {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
                                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_botoscope_shopify_variant_id' "
                                . "AND meta_value=%d LIMIT 1", $id
                        ));
    }

    private function find_attachment_by_shopify_image_id($id) {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
                                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_botoscope_shopify_image_id' "
                                . "AND meta_value=%d LIMIT 1", $id
                        ));
    }

    private function is_shopify_variable($sp) {
        $opts = $sp['options'] ?? [];
        return !(count($opts) === 1 && $opts[0]['name'] === 'Title' && $opts[0]['values'] === ['Default Title']);
    }

    private function ensure_attribute($attr_slug, $attr_name) {
        global $wpdb;

        // Check per-request cache first — prevents duplicates within one sync run
        if (isset(self::$attr_cache[$attr_slug])) {
            return self::$attr_cache[$attr_slug];
        }

        $slug = str_replace('pa_', '', $attr_slug);

        // Read from DB directly — no cache issues
        $attr_id = (int) $wpdb->get_var($wpdb->prepare(
                                "SELECT attribute_id FROM {$wpdb->prefix}woocommerce_attribute_taxonomies
         WHERE attribute_name = %s LIMIT 1",
                                $slug
                        ));

        if (!$attr_id) {
            $result = wc_create_attribute([
                'name' => $attr_name,
                'slug' => $slug,
                'type' => 'select',
                'order_by' => 'menu_order',
                'has_archives' => false,
            ]);

            if (is_wp_error($result)) {
                // Creation failed — try to find by label (attribute_label column)
                $attr_id = (int) $wpdb->get_var($wpdb->prepare(
                                        "SELECT attribute_id FROM {$wpdb->prefix}woocommerce_attribute_taxonomies
                 WHERE attribute_label = %s LIMIT 1",
                                        $attr_name
                                ));
            } else {
                $attr_id = (int) $result;
            }

            delete_transient('wc_attribute_taxonomies');
            WC_Cache_Helper::invalidate_cache_group('woocommerce-attributes');

            //+++
            // Auto-register attribute in Botoscope product_attributes
            if ($attr_id && isset($this->botoscope->product_attributes)) {
                $pa = $this->botoscope->product_attributes;
                global $wpdb;

                $exists = $wpdb->get_var($wpdb->prepare(
                                "SELECT id FROM {$wpdb->prefix}botoscope_product_attributes WHERE taxonomy_slug = %s LIMIT 1",
                                $attr_slug
                        ));

                if (!$exists) {
                    try {
                        $row = $pa->create();
                        $id = $row['id'];

                        $pa->update($id, 'title', $attr_name);
                        $pa->update($id, 'taxonomy_slug', $attr_slug);
                        $pa->update($id, 'display_as', 'button');
                        $pa->update($id, 'cols_in_row', 2);
                        $pa->update($id, 'icon', '🎨');
                        $pa->update($id, 'is_active', 1);
                    } catch (\Throwable $e) {
                        // Silently skip — attribute will be registered manually by user if needed
                    }
                }
            }
        }

        // Register taxonomy in current request so term functions work
        if (!taxonomy_exists($attr_slug)) {
            register_taxonomy($attr_slug, 'product', [
                'hierarchical' => false,
                'show_ui' => false,
                'query_var' => true,
                'rewrite' => false,
            ]);
        }

        // Store in per-request cache
        self::$attr_cache[$attr_slug] = $attr_id;

        return $attr_id;
    }

    private function sync_single_product($shopify_id) {
        $response = $this->shopify_get("products/{$shopify_id}.json");

        if (is_wp_error($response)) {
            return $response;
        }

        $sp = $response['product'] ?? null;

        if (!$sp) {
            return new WP_Error('not_found', 'Product not found in Shopify');
        }

        $wc_id = $this->upsert_wc_product($sp);

        if (!$wc_id) {
            return new WP_Error('sync_failed', 'Failed to sync: ' . ($sp['title'] ?? 'unknown'));
        }

        return ['synced' => 1, 'wc_id' => $wc_id, 'shopify_id' => $shopify_id];
    }

    private function import_shopify_order($so) {
        //If this order already exists in the WC (we pushed it there ourselves) – do not duplicate it
        $existing = $this->find_wc_order_by_shopify_id(intval($so['id']));
        if ($existing) {
            return;
        }

        self::$importing_from_shopify = true;

        //+++

        $order = wc_create_order();

        foreach ($so['line_items'] as $item) {
            $variant_id = $item['variant_id'] ?? null;
            $wc_product_id = null;

            if ($variant_id) {
                $wc_product_id = $this->find_wc_variation_by_shopify_id(intval($variant_id));
            }

            if (!$wc_product_id && !empty($item['product_id'])) {
                $wc_product_id = $this->find_wc_product_by_shopify_id(intval($item['product_id']));
            }

            if ($wc_product_id) {
                $product = wc_get_product($wc_product_id);
                $order->add_product($product, intval($item['quantity']));
            } else {
                // Product not found in WC - adding as a custom line item
                $order_item = new WC_Order_Item_Product();
                $order_item->set_name(sanitize_text_field($item['title'] ?? ''));
                $order_item->set_quantity(intval($item['quantity']));
                $order_item->set_subtotal(floatval($item['price']) * intval($item['quantity']));
                $order_item->set_total(floatval($item['price']) * intval($item['quantity']));
                $order->add_item($order_item);
            }
        }

        // Billing address
        $billing = $so['billing_address'] ?? $so['shipping_address'] ?? [];
        $order->set_billing_first_name(sanitize_text_field($billing['first_name'] ?? ''));
        $order->set_billing_last_name(sanitize_text_field($billing['last_name'] ?? ''));
        $order->set_billing_email(sanitize_email($so['email'] ?? ''));
        $order->set_billing_phone(sanitize_text_field($billing['phone'] ?? ''));
        $order->set_billing_address_1(sanitize_text_field($billing['address1'] ?? ''));
        $order->set_billing_city(sanitize_text_field($billing['city'] ?? ''));
        $order->set_billing_country(sanitize_text_field($billing['country_code'] ?? ''));
        $order->set_billing_postcode(sanitize_text_field($billing['zip'] ?? ''));

        $order->set_status('wc-completed');
        $order->update_meta_data('_botoscope_shopify_order_id', intval($so['id']));
        $order->update_meta_data('_botoscope_shopify_order_source', 'shopify');
        $order->calculate_totals();
        $order->save();

        self::$importing_from_shopify = false;
    }

    public function on_order_created($order) {
        if (self::$importing_from_shopify) {
            return;
        }

        $this->push_order_to_shopify($order);
    }

    private function get_shopify_financial_status($order) {
        $manual_methods = ['swift', 'card', 'payforme', 'gift'];
        $method = $order->get_payment_method();
        return in_array($method, $manual_methods, true) ? 'pending' : 'paid';
    }

    private function find_wc_order_by_shopify_id($id) {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
                                "SELECT post_id FROM {$wpdb->postmeta} 
         WHERE meta_key='_botoscope_shopify_order_id' AND meta_value=%d LIMIT 1", $id
                        ));
    }

    private function map_shopify_status_to_wc($body) {
        $financial = $body['financial_status'] ?? '';
        $fulfillment = $body['fulfillment_status'] ?? '';
        $cancelled = !empty($body['cancelled_at']);

        if ($cancelled) {
            return 'cancelled';
        }

        if ($fulfillment === 'fulfilled') {
            return 'completed';
        }

        if ($financial === 'paid') {
            return 'processing';
        }

        if ($financial === 'refunded' || $financial === 'voided') {
            return 'refunded';
        }

        return null; // Don't change the status if nothing matches
    }

    private function order_has_shopify_products($order) {
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) {
                continue;
            }
            // Check variation first, then parent product
            $product_id = $product->get_id();
            if (get_post_meta($product_id, '_botoscope_shopify_variant_id', true)) {
                return true;
            }
            // For variable products the parent ID is different
            $parent_id = $product->get_parent_id() ?: $product_id;
            if (get_post_meta($parent_id, '_botoscope_shopify_product_id', true)) {
                return true;
            }
        }
        return false;
    }

    public function draw_content($counter) {
        $s = $this->get_settings();
        $is_configured = !empty($s['shopify_store_url']) && (
                !empty($s['api_access_token']) ||
                (!empty($s['shopify_client_id']) && !empty($s['shopify_client_secret']))
                );

        $last_sync = get_option('botoscope_shopify_last_sync', 0);
        $last_count = get_option('botoscope_shopify_last_sync_count', 0);
        $last_sync_str = $last_sync ? wp_date('d.m.Y H:i', $last_sync) : '';
        ?>
        <section id="botoscope-<?php echo esc_attr($this->slug) ?>" <?php if ($counter === 0): ?>class="content-current"<?php endif; ?>>


            <?php if (!$is_configured): ?>
                <div class="botoscope-notice botoscope-notice-warning">
                    ⚙️ <?php esc_html_e('Please configure Shopify credentials in the Settings tab to enable sync.', 'botoscope') ?>
                </div>
            <?php else: ?>

                <div id="botoscope_shopify_sync_card">
                    <div style="flex:1; min-width:160px;">
                        <div class="botoscope_shopify_sync_card_line">
                            <?php esc_html_e('Last sync', 'botoscope') ?>
                        </div>
                        <div id="botoscope_shopify_last_sync_date">
                            <?php echo $last_sync_str ? esc_html($last_sync_str) : '<span>' . esc_html__('Never synced', 'botoscope') . '</span>'; ?>
                        </div>
                    </div>
                    <div style="flex:1; min-width:100px;">
                        <div class="botoscope_shopify_sync_card_line">
                            <?php esc_html_e('Products', 'botoscope') ?>
                        </div>
                        <div id="botoscope_shopify_last_sync_count">
                            <?php echo $last_sync ? esc_html($last_count) : '<span>—</span>'; ?>
                        </div>
                    </div>
                    <button type="button" id="botoscope_shopify_sync_all" class="botoscope-button botoscope-button-small" style="white-space:nowrap;">
                        🔄 <?php esc_html_e('Sync Products', 'botoscope') ?>
                    </button>
                </div>

                <div id="botoscope_shopify_sync_progress">
                    <div>
                        <div id="botoscope_shopify_sync_bar"></div>
                    </div>
                    <div id="botoscope_shopify_sync_status"></div>
                </div>
            <?php endif; ?>

        </section>
        <?php
    }
}
