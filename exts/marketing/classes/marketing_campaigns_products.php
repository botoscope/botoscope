<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

//17-12-2025
class BOTOSCOPE_MARKETING_CAMPAIGNS_PRODUCTS extends BOTOSCOPE_APP {

    protected $table_name = 'botoscope_marketing_campaigns_products';
    protected $data_structure = [
        'marketing_campaign_id' => 0,
        'product_id' => 0
    ];

    public function __construct($args = []) {
        parent::__construct($args);
    }

    public function get_of($marketing_campaign_id) {
        $res = [];
        $all = $this->get();

        if (!empty($all)) {
            foreach ($all as $f) {
                if (intval($f['marketing_campaign_id']) === intval($marketing_campaign_id)) {
                    $res[] = $f;
                }
            }
        }

        return $res;
    }

    public function get_ids($marketing_campaign_id) {
        return array_column($this->get_of($marketing_campaign_id), 'product_id');
    }

    public function get_products($marketing_campaign_id) {
        $res = [];

        $product_ids = $this->get_ids(intval($marketing_campaign_id));

        if (!empty($product_ids)) {
            foreach ($product_ids as $pid) {
                $res[] = [
                    'title' => esc_html(get_the_title($pid)),
                    'id' => intval($pid)
                ];
            }
        }

        return $res;
    }

    public function delete_product($product_id, $marketing_campaign_id) {
        $this->delete(null, ['product_id' => $product_id, 'marketing_campaign_id' => $marketing_campaign_id]);
    }

    public function get_products_ids($id) {
        return $this->get_ids(intval($id));
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
        marketing_campaign_id int(11) NOT NULL,
        product_id int(11) NOT NULL,
        PRIMARY KEY (id),
        KEY marketing_campaign_id (marketing_campaign_id),
        KEY product_id (product_id)
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
