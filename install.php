<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

function simple_smtp_mail_scheduler_activation() {
    global $wpdb;

    $table_name      = simple_smtp_prepare_db_name();
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        email_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        recipient_email VARCHAR(255) NOT NULL,
        subject TEXT NOT NULL,
        message LONGTEXT NOT NULL,
        headers LONGTEXT DEFAULT NULL,
        attachments LONGTEXT DEFAULT NULL,
        scheduled_at DATETIME NOT NULL,
        profile_settings LONGTEXT DEFAULT NULL,
        priority INT DEFAULT 0,
        status ENUM('queued', 'processing', 'sent', 'failed') DEFAULT 'queued',
        retries INT UNSIGNED DEFAULT 0,
        last_attempt_at DATETIME DEFAULT NULL,
        error_message TEXT DEFAULT NULL,
        testing TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (email_id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

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
