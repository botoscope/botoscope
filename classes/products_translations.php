<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

//17-03-2026
final class BOTOSCOPE_PRODUCTS_TRANSLATIONS extends BOTOSCOPE_APP {

    protected $table_name = 'botoscope_products_translations';
    public $synhronize_cache = false;

    public function __construct($args = []) {
        parent::__construct($args);
    }

    public function update($product_id, $field_key, $value, $language = []) {
        $translation_id = $this->get_translation_id($product_id, $language);
        $this->db->update($this->table_name, [$field_key => $value], ['id' => $translation_id]);
    }

    private function get_translation_id($product_id, $language) {
        return $this->get_translation($product_id, $language)['id'];
    }

    public function get_translation($product_id, $language) {
        $row = $this->get_row($product_id, $language);

        if (empty($row)) {
            $this->db->insert($this->table_name, [
                'product_id' => $product_id,
                'language' => $language
            ]);

            $row = [
                'id' => $this->db->insert_id,
                'language' => $language,
                'title' => NULL,
                'description' => NULL
            ];
        }

        return $row;
    }

    private function get_row($product_id, $language) {
        return $this->db->get_row(
                        $this->db->prepare(
                                "SELECT * FROM `{$this->table_name}` 
                                WHERE product_id = %d 
                                AND language = %s",
                                $product_id, $language
                        ),
                        ARRAY_A
                );
    }

    public function get_product_translations($product_id) {
        static $products = [];

        if (!array_key_exists($product_id, $products)) {

            $res = [];

            $rows = $this->db->get_results(
                    $this->db->prepare(
                            "SELECT * FROM `{$this->table_name}` 
                         WHERE product_id = %d",
                            $product_id
                    ),
                    ARRAY_A
            );

            if (!empty($rows)) {
                foreach ($rows as $r) {
                    if (!isset($res[$r['language']])) {
                        $res[$r['language']] = [];
                    }

                    if (!empty($r['title'])) {
                        $res[$r['language']]['title'] = wp_strip_all_tags($r['title']);
                    }

                    if (!empty($r['description'])) {
                        $res[$r['language']]['description'] = wp_strip_all_tags($r['description']);
                    }

                    if (empty($res[$r['language']])) {
                        unset($res[$r['language']]);
                    }
                }
            }


            $products[$product_id] = $res;
        }

        return $products[$product_id];
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
        language varchar(16) NOT NULL,
        product_id int(11) NOT NULL,
        title text DEFAULT NULL,
        description text DEFAULT NULL,
        PRIMARY KEY (id),
        KEY language (language),
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
