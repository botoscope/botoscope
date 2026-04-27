<?php
if (!defined('ABSPATH')) {
    exit;
}

global $Botoscope;
$items_in_visible_menu = 7;

if (wp_is_mobile()) {
    $items_in_visible_menu = 2;
}

$apps_slugs = Botoscope_Hooks::apply_action('botoscope_panel_tabs', []);
$apps_slugs['taxonomies'] = esc_html__('Taxonomies', 'botoscope');
if (!botoscope_is_no_cart()) {
    $apps_slugs['payment'] = esc_html__('Payment', 'botoscope');
}
$apps_slugs['controls'] = esc_html__('System Controls', 'botoscope');
$apps_slugs['extensions'] = esc_html__('Extensions', 'botoscope');
?>

<div class="botoscope-admin-preloader">
    <div class="cssload-loader">
        <div class="cssload-inner cssload-one"></div>
        <div class="cssload-inner cssload-two"></div>
        <div class="cssload-inner cssload-three"></div>
    </div>
</div>

<div class="wrap nosubsub">
    <div class="botoscope-header">
        <div>            
            <h1 class="botoscope-plugin-name">
                <img src="<?php echo esc_url(BOTOSCOPE_ASSETS_LINK) ?>img/logo.webp" alt="logo" style="position: unset" /> Botoscope Business <span>v.<?php echo esc_attr(BOTOSCOPE_VERSION) ?></span> <img src="<?php echo esc_url(BOTOSCOPE_ASSETS_LINK) ?>img/dolphin.svg" alt="logo" />
                <span class="botoscope-slogan"><?php esc_html_e('Boost Your Business with Telegram Commerce', 'botoscope') ?> <em style="color: crimson"><?php if (is_botoscope_free()) echo '[' . esc_html__('free version', 'botoscope') . ']'; ?></em></span>
            </h1>

        </div>

        <div>
            <?php if (is_botoscope_free() && is_botoscope_connected()): ?>
                <a href="https://botoscope.com/upgrade" class="bs-button bs-button-small" target="_blank">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M13.13 22.19L11.5 18.36C13.07 17.78 14.54 17 15.9 16.09L13.13 22.19M5.64 12.5L1.81 10.87L7.91 8.1C7 9.46 6.22 10.93 5.64 12.5M21.61 2.39C21.61 2.39 16.66 .269 11 5.93C8.81 8.12 7.5 10.53 6.65 12.64C6.37 13.39 6.56 14.21 7.11 14.77L9.24 16.89C9.79 17.45 10.61 17.63 11.36 17.35C13.5 16.53 15.88 15.19 18.07 13C23.73 7.34 21.61 2.39 21.61 2.39M14.54 9.46C13.76 8.68 13.76 7.41 14.54 6.63S16.59 5.85 17.37 6.63C18.14 7.41 18.15 8.68 17.37 9.46C16.59 10.24 15.32 10.24 14.54 9.46M8.88 16.53L7.47 15.12L8.88 16.53M6.24 22L9.88 18.36C9.54 18.27 9.21 18.12 8.91 17.91L4.83 22H6.24M2 22H3.41L8.18 17.24L6.76 15.83L2 20.59V22Z"/></svg>
                    <?php esc_html_e('Upgrade', 'botoscope') ?>                                        
                </a>
            <?php endif; ?>

            <a href="https://botoscope.com/documentation" class="bs-button bs-button-small" target="_blank">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm4 18H6V4h7v5h5v11zM8 15h8v2H8zm0-4h8v2H8z"/></svg>
                <?php esc_html_e('Documentation', 'botoscope') ?>                                        
            </a>
        </div>
    </div>


    <?php
    $allowUrlFopen = filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN);
    $hasCurl = function_exists('curl_init');

    if (!$allowUrlFopen) {
        echo '<div class="notice notice-error"><p>';
        esc_html_e('Error: The PHP setting "allow_url_fopen" is disabled. Please enable it in your hosting configuration to allow Botoscope to make outgoing HTTP requests.', 'botoscope');
        echo '</p></div>';
    }

    if (!$hasCurl) {
        echo '<div class="notice notice-error"><p>';
        esc_html_e('Error: The PHP extension "cURL" is not installed or enabled. Please enable it in your hosting configuration to allow Botoscope to make outgoing HTTP requests.', 'botoscope');
        echo '</p></div>';
    }
    ?>



    <div class="botoscope-tab-top-panel">
        <div>
            <div style="display: none;">
                <?php
                //todo, this is for js dependency, later resolve
                $active_languages = $botoscope->controls->get_active_languages();
                $default_language = $botoscope->controls->get_default_language();

                array_unshift($active_languages, $default_language); //!!

                if ($active_languages) {
                    ?>
                    <div id="botoscope_products_language_selector">
                        <select data-default="<?php echo esc_attr($default_language) ?>">
                            <?php foreach ($botoscope->languages as $key => $title) : ?>

                                <?php
                                if (!in_array($key, $active_languages)) {
                                    continue;
                                }
                                ?>

                                <option value="<?php echo esc_attr($key) ?>"><?php echo esc_html($title) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php
                }
                ?>
            </div>
        </div>
        <div>
            &nbsp;
        </div>
    </div>


    <div class="botoscope-tabs botoscope-tabs-style-shape" style="position: relative;">

        <nav <?php if (count($apps_slugs) >= $items_in_visible_menu): ?>style="padding-right: 25px;"<?php endif; ?>>
            <ul id="botoscope_menu_manager_menu">
                <?php
                $counter = 0;
                $offset_counter = 1;
                foreach ($apps_slugs as $key => $title) :

                    if ($counter >= $items_in_visible_menu) {
                        $offset_counter++;
                    }

                    if ($Botoscope->no_bot && in_array($key, ['extensions2', 'payment'])) {
                        continue;
                    }
                    ?>

                    <li style="order: <?php echo intval($counter + 1) ?>;" data-slug="<?php echo esc_attr($key) ?>" <?php if ($counter++ === 0): ?>class="tab-current"<?php endif; ?> data-offset="<?php echo intval($offset_counter * 50) ?>">
                        <a href="#botoscope-<?php echo esc_attr($key) ?>" onclick="return botoscope_init_js_intab('tabs-botoscope-<?php echo esc_attr($key) ?>')"><span class="icon-<?php echo esc_attr(apply_filters("botoscope_{$key}_tab_icon", 'database')) ?>"></span><?php echo esc_html($title) ?></a>
                    </li>

                <?php endforeach; ?>

                <?php if ($Botoscope->no_bot): ?>

                    <li style="order: 99;" class="tab-current" data-offset="50">

                        <a href="#" onclick="window.open('https://botoscope.com/start', '_blank')" target="_blank" style="display: inline-block; background: linear-gradient(135deg, #1DA1F2, #0078D7); color:#fff;"><span class="svg_wrap relative" style="display:inline-block;width:30px; line-height: 36px;"><svg viewBox="0 0 315.1 315.1">
                                <path style="fill:#fff" class="st0" d="M303.4,21L5.5,135.9c-7.3,2.8-7.3,13.2,0.1,16L78.2,179l28.1,90.4c1.8,5.8,8.9,7.9,13.6,4.1l40.5-33  c4.2-3.5,10.3-3.6,14.7-0.4l73,53c5,3.7,12.1,0.9,13.4-5.2L315,30.8C316.2,24,309.7,18.5,303.4,21z M246.5,81.1l-117,108.8  c-4.1,3.8-6.8,9-7.5,14.5l-4,29.6c-0.5,3.9-6.1,4.3-7.2,0.5l-15.3-53.9c-1.8-6.1,0.8-12.7,6.2-16.1l141.9-87.4  C246.1,75.6,248.7,79.1,246.5,81.1z"></path>
                                </svg></span>&nbsp;<?php esc_html_e('Connect your shop to Telegram', 'botoscope') ?>
                        </a>

                    </li>

                <?php endif; ?>

            </ul>
        </nav>

        <?php if (count($apps_slugs) >= $items_in_visible_menu): ?>
            <!-- Hamburger button for hidden items -->
            <span class="botoscope_menu_manager_hamburger" id="botoscope_menu_manager_hamburger">☰</span>
        <?php endif; ?>

        <div class="content-wrap">

            <?php
            $counter = 0;
            foreach (array_keys($apps_slugs) as $key) {
                if (isset($botoscope->$key)) {
                    $botoscope->$key->draw_content($counter);
                    $counter++;
                }
            }
            ?>

            <div id="botoscope-compatible-plugins">
                <a href="https://products-filter.com/" title="HUSKY - WooCommerce Products Filter Professional" target="_blank"><img src="<?php echo esc_url(BOTOSCOPE_ASSETS_LINK) ?>img/husky.png" alt="HUSKY - WooCommerce Products Filter Professional"></a>
                <a href="https://currency-switcher.com/" title="FOX - WooCommerce Currency Switcher Professional" target="_blank"><img src="<?php echo esc_url(BOTOSCOPE_ASSETS_LINK) ?>img/fox.png" alt="FOX - WooCommerce Currency Switcher Professional"></a>
                <a href="https://bulk-editor.com/" title="BEAR - WooCommerce Bulk Editor and Products Manager Professional" target="_blank"><img src="<?php echo esc_url(BOTOSCOPE_ASSETS_LINK) ?>img/bear.png" alt="BEAR - WooCommerce Bulk Editor and Products Manager Professional"></a>
            </div>

        </div>


        <template id="woocommerce_product_attributes"><?php echo wp_json_encode($woocommerce_product_attributes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></template>
        <template id="botoscope_languages_list"><?php echo wp_json_encode($languages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></template>
    </div>

    <div class="transparent-window"></div>

    <input type="hidden" id="botoscope_form_nonce" value="<?php echo esc_attr(wp_create_nonce('botoscope_form_nonce')); ?>">
</div>
