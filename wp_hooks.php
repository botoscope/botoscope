<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
//08-04-2026
add_filter('woocommerce_order_get_formatted_shipping_address', function ($address, $raw_address, $order) {
    $custom_address = $order->get_meta('_botoscope_shipping_address');

    if (!empty($custom_address)) {
        $address = $custom_address;
    }

    return $address;
}, 10, 3);

add_filter('rest_pre_serve_request', function ($served, $result, $request) {
    $html_routes = [
        '/botoscope/v3/cancel_order_paid',
        '/botoscope/v3/set_order_paid',
    ];

    $current_route = $request->get_route();

    foreach ($html_routes as $route) {
        if (strpos($current_route, $route) !== false) {
            header('Content-Type: text/html; charset=UTF-8');
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- REST response data output for HTML payment pages, escaping would break the HTML structure
            echo $result->get_data();
            return true;
        }
    }

    return $served;
}, 10, 3);

add_action('woocommerce_admin_order_totals_after_discount__disabled', function ($order_id) {
    global $Botoscope;
    $order = wc_get_order($order_id);
    $shipping_way = intval($order->get_meta('_botoscope_shipping_way'));
    $shipping_amount = floatval($order->get_meta('_botoscope_shipping_amount'));
    $shipping_title = '';

    if (property_exists($Botoscope, 'shipping')) {
        $delivery_methods = $Botoscope->shipping->get();
        $index = array_search($shipping_way, array_column($delivery_methods, 'id'));
        $shipping_title = $delivery_methods[$index]['title'] ?? '';
    }

    if ($shipping_way) {
        echo '<tr>';
        echo '<td class="label">' . esc_html__('Shipping way', 'botoscope') . ':</td>';
        echo '<td width="1%"></td>';
        echo '<td class="total">' . esc_html($shipping_title ?? $shipping_way) . '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<td class="label">' . esc_html__('Shipping amount', 'botoscope') . ':</td>';
        echo '<td width="1%"></td>';
        // phpcs:ignore WordPress.Security.EscapeOutput -- wc_price() returns pre-escaped HTML
        echo '<td class="total">' . wc_price(BOTOSCOPE_HELPER::woocs_exchange_value($shipping_amount, $order->get_currency()), ['currency' => $order->get_currency()]) . '</td>';
        echo '</tr>';
    }
}, 10, 9999);

//!!
add_filter('woocommerce_coupon_discount_types', function ($discount_types) {
    $discount_types['botoscope_percent_product'] = esc_html__('Botoscope percent product discount', 'botoscope');
    return $discount_types;
});

//shortcode for product details
add_shortcode('botoscope_product_details', function ($atts) {
    if (isset($_GET['product_id']) && is_numeric($_GET['product_id'])) {
        global $post;
        if (!isset($post) || $post->post_type !== 'product') {
            $product_id = intval($_GET['product_id']);
            $post = get_post($product_id);

            $content = apply_filters('the_content', $post->post_content);
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Content passed through the_content filter which applies all WordPress escaping and kses sanitization
            echo "<div class='botoscope-product-details-content'>{$content}</div>";
        }
    }
});

add_shortcode('botoscope_media_casting', function () {
    if (!isset($_GET['product_id'])) {
        return '<p>Error: `product_id` not specified</p>';
    }

    $product_id = intval($_GET['product_id']);
    $download_id = isset($_GET['download_id']) ? sanitize_text_field($_GET['download_id']) : '';

    $product = wc_get_product($product_id);
    if (!$product) {
        return '<p>Error: Product not found</p>';
    }

    $downloads = $product->get_downloads();
    if (empty($downloads)) {
        return '<p>Error: The product has no files</p>';
    }
    ?>
    <h3 class="botoscope-media-casting-title"><?php echo esc_html(get_the_title($product_id)); ?></h3>
    <form method="GET" id="botoscope-media-form">
        <input type="hidden" name="product_id" value="<?php echo esc_attr($product_id); ?>">
        <select name="download_id" onchange="document.getElementById('botoscope-media-form').submit();">
            <?php
            foreach ($downloads as $download):
                $selected = ($download->get_id() === $download_id) ? ' selected' : '';
                ?>
                <option value="<?php echo esc_attr($download->get_id()); ?>" <?php echo esc_attr($selected) ?>>
                    <?php echo esc_html($download->get_name()); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <?php
    //Select the current download_id
    $current_download = null;
    foreach ($downloads as $download) {
        if ($download->get_id() === $download_id || !$download_id) {
            $current_download = $download;
            break;
        }
    }

    if (!$current_download) {
        return '<p>Error: The selected file was not found</p>';
    }

    // We define media content (audio or video)
    $file_url = esc_url($current_download->get_file());
    $file_ext = pathinfo($file_url, PATHINFO_EXTENSION);

    //Checks for known platforms

    if (preg_match('#youtube\.com/watch\?v=([a-zA-Z0-9_-]+)#', $file_url, $match)) {
        $youtube_id = $match[1];
        return '<iframe width="640" height="360" src="https://www.youtube.com/embed/' . esc_attr($youtube_id) . '" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen></iframe>';
    }


    if (preg_match('#vimeo\.com/(\d+)#', $file_url, $match)) {
        $vimeo_id = $match[1];
        return '<iframe src="https://player.vimeo.com/video/' . esc_attr($vimeo_id) . '" width="640" height="360" frameborder="0" allow="autoplay; fullscreen" allowfullscreen></iframe>';
    }

    if (preg_match('#player\.vimeo\.com/video/(\d+)#', $file_url, $match)) {
        $vimeo_id = $match[1];
        return '<iframe src="https://player.vimeo.com/video/' . esc_attr($vimeo_id) . '" width="640" height="360" frameborder="0" allow="autoplay; fullscreen" allowfullscreen></iframe>';
    }

    if (preg_match('#drive\.google\.com/file/d/([^/]+)#', $file_url, $match)) {
        $drive_id = $match[1];
        return '<iframe src="https://drive.google.com/file/d/' . esc_attr($drive_id) . '/preview" width="640" height="360" allow="autoplay" frameborder="0" allowfullscreen></iframe>';
    }


    if (strpos($file_url, 'dropbox.com') !== false) {
        // Convert the link to raw access
        $dropbox_embed_url = preg_replace('/\?dl=\d/', '?raw=1', $file_url);

        // If there is no parameter ?dl=0, add ?raw=1 or &raw=1
        if (strpos($dropbox_embed_url, '?') === false) {
            $dropbox_embed_url .= '?raw=1';
        } elseif (strpos($dropbox_embed_url, 'raw=1') === false) {
            $dropbox_embed_url .= '&raw=1';
        }

        return '<iframe src="' . esc_url($dropbox_embed_url) . '" width="640" height="360" frameborder="0" allow="autoplay; fullscreen" allowfullscreen></iframe>';
    }

    //+++

    $media_output = '';
    if (in_array($file_ext, ['mp3', 'ogg', 'wav', 'm4a'])) {
        // Audio
        $media_output = do_shortcode('[audio src="' . $file_url . '"]');
    } elseif (in_array($file_ext, ['mp4', 'webm', 'ogg', 'avi'])) {
        // Video
        $media_output = do_shortcode('[video src="' . $file_url . '"]');
    } elseif (in_array($file_ext, ['png', 'jpg', 'jpeg', 'gif', 'bmp', 'webp', 'svg'])) {
        // Images
        $media_output = do_shortcode('[caption width="auto" align="center"]<img src="' . $file_url . '" alt="' . esc_attr($current_download->get_name()) . '" style="max-width:100%; height:auto;">[/caption]');
    } else {
        $media_output = '<p>This format is not supported</p>';
    }

    return $media_output;
});

add_shortcode('botoscope_variation_gallery', function ($atts) {
    if (isset($_GET['variation_id']) && is_numeric($_GET['variation_id'])) {
        global $Botoscope;
        $variation_id = intval($_GET['variation_id']);
        $gallery = $Botoscope->products->get_product_media_gallery(wc_get_product($variation_id));
        $links = array_column($gallery, 'media');
        $content = "";

        if (!empty($links)) {
            foreach ($links as $l) {
                $content .= '<img class="aligncenter wp-image-' . esc_attr($variation_id) . '" src="' . esc_url($l) . '" alt="" width="100%" /><br>';
            }
        }

        $content = apply_filters('the_content', $content);
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Content passed through the_content filter which applies all WordPress escaping and kses sanitization
        return "<div class='botoscope-product-details-content'>{$content}</div>";
    }
});

add_filter('wpo_wcpdf_woocommerce_total__disabled', function ($totals, $order, $document_type) {
    if ($document_type !== 'invoice') {
        return $totals;
    }

    global $Botoscope;

    if (property_exists($Botoscope, 'shipping')) {
        $delivery_methods = $Botoscope->shipping->get();
        $shipping_way = intval($order->get_meta('_botoscope_shipping_way'));
        $index = array_search($shipping_way, array_column($delivery_methods, 'id'));
        $bot_shipping_title = $delivery_methods[$index]['title'] ?? '';
        $bot_shipping_cost = floatval($order->get_meta('_botoscope_shipping_amount'));

        if ($bot_shipping_cost > 0) {
            $bot_shipping_cost = BOTOSCOPE_HELPER::woocs_exchange_value($bot_shipping_cost, $order->get_currency());
        }
    }

    $new_totals = [];

    foreach ($totals as $key => $total) {
        if ($key === 'order_total' && isset($bot_shipping_title)) {
            $new_totals['bot_shipping'] = [
                'label' => esc_html($bot_shipping_title),
                'value' => wc_price($bot_shipping_cost, ['currency' => $order->get_currency()])
            ];
        }

        $new_totals[$key] = $total;
    }

    return $new_totals;
}, 10, 3);
