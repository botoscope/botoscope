<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

include_once 'marketing_strategies_formulas.php';

//17-12-2025
final class BOTOSCOPE_MARKETING_STRATEGIES extends BOTOSCOPE_APP {

    protected $table_name = 'botoscope_marketing_strategies';
    protected $slug = 'marketing_strategies';
    protected $data_structure = [
        'title' => 'click to edit ...',
        'description' => '',
        'is_active' => 0
    ];
    protected $formulas;

    public function __construct($args = []) {
        parent::__construct($args);

        if (botoscope_is_no_cart()) {
            return false;
        }

        $this->formulas = new BOTOSCOPE_MARKETING_STRATEGIES_FORMULAS($args);

        $this->botoscope->allrest->add_rest_route($this->slug, [$this, 'register_route']);

        //+++

        Botoscope_Hooks::add_action('botoscope_add_row', function ($what, $parent_row_id, $content) {
            $res = null;

            if ($what === $this->slug) {
                $res = $this->create();
            }

            if ($what === 'marketing_strategies_formulas_table') {
                $res = $this->create_formula($parent_row_id);
            }

            return $res;
        });

        Botoscope_Hooks::add_action('botoscope_edit_cell', function ($what, $id, $key, $value) {

            if ($what === $this->slug) {
                $this->update($id, $key, $value);
                $this->botoscope->reset_cache($this->slug);
            }

            if ($what === 'marketing_strategies_formulas_table') {
                $this->update_formula($id, $value);
                $this->botoscope->reset_cache($this->slug);
            }
        });

        Botoscope_Hooks::add_action('botoscope_get_parent_cell_data', function ($parent_app, $parent_row_id, $parent_cell_name) {
            $res = [];
            if ($parent_app === $this->slug) {
                switch ($parent_cell_name) {
                    case 'formulas':
                        $res = $this->get_formulas($parent_row_id);
                        break;
                }
            }

            return $res;
        });

        Botoscope_Hooks::add_action('botoscope_delete_row', function ($what, $row_id, $parent_row_id) {
            if ($what === $this->slug) {
                return $this->delete($row_id);
            }

            if ($what === 'marketing_strategies_formulas_table') {
                $this->delete_formula($row_id);
                $this->botoscope->reset_cache($this->slug);
            }
        });
    }

    public function register_route(WP_REST_Request $request) {
        return $this->get_active();
    }

    public function get($page_num = 0) {
        $res = parent::get();

        if (!empty($res)) {
            foreach ($res as $k => $st) {
                $res[$k]['formulas'] = $this->formulas->get_ids($st['id']);
            }
        }

        return $res;
    }

    public function get_formulas($id) {
        return (array) $this->formulas->get_of($id);
    }

    public function delete_formula($fid) {
        $this->formulas->delete($fid);
    }

    public function update_formula($id, $value) {
        $this->formulas->update($id, 'formula', $value);
    }

    public function create_formula($strategia_id) {
        $data_structure = $this->formulas->create([
            'strategia_id' => $strategia_id
        ]);

        return $data_structure;
    }

    public function get_active() {

        $res = [];
        $rows = $this->get();

        if (!empty($rows)) {
            foreach ($rows as $r) {

                if (!intval($r['is_active'])) {
                    continue;
                }

                $r['formulas'] = array_column($this->get_formulas($r['id']), 'formula');

                unset($r['is_active']);
                $r['id'] = intval($r['id']);
                array_push($res, $r);
            }
        }

        return $res;
    }

    public function delete($id, $conditions = []) {
        $formulas_ids = $this->formulas->get_ids($id);

        if (!empty($formulas_ids)) {
            foreach ($formulas_ids as $fid) {
                $this->formulas->delete($fid);
            }
        }

        parent::delete($id);
    }

    public function draw_content($counter) {
        ?>

        <section id="botoscope-<?php echo esc_attr($this->slug) ?>" <?php if ($counter === 0): ?>class="content-current"<?php endif; ?>>

            <div id="botoscope-<?php echo esc_attr($this->slug) ?>-w"><?php echo wp_json_encode($this->get(), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></div>
            <br>
            <a href="javascript: void(0);" id="botoscope_create_<?php echo esc_attr($this->slug) ?>" class="button button-primary"><?php esc_html_e('New Marketing strategy', 'botoscope') ?></a><br>

            <template id="<?php echo esc_attr($this->slug) ?>_local_cache"><?php echo wp_json_encode($this->get(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></template>

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
                'title' => esc_html__('2 + 1', 'botoscope'),
                'description' => esc_html__('When more than 2 items are purchased, the cheapest one is free', 'botoscope'),
                'is_active' => 1
            ],
            [
                'title' => esc_html__('1+1=3 or 2+1', 'botoscope'),
                'description' => esc_html__('When 2 items are purchased, the second one is 50% off; when 3 or more are purchased, the cheapest one is free', 'botoscope'),
                'is_active' => 1
            ],
            [
                'title' => esc_html__('Black Friday', 'botoscope'),
                'description' => esc_html__('Applies XX% off to all items', 'botoscope'),
                'is_active' => 1
            ],
        ];

        foreach ($default_data as $data) {
            $wpdb->insert($this->table_name, $data);
        }
    }
}
