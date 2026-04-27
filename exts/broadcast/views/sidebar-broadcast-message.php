<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
?>

<section>
    <form><hr>
        <div class="form-body mt-4">
            <div class="row">
                <div class="col-lg-<?php echo ($is_sent ? 12 : 8) ?>">
                    <div class="border border-3 p-4 rounded" style="border-style: dotted !important;">
                        <div class="mb-3">

                            <?php if (!$is_sent): ?>
                                <div class="row">
                                    <div class="col-12" style="display: flex; padding-bottom: 5px;">
                                        <a href="#" id="botoscope_broadcast_description_ai" class="botoscope-button botoscope-button-small"><?php esc_html_e('generate', 'botoscope') ?></a>&nbsp;<a href="#" id="botoscope_broadcast_grammar_ai" class="botoscope-button botoscope-button-small"><?php esc_html_e('correct grammar', 'botoscope') ?></a>
                                    </div>
                                </div>
                            <?php else: ?>

                                <?php
                                $date_format = get_option('date_format'); // например: "j F Y"
                                $time_format = get_option('time_format'); // например: "H:i"
                                ?>

                            <label class="form-label"><?php esc_html_e('Sent', 'botoscope') ?>: <?php echo esc_html(date_i18n("$date_format $time_format", $sent_time)); ?> (<?php echo intval($count) ?>)</label>

                            <?php endif; ?>

                            <textarea name="message" class="form-control <?php if ($is_sent): ?>disabled<?php endif; ?>" <?php if ($is_sent): ?>readonly=""<?php endif; ?> id="botoscope-broadcast-description" maxlength="5000" rows="10"><?php echo esc_html($data['message']) ?></textarea>
                        </div>
                    </div>
                </div>
                <?php if (!$is_sent): ?>
                    <div class="col-lg-4">
                        <div class="border border-3 p-4 rounded product-sidebar">

                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="d-grid">
                                        <input type="submit" class="btn btn-primary" value="<?php esc_html_e('Save', 'botoscope') ?>">
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="d-grid">
                                        <input type="button" id="botoscope_broadcast_send" class="btn btn-warning" value="<?php esc_html_e('Save and Send', 'botoscope') ?>">
                                    </div>
                                </div>
                            </div> 

                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </form>
</section>
