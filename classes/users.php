<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

//22-04-2026
final class BOTOSCOPE_USERS extends BOTOSCOPE_APP {

    protected $table_name = 'botoscope_users';
    protected $slug = 'users';

    public function __construct($args) {
        parent::__construct($args);
        $this->botoscope->allrest->add_rest_route($this->slug, function (WP_REST_Request $request) {
            return $this->get();
        });

        $this->botoscope->allrest->add_rest_route('create_subscriber', function (WP_REST_Request $request) {
            $data = $request->get_json_params();

            $chat_id = intval($data['chat_id'] ?? 0);
            $first_name = sanitize_text_field($data['first_name'] ?? '');
            $last_name = sanitize_text_field($data['last_name'] ?? '');
            $language_code = sanitize_text_field($data['language_code'] ?? 'en');
            $is_subscribed = sanitize_text_field($data['is_subscribed'] ?? 0);
            $user_id = $this->create_subscriber($chat_id, $first_name, $last_name, $language_code, $is_subscribed);

            return new WP_REST_Response(['user_id' => $user_id], 200);
        }, 'POST');
    }

    public function create_subscriber($chat_id, $first_name, $last_name, $language_code, $is_subscribed = 0) {
        $user_id = 0;

        if ($chat_id > 0) {
            //Let's try to find a user by chat_id
            $users = get_users([
                'meta_key' => 'botoscope_chat_id',
                'meta_value' => $chat_id,
                'number' => 1,
                'count_total' => false,
                'fields' => ['ID'],
            ]);

            if (!empty($users)) {
                $user_id = $users[0]->ID;
            } else {
                //Create a new user
                $username = 'botoscope_' . $chat_id;
                $password = wp_generate_password();

                $user_id = wp_insert_user([
                    'user_login' => $username,
                    'user_pass' => $password,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'role' => 'subscriber',
                ]);

                update_user_meta($user_id, 'botoscope_chat_id', $chat_id);
                update_user_meta($user_id, 'botoscope_language_code', $language_code);
                update_user_meta($user_id, 'botoscope_is_subscribed', $is_subscribed);
            }
        }

        return $user_id;
    }

    public function get_active() {
        return $this->get();
    }

    public function get($page_num = 0) {
        $users = get_users([
            'meta_key' => 'botoscope_chat_id',
            'meta_compare' => 'EXISTS',
        ]);

        $result = [];

        foreach ($users as $user) {
            $chat_id = get_user_meta($user->ID, 'botoscope_chat_id', true);

            $result[] = [
                'id' => $user->ID,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'chat_id' => $chat_id,
                'is_subscribed' => intval(get_user_meta($user->ID, 'botoscope_is_subscribed', true))
            ];
        }

        return $result;
    }

    public function send_message($message) {
        $users = $this->get();

        if (!empty($users)) {
            foreach ($users as $user) {
                $message = str_replace('%name%', $user['first_name'] . " " . $user['last_name'], $message);
                $this->send_telegram_message($message, $user['chat_id']);
                usleep(wp_rand(150000, 300000)); // random from 150 to 300 ms
            }
        }

        return count($users);
    }

    private function send_telegram_message($message, $chat_id) {

        if (!defined('BOTOSCOPE_BOT_TOKEN')) {
            return;
        }

        $token = BOTOSCOPE_BOT_TOKEN;
        $url = "https://api.telegram.org/bot{$token}/sendMessage";

        $reply_markup = wp_json_encode([
            'inline_keyboard' => [
                [
                    ['text' => '❌', 'callback_data' => 'remove_message_self']
                ]
            ]
        ]);

        $body = [
            'chat_id' => $chat_id,
            'text' => $message,
            'parse_mode' => 'HTML',
            'reply_markup' => $reply_markup
        ];

        $args = [
            'body' => wp_json_encode($body),
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'timeout' => 15,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking' => true,
            'sslverify' => false,
        ];

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            echo esc_html($response->get_error_message());
            return false;
        }

        return true;
    }

    public function get_bot_users() {
        return $this->db->get_results("
            SELECT DISTINCT u.ID,u.display_name
            FROM {$this->db->users} u
            LEFT JOIN {$this->db->usermeta} um ON um.user_id = u.ID AND um.meta_key = 'botoscope_chat_id'
            WHERE u.user_nicename LIKE 'botoscope_%'
               OR um.meta_value IS NOT NULL
        ", ARRAY_A);
    }
}
