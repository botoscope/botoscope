<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
?>
<section>
    <div class="form-body mt-4">
        <div class="row">
            <div class="col-lg-12" style="position: relative;">

                <?php
                $child_ids = isset($data['child_ids']) ? $data['child_ids'] : [];
                $childs_array = $obj->get_variations_by_ids($child_ids);
                ?>

                <div id="botoscope_product_variations_container" style="display: none;"><?php echo wp_json_encode($childs_array, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></div>
            </div><!--end row-->
        </div>
</section>
