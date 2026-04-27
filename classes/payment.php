<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

//22-04-2026
final class BOTOSCOPE_PAYMENT extends BOTOSCOPE_APP {

    protected $controls;
    protected $translations;
    protected $payment_systems;
    protected $table_name = 'botoscope_payment';
    protected $slug = 'payment';
    protected $data_structure = [];

    public function __construct($args = []) {
        parent::__construct($args);

        if (botoscope_is_no_cart()) {
            return false;
        }

        $this->controls = new BOTOSCOPE_CONTROLS($args);
        $this->translations = new BOTOSCOPE_TRANSLATIONS($args);

        $this->payment_systems = [
            'paypal' => [
                'title' => '💸 PayPal',
                'details' => '',
                'settings' => [
                    'client_id' => '',
                    'client_secret' => '',
                    'test' => 1
                ],
                'payforme' => 1
            ],
            'stripe' => [
                'title' => '💸 Stripe',
                'details' => '',
                'settings' => [
                    'secret_key' => ''
                ],
                'payforme' => 1
            ],
            'liqpay' => [
                'title' => '💸 LiqPay',
                'details' => '',
                'settings' => [
                    'public_key' => '',
                    'private_key' => ''
                ],
                'payforme' => 1
            ],
            'coingate' => [
                'title' => '₿ Coingate',
                'details' => '',
                'settings' => [
                    'token' => '',
                    'test' => 1
                ],
                'payforme' => 1
            ],
            'cryptobot' => [
                'title' => '₿ Cryptobot',
                'details' => '',
                'settings' => [
                    'token' => '',
                    'accepted_assets' => 'USDT,TON',
                    'test' => 1
                ],
                'payforme' => 1
            ],
            'stars' => [
                'title' => '🌟 Telegram Stars',
                'details' => '',
                'settings' => [
                    'test' => 1
                ],
                'payforme' => 1
            ],
            'card' => [
                'title' => '💳 ' . 'To a bank card',
                'details' => '',
                'settings' => [],
                'payforme' => 0,
                'settings' => [
                    'v1' => '',
                    'v2' => '',
                    'v3' => ''
                ],
            ],
            'swift' => [
                'title' => '🏦 ' . 'Bank transfer',
                'details' => '',
                'settings' => [],
                'payforme' => 0,
                'settings' => [
                    'v1' => '',
                    'v2' => '',
                    'v3' => ''
                ],
            ],
            'payforme' => [
                'title' => '❤ ' . 'PayForMe',
                'details' => '',
                'settings' => [],
                'payforme' => 0
            ],
            'gift' => [
                'title' => '🎁💝 ' . 'Gift',
                'details' => '',
                'settings' => [],
                'payforme' => 0,
            ]
        ];

        //+++

        Botoscope_Hooks::add_action('botoscope_edit_cell', function ($what, $id, $key, $value) {
            if ($what === 'payment_settings_table') {
                $gateway = sanitize_text_field(wp_strip_all_tags($_REQUEST['additional_params']['parent_row_id']));
                $field_key = $id;

                $this->update_setting($gateway, $field_key, $value);
                $this->botoscope->reset_cache($this->slug);
            }

            if ($what === 'controls' && in_array($id, ['default_language', 'languages'])) {
                $this->botoscope->reset_cache($this->slug);
            }
        });

        Botoscope_Hooks::add_action('botoscope_get_parent_cell_data', function ($parent_app, $parent_row_id, $parent_cell_name) {
            $res = [];
            if ($parent_app === $this->slug) {
                switch ($parent_cell_name) {
                    case 'settings':
                        $res = [];
                        //$parent_row_id is gateway here
                        $settings = $this->get_settings($parent_row_id);

                        if (!empty($settings)) {
                            foreach ($settings as $key => $value) {
                                $res[] = [
                                    'title' => $key,
                                    'value' => $value,
                                    'id' => $key
                                ];
                            }
                        }

                        break;
                }
            }

            return $res;
        });

        add_action("botoscope_{$this->slug}_tab_icon", function () {
            return 'credit-card';
        });

        $this->botoscope->allrest->add_rest_route($this->slug, [$this, 'register_route']);
    }

    public function register_route(WP_REST_Request $request) {
        $gates = $this->get_active();

        $res = [
            'success_url' => rest_url('botoscope/v3/set_order_paid'),
            'cancel_url' => rest_url('botoscope/v3/cancel_order_paid'),
            'gates' => $gates
        ];

        //return $res;
        return defined('BOTOSCOPE_CLIENT_PASS') ? BOTOSCOPE_HELPER::encrypt_array($res, BOTOSCOPE_CLIENT_PASS) : '';
    }

    public function get($page_num = 0) {
        $res = [];
        $current_language = $this->get_current_language();
        $related_app = $this->slug;

        foreach ($this->payment_systems as $gateway => $data) {
            $settings = $this->get_option($gateway, 'settings');
            if (!empty($settings)) {
                $settings = json_decode($settings, true);
            } else {
                $settings = $this->payment_systems[$gateway]['settings'];
            }

            $title = $this->get_option($gateway, 'title') ?: $data['title'];
            $details = $this->get_option($gateway, 'details') ?: $data['details'];

            $ignore_language = defined('REST_REQUEST') && REST_REQUEST ? 1 : 0;

            if (!$ignore_language && $current_language !== $this->get_default_language()) {
                $title = $this->translations->get_translation($current_language, $related_app, $gateway, 'title')['value'] ?: "<ta></ta>" . $title;
                $details = $this->translations->get_translation($current_language, $related_app, $gateway, 'details')['value'] ?: "<ta></ta>" . $details;
            }

            $is_active = intval($this->get_option($gateway, 'is_active'));

            if ($gateway === 'gift') {
                $is_active = 1;
            }

            $res[] = [
                'title' => $title,
                'details' => $details,
                'payforme' => $data['payforme'],
                'settings' => $settings,
                'is_active' => $is_active,
                'id' => $gateway
            ];
        }


        return $res;
    }

    public function update($id, $key, $value, $all_sent_data = []) {
        if ($this->get_current_language() !== $this->get_default_language()) {
            $tr = $this->translations->get_translation($this->get_current_language(), $this->slug, $id, $key);
            $this->translations->update($tr['id'], $key, $value);
        } else {
            $this->update_option($id, $key, $value);
        }
    }

    private function update_option($gateway, $field_key, $value) {
        $row = $this->get_row($gateway);

        if (empty($row)) {
            $this->db->insert($this->table_name, [
                'gateway' => $gateway,
            ]);

            $id = $this->db->insert_id;
        } else {
            $id = intval($row['id']);
        }


        $this->db->update($this->table_name, [$field_key => $value], ['id' => $id]);
    }

    private function get_option($gateway, $field_key) {
        $row = $this->get_row($gateway);

        if (empty($row)) {
            $row[$field_key] = "";
        }

        return $row[$field_key];
    }

    public function get_settings($gateway) {
        $option = $this->get_option($gateway, 'settings');

        if (!empty($option)) {
            $option = json_decode($option, true);
        } else {
            $option = [];
        }

        $need_data = $this->payment_systems[$gateway]['settings'];

        if (is_array($need_data)) {
            foreach (array_keys($need_data) as $key) {
                if (isset($option[$key])) {
                    $need_data[$key] = $option[$key];
                }
            }
        }


        return $need_data;
    }

    public function update_setting($gateway, $key, $value) {
        $settings = $this->get_settings($gateway);
        $settings[$key] = $value;
        $this->update_option($gateway, 'settings', wp_json_encode($settings));
    }

    private function get_row($gateway) {

        static $res = [];//cache

        if (!array_key_exists($gateway, $res)) {

            $res[$gateway] = $this->db->get_row(
                    $this->db->prepare("SELECT * FROM `{$this->table_name}` WHERE gateway = %s", $gateway),
                    ARRAY_A
            );
        }

        return $res[$gateway];
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

                $st = [
                    'title' => $r['title'],
                    'details' => $r['details'],
                    'payforme' => $r['payforme'],
                ];

                if (!empty($r['settings'])) {
                    $settings = $r['settings'];

                    if (is_array($settings)) {
                        foreach ($settings as $sk => $s) {
                            $st[$sk] = wp_strip_all_tags($s);
                        }
                    }
                }

                //+++

                $translations = [];
                $fields = ['title', 'details'];
                if (!empty($this->controls->get_active_languages())) {
                    foreach ($this->controls->get_active_languages() as $language) {

                        if (!isset($translations[$language])) {
                            $translations[$language] = [];
                        }

                        foreach ($fields as $cell_name) {
                            $t = $this->translations->get_row($language, $this->slug, $r['id'], $cell_name);
                            if ($t) {
                                $value = $t['value'] ?: null;

                                if ($value) {
                                    $translations[$language][$cell_name] = $value;
                                }
                            }
                        }

                        if (empty($translations[$language])) {
                            unset($translations[$language]);
                        }
                    }
                }

                $st['translations'] = $translations;

                //+++

                $res[$r['id']] = $st;
            }
        }

        return $res;
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
                    <option value="<?php echo esc_attr($lang_key) ?>" <?php selected($this->get_current_language(), esc_attr($lang_key)) ?>><?php echo esc_attr($lang_title) ?></option>
                <?php endforeach; ?>
            </select>


            <div id="botoscope-<?php echo esc_attr($this->slug) ?>-w"><?php echo wp_json_encode($this->get(), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></div>

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
        gateway varchar(32) NOT NULL,
        title varchar(32) DEFAULT NULL,
        details text DEFAULT NULL,
        settings mediumtext DEFAULT NULL,
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

        // Insert default data
        $default_data = [
            [
                'gateway' => 'stars',
                'title' => '🌟 Telegram Stars'
            ],
            [
                'gateway' => 'paypal',
                'title' => '💸 PayPal'
            ],
            [
                'gateway' => 'stripe',
                'title' => '💸 Stripe'
            ],
            [
                'gateway' => 'liqpay',
                'title' => '💸 LiqPay'
            ],
            [
                'gateway' => 'coingate',
                'title' => '₿ Coingate'
            ],
            [
                'gateway' => 'cryptobot',
                'title' => '₿ Cryptobot'
            ],
            [
                'gateway' => 'card',
                'title' => '💳 ' . esc_html__("To a bank card", 'botoscope'),
                'details' => esc_html__("💳 When sending the payment (bank transfer or card), please include your **order number** 📦 and **phone number** 📱 in the payment reference. ⚠️ Without these details, we may not be able to confirm your payment promptly.", 'botoscope')
            ],
            [
                'gateway' => 'swift',
                'title' => '🏦 ' . esc_html__("Bank transfer", 'botoscope'),
                'details' => esc_html__("💳 When sending the payment (bank transfer or card), please include your **order number** 📦 and **phone number** 📱 in the payment reference. ⚠️ Without these details, we may not be able to confirm your payment promptly.", 'botoscope')
            ],
            [
                'gateway' => 'payforme',
                'title' => '❤️ PayForMe',
                'details' => esc_html__("After placing your order, don't forget to send the link to your partner.", 'botoscope')
            ],
        ];

        foreach ($default_data as $data) {
            $wpdb->insert($this->table_name, $data);
        }
    }
}
