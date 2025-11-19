<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . 'utils.php';
require_once plugin_dir_path( __FILE__ ) . 'globals.php';
require_once plugin_dir_path( __FILE__ ) . '/db/index.php';

Ssmptms\Email_Queue::get_instance()->drop_table();

delete_option(Ssmptms\Constants::DB_VERSION);
delete_option(Ssmptms\Constants::PROFILES);
delete_option(Ssmptms\Constants::PROFILE_ACTIVE);
delete_option(Ssmptms\Constants::EMAILS_PER_UNIT);
delete_option(Ssmptms\Constants::EMAILS_UNIT);
delete_option(Ssmptms\Constants::DISABLE);

?>