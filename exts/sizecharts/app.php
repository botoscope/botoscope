<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

include_once 'classes/sizecharts_tables.php';

//31-12-2024
final class BOTOSCOPE_SIZECHARTS extends BOTOSCOPE_APP {

    protected $table_name = 'botoscope_sizecharts';
    protected $slug = 'sizecharts';
    protected $data_structure = [
        'title' => 'click to edit ...',
        'description' => '',
        'is_default' => 0,
        'is_active' => 0
    ];
    protected $chart_tables;

    public function __construct($args = []) {
        parent::__construct($args);

        if (botoscope_is_no_cart()) {
            return false;
        }

        $this->chart_tables = new BOTOSCOPE_SIZECHARTS_TABLES($args);

        $this->botoscope->allrest->add_rest_route($this->slug, [$this, 'register_route']);

        Botoscope_Hooks::add_action('botoscope_panel_tabs', function ($tabs) {
            $tabs[$this->slug] = esc_html__('Size charts', 'botoscope');
            return $tabs;
        });

        Botoscope_Hooks::add_action('botoscope_add_row', function ($what, $parent_row_id, $content) {
            $res = null;

            if ($what === 'chart_table') {
                $res = $this->create_chart_table($parent_row_id);
            }

            return $res;
        });

        Botoscope_Hooks::add_action('botoscope_edit_cell', function ($what, $id, $key, $value) {
            if ($what === 'chart_table') {
                $this->update_chart_table_cell($id, $key, $value);
                $this->botoscope->reset_cache($this->slug);
            }
        });

        Botoscope_Hooks::add_action('botoscope_get_parent_cell_data', function ($parent_app, $parent_row_id, $parent_cell_name) {
            $res = [];

            if ($parent_app === $this->slug) {
                switch ($parent_cell_name) {
                    case 'sizecharts_chart_tables':
                        $res = $this->get_chart_tables($parent_row_id);
                        break;
                }
            }

            return $res;
        });

        Botoscope_Hooks::add_action('botoscope_delete_row', function ($what, $row_id, $parent_row_id) {
            if ($what === 'chart_table') {
                $this->delete_chart_table($row_id);
                $this->botoscope->reset_cache($this->slug);
            }
        });

        add_action("botoscope_{$this->slug}_tab_icon", function () {
            return 'resize-full';
        });
    }

    public function register_route(WP_REST_Request $request) {
        $res = [
            'default' => $this->get_default_id(),
            'charts' => $this->get_active()
        ];

        return $res;
    }

    public function get($page_num = 0) {
        $res = parent::get();

        if (!empty($res)) {
            foreach ($res as $k => $st) {
                $res[$k]['table'] = $this->chart_tables->get_ids($st['id']);
            }
        }

        return $res;
    }

    public function update($id, $field_key, $value, $all_sent_data = []) {
        if (empty($id) || empty($field_key)) {
            return false;
        }

        if ($field_key === 'is_default') {
            $all = $this->get();

            foreach ($all as $sch) {
                if (intval($sch['id']) !== intval($id)) {
                    $this->db->update($this->table_name, [$field_key => 0], ['id' => intval($sch['id'])]);
                }
            }
        }

        return $this->db->update($this->table_name, [$field_key => $value], ['id' => intval($id)]);
    }

    public function get_chart_tables($id) {
        return (array) $this->chart_tables->get_of($id);
    }

    public function delete_chart_table($chid) {
        $this->chart_tables->delete($chid);
    }

    public function update_chart_table_cell($id, $key, $value) {
        $this->chart_tables->update($id, $key, $value);
    }

    public function create_chart_table($sizechart_id) {
        $data_structure = $this->chart_tables->create([
            'sizechart_id' => $sizechart_id
        ]);

        return $data_structure;
    }

    public function delete($id, $conditions = []) {

        $chart_tables_ids = $this->chart_tables->get_ids($id);

        if (!empty($chart_tables_ids)) {
            foreach ($chart_tables_ids as $chid) {
                $this->chart_tables->delete($chid);
            }
        }

        parent::delete($id);
    }

    public function get_active() {

        $res = [];
        $rows = $this->get();

        if (!empty($rows)) {
            foreach ($rows as $r) {

                if (!intval($r['is_active'])) {
                    continue;
                }

                $r['table'] = [];
                $r['id'] = intval($r['id']);
                $tables = $this->get_chart_tables($r['id']);

                if (!empty($tables)) {
                    foreach ($tables as $row) {

                        $chid = $row['id'];
                        unset($row['id']);
                        unset($row['sizechart_id']);
                        $r['table'][$chid] = [];

                        foreach ($row as $key => $value) {
                            $range = explode(',', $value);
                            $r['table'][$chid][$key] = array_map('intval', $range);
                        }
                    }
                }

                unset($r['is_default']);
                unset($r['is_active']);

                array_push($res, $r);
            }
        }

        return $res;
    }

    private function get_default_id() {
        return intval($this->db->get_var($this->db->prepare("SELECT `id` FROM `{$this->table_name}` WHERE `is_default` = %d", 1)));
    }

    public function draw_content($counter) {
        ?>

        <section id="botoscope-<?php echo esc_attr($this->slug) ?>" <?php if ($counter === 0): ?>class="content-current"<?php endif; ?>>

            <div id="botoscope-<?php echo esc_attr($this->slug) ?>-w"><?php echo wp_json_encode($this->get(), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></div>
            <br>
            <a href="javascript: void(0);" id="botoscope_create_<?php echo esc_attr($this->slug) ?>" class="button button-primary"><?php esc_html_e('New sizechart', 'botoscope') ?></a><br>

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
        title varchar(64) DEFAULT NULL,
        is_default tinyint(1) NOT NULL DEFAULT 0,
        description text DEFAULT NULL,
        is_active smallint(1) NOT NULL DEFAULT 0,
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

        // Insert default data
        $default_data = [
            [
                'title' => esc_html__('EXAMPLE brand name', 'botoscope'),
                'description' => esc_html__('Sizes corresponding to brand EXAMPLE', 'botoscope'),
                'is_default' => 1
            ]
        ];

        foreach ($default_data as $data) {
            $wpdb->insert($this->table_name, $data);
        }
    }
}
