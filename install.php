<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

function simple_smtp_mail_scheduler_activation() {
    Simple_SMTP_Email_Queue::get_instance()->create_table();

    // bump version
    update_option( Simple_SMTP_Constants::DB_VERSION, Simple_SMTP_Constants::VERSION );

    // ensure cron job is registered
    if ( ! wp_next_scheduled( 'simple_smtp_mail_send_emails_event' ) ) {
        wp_schedule_event( time(), 'minute', 'simple_smtp_mail_send_emails_event' );
    }
}

add_action( 'plugins_loaded', function() {
    $installed_version = get_option( Simple_SMTP_Constants::DB_VERSION, null );

    if ($installed_version === null) {
        throw new RuntimeException( 'Simple SMTP Mail Scheduler plugin not properly installed! Could not obtain version.' );
    }

    // DB upgrades come here
});
