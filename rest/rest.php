<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

//08-04-2026
class BOTOSCOPE_REST {

    public function __construct($botoscope) {
        $this->botoscope = $botoscope;
    }

    public function get_request_data($salt = '', $salted_fields = []) {
        $requestUri = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']));

        // Extract the part after the "?" sign (GET parameters)
        $queryString = wp_parse_url($requestUri, PHP_URL_QUERY);

        parse_str($queryString, $getParams);

        // If salt is set, only process encrypted fields
        if (!empty($salt) && !empty($salted_fields)) {
            foreach ($getParams as $key => $value) {
                // Check if a field is encrypted
                if (in_array($key, $salted_fields, true)) {
                    $decodedValue = base64_decode($value);
                    // We remove the salt
                    if (strpos($decodedValue, $salt) === 0) {
                        $getParams[$key] = substr($decodedValue, strlen($salt));
                    } else {
                        throw new Exception("Invalid salt in field: {$key}"); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                    }
                }
            }
        }

        return $getParams;
    }

    public function authenticate_request(WP_REST_Request $request) {
        if ($this->botoscope->debug) {
            return true;
        }

        if (!defined('BOTOSCOPE_CLIENT_API_KEY')) {
            return new WP_Error('rest_forbidden', 'Plugin is not configured', ['status' => 403]);
        }

        $headers = $request->get_headers();

        if (!isset($headers['authorization']) || !isset($headers['client_api_key'])) {
            return new WP_Error('rest_forbidden', 'Authorization header is wrong', ['status' => 403]);
        }

        $auth_header = $headers['authorization'][0];
        @list($auth_type, $auth_credentials) = explode(' ', $auth_header, 2);

        if ($auth_type !== 'Bearer') {
            return new WP_Error('rest_forbidden', 'Invalid authorization type', ['status' => 403]);
        }

        if (defined('BOTOSCOPE_CLIENT_PASS')) {
            if (BOTOSCOPE_HELPER::encrypt_value(BOTOSCOPE_CLIENT_PASS, BOTOSCOPE_CLIENT_API_KEY) !== $auth_credentials) {
                return new WP_Error('rest_forbidden', 'Invalid authorization data', ['status' => 403]);
            }
        } else {
            return new WP_Error('rest_forbidden', 'Server is not configured for REST auth', ['status' => 403]);
        }


        if (isset($headers['language'])) {
            $_REQUEST['botoscope_rest_language'] = $headers['language'];
        }

        return true;
    }
}
