<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
?>

<div id="botoscope-products-meta-packs-wrapper"><?php echo wp_json_encode($packs, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></div>
