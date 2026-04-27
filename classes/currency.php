<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

//23-01-2026
final class BOTOSCOPE_CURRENCY extends BOTOSCOPE_APP {

    protected $table_name = 'botoscope_currency';
    protected $slug = 'currency';

    public function __construct($args) {
        parent::__construct($args);

        add_action('woocommerce_update_options_woocs', function () {
            $this->botoscope->do_command(-1, 'reset_cache', [
                'cache_name' => 'currency'
            ]);
        }, 10, 1);

        add_action('woocs_sheduler_rates_updated', function () {
            $this->botoscope->do_command(-1, 'reset_cache', [
                'cache_name' => 'currency'
            ]);
        }, 10, 1);

        $this->botoscope->allrest->add_rest_route($this->slug, [$this, 'register_route']);
    }

    public function register_route(WP_REST_Request $request) {
        return $this->get_active();
    }

    public function get_active() {
        return $this->get_currency();
    }

    public function get_currency() {
        $shop_currency = strtolower(get_option('woocommerce_currency', 'eur'));

        $res = [
            'basic' => $shop_currency,
            'default' => $shop_currency,
            'is_multiple_allowed' => 0,
            'set' => [
                //this is default data if WOOCS plugin not installed
                $shop_currency => [
                    'title' => strtoupper($shop_currency),
                    'rate' => 1,
                    'symbol' => html_entity_decode(get_woocommerce_currency_symbol(strtoupper($shop_currency)), ENT_QUOTES | ENT_HTML5),
                    'decimals' => (int) get_option('woocommerce_price_num_decimals', 2),
                    'position' => get_option('woocommerce_currency_pos', 'left')
                ]
            ]
        ];

        //+++
        //global $WOOCS;//sometimes it is null
        $woocs_data = get_option('woocs', []);
        if (!empty($woocs_data) && is_array($woocs_data)) {
            //$woocs_data = get_option('woocs');

            if (!empty($woocs_data)) {
                $currencies = [];
                $basic = '';
                $default = '';

                foreach ($woocs_data as $ckey => $c) {

                    if (!is_array($c)) {
                        continue;
                    }

                    if (intval($c['hide_on_front'])) {
                        continue;
                    }

                    $currencies[strtolower((string) $ckey)] = [
                        'title' => $c['name'],
                        'symbol' => html_entity_decode($c['symbol'], ENT_QUOTES | ENT_HTML5),
                        'position' => $c['position'],
                        'rate' => floatval($c['rate']),
                        'decimals' => intval($c['decimals']),
                        'rate_plus' => intval($c['rate_plus']),
                        'hide_cents' => intval($c['hide_cents']),
                    ];

                    if (intval($c['is_etalon']) === 1) {
                        $default = $basic = strtolower((string) $ckey);
                    }
                }

                $res = [
                    'basic' => $basic,
                    'default' => $default,
                    'is_multiple_allowed' => intval(get_option('woocs_is_multiple_allowed')),
                    'set' => $currencies
                ];
            }
        }

        return $res;
    }
}
