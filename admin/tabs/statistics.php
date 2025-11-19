<?php
namespace Ssmptms;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

if ( ! class_exists( 'Statistics' ) ) {

    class Statistics {

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
                <h2><?php esc_html_e('Statistics', Constants::DOMAIN); ?></h2>
                <div class="ssmptms-mail-charts_container">
                    <div class="ssmptms-mail-charts">
                        <?php
                            $chart = new Hour_Stats_Bar_Chart();
                            $chart->display();
                        ?>
                        <?php
                            $chart = new Donut_Chart();
                            $chart->display();
                        ?>
                    </div>
                    <div class="ssmptms-mail-charts">
                        <div class="ssmptms-mail-chart ssmptms-bar-chart">
                            <?php
                                $chart = new Queue_Status_Bar_Chart();
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

Statistics::get_instance();
