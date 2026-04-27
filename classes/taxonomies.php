<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

include_once 'taxonomies_translations.php';

//22-04-2026
final class BOTOSCOPE_TAXONOMIES extends BOTOSCOPE_APP {

    protected $botoscope;
    protected $controls;
    protected $translations;
    private $taxonomies_translations;
    protected $table_name = 'botoscope_taxonomies'; //do not need - as used native taxonomies system, so use it as key-slug
    protected $slug = 'taxonomies';
    protected $data_structure = [
        'title' => '',
        'is_active' => 0,
        'child_count' => 0
    ];

    public function __construct($args = []) {
        parent::__construct($args);

        $this->controls = new BOTOSCOPE_CONTROLS($args);
        $this->translations = new BOTOSCOPE_TRANSLATIONS($args);
        $this->taxonomies_translations = new BOTOSCOPE_TAXONOMIES_TRANSLATIONS($args);

        //lets init all terms for first time to avoid users confusing
        add_action('admin_init', function () {
            $is_initialized = get_option('botoscope_taxonomies_initialized', false);

            if (!$is_initialized) {
                $this->activate_all_existing_terms();
                $this->normalize_all_products_categories();
                update_option('botoscope_taxonomies_initialized', true);
            }
        }, 1);

        add_action("wp_ajax_botoscope_taxonomies_set_current", function () {
            if ($this->botoscope->is_ajax_request_valid()) {
                $this->set_parent_taxonomy(sanitize_text_field($_REQUEST['taxonomy']));
            }
        }, 1);

        add_action("wp_ajax_botoscope_taxonomies_set_parent", function () {
            if ($this->botoscope->is_ajax_request_valid()) {
                $this->set_parent_taxonomy_term_id(intval($_REQUEST['parent_id']));
            }
        }, 1);

        add_action("wp_ajax_botoscope_taxonomies_get_breadcumb", function () {
            if ($this->botoscope->is_ajax_request_valid()) {
                $this->draw_breadcrumb();
                exit;
            }
        }, 1);

        add_action("wp_ajax_botoscope_taxonomies_get_hierarchical_terms", function () {
            if ($this->botoscope->is_ajax_request_valid()) {
                die(wp_json_encode($this->get_hierarchical_terms(sanitize_text_field($_REQUEST['taxonomy'])), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_HEX_TAG));
            }
        }, 1);

        add_action("botoscope_{$this->slug}_tab_icon", function () {
            return 'list';
        });

        add_action("wp_ajax_botoscope_delete_taxonomy", function () {
            if ($this->botoscope->is_ajax_request_valid()) {
                $slug = sanitize_text_field($_REQUEST['taxonomy']);

                if (strpos($slug, 'pa_') === 0) {
                    global $wpdb;

                    $attribute_name = str_replace('pa_', '', $slug);
                    $exists = $wpdb->get_var($wpdb->prepare(
                                    "SELECT attribute_id FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name = %s",
                                    $attribute_name
                            ));

                    if ($exists) {
                        $wpdb->delete(
                                $wpdb->prefix . 'woocommerce_attribute_taxonomies',
                                ['attribute_name' => $attribute_name]
                        );

                        $taxonomy = 'pa_' . $attribute_name;
                        unregister_taxonomy($taxonomy);
                        //Regenerate woo cache
                        delete_transient('wc_attribute_taxonomies');

                        wp_send_json_success([
                            'message' => "Deleted successfully"
                        ]);
                    } else {
                        wp_send_json_error([
                            'message' => "Error"
                        ]);
                    }
                } else {
                    $existing_taxonomies = get_option('botoscope_taxonomies', []);
                    unset($existing_taxonomies[$slug]);
                    update_option('botoscope_taxonomies', $existing_taxonomies);
                }
            }
        }, 1);

        Botoscope_Hooks::add_action('botoscope_get_sidebar_html', function ($what, $template_name, $id) {
            if ($what === $this->slug) {

                $data = [];

                if (sanitize_key($_REQUEST['id'] ?? '') === 'edit_taxonomy') {
                    if (!empty($_REQUEST['more_data'])) {
                        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- JSON string decoded and fields sanitized individually below
                        $raw = wp_unslash($_REQUEST['more_data']);
                        $data = json_decode($raw, true);

                        if (!is_array($data)) {
                            $data = [];
                        }

                        $taxonomy = get_taxonomy(sanitize_key($data['taxonomy']));
                        $data['title'] = sanitize_text_field($taxonomy->labels->name ?? '');
                        if (strpos($data['taxonomy'], 'pa_') === 0) {
                            $data['type'] = 'attribute';
                        } else {
                            $data['type'] = 'taxonomy';
                        }
                    }
                }

                BOTOSCOPE_HELPER::render_html_e(BOTOSCOPE_PATH . "views/{$template_name}.php", $data);
            }
        });

        Botoscope_Hooks::add_action('botoscope_edit_row', function ($what, $id, $data) {
            if ($what === $this->slug && $id === 'new_attribute') {
                if (!empty($data['title'])) {
                    if ($data['type'] === 'attribute') {
                        $attribute_label = sanitize_text_field($data['title']);
                        $base_slug = sanitize_title($attribute_label);
                        $attribute_name = $base_slug;
                        $suffix = 1;

                        //Checking the uniqueness of a name
                        global $wpdb;
                        while ($wpdb->get_var($wpdb->prepare(
                                        "SELECT attribute_id FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name = %s",
                                        $attribute_name
                                ))) {
                            $attribute_name = $base_slug . '-' . $suffix;
                            $attribute_label = $attribute_label . '-' . $suffix;
                            $suffix++;
                        }

                        $data = [
                            'name' => $attribute_label,
                            'slug' => $attribute_name,
                            'type' => 'select',
                            'order_by' => 'menu_order',
                            'has_archives' => false
                        ];

                        $result = wc_create_attribute($data);

                        if (is_wp_error($result)) {
                            wp_send_json_error("Error creating attribute: " . $result->get_error_message());
                        }

                        wp_send_json_success([
                            'message' => "Attribute '{$attribute_label}' created successfully",
                            'attribute_name' => $attribute_name,
                            ...$data
                        ]);
                    }
                }
            }

            //+++

            if ($what === $this->slug && $id === 'new_taxonomy') {
                if (!empty($data['title'])) {
                    if ($data['type'] === 'taxonomy') {
                        $taxonomy_label = sanitize_text_field($data['title']);
                        $base_slug = sanitize_title($taxonomy_label);
                        $taxonomy_slug = $base_slug;
                        $suffix = 1;

                        // Checking the uniqueness of a taxonomy slug
                        while (taxonomy_exists($taxonomy_slug)) {
                            $taxonomy_slug = $base_slug . '-' . $suffix;
                            $taxonomy_label = $taxonomy_label . '-' . $suffix;
                            $suffix++;
                        }

                        // Save in the database
                        $existing_taxonomies = get_option('botoscope_taxonomies', []);

                        $existing_taxonomies[$taxonomy_slug] = [
                            'name' => $taxonomy_label,
                            'slug' => $taxonomy_slug,
                        ];

                        update_option('botoscope_taxonomies', $existing_taxonomies);

                        wp_send_json_success([
                            'message' => "Taxonomy '{$taxonomy_label}' created successfully",
                            'name' => $taxonomy_label,
                            'slug' => $taxonomy_slug
                        ]);
                    }
                }
            }

            //+++

            if ($what === $this->slug && $id === 'edit_taxonomy') {
                if (!empty($data['title'])) {

                    $new_label = sanitize_text_field($data['title']);
                    $slug = sanitize_text_field($data['slug']);

                    if ($data['type'] === 'taxonomy') {
                        $existing_taxonomies = get_option('botoscope_taxonomies', []);

                        $existing_taxonomies[$slug] = [
                            'name' => $new_label,
                            'slug' => $slug,
                        ];

                        update_option('botoscope_taxonomies', $existing_taxonomies);
                    } else {
                        global $wpdb;

                        $wpdb->update(
                                $wpdb->prefix . 'woocommerce_attribute_taxonomies',
                                ['attribute_label' => $new_label],
                                ['attribute_name' => str_replace('pa_', '', $slug)]
                        );

                        //Regenerate woo cache
                        delete_transient('wc_attribute_taxonomies');
                    }

                    wp_send_json_success([
                        'message' => "Taxonomy/Attribute '{$new_label}' edit successfully",
                        'name' => $new_label,
                        'slug' => $slug
                    ]);
                }
            }
        });

        //+++


        $this->botoscope->allrest->add_rest_route($this->slug, [$this, 'register_route']);
    }

    public function register_route(WP_REST_Request $request) {
        return $this->get_active();
    }

    public function set_parent_taxonomy($taxonomy) {
        $this->storage->set_val('botoscope_taxonomies_selected', sanitize_text_field($taxonomy));
        $this->set_parent_taxonomy_term_id(0);
    }

    public function set_parent_taxonomy_term_id($term_id) {
        $this->storage->set_val('botoscope_taxonomies_selected_parent', intval($term_id));
    }

    public function get_current_taxonomy() {
        $current = $this->storage->get_val('botoscope_taxonomies_selected');
        if (!taxonomy_exists($current)) {
            $current = '';
        }
        return sanitize_text_field($current ?: 'product_cat');
    }

    public function get_current_taxonomy_parent() {
        return intval($this->storage->get_val('botoscope_taxonomies_selected_parent') ?? 0);
    }

    //for solo bs systems
    private function normalize_terms($taxonomy) {
        global $wpdb;

        $term_ids = $wpdb->get_col($wpdb->prepare("
            SELECT t.term_id
            FROM {$wpdb->terms} t
            INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            LEFT JOIN {$wpdb->termmeta} tm ON t.term_id = tm.term_id AND tm.meta_key = 'menu_order'
            WHERE tt.taxonomy = %s
              AND tm.meta_id IS NULL
        ", $taxonomy));

        if (!empty($term_ids)) {
            foreach ($term_ids as $term_id) {
                update_term_meta($term_id, 'menu_order', 0);
            }
        }
    }

    public function get_active() {
        $taxonomies = $this->get_products_taxonomies();
        $res = [];

        foreach ($taxonomies as $taxonomy) {

            $this->normalize_terms($taxonomy->name);

            $args = [
                'taxonomy' => $taxonomy->name,
                'hide_empty' => false,
                'meta_key' => 'menu_order',
                'orderby' => 'meta_value_num',
                'exclude' => [],
                'order' => 'ASC'
            ];

            $terms = get_terms($args);
            $terms_data = [];

            foreach ($terms as $term) {

                if (!intval(get_term_meta($term->term_id, 'is_active', true))) {
                    continue;
                }

                $terms_data[$term->term_id] = array(
                    'id' => $term->term_id,
                    'title' => $term->name,
                    'translations' => $this->taxonomies_translations->get_term_translations($term->term_id),
                    'parent' => $term->parent,
                    'count' => $term->count,
                    'icon' => get_term_meta($term->term_id, 'icon', true) ?? '',
                    'menu_order' => intval(get_term_meta($term->term_id, 'menu_order', true))
                );
            }

            $res[$taxonomy->name] = array(
                'title' => $taxonomy->labels->singular_name,
                'terms' => $terms_data,
            );
        }

        //+++

        $res['category'] = $res['product_cat'];
        unset($res['product_cat']);

        return $res;
    }

    public function get($page_num = 0) {
        $res = [];
        $taxonomy = $this->get_current_taxonomy();
        $parent_id = $this->get_current_taxonomy_parent();

        $taxonomy_objects = $this->get_products_taxonomies();

        if (isset($taxonomy_objects[$taxonomy])) {

            $this->normalize_terms($taxonomy);

            $object_terms = get_terms(array(
                'taxonomy' => $taxonomy,
                'hide_empty' => false,
                'parent' => $parent_id,
                'orderby' => 'meta_value_num',
                'meta_key' => 'menu_order',
                'order' => 'ASC'
            ));

            if (!is_wp_error($object_terms) && !empty($object_terms)) {

                foreach ($object_terms as $term) {
                    if (is_object($term)) {
                        $original = $title = $term->name;

                        if ($this->get_current_language() !== $this->get_default_language()) {
                            $title = $this->taxonomies_translations->get_translation($term->term_id, $this->get_current_language())['title'] ?: "<ta></ta>{$term->name}";
                        }


                        $res[] = array(
                            'id' => $term->term_id,
                            'title' => $title,
                            'original' => $original,
                            'is_active' => intval(get_term_meta($term->term_id, 'is_active', true)),
                            'child_count' => count(get_terms(array(
                                'taxonomy' => $taxonomy,
                                'hide_empty' => false,
                                'parent' => $term->term_id
                            ))),
                            'taxonomy' => $taxonomy,
                            'icon' => get_term_meta($term->term_id, 'icon', true) ?? '',
                            'menu_order' => intval(get_term_meta($term->term_id, 'menu_order', true))
                        );
                    }
                }

                usort($res, function ($a, $b) {
                    return $a['menu_order'] - $b['menu_order'];
                });
            }
        }

        return $res;
    }

    public function create($data = []) {
        $args = array(
            'parent' => $this->get_current_taxonomy_parent()
        );

        $term_name = uniqid('Term-' . time() . '-');

        remove_action('wc_schedule_update_product_default_cat', ['Automattic\WooCommerce\Internal\AssignDefaultCategory', 'maybe_assign_default_product_cat']);

        $result = wp_insert_term($term_name, $this->get_current_taxonomy(), $args);

        if (!is_wp_error($result)) {
            add_term_meta($result['term_id'], 'is_active', 0);
            add_term_meta($result['term_id'], 'menu_order', 9999);

            if ($result['term_id']) {
                $this->data_structure['id'] = $result['term_id'];
                $this->data_structure['title'] = $term_name;
                $this->data_structure['is_active'] = 0;
                $this->data_structure['child_count'] = 0;
                $this->data_structure['icon'] = '';
                $this->data_structure['taxonomy'] = $this->get_current_taxonomy();

                return $this->data_structure;
            }
        }

        add_action('wc_schedule_update_product_default_cat', ['Automattic\WooCommerce\Internal\AssignDefaultCategory', 'maybe_assign_default_product_cat']);

        return [];
    }

    public function update($term_id, $field_key, $value, $all_sent_data = []) {
        switch ($field_key) {
            case 'title':
                if ($term_id) {
                    $term = get_term($term_id);
                    if ($this->get_current_language() === $this->get_default_language()) {
                        wp_update_term($term_id, $term->taxonomy, array(
                            'name' => sanitize_text_field($value),
                            'slug' => sanitize_text_field($value),
                        ));
                    } else {
                        $this->taxonomies_translations->update($term_id, 'title', sanitize_text_field($value), $this->get_current_language());
                    }
                }
                break;

            case 'is_active':
                if ($term_id) {
                    update_term_meta($term_id, $field_key, intval($value));
                }
                break;

            case 'menu_order':

                $terms_ids = explode(',', $value);
                if (!empty($terms_ids)) {
                    foreach ($terms_ids as $menu_order => $term_id) {
                        update_term_meta($term_id, 'menu_order', $menu_order);
                    }
                }

                break;

            case 'icon':
                update_term_meta($term_id, $field_key, sanitize_text_field($value));
                break;
        }
    }

    public function delete($term_id, $conditions = []) {
        if (!$term_id) {
            wp_send_json_error('Invalid term ID');
        } else {
            $term = get_term($term_id);
            if (!is_wp_error($term) && $term) {
                /*
                 * The Duplicate entry error after deleting terms may occur because WooCommerce tries to automatically
                 * set a default category for products that no longer have an associated category.
                 * This is done automatically via the Action Scheduler, which ensures that each product has at least one category.
                 */
                remove_action('wc_schedule_update_product_default_cat', ['Automattic\WooCommerce\Internal\AssignDefaultCategory', 'maybe_assign_default_product_cat']);
                wp_delete_term($term_id, $term->taxonomy);
                $this->taxonomies_translations->delete($term_id);
                add_action('wc_schedule_update_product_default_cat', ['Automattic\WooCommerce\Internal\AssignDefaultCategory', 'maybe_assign_default_product_cat']);
            }
        }
    }

    public function draw_breadcrumb() {
        $term_id = $this->get_current_taxonomy_parent();

        if ($term_id === 0) {
            return;
        }

        //+++

        $term = get_term($term_id);
        $tpid = $term->parent;
        $terms = [];

        while ($tpid > 0) {
            $t = get_term($tpid);
            array_unshift($terms, $t); //add to the array begining!!
            $tpid = $t->parent;
        }

        $html = "<a href='javascript: void(0);' data-term-id=0>" . esc_html__('Start', 'botoscope') . "</a>&nbsp;>&nbsp;";
        if (!empty($terms)) {
            foreach ($terms as $t) {
                $html .= "<a href='javascript: void(0);' data-term-id=" . intval($t->term_id) . ">" . esc_html($t->name) . "</a>&nbsp;>&nbsp;";
            }
        }

        $html .= "<b>" . esc_html($term->name) . "</b>";
        
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML built internally from WP term data, term names are sanitized by WordPress on save
        echo $html;
    }

    public function get_products_taxonomies() {
        $taxonomy_objects = get_object_taxonomies('product', 'objects');
        unset($taxonomy_objects['product_type']);
        unset($taxonomy_objects['product_visibility']);
        unset($taxonomy_objects['product_shipping_class']);
        unset($taxonomy_objects['product_tag']);

        if (!empty($taxonomy_objects)) {
            foreach ($taxonomy_objects as $key => $t) {
                if (strpos($key, 'pa_') === 0) {
                    $words = explode(' ', $t->label);
                    array_shift($words);
                    $t->label = implode(' ', $words);
                    $taxonomy_objects[$key]->label = $t->label;
                    $taxonomy_objects[$key]->labels->name = $t->label;
                }
            }
        }

        return $taxonomy_objects;
    }

    public function get_hierarchical_terms($taxonomy = 'product_cat') {
        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false
        ]);

        if (is_wp_error($terms) || empty($terms)) {
            return [];
        }

        $terms_hierarchy = [];

        $terms_index = [];
        foreach ($terms as $term) {

            if (!intval(get_term_meta($term->term_id, 'is_active', true))) {
                continue;
            }

            $terms_index[$term->term_id] = (object) [
                        'id' => $term->term_id,
                        'title' => $term->name,
                        //'slug' => urldecode($term->slug),
                        'children' => [],
            ];
        }

        // Building a hierarchy
        foreach ($terms as $term) {
            if (isset($terms_index[$term->term_id])) {
                if ($term->parent == 0) {
                    // If the term is root, add it to the top level
                    $terms_hierarchy[] = &$terms_index[$term->term_id];
                } else {
                    // If the term is a child, add it to the parent
                    if (isset($terms_index[$term->parent])) {
                        $terms_index[$term->parent]->children[] = &$terms_index[$term->term_id];
                    }
                }
            }
        }

        return $terms_hierarchy;
    }

    public function get_brands() {
        return $this->get_active()['product_brand']['terms'] ?? [];
    }

    private function activate_all_existing_terms() {
        $taxonomies = $this->get_products_taxonomies();

        foreach ($taxonomies as $taxonomy) {
            $args = [
                'taxonomy' => $taxonomy->name,
                'hide_empty' => false,
            ];

            $terms = get_terms($args);

            if (!is_wp_error($terms) && !empty($terms)) {
                foreach ($terms as $term) {
                    update_term_meta($term->term_id, 'is_active', 1);
                }
            }
        }
    }

    //Bulk normalization function for all existing products
    //executed only for the same first time 
    private function normalize_all_products_categories() {
        $args = [
            'post_type' => 'product',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ];

        $product_ids = get_posts($args);

        foreach ($product_ids as $product_id) {
            $terms = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);

            if (is_wp_error($terms)) {
                continue;
            }

            if (empty($terms)) {
                continue;
            }

            $all_terms = [];
            foreach ($terms as $term_id) {
                $all_terms[] = $term_id;
                $ancestors = get_ancestors($term_id, 'product_cat', 'taxonomy');

                if (!empty($ancestors)) {
                    $all_terms = array_merge($all_terms, $ancestors);
                }
            }

            wp_set_object_terms($product_id, array_unique($all_terms), 'product_cat', false);
        }

        return [
            'success' => true,
            'total' => count($product_ids)
        ];
    }

    public function draw_content($counter) {
        $taxonomy_objects = $this->get_products_taxonomies();
        $default_lang = $this->botoscope->controls->get_default_language();
        $active_langs = $this->botoscope->controls->get_active_languages();
        $langs = array_intersect_key($this->botoscope->languages, array_flip(array_merge($active_langs, [$default_lang])));
        ?>
        <section id="botoscope-<?php echo esc_attr($this->slug) ?>" <?php if ($counter === 0): ?>class="content-current"<?php endif; ?>>
            <div id="botoscope-<?php echo esc_attr($this->slug) ?>-selector">
                <div id="botoscope-<?php echo esc_attr($this->slug) ?>-breadcrumb"><?php $this->draw_breadcrumb(); ?></div>

                <div style="display: flex; gap: 5px;">
                    <div style="display: flex; gap: 5px; align-items: center;">
                        <a href="#" id="bs-taxonomies-activate-all-terms" class="button"><?php esc_html_e('Activate all terms', 'botoscope') ?></a>
                        <a href="#" id="bs-taxonomies-deactivate-all-terms" class="button"><?php esc_html_e('Deactivate all terms', 'botoscope') ?></a>
                    </div>

                    <select id="botoscope-<?php echo esc_attr($this->slug) ?>-selector1">
                        <?php foreach ($taxonomy_objects as $taxonomy) : ?>
                            <option value="<?php echo esc_attr($taxonomy->name) ?>" <?php selected($this->get_current_taxonomy(), $taxonomy->name) ?>><?php echo esc_html($taxonomy->labels->name) ?></option>
                        <?php endforeach; ?>
                    </select>


                    <select id="botoscope-<?php echo esc_attr($this->slug) ?>-lang-selector" class="botoscope-lang-selector" data-default-language="<?php echo esc_attr($default_lang) ?>">
                        <?php foreach ($langs as $lang_key => $lang_title) : ?>
                            <option value="<?php echo esc_attr($lang_key) ?>" <?php selected($this->get_current_language(), $lang_key) ?>><?php echo esc_attr($lang_title) ?></option>
                        <?php endforeach; ?>
                    </select>

                </div>
            </div>
            <div id="botoscope-<?php echo esc_attr($this->slug) ?>-w"><?php echo wp_json_encode($this->get(), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></div>
            <br>
            <div style="display: flex; justify-content: space-between;">
                <div>
                    <a href="javascript: void(0);" id="botoscope_create_<?php echo esc_attr($this->slug) ?>" class="button button-primary"><?php esc_html_e('Create term', 'botoscope') ?></a>
                </div>
                <div>
                    <a href="#" id="botoscope-<?php echo esc_attr($this->slug) ?>-edit-taxonomy-attribute-btn" style="display: <?php echo(in_array($this->get_current_taxonomy(), ['product_brand', 'product_cat']) ? 'none' : 'inline-block') ?>;" class="button"><?php esc_html_e('Edit', 'botoscope') ?></a>
                    <a href="#" id="botoscope-<?php echo esc_attr($this->slug) ?>-create-taxonomy-attribute-btn" class="button"><?php esc_html_e('Create product attribute', 'botoscope') ?></a>
                    <a href="#" id="botoscope-<?php echo esc_attr($this->slug) ?>-create-taxonomy-btn" class="button"><?php esc_html_e('Create product taxonomy', 'botoscope') ?></a>
                </div>
            </div>
        </section>
        <?php
    }
}
