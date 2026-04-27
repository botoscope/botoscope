<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

//23-01-2026
final class BOTOSCOPE_SUPPORT_INTERACTIONS extends BOTOSCOPE_APP {

    protected $table_name = 'botoscope_support_interactions';
    protected $data_structure = [
        'ticket_id' => 0,
        'message_type' => 'answer',
        'content' => '',
        'time' => 0,
        'is_new' => 1
    ];

    public function __construct($args) {
        parent::__construct($args);
    }

    public function get_of($ticket_id) {
        return (array) $this->db->get_results(
                        $this->db->prepare("SELECT * FROM `{$this->table_name}` WHERE ticket_id = %d ORDER BY time ASC", $ticket_id),
                        ARRAY_A
                );
    }

    public function get_ids($ticket_id) {
        return array_column($this->get_of($ticket_id), 'ticket_id');
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
        ticket_id int(11) NOT NULL,
        message_type set('question','answer') NOT NULL,
        content text NOT NULL,
        is_new tinyint(1) NOT NULL DEFAULT 1,
        time bigint(20) NOT NULL,
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
