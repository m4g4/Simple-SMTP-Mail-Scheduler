<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

function ssmptms_activation() {
    Ssmptms\Email_Queue::get_instance()->create_table();

    // bump version
    update_option( Ssmptms\Constants::DB_VERSION, Ssmptms\Constants::VERSION );

    if (Ssmptms\Email_Queue::get_instance()->has_email_entries_for_sending()) {
        Ssmptms\schedule_cron_event();
    }
}

add_action( 'plugins_loaded', function() {
    Ssmptms\migrate();

    $installed_version = get_option( Ssmptms\Constants::DB_VERSION, null );

    if ($installed_version === null) {
        throw new RuntimeException( 'WO SMTP Mail Scheduler plugin not properly installed! Could not obtain version.' );
    }

    // DB upgrades come here
});