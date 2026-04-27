<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

include_once 'classes/marketing_campaigns_app.php';
include_once 'classes/marketing_strategies_app.php';

//08-04-2026
final class BOTOSCOPE_MARKETING extends BOTOSCOPE_APP {

    protected $table_name = '';
    protected $slug = 'marketing';
    public $marketing_strategies;
    protected $marketing_campaigns;

    public function __construct($args = []) {
        parent::__construct($args);

        if (botoscope_is_no_cart()) {
            return false;
        }

        Botoscope_Hooks::add_action('botoscope_panel_tabs', function ($tabs) {
            $tabs[$this->slug] = esc_html__('Marketing', 'botoscope');
            return $tabs;
        });

        add_action("botoscope_{$this->slug}_tab_icon", function () {
            return 'chart-line';
        });

        $this->marketing_strategies = new BOTOSCOPE_MARKETING_STRATEGIES($args);
        $this->marketing_campaigns = new BOTOSCOPE_MARKETING_CAMPAIGNS($args);
    }

    public function draw_content($counter) {
        ?>
        <section id="botoscope-<?php echo esc_attr($this->slug) ?>" <?php if ($counter === 0): ?>class="content-current"<?php endif; ?>>

            <ul id="botoscope-marketing-tabs" class="botoscope-tabs">
                <li><a href="#" data-tab="campaigns" class="botoscope-button selected"><?php esc_html_e('Campaigns', 'botoscope') ?></a></li>
                <li><a href="#" data-tab="strategies" class="botoscope-button"><?php esc_html_e('Strategies', 'botoscope') ?></a></li>
            </ul>



            <div class="form-body mt-4 botoscope-tab-container">
                <div class="row">
                    <div class="col-lg-12">

                        <?php $this->marketing_campaigns->draw_content(0) ?>

                    </div>
                </div>
            </div>



            <div class="form-body mt-4 botoscope-tab-container" style="display:  none;">
                <div class="row">
                    <div class="col-lg-12">
                        <?php $this->marketing_strategies->draw_content(0) ?>
                    </div>
                </div>
            </div>
        </section>
        <?php
    }
}
