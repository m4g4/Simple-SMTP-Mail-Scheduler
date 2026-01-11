<?php
namespace Ssmptms;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'Test_Settings' ) ) {

	class Test_Settings {

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

            if ( isset( $_POST['ssmptms_send_test_email'] ) ) {
                check_admin_referer( 'ssmptms_send_test_email_action', 'ssmptms_send_test_email_nonce' );
                echo_message_styles();

                $to      = isset( $_POST['smtp_to'] ) ? sanitize_email( $_POST['smtp_to'] ) : '';
                $subject = isset( $_POST['smtp_subject'] ) ? sanitize_text_field( $_POST['smtp_subject'] ) : '';
                $body    = isset( $_POST['smtp_message'] ) ? sanitize_textarea_field( $_POST['smtp_message'] ) : '';

                if ( is_email( $to ) ) {
                    $result  = wp_mail( $to, $subject, $body );
                    $class   = $result ? 'smtp-mail-message smtp-mail-success' : 'smtp-mail-message smtp-mail-error';
                    $message = $result
                        ? esc_html__( 'Email sent (or queued) successfully!', Constants::DOMAIN )
                        : esc_html__( 'Failed to send email.', Constants::DOMAIN );
                } else {
                    $class   = 'smtp-mail-message smtp-mail-error';
                    $message = esc_html__( 'Invalid email address.', Constants::DOMAIN );
                }

                $test_sent = true;
            }

            // Preserve values after submission
            $to_value      = isset( $_POST['smtp_to'] ) ? esc_attr( $_POST['smtp_to'] ) : '';
            $subject_value = isset( $_POST['smtp_subject'] ) ? esc_attr( $_POST['smtp_subject'] ) : '';
            $body_value    = isset( $_POST['smtp_message'] ) ? esc_textarea( $_POST['smtp_message'] ) : '';
            ?>

            <div class="wrap">
                <h1><?php echo esc_html__( 'Send Test Email (via wp_mail)', Constants::DOMAIN ); ?></h1>

                <?php if ( $test_sent ) : ?>
                    <div class="<?php echo esc_attr( $class ); ?>">
                        <?php echo esc_html( $message ); ?>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <?php wp_nonce_field( 'ssmptms_send_test_email_action', 'ssmptms_send_test_email_nonce' ); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="smtp_to"><?php echo esc_html__( 'To', Constants::DOMAIN ); ?></label>
                            </th>
                            <td>
                                <input name="smtp_to" type="email" required class="regular-text" 
                                       value="<?php echo $to_value; ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="smtp_subject"><?php echo esc_html__( 'Subject', Constants::DOMAIN ); ?></label>
                            </th>
                            <td>
                                <input name="smtp_subject" type="text" required class="regular-text"
                                       value="<?php echo $subject_value; ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="smtp_message"><?php echo esc_html__( 'Message', Constants::DOMAIN ); ?></label>
                            </th>
                            <td>
                                <textarea name="smtp_message" rows="5" class="large-text code" required><?php echo $body_value; ?></textarea>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button( esc_html__( 'Send Test Email', Constants::DOMAIN ), 'primary', 'ssmptms_send_test_email' ); ?>
                </form>
            </div>
            <?php
        }
    }
}
