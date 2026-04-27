<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
?>
<section>
    <form><hr>
        <div class="form-body mt-4">
            <div class="row">
                <div class="col-lg-8">
                    <div class="border border-3 p-4 rounded" style="border-style: dotted !important;">
                        <div class="mb-3">
                            <label for="inputTitle" class="form-label"><?php esc_html_e('Title', 'botoscope') ?></label>
                            <input type="text" id="inputTitle" name="title" class="form-control" placeholder="<?php esc_html_e('Enter new name', 'botoscope') ?>" required="" value="<?php echo esc_html($title) ?>"><br>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="border border-3 p-4 rounded product-sidebar">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="d-grid">

                                    <input type="hidden" name="slug" value="<?php echo esc_html($taxonomy) ?>" />
                                    <input type="hidden" name="type" value="<?php echo esc_html($type) ?>" />
                                    <input type="submit" class="btn btn-primary" value="<?php esc_html_e('Save', 'botoscope') ?>">
                                    <input type="submit" data-slug="<?php echo esc_attr($taxonomy) ?>" class="btn btn-danger mt-2" id="botoscope-delete-taxonomy" value="<?php esc_html_e('Delete', 'botoscope') ?>">

                                </div>
                            </div>
                        </div> 
                    </div>
                </div>
            </div>
        </div>

    </form>
</section>
