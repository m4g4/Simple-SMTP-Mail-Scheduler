<?php 

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

function simple_smtp_prepare_db_name() {
    global $wpdb;
    return $wpdb->prefix . Simple_SMTP_Constants::DB_NAME;
}

?>