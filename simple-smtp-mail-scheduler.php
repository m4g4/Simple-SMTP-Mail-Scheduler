<?php
/**
 * Plugin Name: Simple SMTP Mail Scheduler
 * Description: Intercepts WordPress emails, queues them, and sends via SMTP with retry logic and logging.
 * Version:     1.5.0
 * Author:      m4g4
 * License:     GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: simple-smtp-mail-scheduler
 * Domain Path: /languages
 * 
 * Requires at least: 5.6
 * Tested up to: 6.8
 * Requires PHP: 7.4
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

define('SIMPLE_SMTP_MAIL_SCHEDULER_PLUGIN',    plugin_basename(__FILE__));

// TESTING MODE
define('SIMPLE_SMTP_TESTING_MODE', 1);

require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';

require_once __DIR__ . '/globals.php';
require_once __DIR__ . '/db/index.php';
require_once __DIR__ . '/install.php';
require_once __DIR__ . '/includes/index.php';
require_once __DIR__ . '/admin/index.php';

register_activation_hook(__FILE__, 'simple_smtp_mail_scheduler_activation');
register_deactivation_hook( __FILE__, 'simple_smtp_mail_scheduler_deactivation' );

add_action('plugins_loaded', 'simple_smtp_mail_scheduler_textdomain');
function simple_smtp_mail_scheduler_textdomain() {
    load_plugin_textdomain(Simple_SMTP_Constants::DOMAIN, false, basename(dirname(__FILE__)) . '/languages/' );
}

function simple_smtp_mail_scheduler_deactivation() {
    simple_smtp_unschedule_cron_event();
}

add_filter('cron_schedules', 'simple_smtp_mail_add_cron_interval');
function simple_smtp_mail_add_cron_interval() {
    $schedules['minute'] = array(
    	'interval' => 60,
    	'display'  => __('Every Minute', Simple_SMTP_Constants::DOMAIN),
	);

	return $schedules;
}
?>