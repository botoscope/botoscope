<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

//20-04-2026
final class BOTOSCOPE_BUSINESS_IN_POCKET extends BOTOSCOPE_APP {

    protected $botoscope;
    protected $controls;
    protected $table_name = 'business_in_pocket';
    protected $slug = 'business_in_pocket';

    public function __construct($args = []) {
        parent::__construct($args);

        $this->botoscope->allrest->add_rest_route("/{$this->slug}/checkout_is_finished/(?P<order_id>\d+)", [$this, 'checkout_is_finished']);

        add_action('botoscope_controls', function ($controls) {

            $controls['business_in_pocket_notify_about_new_orders'] = [
                'title' => esc_html__('In pocket: notify about new orders', 'botoscope'),
                'value' => 0,
                'type' => 'switcher',
                'help' => esc_html__('Get notifications about new orders in your Business-in-Pocket bot', 'botoscope'),
            ];

            if ($this->botoscope->support) {
                $controls['business_in_pocket_notify_about_customer_messages'] = [
                    'title' => esc_html__('In pocket: notify about customer messages', 'botoscope'),
                    'value' => 0,
                    'type' => 'switcher',
                    'help' => esc_html__('Get notifications about new customer messages in your Business-in-Pocket bot', 'botoscope'),
                ];
            }


            $controls['business_in_pocket_notify_about_stock'] = [
                'title' => esc_html__('In pocket: notify about low stock', 'botoscope'),
                'value' => 0,
                'type' => 'switcher',
                'help' => esc_html__('Notify my Business-in-Pocket bot about low stock', 'botoscope'),
            ];

            return $controls;
        });

        $this->controls = new BOTOSCOPE_CONTROLS($args);

        if (intval($this->controls->get_option('business_in_pocket_notify_about_customer_messages'))) {

            add_action('init', function () {
                add_rewrite_rule('^botoscope-chat/?$', 'index.php?botoscope_chat=1', 'top');
                add_rewrite_tag('%botoscope_chat%', '1');
            });

            add_action('template_redirect', function () {
                if (get_query_var('botoscope_chat') && intval(get_query_var('botoscope_chat')) === 1) {
                    header('Content-Type: text/html; charset=utf-8');

                    if (isset($_GET['ticket_id'])) {
                        $ticket_id = intval($_GET['ticket_id']);

                        if ($ticket_id > 0 && isset($_GET['hash_key'])) {
                            $hash_key = sanitize_text_field($_GET['hash_key']);
                            $ticket = $this->botoscope->support->get_ticket_by_id($ticket_id);
                            $non_answered_tickets = $this->botoscope->support->get_tickets_non_answered();

                            if ($ticket['hash_key'] === $hash_key) {
                                $data = [
                                    'ajaxurl' => admin_url('admin-ajax.php'),
                                    'nonce' => wp_create_nonce('botoscope_form_nonce'),
                                    'ticket_id' => $ticket_id,
                                    'non_answered_tickets' => $non_answered_tickets,
                                    'messages' => $this->botoscope->support->get_messages($ticket_id)
                                ];

                                BOTOSCOPE_HELPER::render_html_e(__DIR__ . '/views/chat.php', $data);
                            }
                        }
                    } else {
                        wp_die('Access denied');
                    }
                    exit;
                }
            });

            add_action('botoscope_support_receive_message', function ($data) {

                $text = $data['content'] . PHP_EOL . PHP_EOL;

                if ($data['object_type'] === 'order') {
                    $text .= sprintf(
                            '<b>%s</b>',
                            /* translators: %d: order number */ esc_html__('Related to order #%d', 'botoscope')
                    );
                    $text = sprintf($text, $data['object_id']);
                } else {
                    $product = wc_get_product($data['object_id']);
                    if ($product) {
                        $title = wp_strip_all_tags($product->get_title());

                        $text .= sprintf(
                                '<b>%s</b>',
                                sprintf(
                                        /* translators: 1: product ID, 2: product title */esc_html__('Related to product #%1$d "%2$s"', 'botoscope'),
                                        $data['object_id'],
                                        $title
                                )
                        );
                    }
                }

                $url = str_replace('http://', 'https://', get_site_url(null, 'botoscope-chat') . "/?botoscope_chat=1&ticket_id={$data['ticket_id']}&hash_key={$data['hash_key']}");

                $buttons = [
                    [
                        [
                            'text' => '💬 ' . esc_html__('Answer to customer now', 'botoscope'),
                            'web_app' => [
                                'url' => $url
                            ]
                        ]
                    ]
                ];

                $this->send_message(html_entity_decode($text), $buttons);
            });

            add_action('wp_ajax_botoscope_business_in_pocket_answer_to_customer', [$this, 'answer_to_customer'], 1);
            add_action('wp_ajax_nopriv_botoscope_business_in_pocket_answer_to_customer', [$this, 'answer_to_customer'], 1);
        }

        //+++
        if (intval($this->controls->get_option('business_in_pocket_notify_about_stock'))) {
            add_action('woocommerce_reduce_order_stock', function ($order) {
                if (is_numeric($order)) {
                    $order = wc_get_order($order);
                }

                if (!$order) {
                    return;
                }

                foreach ($order->get_items(['line_item']) as $item) {
                    $product = $item->get_product();

                    if (!$product) {
                        continue;
                    }

                    $this->notify_stock($product);
                }
            }, 10, 1);
        }

        if ($this->botoscope->no_bot) {
            add_action('woocommerce_new_order', function ($order_id) {
                $this->checkout_is_finished(['order_id' => $order_id]);
            }, 10, 1);
        }

        //+++

        $page_slug = 'botoscope-chat';
        $page = get_page_by_path($page_slug, OBJECT, 'page');
        if (!$page) {
            wp_insert_post([
                'post_title' => esc_html__('Botoscope chat: business in pocket', 'botoscope'),
                'post_name' => $page_slug,
                'post_content' => '',
                'post_status' => 'publish',
                'post_type' => 'page'
            ]);
        }
    }

    //ajax
    public function answer_to_customer() {
        if (wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['botoscope_form_nonce'])), 'botoscope_form_nonce')) {
            $message = sanitize_textarea_field($_REQUEST['message']);
            $ticket_id = intval($_REQUEST['ticket_id']);

            if (!empty($message) && $ticket_id > 0) {
                do_action('botoscope_support_answer_to_customer', [
                    'message' => $message,
                    'ticket_id' => $ticket_id
                ]);
            }
        }

        exit;
    }

    public function checkout_is_finished($request) {

        global $WOOCS;

        if (intval($this->controls->get_option('business_in_pocket_notify_about_new_orders')) === 0) {
            return;
        }

        $order_id = intval($request['order_id']);
        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        $lines = [];

        // Header
        /* translators: %d: order number */
        $lines[] = "🛒 <b>" . sprintf(esc_html__('New order #%d', 'botoscope'), $order->get_id()) . "</b>" . PHP_EOL;
        $lines[] = "📅 " . $order->get_date_created()->date(get_option('date_format') . ' ' . get_option('time_format')) . PHP_EOL;
        $lines[] = "👤 " . $order->get_formatted_billing_full_name() . " (<b>ID: {$order->get_customer_id()}</b>)" . PHP_EOL;

        $payment_method_title = $order->get_payment_method_title();
        if (!empty($payment_method_title)) {
            $lines[] = "💳 <b>" . esc_html__('Payment method', 'botoscope') . ":</b> " . $payment_method_title . PHP_EOL;
        }

        $order_status = wc_get_order_status_name($order->get_status());
        $lines[] = "📦 <b>" . esc_html__('Status', 'botoscope') . ":</b> " . $order_status . PHP_EOL;

        // Items
        $lines[] = "<b>🧾 " . esc_html__('Order items', 'botoscope') . ":</b>";
        foreach ($order->get_items() as $item) {
            $name = $item->get_name();
            $qty = $item->get_quantity();
            $total = $this->format_price($item->get_total(), $order->get_currency());

            $product = $item->get_product();
            $product_id = $product ? $product->get_id() : '—';
            $sku = $product && $product->get_sku() ? $product->get_sku() : '—';

            $line = "       • {$name} × {$qty} — <b>{$total}</b>";
            $line .= " (ID: {$product_id}, SKU: {$sku})";

            if ($product->get_type() === 'botoscope_simple_virtual' && isset($this->botoscope->booking)) {
                $reservation_id = intval(get_post_meta($order_id, '_botoscope_reservation_id', true));

                if ($reservation_id > 0) {
                    $line .= PHP_EOL . "<b>" . wp_strip_all_tags($this->botoscope->booking->get_order_item_timeslot($reservation_id)) . "</b>";
                }
            }

            $lines[] = $line . PHP_EOL;
        }

        // Coupon
        if ($order->get_coupon_codes()) {
            $coupon_codes = implode(', ', $order->get_coupon_codes());
            $lines[] = "💸 <b>" . esc_html__('Coupon applied', 'botoscope') . ":</b> <i>{$coupon_codes}</i>";
        }

        // Customer note
        $note = $order->get_customer_note();
        if (!empty($note)) {
            $lines[] = "";
            $lines[] = "📝 <b>" . esc_html__('Note', 'botoscope') . ":</b> <u>{$note}</u>" . PHP_EOL;
        }

        // Shipping
        $shipping_total = floatval($order->get_shipping_total());
        if ($shipping_total > 0) {
            $lines[] = "";
            $lines[] = "🚚 <b>" . esc_html__('Shipping', 'botoscope') . ":</b> <b>" . $this->format_price($shipping_total, $order->get_currency()) . "</b>" . PHP_EOL;

            if (isset($this->botoscope->shipping)) {
                $shipping_data = $this->botoscope->shipping->get_active();
                $shipping_id = intval($order->get_meta_data('_botoscope_shipping_way'));
                if ($shipping_id && isset($shipping_data['ways'][$shipping_id]['title'])) {
                    $shipping_way_title = $shipping_data['ways'][$shipping_id]['title'];
                    $lines[] = "🏬 <b>" . esc_html__('Shipping method', 'botoscope') . ":</b> " . $shipping_way_title . PHP_EOL;
                }
            }

            $shipping_address = $order->get_formatted_shipping_address();
            if (!empty($shipping_address)) {
                $lines[] = "🏠 <b>" . esc_html__('Shipping address', 'botoscope') . ":</b> " . wp_strip_all_tags($shipping_address) . PHP_EOL;
            }
        }

        // Total
        $lines[] = "💰 <b>" . esc_html__('Order total', 'botoscope') . ": " . $this->format_price($order->get_total(), $order->get_currency()) . "</b>" . PHP_EOL;

        // Today summary
        $timezone = wp_timezone(); // WordPress timezone object
        $start_of_day = (new DateTimeImmutable('today', $timezone))->setTime(0, 0);
        $now = new DateTimeImmutable('now', $timezone);

        $args = [
            'limit' => -1,
            'status' => ['processing', 'completed', 'pending', 'on-hold'],
            'date_created' => $start_of_day->format('Y-m-d H:i:s') . '...' . $now->format('Y-m-d H:i:s'),
            'return' => 'ids',
        ];

        $today_orders = wc_get_orders($args);
        $order_count = count($today_orders);
        $total_today = 0;
        $base_currency = $WOOCS ? strtoupper($WOOCS->default_currency) : strtoupper(get_option('woocommerce_currency'));

        foreach ($today_orders as $id) {
            $order_today = wc_get_order($id);
            $order_total = floatval($order_today->get_total());
            $order_currency = strtoupper($order_today->get_currency());

            if ($order_currency !== $base_currency) {
                $order_rate = floatval($order_today->get_meta('_woocs_order_rate', true));
                if ($order_rate > 0) {
                    $order_total = $order_total / $order_rate;
                }
            }

            $total_today += $order_total;
        }

        if ($total_today) {
            $t = $this->format_price($total_today, $base_currency);
            /* translators: 1: number of orders, 2: total amount */
            $lines[] = "📊 <b>" . sprintf(esc_html__('Today: %1$d order(s) totaling %2$s', 'botoscope'), $order_count, $t) . "</b>";
        }

        // Final send
        $text = implode(PHP_EOL, $lines);
        $this->send_message(html_entity_decode($text));

        return true;
    }

    private function format_price($amount, $currency = 'EUR') {
        global $WOOCS;

        if ($WOOCS) {
            $prev_currency = $WOOCS->current_currency;
            $WOOCS->set_currency(strtoupper($currency));
            $result = wp_strip_all_tags(wc_price($amount));
            $WOOCS->set_currency($prev_currency);
        } else {
            $result = wp_strip_all_tags(wc_price($amount));
        }

        return $result;
    }

    private function get_settings() {
        $res = [];
        $settings = $this->botoscope->extensions->get_settings($this->slug);
        if (!empty($settings)) {
            foreach ($settings as $key => $value) {
                $res[$key] = explode(',', trim($value));
            }
        }

        return $res;
    }

    public function notify_stock(WC_Product $product) {
        if (!$product->managing_stock()) {
            return;
        }

        $qty = (int) max(0, (int) $product->get_stock_quantity());
        $status = $product->get_stock_status(); // 'instock' | 'outofstock' | 'onbackorder'
        $pid = $product->get_id();
        $name = $product->get_name();
        $is_var = $product->is_type('variation');
        $parent = $is_var ? wc_get_product($product->get_parent_id()) : null;
        $title = $is_var && $parent ? ($parent->get_name() . ' — ' . wc_get_formatted_variation($product, true, false, true)) : $name;

        if ($qty <= 0 || $status === 'outofstock') {
            $this->send_stock_alert('out_of_stock', [
                'product_id' => $pid,
                'parent_id' => $is_var ? $product->get_parent_id() : 0,
                'title' => $title,
                'sku' => $product->get_sku(),
                'qty' => $qty,
                'stock_status' => $status,
            ]);
        } else {
            if ($qty <= wc_get_low_stock_amount($product)) {
                $this->send_stock_alert('low_stock', [
                    'product_id' => $pid,
                    'parent_id' => $is_var ? $product->get_parent_id() : 0,
                    'title' => $title,
                    'sku' => $product->get_sku(),
                    'qty' => $qty,
                    'stock_status' => $status,
                ]);
            }
        }
    }

    private function send_stock_alert(string $type, array $payload): void {
        $key = 'botoscope_stock_alert_' . $type . '_' . $payload['product_id'];

        if (get_transient($key)) {
            return;
        }

        set_transient($key, 1, 15 * MINUTE_IN_SECONDS);

        $title = isset($payload['title']) ? esc_html($payload['title']) : '';
        $pid = isset($payload['product_id']) ? (int) $payload['product_id'] : 0;
        $parent_id = isset($payload['parent_id']) ? (int) $payload['parent_id'] : 0;
        $sku = isset($payload['sku']) ? esc_html((string) $payload['sku']) : '';
        $qty = isset($payload['qty']) ? (int) $payload['qty'] : 0;
        $status = isset($payload['stock_status']) ? esc_html((string) $payload['stock_status']) : '';
        $threshold = isset($payload['threshold']) ? (int) $payload['threshold'] : 0;

        $icon = [
            'bell' => '🔔',
            'warn' => '⚠️',
            'stop' => '❌',
            'box' => '📦',
            'id' => '🆔',
            'parent' => '🧩',
            'sku' => '🏷️',
            'qty' => '📉',
            'th' => '🎚️',
            'status' => '📊',
            'restock' => '🔁',
            'oos' => '⛔',
        ];

        if ($type === 'out_of_stock') {
            $heading = $icon['stop'] . ' ' . esc_html__('Out of stock alert', 'botoscope') . PHP_EOL;
        } elseif ($type === 'low_stock') {
            $heading = $icon['warn'] . ' ' . esc_html__('Low stock alert', 'botoscope') . PHP_EOL;
        } else {
            $heading = $icon['bell'] . ' ' . esc_html__('Stock alert', 'botoscope') . PHP_EOL;
        }

        $lines = [];
        $lines[] = $heading;
        /* translators: %s: product title */
        $lines[] = $icon['box'] . ' ' . sprintf(esc_html__('Product: %s', 'botoscope'), $title) . PHP_EOL;
        /* translators: %d: product ID */
        $lines[] = $icon['id'] . ' ' . sprintf(esc_html__('Product ID: %d', 'botoscope'), $pid) . PHP_EOL;

        if ($parent_id > 0) {
            /* translators: %d: parent product ID */
            $lines[] = $icon['parent'] . ' ' . sprintf(esc_html__('Parent ID: %d', 'botoscope'), $parent_id) . PHP_EOL;
        }
        if ($sku !== '') {
            /* translators: %s: product SKU */
            $lines[] = $icon['sku'] . ' ' . sprintf(esc_html__('SKU: %s', 'botoscope'), $sku) . PHP_EOL;
        }

        if ($type === 'low_stock') {
            /* translators: %d: stock quantity */
            $lines[] = $icon['qty'] . ' ' . sprintf(esc_html__('Quantity left: %d', 'botoscope'), $qty) . PHP_EOL;
            if ($threshold > 0) {
                /* translators: %d: low stock threshold */
                $lines[] = $icon['th'] . ' ' . sprintf(esc_html__('Low stock threshold: %d', 'botoscope'), $threshold) . PHP_EOL;
            }
            /* translators: %s: stock status */
            $lines[] = $icon['status'] . ' ' . sprintf(esc_html__('Stock status: %s', 'botoscope'), $status) . PHP_EOL;
            $lines[] = $icon['restock'] . ' ' . esc_html__('Please restock soon.', 'botoscope') . PHP_EOL;
        } elseif ($type === 'out_of_stock') {
            /* translators: %d: stock quantity */
            $lines[] = $icon['qty'] . ' ' . sprintf(esc_html__('Quantity left: %d', 'botoscope'), $qty) . PHP_EOL;
            /* translators: %s: stock status */
            $lines[] = $icon['status'] . ' ' . sprintf(esc_html__('Stock status: %s', 'botoscope'), $status) . PHP_EOL;
            $lines[] = $icon['oos'] . ' ' . esc_html__('This item is now out of stock.', 'botoscope') . PHP_EOL;
        }

        $text = implode("\n", $lines);
        $this->send_message($text);
    }

    public function send_message($text, $buttons = null) {
        $settings = $this->get_settings();
        $result = null;

        if (!empty($settings)) {

            foreach ($settings['bot_name'] as $index => $bot_name) {
                if (!empty($settings['bot_token'][$index])) {
                    $url = "https://api.telegram.org/bot{$settings['bot_token'][$index]}/sendMessage";

                    $data = [
                        'chat_id' => $settings['admin_chat_id'][$index],
                        'text' => $text,
                        'parse_mode' => 'HTML'
                    ];

                    if (!empty($buttons)) {
                        $data['reply_markup'] = wp_json_encode([
                            'inline_keyboard' => $buttons
                        ]);
                    }

                    $response = wp_remote_post($url, [
                        'headers' => ['Content-Type' => 'application/json'],
                        'body' => wp_json_encode($data),
                        'timeout' => 15,
                    ]);
                    $result = wp_remote_retrieve_body($response);
                }
            }
        }

        return $result;
    }
}
