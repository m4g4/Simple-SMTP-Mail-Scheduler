<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . 'utils.php';
require_once plugin_dir_path( __FILE__ ) . 'globals.php';
require_once plugin_dir_path( __FILE__ ) . '/db/index.php';

Simple_SMTP_Email_Queue::get_instance()->drop_table();

delete_option(Simple_SMTP_Constants::DB_VERSION);
delete_option(Simple_SMTP_Constants::PROFILES);
delete_option(Simple_SMTP_Constants::PROFILE_ACTIVE);
delete_option(Simple_SMTP_Constants::EMAILS_PER_UNIT);
delete_option(Simple_SMTP_Constants::EMAILS_UNIT);
delete_option(Simple_SMTP_Constants::EMAILS_TESTING);
delete_option(Simple_SMTP_Constants::EMAILS_SCHEDULER_CARRY);

?>