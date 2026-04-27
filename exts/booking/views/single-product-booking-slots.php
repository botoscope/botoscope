<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
?>
<section>
    <div class="form-body mt-4">
        <div class="row">
            <div class="col-lg-12" style="position: relative;">
                <ul id="botoscope-booking-weekdays-list"></ul>
                <div id="botoscope_product_booking_slots_wrapper"><?php echo wp_json_encode($slots, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></div>
            </div>
        </div>
</section>
