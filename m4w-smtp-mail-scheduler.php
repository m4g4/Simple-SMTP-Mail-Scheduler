<?php
/**
 * Plugin Name: M4W SMTP Mail Scheduler
 * Description: Intercepts WordPress emails, queues them, and sends via SMTP with retry logic and logging.
 * Version:     1.11.1
 * Author:      m4g4
 * License:     GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: m4w-smtp-mail-scheduler
 * Domain Path: /languages
 * 
 * Requires at least: 5.6
 * Tested up to: 6.8
 * Requires PHP: 8.2
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

define('SSMPTMS_PLUGIN',              plugin_basename(__FILE__));
define('SSMPTMS_PLUGIN_LANG_PATH',    plugin_dir_path( __FILE__ ) . 'languages');

// TESTING MODE
define('SSMPTMS_TESTING_MODE', 0);

require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';

require_once __DIR__ . '/globals.php';
require_once __DIR__ . '/db/index.php';
require_once __DIR__ . '/install.php';
require_once __DIR__ . '/includes/index.php';
require_once __DIR__ . '/admin/index.php';

register_activation_hook(__FILE__, 'ssmptms_activation');
register_deactivation_hook( __FILE__, 'ssmptms_deactivation' );

add_action('plugins_loaded', 'ssmptms_textdomain');
function ssmptms_textdomain() {
    load_plugin_textdomain(Ssmptms\Constants::DOMAIN, false, plugin_basename(__DIR__) . '/languages/' );
}

function ssmptms_deactivation() {
    Ssmptms\unschedule_cron_event();
}

add_filter('cron_schedules', 'ssmptms_add_cron_interval');
function ssmptms_add_cron_interval($schedules) {
    $schedules['minute'] = array(
    	'interval' => 60,
    	'display'  => __('Every Minute', Ssmptms\Constants::DOMAIN),
	);

	return $schedules;
}
?>
