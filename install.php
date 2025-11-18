<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

function ssmptms_activation() {
    Simple_SMTP_Email_Queue::get_instance()->create_table();

    // bump version
    update_option( Ssmptms_Constants::DB_VERSION, Ssmptms_Constants::VERSION );

    if (Simple_SMTP_Email_Queue::get_instance()->has_email_entries_for_sending()) {
        simple_smtp_schedule_cron_event();
    }
}

add_action( 'plugins_loaded', function() {
    $installed_version = get_option( Ssmptms_Constants::DB_VERSION, null );

    if ($installed_version === null) {
        throw new RuntimeException( 'Simple SMTP Mail Scheduler plugin not properly installed! Could not obtain version.' );
    }

    // DB upgrades come here
});
