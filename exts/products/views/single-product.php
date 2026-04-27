<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

global $Botoscope;
$languages = $Botoscope->controls->get_active_languages();
$default_language = $Botoscope->controls->get_default_language();
$all_languages = array_merge([$default_language], $languages);
?>

<section>
    <form>
        <div class="form-body mt-4">
            <div class="row">
                <div class="col-lg-8">
                    <div class="border border-3 p-4 rounded product-sidebar">
                        <div class="mb-3" style="margin-bottom: 0 !important;">
                            <label for="inputProductTitle" class="form-label"><?php esc_html_e('Product Title', 'botoscope') ?></label>
                            <input type="text" id="inputProductTitle" name="title" class="form-control" placeholder="<?php esc_attr_e('Enter product title', 'botoscope') ?>" required="" value="<?php echo esc_attr($data['title']); ?>"><br>
                        </div>
                        <div class="mb-3">

                            <div class="botoscope-accordion" style="display: <?php echo $Botoscope->no_bot ? 'none' : 'block' ?>">
                                <input type="checkbox" id="botoscope-meta-accordion">
                                <label for="botoscope-meta-accordion">&nbsp;<?php esc_html_e('Botoscope meta', 'botoscope') ?></label>
                                <div class="botoscope-accordion-content">
                                    <div id="botoscope-single-product-meta" data-meta_position="<?php echo esc_attr($meta_position) ?>"></div>
                                </div>
                            </div>

                            <label for="botoscope-product-description" class="form-label"><?php esc_html_e('Description', 'botoscope') ?></label>

                            <div class="row">
                                <div class="col-12">
                                    <a href="#" id="botoscope_product_description_ai" class="botoscope-button botoscope-button-small"><?php esc_html_e('generate', 'botoscope') ?></a>&nbsp;<a href="#" id="botoscope_product_grammar_ai" class="botoscope-button botoscope-button-small"><?php esc_html_e('correct grammar', 'botoscope') ?></a>
                                </div>
                            </div>


                            <?php
                            wp_editor($data['description'], 'botoscope-product-description', [
                                'textarea_name' => 'description',
                                'media_buttons' => false,
                                'textarea_rows' => 10,
                                'teeny' => false,
                                'quicktags' => true
                            ]);
                            ?>
                        </div>


                        <div class="botoscope-accordion" style="display: <?php echo $Botoscope->no_bot ? 'none' : 'block' ?>">
                            <input type="checkbox" id="botoscope-meta-accordion-audio">
                            <label for="botoscope-meta-accordion-audio">&nbsp;<?php esc_html_e('Audio', 'botoscope') ?></label>
                            <div class="botoscope-accordion-content">

                                <?php foreach ($all_languages as $lang) : ?>
                                    <div style="display: flex; gap: 7px; margin-bottom: 3px;">
                                        <input name="audio_<?php echo esc_attr($lang) ?>" type="text" class="inputProductAudio form-control" placeholder="<?php esc_html_e('paste link to audio mp3 or m4a', 'botoscope') ?>" value="<?php echo esc_attr($data['audio'][$lang] ?? '') ?>">
                                        <button type="button" class="uploadAudioButton button button-primary"><?php esc_html_e('Select audio', 'botoscope') ?>&nbsp;[<?php echo esc_html($lang) ?>]</button>
                                    </div>
                                <?php endforeach; ?>


                            </div>
                        </div>




                        <div class="mb-3">
                            <!-- <label class="form-label">Media</label> -->
                            <div id="botoscope-single-product-media-container" style="position: relative;"></div>
                            <input name="media" id="botoscope-single-product-media-value" type="hidden" value="<?php echo esc_attr(implode(',', array_column($data['media'], 'aid'))) ?>">
                            <div id="botoscope-single-product-media-value-container" style="display: none;"><?php echo wp_json_encode($data['media'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></div>
                        </div>


                        <div class="mb-3">
                            <label class="form-label"><?php esc_html_e('Details', 'botoscope') ?></label>

                            <?php
                            wp_editor($data['product_details'], 'botoscope-product-details', [
                                'textarea_name' => 'product_details',
                                'media_buttons' => true,
                                'textarea_rows' => 10,
                                'teeny' => false,
                                'quicktags' => true
                            ]);
                            ?>

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
                                <label class="form-label"><?php esc_html_e('Published', 'botoscope') ?></label>
                                <select class="form-select" name="is_published">
                                    <option value="0" <?php echo selected(intval($data['is_published']), 0) ?>><?php esc_html_e('no', 'botoscope') ?></option>
                                    <option value="1" <?php echo selected(intval($data['is_published']), 1) ?>><?php esc_html_e('yes', 'botoscope') ?></option>
                                </select>
                            </div>

                            <div class="col-12">
                                <label for="inputProductSKU" class="form-label"><?php esc_html_e('SKU', 'botoscope') ?></label>
                                <input name="sku" type="text" class="form-control" id="inputProductSKU" placeholder="" value="<?php echo esc_attr($data['sku']) ?>">
                            </div>

                            <div class="col-12">
                                <?php
                                $type = $data['type'];
                                $files_count = isset($data['downloads']) ? count((array) $data['downloads']) : 0;
                                $products_count = isset($data['child_ids']) ? count((array) $data['child_ids']) : 0;
                                ?>
                                <label for="botoscope_product_type" class="form-label"><?php esc_html_e('Product type', 'botoscope') ?></label>
                                <select class="form-select" id="botoscope_product_type" name="type">

                                    <?php foreach ($obj->types as $key => $value) : ?>

                                        <?php
                                        if (botoscope_is_no_cart()) {
                                            if (!in_array($key, ['simple', 'external'])) {
                                                continue;
                                            }
                                        }

                                        if ($Botoscope->no_bot) {
                                            if (in_array($key, ['botoscope_simple_media_casting', 'botoscope_simple_virtual_downloadable', 'botoscope_simple_virtual'])) {
                                                continue;
                                            }
                                        }
                                        ?>

                                        <option value="<?php echo esc_attr($key) ?>" <?php echo selected($type, $key) ?>><?php echo esc_html($value) ?></option>
                                    <?php endforeach; ?>

                                </select><br>

                                <div class="d-grid">
                                    <a href="#" style="display: <?php echo in_array($type, ['botoscope_simple_virtual_downloadable', 'botoscope_simple_media_casting']) ? 'inline-block' : 'none' ?>;" class="btn btn-primary" id="botoscope_product_files"><?php
                                        /* translators: %d: count */
                                        printf(esc_html__('Files (%d)', 'botoscope'), intval($files_count))
                                        ?></a><br>

                                    <div style="display: <?php echo in_array($type, ['botoscope_simple_virtual_downloadable', 'botoscope_simple_media_casting']) ? 'inline-block' : 'none' ?>;">
                                        <label for="botoscope_access_days" class="form-label"><?php esc_html_e('Access days', 'botoscope') ?></label>
                                        <input name="access_days" min="0" type="number" step="any" class="form-control" id="botoscope_access_days" placeholder="" value="<?php echo esc_attr($data['access_days']) ?>"><br>
                                    </div>

                                    <a href="#" style="display: <?php echo in_array($type, ['grouped']) ? 'inline-block' : 'none' ?>;" class="btn btn-primary" id="botoscope_product_products"><?php
                                        /* translators: %d: count */
                                        printf(esc_html__('Products (%d)', 'botoscope'), intval($products_count))
                                        ?></a>
                                    <a href="#" style="display: <?php echo in_array($type, ['variable']) ? 'inline-block' : 'none' ?>;" class="btn btn-primary" id="botoscope_product_variations"><?php
                                        /* translators: %d: count */
                                        printf(esc_html__('Variations (%d)', 'botoscope'), intval($products_count))
                                        ?></a>

                                    <?php if (class_exists('BOTOSCOPE_BOOKING')): ?>
                                        <a href="#" style="display: <?php echo in_array($type, ['botoscope_simple_virtual']) ? 'inline-block' : 'none' ?>;" class="btn btn-primary" id="botoscope_product_booking_slots_btn"><?php esc_html_e('Booking slots', 'botoscope') ?></a>
                                    <?php endif; ?>

                                </div>
                            </div>


                            <div class="col-12" style="display: <?php echo in_array($type, ['external']) ? 'block' : 'none' ?>;">
                                <label for="product_external_link" class="form-label"><?php esc_html_e('External link', 'botoscope') ?></label>
                                <input name="external_link" type="text" class="form-control" id="product_external_link" placeholder="" value="<?php echo esc_url(isset($data['external_link']) ? $data['external_link'] : '') ?>">
                            </div>

                            <div class="col-12" id="botoscope-product-variable-section" style="display: <?php echo in_array($type, ['variable']) ? 'block' : 'none' ?>;">
                                <label class="form-label"><?php esc_html_e('Product attributes', 'botoscope') ?></label>
                                <?php
                                $all_attributes = $obj->get_all_woocommerce_attributes()['taxonomies'];
                                $selected_attributes = $obj->get_product_allowed_attributes($product_id)['taxonomies'];
                                $selected_terms = $obj->get_product_allowed_attributes($product_id)['blocks'];

                                $ids = [];
                                foreach ($selected_terms as $subarray) {
                                    $ids = array_merge($ids, array_keys($subarray));
                                }
                                ?>

                                <div id="botoscope-variable-product-attributes-container"></div>
                                <input name="product_attributes" type="hidden" value="<?php echo esc_attr(implode(',', array_keys($selected_attributes))) ?>">
                                <input name="product_attributes_terms" type="hidden" value="<?php echo esc_attr(implode(',', $ids)) ?>">

                            </div>

                            <!-- <p class="alert alert-success botoscope-products-product-type-grouped" role="alert" style="display: <?php echo in_array($type, ['grouped']) ? 'inline-block' : 'none' ?>;">
                            <?php esc_html_e('The price of a grouped product is automatically calculated as the sum of the prices of all its included items. However, if you wish to set a unique price for the entire group, you can do so. Assigning a custom price can make your offer more appealing to customers and potentially boost sales.', 'botoscope') ?>
                            </p> -->

                            <div class="col-12 botoscope_need_cart botoscope-products-product-type-grouped" style="display: <?php echo in_array($type, ['grouped']) ? 'block' : 'none' ?>;">
                                <label for="ignore_stock_for_collection" class="form-label"><?php esc_html_e('Ignore Stock for Collection', 'botoscope') ?></label>
                                <?php
                                $ignore_stock_for_collection = intval($data['ignore_stock_for_collection'] ?? 0);
                                ?>
                                <select class="form-select" id="ignore_stock_for_collection" name="ignore_stock_for_collection">
                                    <option value="0" <?php echo selected($ignore_stock_for_collection, 0) ?>><?php esc_html_e('no', 'botoscope') ?></option>
                                    <option value="1" <?php echo selected($ignore_stock_for_collection, 1) ?>><?php esc_html_e('yes', 'botoscope') ?></option>
                                </select>
                                <p class="notice"><?php esc_html_e('Recommend buying the full collection even if some items are out of stock', 'botoscope') ?></p>
                            </div>

                            <div class="col-md-6 botoscope-product-price-container" style="display: <?php echo in_array($type, ['variable']) ? 'none' : 'block' ?>;">
                                <label for="inputProductPrice" class="form-label"><?php esc_html_e('Regular price', 'botoscope') ?></label>
                                <input name="price" type="number" step="any" class="form-control" id="inputProductPrice" placeholder="00.00" value="<?php echo floatval($data['price']) ?>">
                            </div>

                            <div class="col-md-6 botoscope-product-price-container" style="display: <?php echo in_array($type, ['variable']) ? 'none' : 'block' ?>;">
                                <label for="inputProductSalePrice" class="form-label"><?php esc_html_e('Sale price', 'botoscope') ?></label>
                                <input name="sale_price" type="number" step="any" class="form-control" id="inputProductSalePrice" placeholder="00.00" value="<?php echo floatval($data['sale_price']) ?>">
                            </div>

                            <div class="col-12 botoscope_need_cart botoscope_manage_stock" style="display: <?php echo (intval($data['manage_stock']) && in_array($type, ['simple', 'variable']) ? 'block' : 'none') ?>;">
                                <label for="stock_quantity" class="form-label"><?php esc_html_e('Stock quantity', 'botoscope') ?></label>
                                <input name="stock_quantity" type="number" step="1" class="form-control" placeholder="0" value="<?php echo intval($data['stock_quantity']) ?>" onwheel="event.preventDefault()">
                            </div>

                            <div class="col-12 botoscope_need_cart botoscope_manage_stock" style="display: <?php echo in_array($type, ['simple', 'variable']) ? 'block' : 'none' ?>;">
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
                                <label class="form-label"><?php esc_html_e('Category', 'botoscope') ?></label>
                                <div id="botoscope-single-product-categories-container"></div>
                                <input name="category" id="botoscope-single-product-categories-value" type="hidden" value="<?php echo esc_attr(implode(',', $data['category'])) ?>">
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="product_brand"><?php esc_html_e('Brand', 'botoscope') ?></label>

                                <select class="form-select" id="product_brand" name="product_brand">
                                    <option value="0"><?php esc_html_e('Select product brand', 'botoscope') ?></option>
                                    <?php foreach ($data['brands'] as $brand_id => $brand_data) : ?>
                                    <option value="<?php echo intval($brand_id) ?>" <?php echo selected(intval($data['product_brand']), intval($brand_id)) ?>><?php echo esc_html($brand_data['title']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>


                            <div class="col-12 botoscope_need_cart" style="display: <?php echo $Botoscope->no_bot ? 'none' : 'block' ?>">
                                <label for="quantity_step" class="form-label"><?php esc_html_e('Quantity step', 'botoscope') ?></label>
                                <input name="quantity_step" type="number" min="1" class="form-control" id="quantity_step" placeholder="<?php esc_html_e('the default value is 1', 'botoscope') ?>" value="<?php echo intval($data['quantity_step'] ?: 1) ?>">
                            </div>

                            <div class="col-12 botoscope_need_cart" style="display: <?php echo $Botoscope->no_bot ? 'none' : 'block' ?>">
                                <label for="min_cart_count" class="form-label"><?php esc_html_e('Minimal cart count', 'botoscope') ?></label>
                                <input name="min_cart_count" type="number" min="1" class="form-control" id="min_cart_count" placeholder="<?php esc_html_e('the default value is 1', 'botoscope') ?>" value="<?php echo intval($data['min_cart_count'] ?: 1) ?>">
                            </div>

                            <div class="col-12" style="display: <?php echo $Botoscope->no_bot ? 'none' : 'block' ?>">
                                <label class="form-label"><?php esc_html_e('Do not display price under media gallery', 'botoscope') ?></label>
                                <select class="form-select" name="hide_price_below_media">
                                    <option value="0" <?php echo selected(intval($data['hide_price_below_media']), 0) ?>><?php esc_html_e('no', 'botoscope') ?></option>
                                    <option value="1" <?php echo selected(intval($data['hide_price_below_media']), 1) ?>><?php esc_html_e('yes', 'botoscope') ?></option>
                                </select>
                            </div>

                            <div class="col-12">
                                <label for="publish_date" class="form-label"><?php esc_html_e('Set publication date', 'botoscope') ?></label>
                                <div id="publish_date"></div>
                                <input name="publish_date" id="botoscope-single-product-publish_date-value" type="hidden" value="<?php echo intval($data['publish_date']) ?>">
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
