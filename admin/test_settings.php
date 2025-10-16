<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'Simple_SMTP_Mail_Test_Settings' ) ) {

	class Simple_SMTP_Mail_Test_Settings {

        private static $instance;

        public static function get_instance() {
		    if ( null === self::$instance ) {
			    self::$instance = new self();
		    }

		    return self::$instance;
	    }
        public function render_tab() {
            $test_sent = false;
            $class     = '';
            $message   = '';

            if ( isset( $_POST['simple_smtp_send_test_email'] ) ) {
                check_admin_referer( 'simple_smtp_send_test_email_action', 'simple_smtp_send_test_email_nonce' );
                simple_smtp_echo_message_styles();

                $to      = isset( $_POST['smtp_to'] ) ? sanitize_email( $_POST['smtp_to'] ) : '';
                $subject = isset( $_POST['smtp_subject'] ) ? sanitize_text_field( $_POST['smtp_subject'] ) : '';
                $body    = isset( $_POST['smtp_message'] ) ? sanitize_textarea_field( $_POST['smtp_message'] ) : '';

                if ( is_email( $to ) ) {
                    $result  = wp_mail( $to, $subject, $body );
                    $class   = $result ? 'smtp-mail-message smtp-mail-success' : 'smtp-mail-message smtp-mail-error';
                    $message = $result
                        ? esc_html__( 'Email sent (or queued) successfully!', Simple_SMTP_Constants::DOMAIN )
                        : esc_html__( 'Failed to send email.', Simple_SMTP_Constants::DOMAIN );
                } else {
                    $class   = 'smtp-mail-message smtp-mail-error';
                    $message = esc_html__( 'Invalid email address.', Simple_SMTP_Constants::DOMAIN );
                }

                $test_sent = true;
            }

            // Preserve values after submission
            $to_value      = isset( $_POST['smtp_to'] ) ? esc_attr( $_POST['smtp_to'] ) : '';
            $subject_value = isset( $_POST['smtp_subject'] ) ? esc_attr( $_POST['smtp_subject'] ) : '';
            $body_value    = isset( $_POST['smtp_message'] ) ? esc_textarea( $_POST['smtp_message'] ) : '';
            ?>

            <div class="wrap">
                <h1><?php echo esc_html__( 'Send Test Email (via wp_mail)', Simple_SMTP_Constants::DOMAIN ); ?></h1>

                <?php simple_stmp_scheduler_status_callback(); ?>

                <?php if ( $test_sent ) : ?>
                    <div class="<?php echo esc_attr( $class ); ?>">
                        <?php echo esc_html( $message ); ?>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <?php wp_nonce_field( 'simple_smtp_send_test_email_action', 'simple_smtp_send_test_email_nonce' ); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="smtp_to"><?php echo esc_html__( 'To', Simple_SMTP_Constants::DOMAIN ); ?></label>
                            </th>
                            <td>
                                <input name="smtp_to" type="email" required class="regular-text" 
                                       value="<?php echo $to_value; ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="smtp_subject"><?php echo esc_html__( 'Subject', Simple_SMTP_Constants::DOMAIN ); ?></label>
                            </th>
                            <td>
                                <input name="smtp_subject" type="text" required class="regular-text"
                                       value="<?php echo $subject_value; ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="smtp_message"><?php echo esc_html__( 'Message', Simple_SMTP_Constants::DOMAIN ); ?></label>
                            </th>
                            <td>
                                <textarea name="smtp_message" rows="5" class="large-text code" required><?php echo $body_value; ?></textarea>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button( esc_html__( 'Send Test Email', Simple_SMTP_Constants::DOMAIN ), 'primary', 'simple_smtp_send_test_email' ); ?>
                </form>
            </div>
            <?php
        }
    }
}
