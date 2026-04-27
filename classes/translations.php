<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

//16-01-2026
final class BOTOSCOPE_TRANSLATIONS extends BOTOSCOPE_APP {

    protected $table_name = 'botoscope_translations';
    public $synhronize_cache = false;

    public function __construct($args = []) {
        parent::__construct($args);

        Botoscope_Hooks::add_action('botoscope_edit_cell', function ($what, $id, $key, $value) {
            if ($what === 'translations') {
                $this->botoscope->reset_cache(sanitize_text_field(wp_strip_all_tags($_REQUEST['additional_params']['parent_table']))); //!!
            }
        });
    }

    public function update($id, $field_key, $value, $all_sent_data = []) {
        $this->db->update($this->table_name, ['value' => wp_unslash($value)], ['id' => $id]);
    }

    public function update_app_field($id, $field_key, $value, $language) {
        $tr = $this->get_translation($language, 'products_meta_gallery', $id, $field_key);
        $this->update($tr['id'], $field_key, wp_unslash($value), $language);
    }

    public function get_translation($language, $related_app, $related_row_id, $related_cell_name) {
        $row = $this->get_row($language, $related_app, $related_row_id, $related_cell_name);

        if (empty($row)) {
            $this->db->insert($this->table_name, [
                'language' => $language,
                'related_app' => $related_app,
                'related_row_id' => $related_row_id,
                'related_cell_name' => $related_cell_name,
            ]);

            $row = [
                'id' => $this->db->insert_id,
                'value' => null
            ];
        }

        return $row;
    }

    public function get_row($language, $related_app, $related_row_id, $related_cell_name) {
        return $this->db->get_row(
                        $this->db->prepare(
                                "SELECT id,value FROM `{$this->table_name}` 
                                WHERE language = %s 
                                AND related_app = %s 
                                AND related_row_id = %s
                                AND related_cell_name = %s",
                                $language, $related_app, $related_row_id, $related_cell_name
                        ),
                        ARRAY_A
                );
    }

    public function get_translations($language, $related_app, $related_cell_name) {
        return $this->db->prepare(
                        "SELECT * FROM `{$this->table_name}` 
                                WHERE language = %s 
                                AND related_app = %s 
                                AND related_cell_name = %s",
                        $language, $related_app, $related_cell_name
                );
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
        value mediumtext DEFAULT NULL,
        related_app varchar(32) NOT NULL,
        related_row_id varchar(16) NOT NULL,
        related_cell_name varchar(32) NOT NULL,
        PRIMARY KEY (id),
        KEY language (language),
        KEY related_row_id (related_row_id),
        KEY related_app (related_app)
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
