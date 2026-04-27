<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

include_once 'rest.php';

//20-04-2026
final class BOTOSCOPE_REST_ORDERS extends BOTOSCOPE_REST {

    public $botoscope = null;

    public function __construct($botoscope) {
        $this->botoscope = $botoscope;

        add_action('woocommerce_order_status_processing', function ($order_id) {
            $order = wc_get_order($order_id);
            if ($order && $order->get_status() !== 'completed') {

                $allowed_types = array(
                    'botoscope_simple_virtual_downloadable',
                    'botoscope_simple_media_casting',
                );

                $all_items_allowed = true;

                foreach ($order->get_items('line_item') as $item) {
                    $product = $item->get_product();

                    if (!$product) {
                        $all_items_allowed = false;
                        break;
                    }

                    if (!in_array($product->get_type(), $allowed_types, true)) {
                        $all_items_allowed = false;
                        break;
                    }
                }

                if ($all_items_allowed) {
                    $order->update_status('completed', 'Automatic translation from Processing');
                }
            }
        });

        add_action('woocommerce_updated', function () {
            global $wpdb;
            $mysql_version = $wpdb->db_version();
            //fix 17-12-2025 to create order items with emodzi in product title
            if (version_compare($mysql_version, '5.5.3', '>=')) {
                $result = $wpdb->query("ALTER TABLE {$wpdb->prefix}woocommerce_order_items CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

                if ($result !== false) {
                    $wpdb->query("ALTER TABLE {$wpdb->prefix}woocommerce_order_itemmeta CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                }
            }
        });

        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes() {
        register_rest_route('botoscope/v3', '/orders/(?P<chat_id>\d+)', [
            'methods' => 'GET',
            'callback' => array($this, 'get_orders'),
            'permission_callback' => array($this, 'authenticate_request'),
        ]);

        register_rest_route('botoscope/v3', '/single_order/(?P<order_id>\d+)', [
            'methods' => 'GET',
            'callback' => array($this, 'get_single_order'),
            'permission_callback' => array($this, 'authenticate_request'),
        ]);

        register_rest_route('botoscope/v3', '/create_order', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_order'),
            'permission_callback' => array($this, 'authenticate_request'),
        ));

        register_rest_route('botoscope/v3', '/edit_order/(?P<order_id>\d+)', [
            'methods' => 'POST',
            'callback' => array($this, 'edit_order'),
            'permission_callback' => array($this, 'authenticate_request'),
        ]);

        register_rest_route('botoscope/v3', '/set_order_paid', array(
            'methods' => array('GET', 'POST'),
            'callback' => array($this, 'set_order_paid'),
            'permission_callback' => '__return_true'
        ));

        register_rest_route('botoscope/v3', '/cancel_order_paid', array(
            'methods' => 'GET',
            'callback' => array($this, 'cancel_order_paid'),
            'permission_callback' => '__return_true'
        ));

        register_rest_route('botoscope/v3', '/invoice/(?P<order_id>\d+)', [
            'methods' => 'GET',
            'callback' => array($this, 'get_pdf_invoice'),
            'permission_callback' => array($this, 'authenticate_request'),
        ]);
    }

    public function get_pdf_invoice($request) {
        $order_id = intval($request['order_id']);
        if (!$order_id) {
            return new WP_Error('invalid_order', 'Incorrect order ID', ['status' => 400]);
        }
        $order = wc_get_order($order_id);

        if (!$order) {
            return new WP_Error('not_found', 'Order not found', ['status' => 404]);
        }
        if (!function_exists('wcpdf_get_document')) {
            return new WP_Error('plugin_missing', 'PDF plugin not found', ['status' => 500]);
        }
        try {
            $invoice = wcpdf_get_document('invoice', $order);
            if (!$invoice) {
                return new WP_Error('no_invoice', 'Could not create invoice', ['status' => 404]);
            }

            if ($invoice->exists()) {
                $invoice->delete();
            }

            $pdf_path = $invoice->get_filename();
            if ($pdf_path && file_exists($pdf_path)) {
                wp_delete_file($pdf_path);
            }

            delete_transient('wcpdf_invoice_number_' . $order_id);
            delete_transient('wcpdf_document_' . $order_id);

            $invoice = wcpdf_get_document('invoice', $order);
            $invoice->save();

            $pdf_content = $invoice->get_pdf();
            if (file_exists($pdf_content)) {
                global $wp_filesystem;
                if (empty($wp_filesystem)) {
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                    WP_Filesystem();
                }
                $pdf_content = $wp_filesystem->get_contents($pdf_content);
            }

            if (empty($pdf_content) || strlen($pdf_content) < 100) {
                ob_start();
                $invoice->output();
                $pdf_content = ob_get_clean();
            }
            if (empty($pdf_content)) {
                return new WP_Error('empty_pdf', 'PDF content is empty', ['status' => 500]);
            }
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="invoice_' . $order_id . '.pdf"');
            header('Content-Transfer-Encoding: binary');
            header('Accept-Ranges: bytes');
            header('Content-Length: ' . strlen($pdf_content));
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary PDF content, escaping would corrupt the file
            echo $pdf_content;
            exit;
        } catch (Exception $e) {
            return new WP_Error('generation_error', $e->getMessage(), ['status' => 500]);
        }
    }

    //+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

    public function set_order_paid($request) {

        if (!defined('BOTOSCOPE_CLIENT_PASS')) {
            return;
        }

        extract($this->botoscope->allrest->get_request_data(substr(BOTOSCOPE_HELPER::encrypt_value(BOTOSCOPE_CLIENT_PASS, BOTOSCOPE_CLIENT_API_KEY), 0, 32), [
                    'order_id',
                    'chat_id',
                    'gate',
        ])); //32 symbols max encrypted key
        $chat_id = intval($chat_id);
        $order_id = intval($order_id);

        if ($order_id && $chat_id) {
            $order = wc_get_order($order_id);
            if ($order) {

                $order_status = 'processing';

                switch ($gate) {
                    case 'liqpay':
                        if (isset($_REQUEST['data'])) {
                            $decoded_data = json_decode(base64_decode(sanitize_text_field(wp_unslash($_REQUEST['data']))), true);
                            if (sanitize_text_field($decoded_data['status'] ?? '') !== 'success') {
                                $order_status = 'pending';
                            } else {
                                $this->botoscope->do_command($chat_id, 'order_is_done', [
                                    'order_id' => $order_id
                                ]);
                            }
                        }

                        break;

                    case 'paypal':
                        $order->update_meta_data('paymentId', $paymentId);
                        $order->update_meta_data('token', $token);
                        $order->update_meta_data('PayerID', $PayerID);
                        $order->save();

                        $this->botoscope->do_command($chat_id, 'order_is_done', [
                            'order_id' => $order_id
                        ]);

                        break;

                    case 'stripe':
                    case 'stars':

                        $this->botoscope->do_command($chat_id, 'order_is_done', [
                            'order_id' => $order_id
                        ]);

                        break;

                    case 'gift':
                        //$order_status = 'completed';
                        $order_status = 'processing';
                        //do not send answer because files in cycle continue sending as messages
                        break;
                }

                //+++

                header('Content-Type: text/html; charset=UTF-8');

                wp_enqueue_script('botoscope-telegram-sdk', 'https://telegram.org/js/telegram-web-app.js', [], BOTOSCOPE_VERSION, false);
                wp_add_inline_script('botoscope-telegram-sdk',
                        'if (window.Telegram && window.Telegram.WebApp) { Telegram.WebApp.close(); }' .
                        'window.onload = function() {' .
                        'history.replaceState(null, null, "' . esc_url(site_url('botoscope-thank-you')) . '");' .
                        'location.replace("https://t.me/' . esc_js(BOTOSCOPE_BOT_NAME) . '");' .
                        '};'
                );
                ?>
                <!DOCTYPE html>
                <html>
                    <head>
                        <title><?php esc_html_e('Order is paid', 'botoscope') ?></title>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <?php wp_print_scripts('botoscope-telegram-sdk'); ?>
                    </head>
                    <body>
                        <div>
                            <p><?php esc_html_e('Payment was successful. Please close this window!', 'botoscope') ?></p>
                        </div>
                    </body>
                </html>
                <?php
                //+++
                /* translators: %s: payment gateway name */
                $order->update_status($order_status, sprintf(esc_html__('Payment completed successfully through %s', 'botoscope'), $gate));
            } else {
                echo 'Order data is wrong';
            }
        } else {
            die('Access denied');
        }
    }

    public function cancel_order_paid() {
        header('Content-Type: text/html; charset=UTF-8');

        wp_enqueue_script('botoscope-telegram-sdk', 'https://telegram.org/js/telegram-web-app.js', [], BOTOSCOPE_VERSION, false);
        wp_add_inline_script('botoscope-telegram-sdk', 'Telegram.WebApp.close();');
        ?>
        <!DOCTYPE html>
        <html>
            <head>
                <title><?php esc_html_e('Order', 'botoscope') ?></title>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <?php wp_print_scripts('botoscope-telegram-sdk'); ?>
            </head>
            <body>
                <div>&nbsp;</div>
            </body>
        </html>
        <?php
    }

    public function create_order(WP_REST_Request $request) {
        $order_data = $request->get_json_params();

        if (empty($order_data) || !isset($order_data['bot_name']) || $order_data['bot_name'] !== 'botoscope') {
            return new WP_REST_Response(array('error' => 'No order data received'), 400);
        }

        //file_put_contents(ABSPATH . 'debug.log', date("Y-m-d H:i:s") . "\n" . print_r($order_data, true) . "\n\n", FILE_APPEND);

        $order = $this->make_order($order_data);

        return new WP_REST_Response(array('order_id' => $order->get_id()), 200);
    }

    public function make_order($order_data) {
        global $WOOCS, $wpdb;

        //file_put_contents(ABSPATH . 'debug.log', date("Y-m-d H:i:s") . "\n" . print_r($order_data, true) . "\n\n", FILE_APPEND);

        if ($WOOCS && !empty($order_data['currency'])) {
            $WOOCS->set_currency($order_data['currency']);
        }

        $order = wc_create_order();
        $currencies = [];
        $to_ceil = false;

        if ($WOOCS) {
            $order->update_meta_data('_woocs_order_base_currency', $WOOCS->default_currency);
            $order->update_meta_data('_woocs_order_currency', $order_data['currency']);
            $currencies = $WOOCS->get_currencies();
            $order->update_meta_data('_woocs_order_rate', $currencies[$order_data['currency']]['rate']);

            $to_ceil = !empty($currencies) && isset($currencies[strtoupper($order_data['currency'])]) && intval($currencies[strtoupper($order_data['currency'])]['hide_cents']) === 1;
        }

        // Setting up payment details
        $order->set_payment_method(sanitize_text_field($order_data['payment_method']));
        $order->set_payment_method_title(sanitize_text_field($order_data['payment_method_title']));

        // Setting up billing data
        $order->set_billing_first_name(sanitize_text_field($order_data['billing']['first_name']));
        $order->set_billing_last_name(sanitize_text_field($order_data['billing']['last_name']));

        foreach ($order_data['items'] as $item) {
            $args = [];
            $product_id = intval($item['id']);
            $product = wc_get_product($product_id);

            if (!$product) {
                continue;
            }

            $botoscope_product_type = $product->get_type();

            if ($product->is_type('variation')) {
                $variation_id = $product_id;
                $variation_attributes = $product->get_variation_attributes();

                $args = [
                    'variation_id' => $variation_id,
                    'variation' => $variation_attributes
                ];

                $custom_price = floatval($product->get_price());
                if (!$custom_price) {
                    $custom_price = floatval(get_post_meta($product_id, '_botoscope_price', true));
                }

                if ($to_ceil) {
                    //$custom_price = ceil($custom_price);
                }

                //fix for empty prices, if sale price is set - works fine self
                $product->set_price($custom_price);
                $product->set_regular_price($custom_price);
                //$product->set_sale_price($custom_price);
                //+++
                /*
                  $parent_product = wc_get_product($parent_id);
                  $parent_id = $product->get_parent_id();
                  $quantity = intval($item['quantity']);

                  $order_item = new WC_Order_Item_Product();
                  $order_item->set_product($product);
                  $order_item->set_quantity($quantity);
                  $order_item->set_variation_id($variation_id); //!!

                  $price = floatval($product->get_price());
                  if (!$price) {
                  $price = floatval(get_post_meta($product_id, '_botoscope_price', true));
                  }

                  $order_item->set_total($price * $quantity);
                  $order_item->set_subtotal($price * $quantity);

                  $order->add_item($order_item);
                  continue;
                 */
            }

            //file_put_contents(ABSPATH . 'debug.log', date("Y-m-d H:i:s") . "\n" . print_r($item, true) . "\n\n", FILE_APPEND);
            //file_put_contents(ABSPATH . 'debug.log', date("Y-m-d H:i:s") . "\n" . print_r($args, true) . "\n\n", FILE_APPEND);

            if ($product->is_type('grouped')) {
                $regular_price = get_post_meta($product->get_id(), '_regular_price', true);
                $sale_price = get_post_meta($product->get_id(), '_sale_price', true);
                $group_price = $sale_price ?: $regular_price;

                if ($to_ceil) {
                    //$group_price = ceil($group_price);
                }

                $child_product_ids = $product->get_children(); // We get the ID of all nested products

                $included_products = [];
                foreach ($child_product_ids as $child_id) {
                    $child_product = wc_get_product($child_id);
                    if ($child_product) {
                        $product_name = $child_product->get_name();

                        if ($child_product->is_type('variable') && isset($item['additional']) && isset($item['additional'][$child_id])) {
                            $attributes = $item['additional'][$child_id];
                            $attribute_string = BOTOSCOPE_HELPER::get_product_variation_string($attributes);

                            if (!empty($attribute_string)) {
                                $product_name .= ": ({$attribute_string})";
                            }
                        }

                        $included_products[] = $product_name . PHP_EOL;
                    }
                }


                $included_products_str = implode("", $included_products);
                // Adding a group product to the order
                $order_item = new WC_Order_Item_Product();
                $order_item->set_product($product);
                $order_item->set_quantity(intval($item['quantity']));
                $order_item->set_subtotal($group_price);
                $order_item->set_total($group_price);

                // Adding a list of products to the meta field
                $order_item->add_meta_data('included_products', $included_products_str, true);
                $order_item->add_meta_data('_additional', $item['additional'], true);

                $order->add_item($order_item);
                $order->calculate_totals(false);
            } else {

                if (isset($item['additional']['custom_price']) && floatval($item['additional']['custom_price']) > 0) {
                    $custom_price = floatval($item['additional']['custom_price']);

                    if ($to_ceil) {
                        //$custom_price = ceil($custom_price);
                    }

                    $product->set_price($custom_price);
                    $product->set_regular_price($custom_price);
                }


                //fix to avoid double multiple
                if ($product->is_type('variation') && $WOOCS) {
                    $WOOCS->reset_currency();
                }

                $order_item_id = $order->add_product($product, intval($item['quantity']), $args);

                if ($product->is_type('variation') && $WOOCS && !empty($order_data['currency'])) {
                    $WOOCS->set_currency($order_data['currency']);
                }

                $order_item = $order->get_item($order_item_id);

                if ($product->is_type('botoscope_simple_virtual_downloadable') || $product->is_type('botoscope_simple_media_casting')) {
                    $order_item->add_meta_data('_botoscope_access_days', $this->botoscope->products->get_access_days($product_id), true);
                }

                if ($order_item instanceof WC_Order_Item_Product && array_key_exists('additional', $item)) {
                    $order_item->add_meta_data('_additional', $item['additional'], true);
                    $order_item->save();

                    if (boolval($this->botoscope->booking)) {
                        if ($botoscope_product_type === 'botoscope_simple_virtual' && isset($item['additional']['year']) && isset($item['additional']['month']) && isset($item['additional']['day'])) {
                            $hash_key = sanitize_key($item['additional']['hash_key']);
                            $slot_id = intval($item['additional']['slot_id']);
                            $year = intval($item['additional']['year']);
                            $month = intval($item['additional']['month']);
                            $day = intval($item['additional']['day']);

                            $slot_data = $this->botoscope->booking->slots->get_slot_by_id($slot_id);

                            if (!empty($hash_key)) {
                                $hashed_slot_data = $this->botoscope->booking->slots->slots_targeted->get_slot_by_hash($hash_key);

                                if ($hashed_slot_data) {
                                    $fields = ['start_h', 'start_m', 'finish_h', 'finish_m'];

                                    foreach ($fields as $field) {
                                        if (!empty($hashed_slot_data[$field]) || strval($hashed_slot_data[$field]) === '0') {
                                            $slot_data[$field] = $hashed_slot_data[$field];
                                        }
                                    }
                                }
                            }

                            $reservation_id = $this->botoscope->booking->create([
                                'slot_id' => $slot_id,
                                'product_id' => $product_id,
                                'start_h' => $slot_data['start_h'],
                                'start_m' => $slot_data['start_m'],
                                'finish_h' => $slot_data['finish_h'],
                                'finish_m' => $slot_data['finish_m'],
                                'hash_key' => $hash_key,
                                'year' => $year,
                                'month' => $month,
                                'day' => $day,
                                'order_id' => $order_item->get_order_id()
                            ]);

                            $order->update_meta_data('_botoscope_reservation_id', $reservation_id);
                        }
                    }
                }
            }
        }

        // Adding Coupons
        if (!empty($order_data['coupon_code'])) {
            $order->apply_coupon(sanitize_text_field($order_data['coupon_code']));
        }

        if (!empty($order_data['customer_comments'])) {
            $order->set_customer_note(mb_substr(sanitize_text_field(wp_strip_all_tags($order_data['customer_comments'])), 0, 1000));
        }

        // Setting meta data
        if (isset($order_data['shipping_address'])) {
            $order->update_meta_data('_botoscope_shipping_address', sanitize_text_field($order_data['shipping_address']));
            $order->update_meta_data('_shipping_address_index', sanitize_text_field($order_data['shipping_address']));
            $order->update_meta_data('_botoscope_shipping_way', intval($order_data['shipping_way']));
            $order->update_meta_data('_botoscope_shipping_amount', floatval($order_data['shipping_amount']));
        }

        $order->update_meta_data('_wc_order_attribution_utm_medium', 'Telegram');
        $order->update_meta_data('_wc_order_attribution_utm_source', 'Botoscope');
        $order->update_meta_data('_wc_order_attribution_source_type', sanitize_text_field($order_data['bot_name']));
        $order->update_meta_data('_botoscope_chat_id', intval($order_data['chat_id']));

        if (isset($order_data['user_id'])) {
            $user_id = intval($order_data['user_id']);
        } else {
            $user_id = $wpdb->get_var($wpdb->prepare(
                            "SELECT user_id FROM $wpdb->usermeta WHERE meta_key = %s AND meta_value = %s LIMIT 1",
                            'chat_id',
                            intval($order_data['chat_id'])
                    ));
        }

        if (intval($user_id) > 0) {
            $order->set_customer_id($user_id);
        }

        if (isset($order_data['order_note'])) {
            $order->add_order_note($order_data['order_note'], false);
        }

        $order->set_currency($order_data['currency']);
        if (isset($order_data['discount_amount']) && isset($order_data['discount_reason'])) {
            $order->update_meta_data('_botoscope_discount_amount', sanitize_text_field($order_data['discount_amount']));
            $order->update_meta_data('_botoscope_discount_reason', sanitize_text_field($order_data['discount_reason']));
        }

        // Setting the total order amount
        $total_amount = BOTOSCOPE_HELPER::woocs_exchange_value($order_data['total_amount'], $order_data['currency']);
        $total_amount = round($total_amount, 2);

        if ($to_ceil) {
            $total_amount = ceil($total_amount);
        }


        // fee — for visual purposes only, without currency conversion, from the actual subtotal minus the rounded total
        if (!empty($order_data['discount_amount']) && floatval($order_data['discount_amount']) > 0) {
            // No fee needed if discount is already covered by a coupon, as "fee" is related to marketing ext discounts
            $coupons = $order->get_coupon_codes();
            if (empty($coupons)) {
                $items_subtotal = 0;
                foreach ($order->get_items() as $oi) {
                    $items_subtotal += floatval($oi->get_subtotal());
                }

                $discount_val = round($items_subtotal - $total_amount, 2);

                if ($discount_val > 0) {
                    $fee_label = !empty($order_data['discount_reason']) ? sanitize_text_field($order_data['discount_reason']) : esc_html__('Discount', 'botoscope');

                    $fee = new WC_Order_Item_Fee();
                    $fee->set_name($fee_label);
                    $fee->set_amount(-$discount_val);
                    $fee->set_total(-$discount_val);
                    $fee->set_tax_status('none');
                    $order->add_item($fee);
                }
            }
        }

        $order->set_total($total_amount);
        /* translators: %s: bot name */
        $order->update_status('pending', sprintf(esc_html__("created by Botoscope user: %s", 'botoscope'), $order_data['bot_name']));

        $order->save();

        //++++++
        // Setting shipping data
        if (isset($order_data['shipping_address'])) {
            $shipping_id = intval($order_data['shipping_way']);
            if ($shipping_id > 0 && isset($this->botoscope->shipping)) {
                $shipping_amount = floatval($order_data['shipping_amount']);
                $shipping_data = $this->botoscope->shipping->get_active();

                $shipping_item = new WC_Order_Item_Shipping();
                $shipping_item->set_method_title($shipping_data['ways'][$shipping_id]['title']);
                $shipping_item->set_method_id("botoscope_shipping_{$shipping_id}");

                if ($WOOCS && !empty($order_data['currency'])) {
                    $shipping_amount = BOTOSCOPE_HELPER::woocs_exchange_value($shipping_amount, strtoupper($order_data['currency']));

                    if ($to_ceil) {
                        $shipping_amount = ceil($shipping_amount);
                    }
                }

                $shipping_item->set_total($shipping_amount);
                $order->add_item($shipping_item);
                $order->calculate_totals();
            }
        }

        //+++

        if ($to_ceil) {
            foreach ($order->get_items() as $item) {
                $item_total = ceil($item->get_total());
                $item_subtotal = ceil($item->get_subtotal());

                $item->set_total($item_total);
                $item->set_subtotal($item_subtotal);
                $item->save();
            }

            $order->calculate_totals();
        }

        $order->save();

        do_action('botoscope_order_created', $order);
        return $order;
    }

    public function edit_order(WP_REST_Request $request) {
        $order_id = intval($request['order_id']);
        $order_data = $request->get_json_params();

        if (!$order_id || empty($order_data)) {
            return new WP_REST_Response(array('error' => 'No order data received'), 400);
        }

        if (!empty($order_data)) {

            global $WOOCS;
            $order = wc_get_order($order_id);
            $shipping_id = 0;
            $changed = true;

            foreach ($order_data as $key => $value) {
                switch ($key) {
                    case 'payment_way':
                        $order->set_payment_method(sanitize_text_field($value));
                        $payment_data = $this->botoscope->payment->get_active();
                        $order->set_payment_method_title($payment_data[$value]['title']);
                        break;

                    case 'shipping_address':
                        $order->update_meta_data('_botoscope_shipping_address', sanitize_text_field($value));
                        $order->update_meta_data('_shipping_address_index', sanitize_text_field($value));
                        break;

                    case 'shipping_way':
                        $shipping_id = intval($value);
                        $order->update_meta_data('_botoscope_shipping_way', $shipping_id);
                        break;

                    case 'shipping_price':
                        $order->update_meta_data('_botoscope_shipping_amount', floatval($value));

                        foreach ($order->get_items('shipping') as $item_id => $item) {
                            $order->remove_item($item_id);
                        }

                        $shipping_item = new WC_Order_Item_Shipping();
                        $shipping_item->set_method_title("Botoscope #{$shipping_id}");
                        $shipping_item->set_method_id("custom_shipping_{$shipping_id}");

                        $value = floatval($value);
                        if ($WOOCS && !empty($order_data['currency'])) {
                            $value = BOTOSCOPE_HELPER::woocs_exchange_value($value, strtoupper($order_data['currency']));
                        }

                        $shipping_item->set_total($value);

                        $order->add_item($shipping_item);
                        $order->calculate_totals();
                        break;

                    case 'coupon_code':
                        //Remove all applied coupons
                        $applied_coupons = $order->get_used_coupons();
                        foreach ($applied_coupons as $coupon_code) {
                            $order->remove_coupon($coupon_code);
                        }

                        $order->apply_coupon(sanitize_text_field($value));
                        $order->calculate_totals();
                        break;

                    case 'customer_comments':
                        $order->set_customer_note(mb_substr(sanitize_text_field(wp_strip_all_tags($value)), 0, 1000));
                        break;

                    case 'status':
                        $order->update_status($value, 'via Telegram bot');
                        $changed = false;
                        break;
                }
            }

            if ($changed) {
                $order->save();
            }
        }

        return new WP_REST_Response(array('order_id' => $order->get_id()), 200);
    }

    public function get_orders(WP_REST_Request $request) {
        $chat_id = $request['chat_id'];

        if (!$chat_id) {
            return new WP_REST_Response(['error' => 'Chat ID is required'], 400);
        }

        $args = array(
            'limit' => -1, // We receive all orders
            //'status' => array('pending', 'processing', 'completed'), // order statuses
            'meta_key' => '_botoscope_chat_id',
            'meta_value' => $chat_id
        );

        $orders = wc_get_orders($args);
        $orders_data = [];

        foreach ($orders as $order) {
            $orders_data[] = $this->get_order_data($order);
        }

        return new WP_REST_Response($orders_data, 200);
    }

    public function get_single_order(WP_REST_Request $request) {
        $order_id = $request['order_id'];

        if (!$order_id) {
            return new WP_REST_Response(['error' => 'Order ID is required'], 400);
        }

        $order = wc_get_order($order_id);
        return new WP_REST_Response($this->get_order_data($order), 200);
    }

    private function get_order_data($order) {
        $order_data = [];

        if ($order) {
            $shipping_address = $order->get_meta('_botoscope_shipping_address', true);

            $order_data = array(
                'order_id' => $order->get_id(),
                'chat_id' => intval($order->get_meta('_botoscope_chat_id', true)),
                'status' => $order->get_status(),
                'total' => floatval($order->get_total()),
                'currency' => strtolower((string) $order->get_currency()),
                'date_created' => strtotime($order->get_date_created()->date('Y-m-d H:i:s')),
                'date_paid' => $order->get_date_paid() ? $order->get_date_paid()->getTimestamp() : null,
                'shipping_address' => $shipping_address,
                'payment_method' => $order->get_payment_method(),
                'transaction_id' => $order->get_transaction_id(),
                'woocs_order_base_currency' => $order->get_meta('_woocs_order_base_currency', true),
                'woocs_order_currency' => $order->get_meta('_woocs_order_currency', true),
                'woocs_order_rate' => floatval($order->get_meta('_woocs_order_rate', true)),
                'items' => []
            );

            foreach ($order->get_items() as $item_id => $item) {
                $product_id = $item->get_product_id();
                $parent_id = $product_id;
                $params = $this->get_item_attributes($item);
                if (!empty($params)) {
                    $product_id = $item->get_variation_id();
                }

                $order_data['items'][] = array(
                    'product_id' => $product_id,
                    'parent_id' => $parent_id,
                    'quantity' => $item->get_quantity(),
                    'subtotal' => floatval($item->get_subtotal()),
                    'params' => $params,
                    'botoscope_access_days' => intval($item->get_meta('_botoscope_access_days', true)),
                );
            }
        }

        return $order_data;
    }

    private function get_item_attributes($item) {
        $attributes = [];

        if ($item instanceof WC_Order_Item_Product) {
            $product = $item->get_product();
            if ($product && isset($product->attribute_summary)) {
                $attributes = $product->get_attributes();
                foreach ($attributes as $attr_slug => $attr_value) {
                    $decoded_value = urldecode($attr_value);
                    $term = get_term_by('slug', $decoded_value, $attr_slug);
                    if ($term) {
                        $attributes[$attr_slug] = $term->term_id;
                    }
                }
            }
        }

        return $attributes;
    }
}
