<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
?>
<section>
    <div class="form-body mt-4">
        <div class="row">
            <div class="col-lg-12">
                <div id="botoscope_product_downloads" style="display: none;"><?php echo wp_json_encode(isset($data['downloads']) ? $data['downloads'] : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></div>
            </div><!--end row-->
        </div>
</section>
