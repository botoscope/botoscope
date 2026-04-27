<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

include_once 'marketing_campaigns_products.php';
include_once 'marketing_campaigns_products_excluded.php';
include_once 'marketing_campaigns_terms.php';

//23-01-2026
final class BOTOSCOPE_MARKETING_CAMPAIGNS extends BOTOSCOPE_APP {

    protected $botoscope;
    protected $controls;
    protected $translations;
    protected $marketing_strategies;
    protected $marketing_campaigns_products;
    protected $marketing_campaigns_products_excluded;
    protected $marketing_campaigns_terms;
    protected $table_name = 'botoscope_marketing_campaigns';
    protected $slug = 'marketing_campaigns';
    protected $data_structure = [
        'title' => 'click to edit ...',
        'strategia_id' => 0,
        'time_start' => 0,
        'time_finish' => 0,
        'is_active' => 0
    ];

    public function __construct($args = []) {
        parent::__construct($args);

        if (botoscope_is_no_cart()) {
            return false;
        }

        $this->controls = new BOTOSCOPE_CONTROLS($args);
        $this->translations = new BOTOSCOPE_TRANSLATIONS($args);
        $this->marketing_campaigns_products = new BOTOSCOPE_MARKETING_CAMPAIGNS_PRODUCTS($args);
        $this->marketing_campaigns_products_excluded = new BOTOSCOPE_MARKETING_CAMPAIGNS_PRODUCTS_EXCLUDED($args);
        $this->marketing_campaigns_terms = new BOTOSCOPE_MARKETING_CAMPAIGNS_TERMS($args);

        $this->botoscope->allrest->add_rest_route($this->slug, [$this, 'register_route']);

        //+++

        Botoscope_Hooks::add_action('botoscope_get_parent_cell_data', function ($parent_app, $parent_row_id, $parent_cell_name) {
            $res = [];
            if ($parent_app === $this->slug) {
                switch ($parent_cell_name) {
                    case 'included_products':
                        $res = $this->marketing_campaigns_products->get_products($parent_row_id);
                        break;
                    case 'excluded_products':
                        $res = $this->marketing_campaigns_products_excluded->get_products($parent_row_id);
                        break;
                }
            }

            return $res;
        });

        Botoscope_Hooks::add_action('botoscope_delete_row', function ($what, $row_id, $parent_row_id) {

            if ($what === $this->slug) {
                return $this->delete($row_id);
            }

            if ($what === 'marketing_campaigns_products') {
                $parent_cell_name = sanitize_title($_REQUEST['additional_params']['parent_cell']);

                switch ($parent_cell_name) {
                    case 'included_products':
                        $this->marketing_campaigns_products->delete_product($row_id, $parent_row_id);
                        break;
                    case 'excluded_products':
                        $this->marketing_campaigns_products_excluded->delete_product($row_id, $parent_row_id);
                        break;
                }

                $this->botoscope->reset_cache($this->slug);
            }
        });

        Botoscope_Hooks::add_action('botoscope_search_products_not_in', function ($what, $parent_row_id) {
            if ($what === $this->slug) {

                $key = sanitize_text_field($_REQUEST['key']);

                switch ($key) {
                    case 'included_products':
                        return $this->marketing_campaigns_products->get_products_ids($parent_row_id);
                        break;
                    case 'excluded_products':
                        return $this->marketing_campaigns_products_excluded->get_products_ids($parent_row_id);
                        break;
                }
            }

            return [];
        });

        Botoscope_Hooks::add_action('botoscope_edit_cell', function ($what, $id, $key, $value, $all_sent_data) {
            if ($what === $this->slug) {
                $this->update($id, $key, $value, $all_sent_data);
                $this->botoscope->reset_cache($this->slug);
            }
        });

        Botoscope_Hooks::add_action('botoscope_add_row', function ($what, $parent_row_id, $content) {
            $res = null;

            if ($what === $this->slug) {
                $res = $this->create();
            }

            return $res;
        });

        Botoscope_Hooks::add_action('botoscope_get_page_data', function ($what, $page_num) {
            $res = [];

            if ($what === $this->slug) {
                $res = $this->get($page_num);
            }

            return $res;
        });

        add_action('wp_ajax_botoscope_marketing_campaigns_get', function () {
            if ($this->botoscope->is_ajax_request_valid()) {
                $res = $this->get_active();

                if (!empty($res)) {
                    wp_send_json_success($res[0]);
                } else {
                    wp_send_json_error('no active campaigns');
                }
            }
        });

        add_action('wp_ajax_botoscope_marketing_campaigns_test_mode', function () {
            if ($this->botoscope->is_ajax_request_valid()) {
                $this->botoscope->controls->update_option('botoscope_marketing_test_mode', 'value', intval($_REQUEST['value']));
                $this->botoscope->reset_cache($this->slug);
            }
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

        //+++

        if (!empty($res)) {
            foreach ($res as $key => $value) {
                $res[$key]['strategia_id'] = intval($value['strategia_id']);
                $res[$key]['time_start'] = intval($value['time_start']);
                $res[$key]['time_finish'] = intval($value['time_finish']);
                $res[$key]['is_active'] = intval($value['is_active']);
                $res[$key]['id'] = intval($value['id']);
                $res[$key]['included_products'] = $this->marketing_campaigns_products->get_ids(intval($value['id']));
                $res[$key]['excluded_products'] = $this->marketing_campaigns_products_excluded->get_ids(intval($value['id']));
                $res[$key]['included_terms'] = [];
                $res[$key]['excluded_terms'] = [];
            }
        }

        return $res;
    }

    public function update($id, $key, $value, $all_sent_data = []) {
        if (empty($id) || empty($key)) {
            return false;
        }

        $res = false;

        switch ($key) {
            case 'included_products':

                if (intval($all_sent_data['add'])) {
                    $data_structure = $this->marketing_campaigns_products->create([
                        'marketing_campaign_id' => $id,
                        'product_id' => $value
                    ]);
                } else {
                    $this->marketing_campaigns_products->delete_product($value, $id);
                }

                break;

            case 'excluded_products':

                if (intval($all_sent_data['add'])) {
                    $data_structure = $this->marketing_campaigns_products_excluded->create([
                        'marketing_campaign_id' => $id,
                        'product_id' => $value
                    ]);
                } else {
                    $this->marketing_campaigns_products_excluded->delete_product($value, $id);
                }

                break;

            case 'is_active':

                $all = $this->get();

                //only one marketing campaign can be activated
                foreach ($all as $mc) {
                    if (intval($mc['id']) !== intval($id)) {
                        $this->db->update($this->table_name, [$key => 0], ['id' => intval($mc['id'])]);
                    }
                }

                $res = parent::update($id, $key, $value);

                break;

            default:

                if ($this->get_current_language() !== $this->get_default_language()) {
                    $tr = $this->translations->get_translation($this->get_current_language(), $this->slug, $id, $key);
                    $res = $this->translations->update($tr['id'], $key, $value);
                } else {
                    $res = parent::update($id, $key, $value);
                }

                break;
        }


        return $res;
    }

    public function delete($id, $conditions = []) {
        $product_ids = $this->marketing_campaigns_products->get_ids($id);

        if (!empty($product_ids)) {
            foreach ($product_ids as $pid) {
                $this->marketing_campaigns_products->delete_product($pid, $id);
            }
        }

        //+++

        $product_ids = $this->marketing_campaigns_products_excluded->get_ids($id);

        if (!empty($product_ids)) {
            foreach ($product_ids as $pid) {
                $this->marketing_campaigns_products_excluded->delete_product($pid, $id);
            }
        }

        //+++

        parent::delete($id);
        $this->translations->delete(0, ['related_app' => $this->slug, 'related_row_id' => intval($id)]);
    }

    public function get_active() {

        $res = [];
        $rows = $this->get(0, true);

        if (!empty($rows)) {

            $controls = new BOTOSCOPE_CONTROLS(['botoscope' => $this->botoscope]);
            $languages = $controls->get_active_languages();
            $default_language = $controls->get_default_language();
            $translations = new BOTOSCOPE_TRANSLATIONS(['botoscope' => $this->botoscope]);

            foreach ($rows as $r) {

                if (!intval($r['is_active'])) {
                    continue;
                }

                $strategia_id = intval($r['strategia_id']);

                if (!$strategia_id) {
                    continue;
                } else {
                    $ids = array_column($this->botoscope->marketing->marketing_strategies->get_active(), 'id');
                    if (!in_array($strategia_id, $ids)) {
                        continue;
                    }
                }

                unset($r['is_active']);

                //+++

                $offset = intval($this->botoscope->controls->get_option('shop_time_zone') ?? 0);
                $timestamp_local = time() + $offset * 3600;

                if ($timestamp_local < $r['time_start']) {
                    continue;
                }

                if ($timestamp_local > $r['time_finish']) {
                    continue;
                }

                //+++

                $r['translations'] = [];

                if (!empty($languages)) {
                    foreach ($languages as $language) {
                        if (!isset($r['translations'][$language])) {
                            $r['translations'][$language] = [];
                        }
                        $r['translations'][$language]['title'] = $translations->get_translation($language, $this->slug, $r['id'], 'title')['value'];
                    }
                }

                //+++

                $r['included_products'] = array_map('intval', $r['included_products']);
                $r['excluded_products'] = array_map('intval', $r['excluded_products']);
                $r['included_terms'] = array_map('intval', $r['included_terms']);
                $r['excluded_terms'] = array_map('intval', $r['excluded_terms']);
                $r['test_mode'] = intval($this->botoscope->controls->get_option('botoscope_marketing_test_mode'));

                $res[$r['id']] = $r;
            }
        }

        return array_values($res);
    }

    public function draw_content($counter) {
        $default_lang = $this->controls->get_default_language();
        $active_langs = $this->controls->get_active_languages();
        $langs = array_intersect_key($this->botoscope->languages, array_flip(array_merge($active_langs, [$default_lang])));
        ?>

        <section id="botoscope-<?php echo esc_attr($this->slug) ?>" <?php if ($counter === 0): ?>class="content-current"<?php endif; ?>>

            <div id="botoscope-marketing-active-campaign" class="botoscope-marketing-active-campaign"></div>

            <select id="botoscope-<?php echo esc_attr($this->slug) ?>-lang-selector" class="botoscope-lang-selector" data-default-language="<?php echo esc_attr($default_lang) ?>">
                <?php foreach ($langs as $lang_key => $lang_title) : ?>
                    <option value="<?php echo esc_attr($lang_key) ?>" <?php selected($this->get_current_language(), $lang_key) ?>><?php echo esc_attr($lang_title) ?></option>
                <?php endforeach; ?>
            </select>

            <div id="botoscope-<?php echo esc_attr($this->slug) ?>-w"><?php echo wp_json_encode($this->get(), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></div>
            <br>
            <a href="javascript: void(0);" id="botoscope_create_<?php echo esc_attr($this->slug) ?>" class="button button-primary"><?php esc_html_e('New marketing campaign', 'botoscope') ?></a><br>

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
        strategia_id int(11) NOT NULL DEFAULT 0,
        time_start bigint(20) NOT NULL DEFAULT 0,
        time_finish bigint(20) NOT NULL DEFAULT 0,
        is_active smallint(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY strategia_id (strategia_id)
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
                'title' => esc_html__('Buy one or more and get 50% discount', 'botoscope'),
                'strategia_id' => 1,
            ],
            [
                'title' => esc_html__('50% discount on 2, 100% on 3', 'botoscope'),
                'strategia_id' => 2,
            ],
            [
                'title' => esc_html__('Black Friday - 50% OFF', 'botoscope'),
                'strategia_id' => 3,
            ],
        ];

        foreach ($default_data as $data) {
            $wpdb->insert($this->table_name, $data);
        }
    }
}
