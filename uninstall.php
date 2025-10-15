<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . 'utils.php';
require_once plugin_dir_path( __FILE__ ) . 'globals.php';

$table_name = simple_smtp_prepare_db_name();

global $wpdb;

$wpdb->query("DROP TABLE IF EXISTS $table_name");

delete_option(Simple_SMTP_Constants::DB_VERSION);
delete_option(Simple_SMTP_Constants::PROFILES);
delete_option(Simple_SMTP_Constants::PROFILE_ACTIVE);
delete_option(Simple_SMTP_Constants::EMAILS_PER_UNIT);
delete_option(Simple_SMTP_Constants::EMAILS_UNIT);
delete_option(Simple_SMTP_Constants::EMAILS_TESTING);
delete_option(Simple_SMTP_Constants::EMAILS_ITERATION);
delete_option(Simple_SMTP_Constants::EMAILS_TOTAL_SENT);
delete_option(Simple_SMTP_Constants::EMAILS_RESET_TIME);

?>