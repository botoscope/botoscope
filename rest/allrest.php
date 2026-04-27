<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

include_once 'rest.php';

//02-04-2026
final class BOTOSCOPE_REST_ALLREST extends BOTOSCOPE_REST {

    public $botoscope = null;

    public function __construct($botoscope) {
        $this->botoscope = $botoscope;
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes() {

        add_filter('rest_authentication_errors', function ($result) {
            // Close only the root botoscope/v3 (list of endpoints)
            if (preg_match('/wp-json\/botoscope\/v3\/?$/', sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])))) {
                return new WP_Error(
                        'rest_disabled',
                        'REST API index disabled',
                        ['status' => 403]
                );
            }

            return $result;
        });

        //+++

        register_rest_route('botoscope/v3', '/receive_command', array(
            'methods' => array('POST'),
            'callback' => array($this, 'receive_command'),
            'permission_callback' => array($this, 'authenticate_request'),
        ));
    }

    public function receive_command(WP_REST_Request $data) {
        if (isset($data['command'])) {
            switch ($data['command']) {
                case 'ask_manager_about_object':
                    $object_id = intval($data['object_id']);
                    $chat_id = intval($data['chat_id']);
                    $content = esc_html($data['content']);
                    $type = esc_html($data['type']);

                    if (isset($this->botoscope->support)) {
                        if ($object_id > 0 && $chat_id > 0 && !empty($content) && !empty($type)) {
                            $this->botoscope->support->receive_message($object_id, $chat_id, $content, $type);
                        }
                    }

                    break;
            }
        }
    }

    //for exts
    public function add_rest_route($path, $callback, $method = 'GET', $public = false) {
        $instance = $this;
        add_action('rest_api_init', function () use ($path, $callback, $instance, $method, $public) {
            register_rest_route('botoscope/v3', '/' . $path, array(
                'methods' => $method,
                'callback' => function (WP_REST_Request $request) use ($callback) {
                    $res = call_user_func($callback, $request);
                    return new WP_REST_Response($res, 200);
                },
                'permission_callback' => $public ? '__return_true' : array($instance, 'authenticate_request'),
            ));
        });
    }
}
