<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

//18-12-2024
final class BOTOSCOPE_SIZECHARTS_TABLES extends BOTOSCOPE_APP {

    protected $table_name = 'botoscope_sizecharts_tables';
    protected $data_structure = [
        'sizechart_id' => 0,
        'height' => 0,
        'neck' => 0,
        'shoulder' => 0,
        'breast' => 0,
        'waist' => 0,
        'hip' => 0,
        'arm' => 0,
        'leg_length_from_waist' => 0
    ];

    public function __construct($args = []) {
        parent::__construct($args);
    }

    public function get_of($sizechart_id) {
        $res = [];
        $all = $this->get();

        if (!empty($all)) {
            foreach ($all as $f) {
                if (intval($f['sizechart_id']) === intval($sizechart_id)) {
                    $res[] = $f;
                }
            }
        }

        return $res;
    }

    public function get_ids($sizechart_id) {
        return array_column($this->get_of($sizechart_id), 'id');
    }

    protected function process_value_before_upfate($value, $field_key) {
        return str_replace(' ', '', $value);
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
        sizechart_id int(11) NOT NULL,
        height varchar(16) DEFAULT NULL,
        neck varchar(16) DEFAULT NULL,
        shoulder varchar(16) DEFAULT NULL,
        breast varchar(16) DEFAULT NULL,
        waist varchar(16) DEFAULT NULL,
        hip varchar(16) DEFAULT NULL,
        arm varchar(16) DEFAULT NULL,
        leg_length_from_waist varchar(16) DEFAULT NULL,
        PRIMARY KEY (id),
        KEY sizechart_id (sizechart_id)
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
                'sizechart_id' => 1,
                'height' => '160,163',
                'neck' => '34',
                'shoulder' => '36',
                'breast' => '82,85',
                'waist' => '58,62',
                'hip' => '86,90',
                'arm' => '72,74',
                'leg_length_from_waist' => '101,104',
            ],
            [
                'sizechart_id' => 1,
                'height' => '164,167',
                'neck' => '35',
                'shoulder' => '37',
                'breast' => '86,89',
                'waist' => '62,66',
                'hip' => '90,94',
                'arm' => '74,76',
                'leg_length_from_waist' => '104,107',
            ],
            [
                'sizechart_id' => 1,
                'height' => '168,171',
                'neck' => '36',
                'shoulder' => '38',
                'breast' => '90,93',
                'waist' => '66,70',
                'hip' => '94,98',
                'arm' => '76,78',
                'leg_length_from_waist' => '107,109',
            ],
            [
                'sizechart_id' => 1,
                'height' => '172,175',
                'neck' => '37',
                'shoulder' => '39',
                'breast' => '94,97',
                'waist' => '70,74',
                'hip' => '98,102',
                'arm' => '78,80',
                'leg_length_from_waist' => '109,112',
            ],
            [
                'sizechart_id' => 1,
                'height' => '176,179',
                'neck' => '38',
                'shoulder' => '40',
                'breast' => '98,102',
                'waist' => '74,78',
                'hip' => '102,106',
                'arm' => '80,82',
                'leg_length_from_waist' => '112,115',
            ],
            [
                'sizechart_id' => 1,
                'height' => '180,183',
                'neck' => '39',
                'shoulder' => '41',
                'breast' => '102,106',
                'waist' => '78,82',
                'hip' => '106,110',
                'arm' => '82,84',
                'leg_length_from_waist' => '115,118',
            ],
            [
                'sizechart_id' => 1,
                'height' => '184,187',
                'neck' => '40',
                'shoulder' => '42',
                'breast' => '106,110',
                'waist' => '82,86',
                'hip' => '110,114',
                'arm' => '84,86',
                'leg_length_from_waist' => '118,121',
            ],
            [
                'sizechart_id' => 1,
                'height' => '188,191',
                'neck' => '41',
                'shoulder' => '43',
                'breast' => '110,114',
                'waist' => '86,90',
                'hip' => '114,118',
                'arm' => '86,88',
                'leg_length_from_waist' => '121,124',
            ],
            [
                'sizechart_id' => 2,
                'height' => '158,161',
                'neck' => '33',
                'shoulder' => '35',
                'breast' => '80,83',
                'waist' => '56,60',
                'hip' => '84,88',
                'arm' => '70,72',
                'leg_length_from_waist' => '99,102',
            ]
        ];

        foreach ($default_data as $data) {
            $wpdb->insert($this->table_name, $data);
        }
    }
}
