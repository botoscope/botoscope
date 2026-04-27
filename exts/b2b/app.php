<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

//17-12-2025
final class BOTOSCOPE_B2B extends BOTOSCOPE_APP {

    protected $table_name = 'botoscope_b2b';
    protected $slug = 'b2b';
    protected $data_structure = [];
    protected $controls = [];

    public function __construct($args) {
        parent::__construct($args);

        if (botoscope_is_no_cart()) {
            return false;
        }

        $this->controls = [
            'min_cart_amount' => [
                'title' => esc_html__('Min cart amount to pay', 'botoscope'),
                'value' => 0,
                'description' => esc_html__('The minimum cart total required to enable checkout and payment', 'botoscope')
            ],
            'show_on_cart_set_qty_btn' => [
                'title' => esc_html__('Display quantity adjustment button in cart item', 'botoscope'),
                'value' => 0,
                'description' => esc_html__('Adds a button to each cart item that prompts users in Telegram to enter a custom quantity', 'botoscope')
            ]
        ];

        Botoscope_Hooks::add_action('botoscope_panel_tabs', function ($tabs) {
            $tabs[$this->slug] = 'B2B';
            return $tabs;
        });

        add_action("botoscope_{$this->slug}_tab_icon", function () {
            return 'building';
        });

        $this->botoscope->allrest->add_rest_route($this->slug, [$this, 'register_route']);
    }

    public function register_route(WP_REST_Request $request) {
        return $this->get_active();
    }

    public function get($exept_exts = 0) {
        $res = [];

        foreach ($this->controls as $key => $o) {

            $value = $this->get_option($key) ?? $o['value'];

            $res[] = [
                'id' => $key,
                'title' => $o['title'],
                'description' => $o['description'],
                'value' => $value,
                'is_active' => 1
            ];
        }

        if ($exept_exts) {
            return $res;
        }

        return $res;
    }

    public function update($id, $key, $value, $all_sent_data = []) {
        $this->update_option($id, $key, $value);
    }

    private function update_option($key, $field_key, $value) {
        $row = $this->get_row($key);

        if (empty($row)) {
            $this->db->insert($this->table_name, [
                'control_key' => $key,
            ]);

            $id = $this->db->insert_id;
        } else {
            $id = intval($row['id']);
        }

        //***

        $this->db->update($this->table_name, [$field_key => $value], ['id' => $id]);
    }

    public function get_option($key, $field_key = 'value') {
        $row = $this->get_row($key);

        if (empty($row)) {
            $row[$field_key] = $this->controls[$key]['value'];
        }

        return $row[$field_key];
    }

    private function get_row($key) {
        return $this->db->get_row(
                        $this->db->prepare("SELECT * FROM `{$this->table_name}` WHERE control_key = %s", $key),
                        ARRAY_A
                );
    }

    public function get_active() {
        $res = [];
        $rows = $this->get();

        if (!empty($rows)) {
            foreach ($rows as $v) {
                $res[$v['id']] = $v['value'];
            }
        }

        return $res;
    }

    public function draw_content($counter) {
        ?>
        <section id="botoscope-<?php echo esc_attr($this->slug) ?>" <?php if ($counter === 0): ?>class="content-current"<?php endif; ?>>
            <div id="botoscope-<?php echo esc_attr($this->slug) ?>-w"><?php echo wp_json_encode($this->get(true), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></div>
        </section>
        <?php
    }

    protected function install() {
        global $wpdb;

        if (get_option("botoscope_{$this->table_name}_is_installed")) {
            return;
        }

        add_option("botoscope_{$this->table_name}_is_installed", 1); //!!
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
        control_key varchar(32) DEFAULT NULL,
        value text DEFAULT NULL,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Additional safety measure: convert table after creation
        if ($supports_utf8mb4) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name is an internal class constant, not user input. wpdb->prepare() does not support table name placeholders.
            $wpdb->query("ALTER TABLE `" . esc_sql($this->table_name) . "` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }

        // Insert default data
        $default_data = [
            [
                'control_key' => 'test',
                'value' => '1'
            ]
        ];

        foreach ($default_data as $data) {
            $wpdb->insert($this->table_name, $data);
        }
    }
}
