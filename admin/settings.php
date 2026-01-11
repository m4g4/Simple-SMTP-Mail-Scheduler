<?php

namespace Ssmptms;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('Settings')) {

    class Settings {

        public function __construct() {
            add_action('admin_menu', [$this, 'register_menus']);
            add_action('admin_init', [$this, 'register_settings']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
            add_action('wp_ajax_ssmptms-start', [$this, 'ajax_start_scheduler']);

            add_filter('plugin_action_links_' . SSMPTMS_PLUGIN, [$this, 'add_settings_link']);
        }

        public function enqueue_assets() {
            wp_enqueue_style(
		    	'ssmptms-admin-css',
		    	plugins_url('css/admin.css', __FILE__),
		    	array(),
				Constants::PLUGIN_VERSION
		    );

            wp_enqueue_script(
		    	'ssmptms-admin-js',
		    	plugins_url('js/admin.js', __FILE__),
		    	array('jquery'),
				Constants::PLUGIN_VERSION
		    );

            wp_enqueue_script(
		    	'ssmptms-profile-page-js',
		    	plugins_url('js/profile_page.js', __FILE__),
		    	array('jquery'),
				Constants::PLUGIN_VERSION
		    );

            wp_localize_script( 
                'ssmptms-admin-js',
                'ssmptms_admin_ajax_params',
                array(
                    'ajax_url'       => admin_url( 'admin-ajax.php' ),
                    'start_action'   => 'ssmptms-start',
                    'start_text'     => __( 'Start', Constants::DOMAIN ),
                    'started_text'   => __( 'Started', Constants::DOMAIN ),
                    'starting_text'  => __( 'Starting', Constants::DOMAIN ),
                    'ajax_nonce'     => wp_create_nonce( 'ssmptms-start' ),
                )
            );
        }

        public function register_menus() {
            add_options_page(
                __('WO SMTP Mail Scheduler', Constants::DOMAIN),
                __('WO SMTP Mail Scheduler', Constants::DOMAIN),
                'manage_options',
                Constants::SETTINGS_PAGE,
                [$this, 'settings_page']
            );

            add_submenu_page(
                null,
                __('Edit SMTP Profile', Constants::DOMAIN),
                __('Edit SMTP', Constants::DOMAIN),
                'manage_options',
                Constants::PROFILE_EDIT_PAGE,
                [$this, 'edit_profiles']
            );
        }

        public function settings_page() {
            $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
            ?>
            <div class="wrap">
                <div class="ssmptms-headline-wrapper">
                    <div class="ssmptms-headline">
                        <h1><?php echo __('WO SMTP Mail Scheduler', Constants::DOMAIN); ?></h1>
                        <span class="ssmptms-tooltip-icon">?</span>
                        <span class="ssmptms-tooltip-text">
                            <?php echo __('This plugin intercepts all emails sent using WordPress’s wp_mail() function and processes them according to the configured rules (scheduling, sending, logging, status tracking, etc.).<br/>Other methods of sending emails in WordPress are not affected and bypass the plugin completely.', Constants::DOMAIN); ?>
                        </span>
                    </div>
                    <?php print_system_status(); ?>
                </div>


                <h2 class="nav-tab-wrapper">
                    <a href="?page=<?php echo esc_attr(Constants::SETTINGS_PAGE); ?>&tab=general"
                       class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                        <?php echo __('General Settings', Constants::DOMAIN); ?>
                    </a>
                    <a href="?page=<?php echo esc_attr(Constants::SETTINGS_PAGE); ?>&tab=log"
                       class="nav-tab <?php echo $active_tab === 'log' ? 'nav-tab-active' : ''; ?>">
                        <?php echo __('Email Log', Constants::DOMAIN); ?>
                    </a>
                    <a href="?page=<?php echo esc_attr(Constants::SETTINGS_PAGE); ?>&tab=stats"
                       class="nav-tab <?php echo $active_tab === 'stats' ? 'nav-tab-active' : ''; ?>">
                        <?php echo __('Statistics', Constants::DOMAIN); ?>
                    </a>
                    <a href="?page=<?php echo esc_attr(Constants::SETTINGS_PAGE); ?>&tab=test"
                        class="nav-tab <?php echo $active_tab === 'test' ? 'nav-tab-active' : ''; ?>">
                        <?php echo __('Email Test', Constants::DOMAIN); ?>
                    </a>
                </h2>

                <?php
                if ($active_tab === 'general') {
                    $this->render_general_settings_tab();
                } elseif ($active_tab === 'log') {
                    Log_Settings::get_instance()->render_tab();
                } elseif ($active_tab === 'stats') {
                    Statistics::get_instance()->render_tab();
                } elseif ($active_tab === 'test' && defined('SSMPTMS_TESTING_MODE')) {
                    Test_Settings::get_instance()->render_tab();
                }
                ?>
            </div>
            <?php
        }

        public function render_general_settings_tab() {
            General_Settings::get_instance()->render_tab();
        }

        public function register_settings() {
            General_Settings::get_instance()->register_settings();
        }

        public function edit_profiles() {
            $profile_id = isset($_GET['profile']) ? sanitize_text_field($_GET['profile']) : null;
            Profile_Page::get_instance()->display_profile($profile_id);
        }

        public function ajax_start_scheduler() {
            check_ajax_referer('ssmptms-start', 'ajax_nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Permission denied']);
            }
        
            schedule_cron_event();

            wp_send_json_success(['message' => 'Scheduler started successfully!']);
        }

        public function add_settings_link($links) {
            $settings_link = '<a href="options-general.php?page='.Constants::SETTINGS_PAGE.'">' . __('Settings', Constants::DOMAIN) . '</a>';
            array_unshift($links, $settings_link);
            return $links;
        }
    }
}

new Settings();
?>