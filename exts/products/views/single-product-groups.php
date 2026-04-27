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
                $groups = $obj->get_product_group_ids($product_id);
                $display = [];
                if (!empty($groups)) {
                    foreach ($groups as $pid) {
                        $product = wc_get_product($pid);

                        if ($product) {
                            $display[] = [
                                'id' => $pid,
                                'oid' => $pid,
                                'title' => $product->get_name()
                            ];
                        }
                    }
                }
                ?>

                <div id="botoscope_product_groupes_container" style="display: none;"><?php echo wp_json_encode($display, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></div>
            </div><!--end row-->
        </div>
</section>
