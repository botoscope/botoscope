<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
//template for grouped product
?>
<section>
    <div class="form-body mt-4">
        <div class="row">
            <div class="col-lg-12" style="position: relative;">

                <?php
                $child_ids = isset($data['child_ids']) ? $data['child_ids'] : [];
                $childs_array = [];
                if (!empty($child_ids)) {
                    foreach ($child_ids as $pid) {
                        $product = wc_get_product($pid);

                        if ($product/* && !$product->is_type('variation') */) {
                            $childs_array[] = [
                                'id' => $pid,
                                'oid' => $pid,
                                'title' => $product->get_name()
                            ];
                        }
                    }
                }
                ?>

                <div id="botoscope_product_products_container" style="display: none;"><?php echo wp_json_encode($childs_array, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></div>
            </div><!--end row-->
        </div>
</section>
