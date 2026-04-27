<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

global $Botoscope;
?>

<section>
    <form>
        <div class="form-body mt-4">
            <div class="row">
                <div class="col-lg-8">
                    <div class="border border-3 p-4 rounded product-sidebar">
                        <div class="mb-3">

                            <label for="botoscope-product-description" class="form-label"><?php esc_html_e('Description', 'botoscope') ?></label>

                            <div class="row">
                                <div class="col-12">
                                    <a href="#" id="botoscope_product_variation_description_ai" class="botoscope-button botoscope-button-small"><?php esc_html_e('generate', 'botoscope') ?></a>&nbsp;<a href="#" id="botoscope_product_variation_grammar_ai" class="botoscope-button botoscope-button-small"><?php esc_html_e('correct grammar', 'botoscope') ?></a>
                                </div>
                            </div>


                            <?php
                            wp_editor($data['description'], 'botoscope-variation-description', [
                                'textarea_name' => 'description',
                                'media_buttons' => false,
                                'textarea_rows' => 10,
                                'teeny' => false,
                                'quicktags' => true
                            ]);
                            ?>
                        </div>


                        <div class="mb-3">
                            <!-- <label class="form-label">Media</label> -->
                            <div id="botoscope-product_variation-media-container" style="position: relative;"></div>
                            <input name="media" id="botoscope-product_variation-media-value" type="hidden" value="<?php echo esc_attr(implode(',', array_column($data['media'], 'aid'))) ?>">
                            <div id="botoscope-product_variation-media-value-container" style="display: none;"><?php echo wp_json_encode($data['media'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></div>
                        </div>

                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="border border-3 p-4 rounded product-sidebar">
                        <div class="row g-3">

                            <div class="col-12">
                                <div class="d-grid">
                                    <input type="submit" class="botoscope-button botoscope-button-small" onclick="return botoscope_close_ps_sidebar(this);" value="<?php esc_html_e('Save and close', 'botoscope') ?>">
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="d-grid">
                                    <input type="submit" class="botoscope-button botoscope-button-small" value="<?php esc_html_e('Save', 'botoscope') ?>">
                                </div>
                            </div>

                            <div class="col-12">
                                <?php
                                $product_types = [
                                    'variation_physical' => esc_html__('Physical', 'botoscope'),
                                    'variation_virtual' => esc_html__('Virtual', 'botoscope'),
                                    'variation_virtual_downloadable' => esc_html__('Virtual Downloadable', 'botoscope'),
                                    'variation_media_casting' => esc_html__('Media Casting', 'botoscope'),
                                ];

                                if ($Botoscope->no_bot) {
                                    unset($product_types['variation_media_casting']);
                                }

                                $type = $data['botoscope_type'];

                                $files_count = isset($data['downloads']) ? count($data['downloads']) : 0;
                                $products_count = isset($data['child_ids']) ? count($data['child_ids']) : 0;
                                ?>
                                <label for="botoscope_product_variation_type" class="form-label"><?php esc_html_e('Variation type', 'botoscope') ?></label>
                                <select class="form-select" id="botoscope_product_variation_type" name="type">

                                    <?php foreach ($product_types as $key => $value) : ?>
                                        <option value="<?php echo esc_attr($key) ?>" <?php echo selected($type, $key) ?>><?php echo esc_html($value) ?></option>
                                    <?php endforeach; ?>

                                </select><br>

                                <div class="d-grid">
                                    <a href="#" style="display: <?php echo esc_attr(in_array($type, ['variation_virtual_downloadable', 'variation_media_casting']) ? 'inline-block !important' : 'none !important') ?>;" class="botoscope-button botoscope-button-small" id="botoscope_product_variation_files"><?php
                                        /* translators: %d: files count */
                                        printf(esc_html__('Files (%d)', 'botoscope'), intval($files_count))
                                        ?></a>
                                </div>
                            </div>

                            <div class="col-md-6 botoscope-product-price-container">
                                <label for="inputProductPrice_variation" class="form-label"><?php esc_html_e('Regular price', 'botoscope') ?></label>
                                <input name="price" type="number" step="any" class="form-control" id="inputProductPrice_variation" placeholder="00.00" value="<?php echo floatval($data['price']) ?>">
                            </div>

                            <div class="col-md-6 botoscope-product-price-container">
                                <label for="inputProductSalePrice_variation" class="form-label"><?php esc_html_e('Sale price', 'botoscope') ?></label>
                                <input name="sale_price" type="number" step="any" class="form-control" id="inputProductSalePrice_variation" placeholder="00.00" value="<?php echo floatval($data['sale_price']) ?>">
                            </div>



                            <div class="col-12 botoscope_need_cart botoscope_manage_stock" style="display: <?php echo (intval($data['manage_stock']) && in_array($type, ['variation_physical']) ? 'block' : 'none') ?>;">
                                <label for="stock_quantity" class="form-label"><?php esc_html_e('Stock quantity', 'botoscope') ?></label>
                                <input name="stock_quantity" type="number" step="1" class="form-control" placeholder="0" value="<?php echo intval($data['stock_quantity']) ?>" onwheel="event.preventDefault()">
                            </div>

                            <div class="col-12 botoscope_need_cart botoscope_manage_stock" style="display: <?php echo in_array($type, ['variation_physical']) ? 'block' : 'none' ?>;">
                                <label for="manage_stock" class="form-label"><?php esc_html_e('Manage stock', 'botoscope') ?></label>
                                <select class="form-select" id="manage_stock" name="manage_stock" onchange="toggleStockQuantity(this)">
                                    <option value="1" <?php echo selected(intval($data['manage_stock']), 1) ?>><?php esc_html_e('yes', 'botoscope') ?></option>
                                    <option value="0" <?php echo selected(intval($data['manage_stock']), 0) ?>><?php esc_html_e('no', 'botoscope') ?></option>
                                </select>
                            </div>

                            <div class="col-12 botoscope_need_cart" style="display: <?php echo intval($data['manage_stock']) ? 'none' : 'block' ?>;">
                                <label for="is_in_stock" class="form-label"><?php esc_html_e('Stock', 'botoscope') ?></label>
                                <select class="form-select" id="is_in_stock" name="is_in_stock">
                                    <option value="instock" <?php echo selected($data['is_in_stock'], 1) ?>><?php esc_html_e('in stock', 'botoscope') ?></option>
                                    <option value="outofstock" <?php echo selected($data['is_in_stock'], 0) ?>><?php esc_html_e('out of stock', 'botoscope') ?></option>
                                </select>
                            </div>


                            <div class="col-12">
                                <label for="inputProductSKU_variation" class="form-label"><?php esc_html_e('SKU', 'botoscope') ?></label>
                                <input name="sku" type="text" class="form-control" id="inputProductSKU_variation" placeholder="" value="<?php echo esc_attr($data['sku']) ?>">
                            </div>


                            <div class="col-12" style="display: <?php echo $Botoscope->no_bot ? 'none' : 'block' ?>">
                                <label for="quantity_step_variation" class="form-label"><?php esc_html_e('Quantity step', 'botoscope') ?></label>
                                <input name="quantity_step" type="number" min="1" class="form-control" id="quantity_step_variation" placeholder="<?php esc_html_e('the default value comes from the parent product', 'botoscope') ?>" value="<?php echo esc_attr($data['quantity_step'] ?: '') ?>">
                            </div>

                            <div class="col-12" style="display: <?php echo $Botoscope->no_bot ? 'none' : 'block' ?>">
                                <label for="min_cart_count_variation" class="form-label"><?php esc_html_e('Minimal cart count', 'botoscope') ?></label>
                                <input name="min_cart_count" type="number" min="1" class="form-control" id="min_cart_count_variation" placeholder="<?php esc_html_e('the default value comes from the parent product', 'botoscope') ?>" value="<?php echo esc_attr($data['min_cart_count'] ?: '') ?>">
                            </div>

                            <div class="col-12">
                                <div class="d-grid">
                                    <input type="submit" class="botoscope-button botoscope-button-small" value="<?php esc_html_e('Save', 'botoscope') ?>">
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="d-grid">
                                    <input type="submit" onclick="return botoscope_close_ps_sidebar(this);" class="botoscope-button botoscope-button-small" value="<?php esc_html_e('Save and close', 'botoscope') ?>">
                                </div>
                            </div>


                        </div> 
                    </div>
                </div>
            </div><!--end row-->
        </div>

    </form>
</section>
