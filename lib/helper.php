<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

//14-04-2026
final class BOTOSCOPE_HELPER {

    public static function render_html_e($pagepath, $data = array()) {

        if (is_array($data) AND !empty($data)) {
            if (isset($data['pagepath'])) {
                unset($data['pagepath']);
            }
            extract($data);
        }

        //***
        include(str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $pagepath));
    }

    public static function sanitize_array($array) {
        if (is_array($array)) {
            $sanitized = [];
            foreach ($array as $key => $value) {
                $clean_key = sanitize_key($key);
                if (is_array($value)) {
                    $sanitized[$clean_key] = self::sanitize_array($value);
                } else {
                    $sanitized[$clean_key] = sanitize_text_field($value);
                }
            }
            return $sanitized;
        }
        return $array;
    }

    public static function compare_assoc_arrays($array1, $array2) {
        $diff1 = array_diff_assoc($array1, $array2);
        $diff2 = array_diff_assoc($array2, $array1);

        if (empty($diff1) && empty($diff2)) {
            return true;
        }

        return false;
    }

    public static function encrypt_value($value, $salt) {
        return hash_hmac('sha256', $value, $salt);
    }

    public static function encrypt_array($data, $salt) {
        $json = wp_json_encode($data);
        return base64_encode($salt . $json);
    }

    public static function woocs_exchange_value($value, $selected_currency) {
        global $WOOCS;

        if ($WOOCS && $WOOCS->default_currency !== $selected_currency) {
            $currencies = $WOOCS->get_currencies();
            $value = floatval($value) * floatval($currencies[$selected_currency]['rate']);
            $precision = $WOOCS->get_currency_price_num_decimals($selected_currency, $WOOCS->price_num_decimals);
            $value = number_format($value, $precision, $WOOCS->decimal_sep, '');
        }

        return $value;
    }

    public static function get_product_variation_string($attributes) {
        $attribute_strings = [];
        /*
         * $attributes
          [pa_color] => 18
          [pa_material] => 25
          [pa_size] => 30
         */

        foreach ($attributes as $attribute_name => $attribute_value) {
            $attribute_label = wc_attribute_label($attribute_name);
            $term = get_term($attribute_value);
            $attribute_value_label = $term ? mb_strtolower($term->name) : esc_html__('unknown', 'botoscope');
            $attribute_strings[] = "{$attribute_label}: {$attribute_value_label}";
        }

        return implode(", ", $attribute_strings);
    }

    public static function format_time($time) {
        try {
            if (is_numeric($time)) {
                // Timestamp
                $datetime = new DateTime('@' . intval($time));
            } else {
                $datetime = new DateTime($time);
            }
            $datetime->setTimezone(wp_timezone());
            $format = get_option('date_format') . ' ' . get_option('time_format');
            return $datetime->format($format);
        } catch (Exception $e) {
            return '';
        }
    }

    public static function http_request(string $url, array $opts = []): array {
        $timeout = $opts['timeout'] ?? 8;
        $headers = $opts['headers'] ?? [];
        $method = strtoupper($opts['method'] ?? 'GET');
        $body = $opts['body'] ?? null;

        $args = [
            'method' => $method,
            'timeout' => $timeout,
            'headers' => $headers,
        ];
        if ($body !== null) {
            $args['body'] = $body;
        }

        $wp_response = wp_remote_request($url, $args);

        if (is_wp_error($wp_response)) {
            return ['ok' => false, 'code' => 0, 'error' => $wp_response->get_error_message(), 'body' => null];
        }

        $code = wp_remote_retrieve_response_code($wp_response);
        $response = wp_remote_retrieve_body($wp_response);

        return ['ok' => ($code >= 200 && $code < 300), 'code' => $code, 'error' => null, 'body' => $response];
    }
}
