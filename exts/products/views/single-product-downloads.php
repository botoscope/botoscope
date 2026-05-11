<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
?>
<section>
    <div class="form-body mt-4">
        <div class="row">
            <div class="col-lg-12">
                <?php if (is_botoscope_free()): ?>
                    <div class="bs-warning-box"><p><?php
                            /* translators: 1: max files free */
                            printf(esc_html__('Free version: showing up to %1$s media files. Upgrade to unlock all.', 'botoscope'), 3)
                            ?></p></div>
                <?php endif; ?>
                <div id="botoscope_product_downloads" style="display: none;"><?php echo wp_json_encode(isset($data['downloads']) ? $data['downloads'] : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></div>
            </div><!--end row-->
        </div>
</section>
