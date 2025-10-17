<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

if ( ! class_exists( 'Simple_SMTP_Mail_Scheduler_Settings' ) ) {

    class Simple_SMTP_Mail_Scheduler_Settings {

        private static $instance;
        public function __construct() {
            add_action( 'admin_init', [ $this, 'register_settings' ] );
        }

        public static function get_instance() {
		    if ( null === self::$instance ) {
			    self::$instance = new self();
		    }

		    return self::$instance;
	    }

        /**
         * Register plugin settings.
         */
        public function register_settings(): void {
            register_setting(
                'simple_smtp_mail_settings_group',
                Simple_SMTP_Constants::EMAILS_PER_UNIT,
                [
                    'type'              => 'integer',
                    'sanitize_callback' => [ $this, 'sanitize_emails_per_unit' ],
                    'default'           => 5,
                ]
            );

            register_setting(
                'simple_smtp_mail_settings_group',
                Simple_SMTP_Constants::EMAILS_UNIT,
                [
                    'type'              => 'string',
                    'sanitize_callback' => [ $this, 'sanitize_emails_unit' ],
                    'default'           => 'minute',
                ]
            );

            add_settings_section(
                'scheduler_section',
                __( 'Email Scheduler Settings', Simple_SMTP_Constants::DOMAIN ),
                [ $this, 'scheduler_section_text' ],
                Simple_SMTP_Constants::SETTINGS_PAGE
            );

            add_settings_field(
                'emails_per_unit',
                __( 'Emails per unit', Simple_SMTP_Constants::DOMAIN ),
                [ $this, 'emails_per_unit_callback' ],
                Simple_SMTP_Constants::SETTINGS_PAGE,
                'scheduler_section'
            );
        }

        /**
         * Render settings tab.
         */
        public function render_tab(): void {
            ?>
            <div class="wrap">
                <form method="post" action="options.php">
                    <?php
                    settings_fields( 'simple_smtp_mail_settings_group' );
                    do_settings_sections( Simple_SMTP_Constants::SETTINGS_PAGE );
                    submit_button();
                    ?>
                </form>
            </div>
            <?php
        }

        public function scheduler_section_text(): void {
            echo '<p>' . esc_html__( 'Configure the email sending limits and scheduler settings below.', Simple_SMTP_Constants::DOMAIN ) . '</p>';
        }

        /**
         * Emails per unit + unit selector field.
         */
        public function emails_per_unit_callback(): void {
            $value = (int) get_option( Simple_SMTP_Constants::EMAILS_PER_UNIT, 5 );
            $unit  = get_option( Simple_SMTP_Constants::EMAILS_UNIT, 'minute' );
            ?>
            <input type="number" name="<?php echo esc_attr( Simple_SMTP_Constants::EMAILS_PER_UNIT ); ?>" value="<?php echo esc_attr( $value ); ?>" min="1" />
            <select name="<?php echo esc_attr( Simple_SMTP_Constants::EMAILS_UNIT ); ?>">
                <?php
                $units = [
                    'minute' => __( 'per Minute', Simple_SMTP_Constants::DOMAIN ),
                    'hour'   => __( 'per Hour', Simple_SMTP_Constants::DOMAIN ),
                    'day'    => __( 'per Day', Simple_SMTP_Constants::DOMAIN ),
                ];

                foreach ( $units as $key => $label ) {
                    printf(
                        '<option value="%s" %s>%s</option>',
                        esc_attr( $key ),
                        selected( $unit, $key, false ),
                        esc_html( $label )
                    );
                }
                ?>
            </select>
            <p class="description"><?php esc_html_e( 'Maximum number of emails that can be sent per time unit.', Simple_SMTP_Constants::DOMAIN ); ?></p>
            <?php
        }

        /**
         * Sanitize emails per unit.
         */
        public function sanitize_emails_per_unit( $value ): int {
            $value = absint( $value );
            if ( $value < 1 ) {
                add_settings_error(
                    Simple_SMTP_Constants::EMAILS_PER_UNIT,
                    'invalid_emails_per_unit',
                    __( 'Emails per unit must be a positive number.', Simple_SMTP_Constants::DOMAIN ),
                    'error'
                );
                return (int) get_option( Simple_SMTP_Constants::EMAILS_PER_UNIT, 5 );
            }
            return $value;
        }

        /**
         * Sanitize time unit.
         */
        public function sanitize_emails_unit( $value ): string {
            $valid_units = [ 'minute', 'hour', 'day' ];
            if ( ! in_array( $value, $valid_units, true ) ) {
                add_settings_error(
                    Simple_SMTP_Constants::EMAILS_UNIT,
                    'invalid_emails_unit',
                    __( 'Invalid time unit selected.', Simple_SMTP_Constants::DOMAIN ),
                    'error'
                );
                return get_option( Simple_SMTP_Constants::EMAILS_UNIT, 'minute' );
            }
            return $value;
        }
    }
}

Simple_SMTP_Mail_Scheduler_Settings::get_instance();
