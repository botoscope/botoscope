<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

//17-12-2025
final class BOTOSCOPE_MARKETING_STRATEGIES_FORMULAS extends BOTOSCOPE_APP {

    protected $table_name = 'botoscope_marketing_strategies_formulas';
    protected $data_structure = [
        'formula' => '',
        'strategia_id' => 0
    ];

    public function __construct($args = []) {
        parent::__construct($args);
    }

    public function get_of($strategia_id) {
        $res = [];
        $all = $this->get();

        if (!empty($all)) {
            foreach ($all as $f) {
                if (intval($f['strategia_id']) === intval($strategia_id)) {
                    $res[] = $f;
                }
            }
        }

        return $res;
    }

    public function get_ids($strategia_id) {
        return array_column($this->get_of($strategia_id), 'id');
    }

    protected function install() {
        global $wpdb;

        if (get_option("botoscope_{$this->table_name}_is_installed")) {
            return;
        }

        $mysql_version = $wpdb->db_version();
        $supports_utf8mb4 = version_compare($mysql_version, '5.5.3', '>=');

        if ($supports_utf8mb4) {
            $charset_collate = 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        } else {
            $charset_collate = $wpdb->get_charset_collate();
        }

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
        id int(11) NOT NULL AUTO_INCREMENT,
        formula varchar(128) DEFAULT NULL,
        strategia_id int(11) NOT NULL DEFAULT 0,
        menu_order int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY strategia_id (strategia_id)
    ) ENGINE=InnoDB {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        if ($supports_utf8mb4) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name is an internal class constant, not user input. wpdb->prepare() does not support table name placeholders.
            $wpdb->query("ALTER TABLE `" . esc_sql($this->table_name) . "` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }

        add_option("botoscope_{$this->table_name}_is_installed", 1);

        $default_data = [
            [
                'formula' => 'c>=2; 100%; mc',
                'strategia_id' => 1,
            ],
            [
                'formula' => 'c>=2; 50%; mc',
                'strategia_id' => 2,
                'menu_order' => 1
            ],
            [
                'formula' => 'c>=3; 100%; mc, cap=2',
                'strategia_id' => 2,
                'menu_order' => 2
            ],
            [
                'formula' => 'c>=1; 50%;',
                'strategia_id' => 3,
            ],
        ];

        foreach ($default_data as $data) {
            $wpdb->insert($this->table_name, $data);
        }
    }
}
