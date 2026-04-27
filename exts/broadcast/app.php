<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

//23-01-2026
final class BOTOSCOPE_BROADCAST extends BOTOSCOPE_APP {

    protected $botoscope;
    protected $controls;
    protected $translations;
    protected $table_name = 'botoscope_broadcast';
    protected $slug = 'broadcast';
    protected $per_page = 10;
    protected $data_structure = [
        'title' => 'click to edit ...',
        'is_active' => 1
    ];

    public function __construct($args = []) {
        parent::__construct($args);

        Botoscope_Hooks::add_action('botoscope_panel_tabs', function ($tabs) {
            $tabs[$this->slug] = esc_html__('Broadcast', 'botoscope');
            return $tabs;
        });

        add_action("botoscope_{$this->slug}_tab_icon", function () {
            return 'megaphone';
        });

        Botoscope_Hooks::add_action('botoscope_get_sidebar_html', function ($what, $template_name, $id) {
            if ($what === $this->slug) {
                $data = [];
                $data['id'] = $id;
                $data['message'] = $this->get_column_value($id, 'message');
                $data['is_sent'] = $this->get_column_value($id, 'is_sent');
                $data['sent_time'] = $this->get_column_value($id, 'sent_time');
                $data['count'] = $this->get_column_value($id, 'count');
                BOTOSCOPE_HELPER::render_html_e(__DIR__ . "/views/{$template_name}.php", $data);
            }
        });

        Botoscope_Hooks::add_action('botoscope_edit_row', function ($what, $id, $data) {
            if ($what === $this->slug) {
                if (!empty($data) && $id > 0) {
                    foreach ($data as $field_key => $value) {
                        $this->update($id, $field_key, $value);
                    }
                }
            }
        });

        add_action("wp_ajax_botoscope_broadcast_message", function () {
            if ($this->botoscope->is_ajax_request_valid()) {
                $count = $this->botoscope->users->send_message(sanitize_textarea_field($_REQUEST['value']));
                $this->update(intval($_REQUEST['message_id']), 'is_sent', 1);
                $this->update(intval($_REQUEST['message_id']), 'sent_time', current_time('timestamp'));
                $this->update(intval($_REQUEST['message_id']), 'count', intval($count));

                echo intval($count);
                exit;
            }
        }, 1);
    }

    public function get($page_num = 0) {
        $res = parent::get();

        return $res;
    }

    public function get_active() {
        $res = [];
        return $res;
    }

    public function draw_content($counter) {
        ?>

        <section id="botoscope-<?php echo esc_attr($this->slug) ?>" <?php if ($counter === 0): ?>class="content-current"<?php endif; ?>>

            <div id="botoscope-<?php echo esc_attr($this->slug) ?>-w"><?php echo wp_json_encode($this->get(), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></div>
            <br>
            <a href="javascript: void(0);" id="botoscope_create_<?php echo esc_attr($this->slug) ?>" class="button button-primary"><?php esc_html_e('Create', 'botoscope') ?></a><br>

        </section>

        <?php
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
        title text DEFAULT NULL,
        message text DEFAULT NULL,
        is_active smallint(1) NOT NULL DEFAULT 0,
        is_sent smallint(1) NOT NULL DEFAULT 0,
        sent_time bigint(20) DEFAULT NULL,
        count int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (id)
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
