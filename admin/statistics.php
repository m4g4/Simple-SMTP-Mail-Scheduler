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
                <h2><?php esc_html_e('SMTP Mail Statistics', Simple_SMTP_Constants::DOMAIN); ?></h2>
                <div style="max-width: 600px; max-height: 400px;">
                    <?php
                        Simple_SMTP_Mail_Hour_Stats_Bar_Chart::get_instance()->display();
                    ?>
                </div>
            </div>
            <?php
        }
    }
}

Simple_SMTP_Mail_Statistics::get_instance();
