<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
?>

<select id="botoscope-products_meta_gallery-lang-selector" class="botoscope-lang-selector" data-default-language="<?php echo esc_attr($default_lang) ?>">
    <?php foreach ($langs as $lang_key => $lang_title) : ?>
        <option value="<?php echo esc_attr($lang_key) ?>" <?php selected($current_language, $lang_key) ?>><?php echo esc_attr($lang_title) ?></option>
    <?php endforeach; ?>
</select>

<div id="botoscope-products-meta-gallery-wrapper" data-meta-exclude="<?php echo esc_attr(implode(',', $exclude)) ?>" data-meta-types="<?php echo esc_attr(implode(',', $meta_types)) ?>" data-product-id="<?php echo intval($product_id) ?>"><?php echo wp_json_encode($gallery, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></div>
