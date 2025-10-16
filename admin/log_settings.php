<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('Simple_SMTP_Mail_Log_Settings')) {

    class Simple_SMTP_Mail_Log_Settings {

        private static $instance;
        public static function get_instance() {
		    if ( null === self::$instance ) {
			    self::$instance = new self();
		    }

		    return self::$instance;
	    }
        public function handle_log_actions() {
            $action   = sanitize_text_field($_GET['action'] ?? '');
            $email_id = isset($_GET['email_id']) ? intval($_GET['email_id']) : 0;

            if (!$action || !$email_id) {
                return;
            }

            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], $action . '_' . $email_id)) {
                wp_die(__('Security check failed.', Simple_SMTP_Constants::DOMAIN));
            }

            switch ($action) {
                case 'simple_smtp_mail_retry':
                    Simple_SMTP_Email_Queue::get_instance()->retry_sending_email($email_id);
                    break;

                case 'simple_smtp_mail_remove':
                    Simple_SMTP_Email_Queue::get_instance()->delete_email($email_id);
                    break;

                case 'simple_smtp_mail_front':
                    Simple_SMTP_Email_Queue::get_instance()->prioritize_email($email_id);
                    break;
            }

            // Redirect to prevent action re-execution on page refresh
            wp_safe_redirect(remove_query_arg(['action', 'email_id', '_wpnonce']));
            exit;
        }

        public function render_tab() {
            $this->handle_log_actions();

            $log_table = new Simple_SMTP_Mail_Scheduler_Log_Table();
            $log_table->prepare_items();

            echo '<div class="wrap">';
            echo '<h2>' . esc_html__('Mail Log', Simple_SMTP_Constants::DOMAIN) . '</h2>';
            simple_stmp_scheduler_status_callback();
            echo '<form method="get">';
            echo '<input type="hidden" name="page" value="' . Simple_SMTP_Constants::SETTINGS_PAGE . '" />';
            echo '<input type="hidden" name="tab" value="log" />';
            $log_table->display();
            echo '</form>';
            echo '</div>';
        }
    }
}