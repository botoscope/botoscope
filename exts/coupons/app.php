<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

//13-10-2025
final class BOTOSCOPE_COUPONS extends BOTOSCOPE_APP {

    protected $table_name = ''; //do not need here as used native woo coupons
    protected $slug = 'coupons';
    protected $data_structure = [
        'code' => '',
        'amount' => 0,
        'discount_type' => 'percent',
        'usage_limit' => 0,
        'minimum_amount' => 0,
        'maximum_amount' => 0,
        'date_expires' => 0,
        'is_active' => 0,
    ];

    public function __construct($args = []) {
        parent::__construct($args);

        if (botoscope_is_no_cart()) {
            return false;
        }

        $this->botoscope->allrest->add_rest_route($this->slug, [$this, 'register_route']);

        Botoscope_Hooks::add_action('botoscope_panel_tabs', function ($tabs) {
            $tabs[$this->slug] = esc_html__('Coupons', 'botoscope');
            return $tabs;
        });

        //+++

        Botoscope_Hooks::add_action('botoscope_edit_cell', function ($what, $id, $key, $value) {
            if ($what === 'coupons_update_products') {
                $add = intval($_REQUEST['add']);
                $product_id = intval($_REQUEST['product_id']);
                $coupon_id = intval($_REQUEST['coupon_id']);

                $this->update_products($coupon_id, $product_id, $add);
                $this->botoscope->reset_cache($this->slug);
            }

            if ($what === 'coupons') {
                if ($key === 'is_active') {
                    $value = intval($value);
                    $status = $value ? 'publish' : 'draft';

                    $post = get_post(intval($id));
                    if ($post && $post->post_type === 'shop_coupon') {
                        if ($post->post_status !== $status) {
                            wp_update_post(['ID' => $post->ID, 'post_status' => $status]);
                            clean_post_cache($post->ID);
                        }
                    }
                }
            }
        });

        Botoscope_Hooks::add_action('botoscope_get_parent_cell_data', function ($parent_app, $parent_row_id, $parent_cell_name) {
            $res = [];
            if ($parent_app === $this->slug) {
                switch ($parent_cell_name) {
                    case 'products':
                        $res = $this->get_products($parent_row_id);
                        break;
                }
            }

            return $res;
        });

        Botoscope_Hooks::add_action('botoscope_delete_row', function ($what, $row_id, $parent_row_id) {
            if ($what === 'coupons_products') {
                $this->delete_product($parent_row_id, $row_id);
                $this->botoscope->reset_cache($this->slug);
            }
        });

        Botoscope_Hooks::add_action('botoscope_search_products_not_in', function ($what, $parent_row_id, $search_term) {
            if ($what === $this->slug) {
                return (new Botoscope_WC_Coupon($parent_row_id))->get_product_ids();
            }

            return [];
        });

        add_action("botoscope_{$this->slug}_tab_icon", function () {
            return 'ticket';
        });
    }

    public function register_route(WP_REST_Request $request) {
        return $this->get_coupons_data();
    }

    public function create($data = []) {
        $res = [];

        $coupon = new Botoscope_WC_Coupon();
        $coupon_code = substr(md5(time()), 0, 12);
        $coupon->set_code($coupon_code);
        $coupon->set_discount_type('percent');
        $coupon_id = $coupon->save();

        if ($coupon_id) {
            $coupon->update_meta_data('created_in_botoscope', time());
            $coupon->save();

            $this->data_structure['id'] = $coupon_id;
            $this->data_structure['code'] = $coupon_code;

            $res = $this->data_structure;
        }

        return $res;
    }

    public function get($page_num = 0) {
        $res = [];

        $args = array(
            'post_type' => 'shop_coupon',
            'posts_per_page' => -1,
            'post_status' => 'any'
        );

        $coupons = get_posts($args);
        $botoscope_coupons = [];

        if ($coupons) {
            foreach ($coupons as $coupon_post) {
                $coupon = new Botoscope_WC_Coupon($coupon_post->ID);

                //if (intval($coupon->get_meta('created_in_botoscope'))) {
                $botoscope_coupons[] = $coupon;
                //}
            }
        }

        //+++

        if (!empty($botoscope_coupons)) {
            foreach ($botoscope_coupons as $coupon) {
                $res[] = [
                    'code' => $coupon->get_code(),
                    'amount' => $coupon->get_amount(),
                    'discount_type' => $coupon->get_discount_type() ?? '',
                    'usage_limit' => $coupon->get_usage_limit() ?? '',
                    'date_expires' => $coupon->get_date_expires()?->getTimestamp() ?? '',
                    'minimum_amount' => floatval($coupon->get_minimum_amount()),
                    'maximum_amount' => floatval($coupon->get_maximum_amount()),
                    'is_active' => get_post_status($coupon->get_id()) === 'publish' ? 1 : 0,
                    'id' => $coupon->get_id(),
                    'product_ids' => implode(',', $coupon->get_product_ids())
                ];
            }
        }

        return $res;
    }

    public function update($coupon_id, $field_key, $value, $all_sent_data = []) {

        if (!$coupon_id) {
            wp_send_json_error('Invalid coupon ID');
        }

        $coupon = new Botoscope_WC_Coupon($coupon_id);

        if ($coupon) {

            $allowed_methods = array(
                'code' => 'set_code',
                'amount' => 'set_amount',
                'discount_type' => 'set_discount_type',
                'usage_limit' => 'set_usage_limit',
                'minimum_amount' => 'set_minimum_amount',
                'maximum_amount' => 'set_maximum_amount',
                'date_expires' => 'set_date_expires',
                'product_ids' => 'set_product_ids',
                'is_active' => 'set_active',
            );

            // Check if such a key exists in the allowed methods
            if (array_key_exists($field_key, $allowed_methods)) {
                call_user_func(array($coupon, $allowed_methods[$field_key]), $value);
                $coupon->save();
            } else {
                wp_send_json_error('Invalid field key');
            }
        }
    }

    public function delete($id, $conditions = []) {
        $coupon = new Botoscope_WC_Coupon($id);
        $coupon->delete();
    }

    public function get_products($coupon_id) {
        $coupon = new Botoscope_WC_Coupon($coupon_id);
        $product_ids = $coupon->get_product_ids();
        $res = [];

        if (!empty($product_ids)) {
            foreach ($product_ids as $pid) {
                $product = wc_get_product($pid);

                if ($product && $product->is_type('variation')) {
                    $parent_id = $product->get_parent_id();
                    $parent_title = get_the_title($parent_id);

                    $attributes = $product->get_attributes();
                    $attributes_string = implode(' | ', array_map(function ($key, $value) {
                                //$attribute_label = wc_attribute_label($key);
                                $term = get_term_by('slug', $value, $key);
                                $term_name = $term ? $term->name : $value;

                                return $term_name;
                            }, array_keys($attributes), $attributes));

                    $title = $parent_title . ': ' . $attributes_string;
                } else {
                    $title = get_the_title($pid);
                }

                $res[] = [
                    'title' => esc_html($title),
                    'id' => intval($pid)
                ];
            }
        }

        return $res;
    }

    public function delete_product($coupon_id, $product_id) {
        $coupon = new Botoscope_WC_Coupon($coupon_id);
        $product_ids = $coupon->get_product_ids();

        if (($key = array_search($product_id, $product_ids)) !== false) {
            unset($product_ids[$key]);
            $coupon->set_product_ids($product_ids);
            $coupon->save();
        }
    }

    public function update_products($coupon_id, $product_id, $add) {
        $coupon = new Botoscope_WC_Coupon($coupon_id);
        $product_ids = $coupon->get_product_ids();

        if ($add) {
            $product_ids[] = $product_id;
        } else {
            if (($key = array_search($product_id, $product_ids)) !== false) {
                unset($product_ids[$key]);
            }
        }

        $coupon->set_product_ids($product_ids);
        $coupon->save();
    }

    private function get_coupons_data() {
        $args = array(
            'post_type' => 'shop_coupon',
            'posts_per_page' => -1
        );
        $coupons = get_posts($args);
        $coupons_data = array();

        foreach ($coupons as $coupon) {
            $coupon_id = $coupon->ID;
            $coupon_obj = new WC_Coupon($coupon_id);
            //if (intval($coupon_obj->get_meta('created_in_botoscope')) && intval($coupon_obj->get_meta('is_active'))) {
            if (get_post_status($coupon_id) === 'publish') {
                $coupons_data[$coupon_obj->get_code()] = array(
                    'id' => $coupon_id,
                    'is_active' => 1,
                    'code' => $coupon_obj->get_code(),
                    'type' => $coupon_obj->get_discount_type(),
                    'value' => floatval($coupon_obj->get_amount()),
                    'unit' => (strpos($coupon_obj->get_discount_type(), 'fixed') !== false) ? 'n' : 'p',
                    'disable_marketing' => 0,
                    'date_created' => strtotime($coupon_obj->get_date_created()->date('Y-m-d H:i:s')),
                    'date_expires' => $coupon_obj->get_date_expires() ? strtotime($coupon_obj->get_date_expires()->date('Y-m-d H:i:s')) : 0,
                    'usage_limit' => $coupon_obj->get_usage_limit(),
                    'usage_count' => $coupon_obj->get_usage_count(),
                    //'usage_limit_per_user' => $coupon_obj->get_usage_limit_per_user(),
                    //'limit_usage_to_x_items' => $coupon_obj->get_limit_usage_to_x_items(),
                    //'free_shipping' => $coupon_obj->get_free_shipping() ? 1 : 0,
                    'products' => in_array($coupon_obj->get_discount_type(), ['botoscope_percent_product', 'fixed_product']) ? $coupon_obj->get_product_ids() : [],
                    //'excluded_products' => $coupon_obj->get_excluded_product_ids(),
                    //'product_categories' => $coupon_obj->get_product_categories(),
                    //'excluded_product_categories' => $coupon_obj->get_excluded_product_categories(),
                    //'exclude_sale_items' => $coupon_obj->get_exclude_sale_items() ? 1 : 0,
                    'min_amount' => floatval($coupon_obj->get_minimum_amount()),
                    'max_amount' => floatval($coupon_obj->get_maximum_amount()),
                        //'email_restrictions' => $coupon_obj->get_email_restrictions(),
                );
            }
        }

        return $coupons_data;
    }

    public function draw_content($counter) {
        ?>
        <section id="botoscope-<?php echo esc_attr($this->slug) ?>" <?php if ($counter === 0): ?>class="content-current"<?php endif; ?>>

            <div id="botoscope-<?php echo esc_attr($this->slug) ?>-w"><?php echo wp_json_encode($this->get(), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></div>
            <br>
            <a href="javascript: void(0);" id="botoscope_create_<?php echo esc_attr($this->slug) ?>" class="button button-primary"><?php esc_html_e('Create', 'botoscope') ?></a><br>

        </section>
        <?php
    }
}

class Botoscope_WC_Coupon extends WC_Coupon {

    public function set_active($value) {
        $this->update_meta_data('is_active', intval($value));
        $this->save();
    }

    public function is_active() {
        return intval($this->get_meta('is_active'));
    }
}
