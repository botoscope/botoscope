<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

include_once 'classes/support_interactions.php';

//02-04-2026
final class BOTOSCOPE_SUPPORT extends BOTOSCOPE_APP {

    protected $table_name = 'botoscope_support';
    protected $slug = 'support';
    protected $data_structure = [
        'object_type' => 'product',
        'object_id' => 0,
        'chat_id' => 0,
        'created' => 0,
        'is_active' => 1
    ];
    protected $interactions = NULL;
    protected $per_page = 10;

    public function __construct($args) {
        parent::__construct($args);
        $this->interactions = new BOTOSCOPE_SUPPORT_INTERACTIONS($args);

        //+++

        $instance = $this;
        $this->botoscope->allrest->add_rest_route('/support_messages/(?P<ticket_id>\d+)', function (WP_REST_Request $request) use ($instance) {
            $ticket_id = intval($request['ticket_id']);
            if ($ticket_id > 0) {
                $res = $instance->get_messages($ticket_id);
            }

            return $res;
        });

        $this->botoscope->allrest->add_rest_route('/support_get_ticket_id/(?P<chat_id>\d+)/(?P<id>\d+)/(?P<type>[a-zA-Z_]+)', function (WP_REST_Request $request) use ($instance) {
            $chat_id = intval($request['chat_id']);
            $object_id = intval($request['id']);
            $object_type = sanitize_text_field($request['type']);

            if ($chat_id > 0 && $object_id > 0 && !empty($object_type)) {
                $ticket_id = $instance->get_ticket_id($object_id, $object_type, $chat_id, false);
                return ['ticket_id' => $ticket_id];
            }

            return new WP_REST_Response(['error' => 'Invalid parameters'], 400);
        });

        add_action('wp_ajax_botoscope_support_get_ticket_id', array($this, 'botoscope_support_get_ticket_id'), 1);

        add_action('wp_ajax_botoscope_support_set_username', function () {
            $value = sanitize_text_field($_REQUEST['value']);
            $this->botoscope->controls->update('support_username', 'value', $value);
            $this->botoscope->reset_cache('controls');
            exit;
        }, 1);

        add_action('wp_ajax_botoscope_support_set_web_site', function () {
            $value = sanitize_text_field($_REQUEST['value']);
            $this->botoscope->controls->update('support_web_site', 'value', $value);
            $this->botoscope->reset_cache('controls');
            exit;
        }, 1);

        add_action('wp_ajax_botoscope_support_set_mode', function () {
            $value = sanitize_text_field($_REQUEST['value']);
            $this->botoscope->controls->update('support_mode', 'value', $value);
            $this->botoscope->reset_cache('controls');
            exit;
        }, 1);

        //+++

        Botoscope_Hooks::add_action('botoscope_panel_tabs', function ($tabs) {
            $tabs[$this->slug] = esc_html__('Support', 'botoscope');
            return $tabs;
        });

        Botoscope_Hooks::add_action('botoscope_add_row', function ($what, $parent_row_id, $content) {
            $res = null;

            if ($what === 'support_interactions_table') {
                $res = $this->create_message($parent_row_id, $content);
                $res['time'] = BOTOSCOPE_HELPER::format_time($res['time']);
            }

            return $res;
        });

        Botoscope_Hooks::add_action('botoscope_edit_cell', function ($what, $id, $key, $value) {
            if ($what === 'support_interactions_table') {
                switch ($key) {
                    case 'content':

                        $this->update_message($id, $key, $value);

                        break;
                }
            }
        });

        Botoscope_Hooks::add_action('botoscope_get_parent_cell_data', function ($parent_app, $parent_row_id, $parent_cell_name) {
            $res = [];

            if ($parent_app === $this->slug) {
                switch ($parent_cell_name) {
                    case 'interactions':
                        $res = $this->get_messages($parent_row_id);
                        break;
                }
            }

            return $res;
        });

        Botoscope_Hooks::add_action('botoscope_delete_row', function ($what, $row_id, $parent_row_id) {
            if ($what === 'support_interactions_table') {
                $this->delete_message($row_id);
            }
        });

        add_action("botoscope_{$this->slug}_tab_icon", function () {
            return 'lifebuoy';
        });

        add_action('botoscope_support_answer_to_customer', function ($args) {
            $this->create_message(intval($args['ticket_id']), sanitize_textarea_field($args['message']));
        });
    }

    public function get_messages($ticket_id) {
        $res = [];
        $tickets = $this->interactions->get_of($ticket_id);

        foreach ($tickets as $t) {

            foreach ($t as $key => $value) {
                if (!in_array($key, ['message_type', 'content'])) {
                    $t[$key] = intval($value);
                }

                if ($key === 'time') {
                    if (wp_doing_ajax()) {
                        $t[$key] = BOTOSCOPE_HELPER::format_time($value);
                    } else {
                        $t[$key] = $value;
                    }
                }
            }

            $res[] = $t;
        }

        return $res;
    }

    //message from site admin/manager
    public function create_message($ticket_id, $content) {

        $content = wp_strip_all_tags($content);

        $data_structure = $this->interactions->create([
            'ticket_id' => intval($ticket_id),
            'content' => $content,
            'message_type' => 'answer',
            'time' => time(),
            'is_new' => 0
        ]);

        $data = $this->get_ticket_by_id($ticket_id);

        switch ($data['object_type']) {
            case 'product':

                $product = wc_get_product($data['object_id']);

                if ($product) {
                    $object_title = $product->get_name();
                }

                break;

            case 'order':
                /* translators: %s: order ID */
                $object_title = sprintf(esc_html__('Order #%s', 'botoscope'), $data['object_id']);

                break;
        }

        $content = "<b>👉 {$object_title}</b>" . PHP_EOL . PHP_EOL . $content;
        $this->send_telegram_message($content, $data['chat_id'], $data['object_id'], $data['object_type']);
        $this->update($ticket_id, 'is_active', 0); //set as answered
        $this->update($ticket_id, 'updated', time());
        return $data_structure;
    }

    //customer sent message from botoscope shop
    public function receive_message($object_id, $chat_id, $content, $object_type) {
        $ticket_id = $this->get_ticket_id($object_id, $object_type, $chat_id);
        $ticket = $this->get_ticket_by_id($ticket_id);

        $data = [
            'ticket_id' => $ticket_id,
            'message_type' => 'question',
            'content' => wp_strip_all_tags($content),
            'time' => time(),
            'is_new' => 1
        ];

        $data_structure = $this->interactions->create($data);

        $this->update($ticket_id, 'is_active', 1); //set as not answered
        $this->update($ticket_id, 'updated', time());

        do_action('botoscope_support_receive_message', array_merge($data, ['object_type' => $object_type, 'object_id' => $object_id, 'hash_key' => $ticket['hash_key']]));

        return $data_structure;
    }

    public function get_ticket_by_id($ticket_id) {
        return $this->db->get_row(
                        $this->db->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $ticket_id),
                        ARRAY_A
                );
    }

    private function get_tickets_by_chat_id($chat_id) {
        return $this->db->get_results(
                        $this->db->prepare("SELECT * FROM {$this->table_name} WHERE chat_id = %d ORDER BY updated DESC", $chat_id),
                        ARRAY_A
                );
    }

    public function get_tickets_non_answered() {
        return $this->db->get_results(
                        $this->db->prepare("SELECT * FROM {$this->table_name} WHERE is_active = %d ORDER BY updated DESC", 1),
                        ARRAY_A
                );
    }

    public function get_ticket_id($object_id, $object_type, $chat_id = 0, $create_new = true) {
        $ticket_id = 0;

        if ($chat_id === 0 && $object_type === 'order') {
            $order = wc_get_order($object_id);
            $chat_id = intval($order->get_meta('_botoscope_chat_id'));
        }

        $row = $this->db->get_row(
                $this->db->prepare("SELECT * FROM {$this->table_name} WHERE object_id = %d AND chat_id = %d AND object_type = %s", $object_id, $chat_id, $object_type),
                ARRAY_A
        );

        //+++

        if (!empty($row)) {
            $ticket_id = intval($row['id']);
        } else {

            if ($create_new) {
                $data_structure = $this->create([
                    'object_id' => $object_id,
                    'object_type' => $object_type,
                    'chat_id' => $chat_id,
                    'hash_key' => md5(uniqid()), //security layer for business in the pocket
                    'created' => time(),
                    'updated' => time(),
                ]);

                $ticket_id = $data_structure['id'];
            }
        }

        return intval($ticket_id);
    }

    public function update_message($id, $field_key, $value) {
        $this->interactions->update($id, $field_key, $value);
    }

    public function get($page_num = 0) {
        $offset = $page_num * $this->per_page;

        if ($this->search) {
            $object_ids = $this->get_possible_posts_ids($this->search);
            if (empty($object_ids)) {
                $object_ids = [-1];
            }
            $object_ids = implode(',', $object_ids);

            $tickets = $this->db->get_results("SELECT * FROM `{$this->table_name}` WHERE object_id IN({$object_ids}) ORDER BY is_active DESC, updated DESC LIMIT $offset, {$this->per_page}", ARRAY_A);
        } else {
            $tickets = $this->db->get_results("SELECT * FROM `{$this->table_name}` ORDER BY is_active DESC, updated DESC LIMIT $offset, {$this->per_page}", ARRAY_A);
        }

        //+++

        if (!empty($tickets)) {
            foreach ($tickets as $row_id => $row) {

                switch ($row['object_type']) {
                    case 'product':

                        $product_id = intval($row['object_id']);
                        $product = wc_get_product($product_id);

                        if ($product) {
                            $row['object_title'] = $product->get_name();
                            $row['object_link'] = get_edit_post_link($product_id);
                        }

                        break;

                    case 'order':

                        $order_id = intval($row['object_id']);
                        $order = wc_get_order($order_id);

                        if ($order) {
                            /* translators: %s: order ID */
                            $row['object_title'] = sprintf(esc_html__('Order #%s', 'botoscope'), $order->get_id());
                            $row['object_link'] = get_edit_post_link($order_id);
                        }

                        break;
                }

                $row['messages_count'] = count($this->get_messages($row['id']));
                $row['updated'] = BOTOSCOPE_HELPER::format_time($row['updated']);

                $tickets[$row_id] = $row;
            }
        }

        return $tickets;
    }

    private function get_possible_posts_ids($search) {
        $res = [];
        $prefix = $this->db->prefix;

        if (!empty($search)) {
            if (is_numeric($search)) {
                // Search in wc_orders (HPOS) — only if HPOS is actually enabled
                if (class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
                    $query = $this->db->prepare(
                            "SELECT id FROM {$prefix}wc_orders WHERE id = %d",
                            absint($search)
                    );
                    $result = $this->db->get_results($query);
                    foreach ($result as $row) {
                        $res[] = $row->id;
                    }
                }

                // Also search in posts (old system or if HPOS is disabled)
                $query = $this->db->prepare(
                        "SELECT ID FROM {$prefix}posts WHERE ID = %d AND post_type IN ('shop_order', 'product')",
                        absint($search)
                );
                $result = $this->db->get_results($query);
                foreach ($result as $row) {
                    $res[] = $row->ID;
                }

                // Removing duplicates
                $res = array_unique($res);
            } else {
                // Text search for products only
                $search_like = '%' . $this->db->esc_like($search) . '%';
                $query = $this->db->prepare(
                        "SELECT ID FROM {$prefix}posts WHERE post_type = 'product' AND post_title LIKE %s",
                        $search_like
                );
                
                $result = $this->db->get_results($query);
                
                foreach ($result as $row) {
                    $res[] = $row->ID;
                }
            }
        }

        return $res;
    }

    public function delete_message($message_id) {
        $this->interactions->delete($message_id);
    }

    public function get_active($data) {
        $res = [];
        $chat_id = intval($data['chat_id']);
        $tickets = $this->get_tickets_by_chat_id($chat_id);

        if (!empty($tickets)) {
            foreach ($tickets as $t) {

                foreach ($t as $key => $value) {
                    if ($key !== 'object_type') {
                        $t[$key] = intval($value);
                    }
                }

                $t['count'] = count($this->interactions->get_ids($t['id']));
                $res[$t['id']] = $t;
            }
        }

        return $res;
    }

    //ajax
    public function botoscope_support_get_ticket_id() {
        if ($this->botoscope->is_ajax_request_valid()) {

            $ticket_id = 0;
            $object_type = sanitize_text_field($_REQUEST['object_type']);
            $object_id = intval($_REQUEST['object_id']);

            if ($object_id > 0) {
                $ticket_id = $this->get_ticket_id($object_id, $object_type);
            }
            
            echo intval($ticket_id);
            exit;
        }
    }

    //to set right pagination and etc for data table on the front
    public function get_table_attributes() {
        return [
            'per_page' => $this->per_page,
            'items_count' => $this->db->get_var("SELECT COUNT(*) FROM {$this->table_name}")
        ];
    }

    public function draw_content($counter) {
        $table_attributes = $this->get_table_attributes();
        $mode = $this->botoscope->controls->get_option('support_mode');
        $username = $this->botoscope->controls->get_option('support_username');
        $web_site = $this->botoscope->controls->get_option('support_web_site');
        ?>
        <section id="botoscope-<?php echo esc_attr($this->slug) ?>" <?php if ($counter === 0): ?>class="content-current"<?php endif; ?>>

            <select id="botoscope-<?php echo esc_attr($this->slug) ?>-mode">
                <option value="system" <?php echo $mode === 'system' ? 'selected' : '' ?>><?php esc_html_e('Use botoscope system', 'botoscope') ?></option>
                <option value="username" <?php echo $mode === 'username' ? 'selected' : '' ?>><?php esc_html_e('Use telegram username', 'botoscope') ?></option>
                <option value="web_site" <?php echo $mode === 'web_site' ? 'selected' : '' ?>><?php esc_html_e('Use web site', 'botoscope') ?></option>
            </select>

            <div style="display: <?php echo $mode === 'username' ? 'block' : 'none' ?>" id="botoscope-support-username-block">
                <input type="text" id="botoscope-<?php echo esc_attr($this->slug) ?>-username" value="<?php echo esc_html($username) ?>" placeholder="<?php esc_html_e('Telegram username for support', 'botoscope') ?>" />
            </div>

            <div style="display: <?php echo $mode === 'web_site' ? 'block' : 'none' ?>" id="botoscope-support-web-block">
                <input type="text" id="botoscope-<?php echo esc_attr($this->slug) ?>-web" value="<?php echo esc_html($web_site) ?>" placeholder="<?php esc_html_e('Web site link for support', 'botoscope') ?>" />
            </div>

            <div style="display: <?php echo $mode === 'system' ? 'block' : 'none' ?>" id="botoscope-support-system-block">
                <input type="search" id="botoscope-<?php echo esc_attr($this->slug) ?>-search" value="" placeholder="<?php esc_html_e('search by product title and order id', 'botoscope') ?>" />

                <div id="botoscope-<?php echo esc_attr($this->slug) ?>-w" data-per-page="<?php echo esc_attr($table_attributes['per_page']) ?>" data-items-count="<?php echo esc_attr($table_attributes['items_count']) ?>"><?php echo wp_json_encode($this->get(), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></div>
                <br>
                <a href="javascript: void(0);" id="botoscope_create_<?php echo esc_attr($this->slug) ?>" class="button button-primary"><?php esc_html_e('Contact customer about order', 'botoscope') ?></a><br>
            </div>
        </section>
        <?php
    }

    public function send_telegram_message($message, $chat_id, $object_id = 0, $type = 0) {
        if (!defined('BOTOSCOPE_BOT_TOKEN')) {
            return;
        }

        $token = BOTOSCOPE_BOT_TOKEN ?? '';
        $url = "https://api.telegram.org/bot{$token}/sendMessage";

        $reply_markup = [];
        if (!empty($object_id) && !empty($type)) {
            $reply_markup = wp_json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => '✉️️', 'callback_data' => "{$type}_ask_manager:{$object_id}"],
                        ['text' => '❌', 'callback_data' => 'remove_message_self']
                    ]
                ]
            ]);
        }

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
            $error_message = $response->get_error_message();
            return "Something went wrong: $error_message";
        } else {
            return 'Message sent successfully';
        }
    }

    protected function install() {
        global $wpdb;

        if (get_option("botoscope_{$this->table_name}_is_installed")) {
            return;
        }

        // Check MySQL version for utf8mb4 support
        $mysql_version = $wpdb->db_version();
        $supports_utf8mb4 = version_compare($mysql_version, '5.5.3', '>=');

        // Force utf8mb4 if MySQL supports it
        if ($supports_utf8mb4) {
            $charset_collate = 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        } else {
            $charset_collate = $wpdb->get_charset_collate();
        }

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
        id int(11) NOT NULL AUTO_INCREMENT,
        object_type set('product','order') NOT NULL,
        object_id int(11) NOT NULL,
        chat_id bigint(20) NOT NULL,
        hash_key varchar(32) NOT NULL,
        created bigint(20) NOT NULL,
        updated bigint(20) NOT NULL DEFAULT 0,
        is_active tinyint(1) NOT NULL DEFAULT 1 COMMENT 'means is client wrote smth',
        PRIMARY KEY (id),
        KEY hash_key (hash_key)
    ) ENGINE=InnoDB {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Additional safety measure: convert table after creation
        if ($supports_utf8mb4) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name is an internal class constant, not user input. wpdb->prepare() does not support table name placeholders.
            $wpdb->query("ALTER TABLE `" . esc_sql($this->table_name) . "` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }

        add_option("botoscope_{$this->table_name}_is_installed", 1);
    }
}
