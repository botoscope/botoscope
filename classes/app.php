<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

//17-03-2026
class BOTOSCOPE_APP {

    protected $db;
    protected $storage;
    protected $table_name = '';
    protected $botoscope = null;
    protected $data_structure = [];
    public $synhronize_cache = true;
    protected $per_page = 0;
    public $search = '';
    protected $slug = '';

    public function __construct($args = []) {
        global $wpdb;
        $table_without_prefix = $this->table_name;
        $this->table_name = $wpdb->prefix . $table_without_prefix;
        $this->db = $wpdb;
        $this->storage = new BOTOSCOPE_STORAGE();
        if (array_key_exists('title', $this->data_structure)) {
            $this->data_structure['title'] = esc_html__('click to edit ...', 'botoscope');
        }

        if ($args && isset($args['botoscope'])) {
            $this->botoscope = $args['botoscope'];
        }

        $this->set_up_table();

        add_action("wp_ajax_{$table_without_prefix}_set_current_language", function () {
            if ($this->botoscope->is_ajax_request_valid()) {
                $this->set_current_language(sanitize_text_field($_REQUEST['language']));
            }
        }, 1);
    }

    public function create($data = []) {
        $this->db->insert($this->table_name, $this->data_structure);
        $this->data_structure['id'] = $this->db->insert_id;

        if (!empty($data)) {
            foreach ($data as $key => $value) {
                $this->update($this->data_structure['id'], $key, $value);
                $this->data_structure[$key] = $value;
            }
        }

        return $this->data_structure;
    }

    public function get($page_num = 0) {

        if ($this->per_page > 0) {
            $offset = $page_num * $this->per_page;
            $sql = "SELECT * FROM `{$this->table_name}` ORDER BY id DESC LIMIT $offset, {$this->per_page}";
        } else {
            $sql = "SELECT * FROM `{$this->table_name}` ORDER BY id DESC";
        }

        return $this->db->get_results($sql, ARRAY_A);
    }

    public function update($id, $field_key, $value, $all_sent_data = []) {
        if (empty($id) || empty($field_key)) {
            return false;
        }

        $value = stripslashes($this->process_value_before_upfate($value, $field_key));
        return $this->db->update($this->table_name, [$field_key => $value], ['id' => intval($id)]);
    }

    public function get_column_value($id, $column) {
        if (empty($id) || empty($column)) {
            return null;
        }

        return $this->db->get_var($this->db->prepare("SELECT `{$column}` FROM `{$this->table_name}` WHERE `id` = %d", $id));
    }

    public function delete($id, $conditions = []) {
        if (empty($conditions)) {
            $conditions = ['id' => $id];
        }

        return $this->db->delete($this->table_name, $conditions);
    }

    public function get_current_language() {
        $language = $this->storage->get_val("{$this->table_name}_selected_language") ?: $this->get_default_language();
        if (!in_array($language, $this->controls->get_active_languages())) {
            $language = $this->get_default_language();
        }
        return $language;
    }

    public function get_default_language() {
        return $this->controls->get_default_language();
    }

    public function set_current_language($language) {
        $this->storage->set_val("{$this->table_name}_selected_language", sanitize_text_field($language));
    }

    //for descendants
    protected function process_value_before_upfate($value, $field_key) {
        return $value;
    }

    protected function set_up_table() {

        static $res = [];//cache

        if (!array_key_exists($this->table_name, $res)) {

            if (!empty($this->table_name)) {
                $query = "SHOW TABLES WHERE Tables_in_{$this->db->dbname} = '{$this->table_name}'";

                if ($this->db->get_var($query) === null) {
                    $this->install();
                }

                $res[$this->table_name] = 1;
            }
        }
    }

    protected function convert_html_list_to_telegram_text($html) {
        $html = trim($html);
        $html = str_replace('<ul>', '', $html);
        $html = str_replace('</ul>', '', $html);
        $html = preg_replace('/\s*<li>/', '✅ ', $html);
        $html = str_replace('</li>', PHP_EOL . PHP_EOL, $html);
        $html = str_replace('<p>', '', $html);
        $html = str_replace('</p>', PHP_EOL, $html);

        return trim($html);
    }

    public function draw_content($counter) {
        //api
    }

    protected function install() {
        //api for classes that inherit
    }
}
