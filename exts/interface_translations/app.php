<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

//14-01-2026
final class BOTOSCOPE_INTERFACE_TRANSLATIONS extends BOTOSCOPE_APP {

    protected $botoscope;
    protected $controls;
    protected $translations;
    protected $table_name = 'botoscope_interface_translations';
    protected $slug = 'interface_translations';
    public $synhronize_cache = true;
    protected $per_page = 10;
    protected $data_structure = [
        'key' => '',
        'original' => '', //original string in default language
        'title' => ''
    ];

    public function __construct($args = []) {
        parent::__construct($args);

        $this->controls = new BOTOSCOPE_CONTROLS($args);
        $this->translations = new BOTOSCOPE_TRANSLATIONS($args);

        $this->botoscope->allrest->add_rest_route($this->slug, [$this, 'register_route']);

        Botoscope_Hooks::add_action('botoscope_panel_tabs', function ($tabs) {
            $tabs[$this->slug] = esc_html__('Interface', 'botoscope');
            return $tabs;
        });

        add_action("botoscope_{$this->slug}_tab_icon", function () {
            return 'language';
        });
    }

    public function register_route(WP_REST_Request $request) {
        return $this->get_active();
    }

    public function get($language = 0) {
        $res = [];
        $default_language = $this->get_default_language();
        $current_language = $this->get_current_language();

        $data = $this->botoscope->do_command(-1, 'get_interface_translations', [
            //'language' => $default_language
            'language' => $current_language
        ]);

        if (intval($data['code']) === 200) {
            /*
              if ($default_language !== $current_language) {
              $translated_ids = $this->get_translated_ids();
              }
             * 
             */

            if ((boolval($data))) {
                $data = json_decode($data['body'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    if (!empty($data)) {
                        foreach ($data as $key => $value) {
                            $term_id = intval(str_replace('s', '', $key));
                            /*
                              if ($default_language !== $current_language) {
                              if (!in_array($term_id, $translated_ids)) {
                              continue;
                              }
                              }
                             * 
                             */

                            $res[] = [
                                'key' => $key,
                                'original' => $value,
                                'title' => $this->get_translation($term_id, $current_language)['title'] ?? '',
                                'id' => $term_id
                            ];
                        }
                    }
                }
            }
        }

        return $res;
    }

    private function get_translated_ids() {
        // Get strings that have been translated into the default language
        $rows = $this->db->get_results(
                $this->db->prepare(
                        "SELECT term_id FROM `{$this->table_name}`
                        WHERE language = %s
                        AND title IS NOT NULL
                        AND title != ''",
                        $this->get_default_language()
                ),
                ARRAY_A
        );

        return array_column($rows, 'term_id');
    }

    public function update($term_id, $field_key, $value, $language = []) {

        if (is_array($language)) {
            $language = $this->get_current_language();
        }

        $translation_id = $this->get_translation_id($term_id, $language);

        if (empty($value)) {
            parent::delete($translation_id);
        } else {
            $this->db->update($this->table_name, [$field_key => wp_strip_all_tags($value)], ['id' => $translation_id]);
        }
    }

    private function get_translation_id($term_id, $language) {
        return $this->get_translation($term_id, $language)['id'];
    }

    public function get_translation($term_id, $language) {
        $row = $this->get_row($term_id, $language);

        if (empty($row)) {
            $this->db->insert($this->table_name, [
                'term_id' => $term_id,
                'language' => $language
            ]);

            $row = [
                'id' => $this->db->insert_id,
                'language' => $language,
                'title' => NULL
            ];
        }

        return $row;
    }

    private function get_row($term_id, $language) {
        return $this->db->get_row(
                        $this->db->prepare(
                                "SELECT * FROM `{$this->table_name}`
                                WHERE term_id = %d
                                AND language = %s",
                                $term_id, $language
                        ),
                        ARRAY_A
                );
    }

    public function get_term_translations($term_id) {
        $res = [];
        $rows = $this->db->get_results(
                $this->db->prepare(
                        "SELECT * FROM `{$this->table_name}`
                                WHERE term_id = %s",
                        $term_id
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

                if (empty($res[$r['language']])) {
                    unset($res[$r['language']]);
                }
            }
        }

        return $res;
    }

    public function delete($term_id, $conditions = []) {
        parent::delete($term_id, ['term_id' => $term_id]);
    }

    public function get_active() {
        $res = [];

        $rows = $this->db->get_results(
                "SELECT * FROM `{$this->table_name}`
                        WHERE title IS NOT NULL
                        AND title != ''",
                ARRAY_A
        );

        foreach ($rows as $value) {
            if (!isset($res[$value['term_id']])) {
                $res[$value['term_id']] = [];
            }

            $res[$value['term_id']][$value['language']] = $value['title'];
        }

        return $res;
    }

    public function draw_content($counter) {
        $default_lang = $this->botoscope->controls->get_default_language();
        $active_langs = $this->botoscope->controls->get_active_languages();
        $langs = array_intersect_key($this->botoscope->languages, array_flip(array_merge($active_langs, [$default_lang])));
        ?>

        <section id="botoscope-<?php echo esc_attr($this->slug) ?>" <?php if ($counter === 0): ?>class="content-current"<?php endif; ?>>

            <select id="botoscope-<?php echo esc_attr($this->slug) ?>-lang-selector" class="botoscope-lang-selector" data-default-language="<?php echo esc_attr($default_lang) ?>">
                <?php foreach ($langs as $lang_key => $lang_title) : ?>
                    <option value="<?php echo esc_attr($lang_key) ?>" <?php selected($this->get_current_language(), $lang_key) ?>><?php echo esc_attr($lang_title) ?></option>
                <?php endforeach; ?>
            </select>

            <input type="search" id="botoscope-<?php echo esc_attr($this->slug) ?>-search" value="" placeholder="" />
            <div id="botoscope-<?php echo esc_attr($this->slug) ?>-w" ><?php echo wp_json_encode($this->get(), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></div>

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
        language varchar(16) NOT NULL,
        term_id int(11) NOT NULL,
        title text DEFAULT NULL,
        PRIMARY KEY (id),
        KEY language (language),
        KEY term_id (term_id)
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
