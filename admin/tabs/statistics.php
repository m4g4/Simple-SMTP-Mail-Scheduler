<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

if ( ! class_exists( 'Simple_SMTP_Mail_Statistics' ) ) {

    class Simple_SMTP_Mail_Statistics {

        private static $instance;

        public static function get_instance() {
		    if ( null === self::$instance ) {
			    self::$instance = new self();
		    }

		    return self::$instance;
	    }

        /**
         * Render settings tab.
         */
        public function render_tab(): void {
            ?>
            <div class="wrap">
                <h2><?php esc_html_e('Statistics', Simple_SMTP_Constants::DOMAIN); ?></h2>
                <div class="s-smtp-mail-charts_container">
                    <div class="s-smtp-mail-charts">
                        <?php
                            $chart = new Simple_SMTP_Mail_Hour_Stats_Bar_Chart();
                            $chart->display();
                        ?>
                        <?php
                            $chart = new Simple_SMTP_Mail_Status_Donut_Chart();
                            $chart->display();
                        ?>
                    </div>
                    <div class="s-smtp-mail-charts">
                        <div class="s-smtp-mail-chart s-smtp-mail-bar-chart">
                            <?php
                                $chart = new Simple_SMTP_Mail_Queue_Status_Bar_Chart();
                                $chart->display();
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            </div>
            <?php
        }
    }
}

Simple_SMTP_Mail_Statistics::get_instance();
