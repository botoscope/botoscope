<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

//17-12-2025
final class BOTOSCOPE_ELOGIOS extends BOTOSCOPE_APP {

    protected $botoscope;
    protected $controls;
    protected $translations;
    protected $table_name = 'botoscope_elogios';
    protected $slug = 'elogios';
    protected $data_structure = [
        'title' => 'click to edit ...',
        'is_active' => 0
    ];

    public function __construct($args = []) {
        parent::__construct($args);

        if (botoscope_is_no_cart()) {
            return false;
        }

        $this->controls = new BOTOSCOPE_CONTROLS($args);
        $this->translations = new BOTOSCOPE_TRANSLATIONS($args);

        $this->botoscope->allrest->add_rest_route($this->slug, [$this, 'register_route']);

        Botoscope_Hooks::add_action('botoscope_panel_tabs', function ($tabs) {
            $tabs[$this->slug] = esc_html__('Elogios', 'botoscope');
            return $tabs;
        });

        Botoscope_Hooks::add_action('botoscope_edit_cell', function ($what, $id, $key, $value) {
            if ($what === 'controls' && in_array($id, ['default_language', 'languages'])) {
                $this->botoscope->reset_cache($this->slug);
            }
        });

        add_action("botoscope_{$this->slug}_tab_icon", function () {
            return 'star';
        });
    }

    public function register_route(WP_REST_Request $request) {
        return $this->get_active();
    }

    public function get($page_num = 0) {
        $res = parent::get();

        $ignore_language = defined('REST_REQUEST') && REST_REQUEST ? 1 : 0;

        if (!$ignore_language && $this->get_current_language() !== $this->get_default_language()) {
            $language = $this->get_current_language();
            $related_app = $this->slug;

            if (!empty($res)) {
                foreach ($res as $key => $value) {
                    $res[$key]['title'] = $this->translations->get_translation($language, $related_app, $value['id'], 'title')['value'] ?: "<ta></ta>" . $value['title'];
                }
            }
        }

        return $res;
    }

    public function update($id, $field_key, $value, $all_sent_data = []) {
        if ($this->get_current_language() !== $this->get_default_language()) {
            $tr = $this->translations->get_translation($this->get_current_language(), $this->slug, $id, $field_key);
            $this->translations->update($tr['id'], $field_key, $value);
        } else {
            parent::update($id, $field_key, $value);
        }
    }

    public function get_active() {
        $res = [];
        $controls = new BOTOSCOPE_CONTROLS(['botoscope' => $this->botoscope]);
        $languages = $controls->get_active_languages();
        $default_language = $controls->get_default_language();
        $translations = new BOTOSCOPE_TRANSLATIONS(['botoscope' => $this->botoscope]);

        foreach ($this->get(0, true) as $r) {
            if (intval($r['is_active'])) {
                $related_row_id = intval($r['id']);
                $title = wp_strip_all_tags($r['title']);

                if (!isset($res[$default_language])) {
                    $res[$default_language] = [];
                }

                $res[$default_language][] = $title;

                if (!empty($languages)) {
                    foreach ($languages as $language) {
                        if (!isset($res[$language])) {
                            $res[$language] = [];
                        }

                        $td = $translations->get_translation($language, $this->slug, $related_row_id, 'title');

                        if (!empty($td)) {
                            $res[$language][] = $td['value'] ?: '-';
                        } else {
                            $res[$language][] = '-';
                        }
                    }
                }
            }
        }

        return $res;
    }

    public function delete($id, $conditions = []) {
        parent::delete($id);
        $this->translations->delete(0, ['related_app' => $this->slug, 'related_row_id' => intval($id)]);
    }

    public function draw_content($counter) {

        if (botoscope_is_no_cart()) {
            return false;
        }

        $default_lang = $this->controls->get_default_language();
        $active_langs = $this->controls->get_active_languages();
        $langs = array_intersect_key($this->botoscope->languages, array_flip(array_merge($active_langs, [$default_lang])));
        ?>
        <section id="botoscope-<?php echo esc_attr($this->slug) ?>" <?php if ($counter === 0): ?>class="content-current"<?php endif; ?>>

            <select id="botoscope-<?php echo esc_attr($this->slug) ?>-lang-selector" class="botoscope-lang-selector" data-default-language="<?php echo esc_attr($default_lang) ?>">
                <?php foreach ($langs as $lang_key => $lang_title) : ?>
                    <option value="<?php echo esc_attr($lang_key) ?>" <?php selected($this->get_current_language(), $lang_key) ?>><?php echo esc_attr($lang_title) ?></option>
                <?php endforeach; ?>
            </select>

            <div id="botoscope-<?php echo esc_attr($this->slug) ?>-w"><?php echo wp_json_encode($this->get(), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></div>
            <br>
            <a href="javascript: void(0);" id="botoscope_create_<?php echo esc_attr($this->slug) ?>" class="button button-primary"><?php esc_html_e('Create', 'botoscope') ?></a><br>

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
        title text DEFAULT NULL,
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
    }
}
