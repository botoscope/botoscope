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
                            <input type="text" id="inputTitle" name="title" class="form-control" placeholder="<?php esc_html_e('Enter attribute name', 'botoscope') ?>" required="" value=""><br>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="border border-3 p-4 rounded product-sidebar">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="d-grid">
                                    <input type="hidden" name="type" value="attribute" />
                                    <input type="submit" class="btn btn-primary" value="<?php esc_html_e('Create', 'botoscope') ?>">
                                </div>
                            </div>
                        </div> 
                    </div>
                </div>
            </div>
        </div>

    </form>
</section>
