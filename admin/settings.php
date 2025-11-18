<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('Simple_SMTP_Mail_Settings')) {

    class Simple_SMTP_Mail_Settings {

        public function __construct() {
            add_action('admin_menu', [$this, 'register_menus']);
            add_action('admin_init', [$this, 'register_settings']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
            add_action('wp_ajax_simple-smtp-mail-scheduler-start', [$this, 'ajax_start_scheduler']);

            add_filter('plugin_action_links_' . SIMPLE_SMTP_MAIL_SCHEDULER_PLUGIN, [$this, 'add_settings_link']);
        }

        public function enqueue_assets() {
            wp_enqueue_style(
		    	'simple-smtp-mail-scheduler-admin-css',
		    	plugins_url('css/admin.css', __FILE__),
		    	array(),
				Simple_SMTP_Constants::PLUGIN_VERSION
		    );

            wp_enqueue_script(
		    	'simple-smtp-mail-scheduler-admin-js',
		    	plugins_url('js/admin.js', __FILE__),
		    	array('jquery'),
				Simple_SMTP_Constants::PLUGIN_VERSION
		    );

            wp_enqueue_script(
		    	'simple-smtp-mail-scheduler-profile-page-js',
		    	plugins_url('js/profile_page.js', __FILE__),
		    	array('jquery'),
				Simple_SMTP_Constants::PLUGIN_VERSION
		    );

            wp_localize_script( 
                'simple-smtp-mail-scheduler-admin-js',
                'ssmptms_admin_ajax_params',
                array(
                    'ajax_url'       => admin_url( 'admin-ajax.php' ),
                    'start_action'   => 'simple-smtp-mail-scheduler-start',
                    'start_text'     => __( 'Start', Simple_SMTP_Constants::DOMAIN ),
                    'started_text'   => __( 'Started', Simple_SMTP_Constants::DOMAIN ),
                    'starting_text'  => __( 'Starting', Simple_SMTP_Constants::DOMAIN ),
                    'ajax_nonce'     => wp_create_nonce( 'simple-smtp-mail-scheduler-start' ),
                )
            );
        }

        public function register_menus() {
            add_options_page(
                __('Simple SMTP Mail Scheduler', Simple_SMTP_Constants::DOMAIN),
                __('Simple SMTP Mail Scheduler', Simple_SMTP_Constants::DOMAIN),
                'manage_options',
                Simple_SMTP_Constants::SETTINGS_PAGE,
                [$this, 'settings_page']
            );

            add_submenu_page(
                null,
                __('Edit SMTP Profile', Simple_SMTP_Constants::DOMAIN),
                __('Edit SMTP', Simple_SMTP_Constants::DOMAIN),
                'manage_options',
                Simple_SMTP_Constants::PROFILE_EDIT_PAGE,
                [$this, 'edit_profiles']
            );
        }

        public function settings_page() {
            $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
            ?>
            <div class="wrap">
                <div class="ssmptms-headline-wrapper">
                    <h1><?php echo esc_html__('Simple SMTP Mail Scheduler', Simple_SMTP_Constants::DOMAIN); ?></h1>
                    <?php simple_stmp_scheduler_status_callback(); ?>
                </div>

                <h2 class="nav-tab-wrapper">
                    <a href="?page=<?php echo esc_attr(Simple_SMTP_Constants::SETTINGS_PAGE); ?>&tab=general"
                       class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html__('General Settings', Simple_SMTP_Constants::DOMAIN); ?>
                    </a>
                    <a href="?page=<?php echo esc_attr(Simple_SMTP_Constants::SETTINGS_PAGE); ?>&tab=log"
                       class="nav-tab <?php echo $active_tab === 'log' ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html__('Email Log', Simple_SMTP_Constants::DOMAIN); ?>
                    </a>
                    <a href="?page=<?php echo esc_attr(Simple_SMTP_Constants::SETTINGS_PAGE); ?>&tab=stats"
                       class="nav-tab <?php echo $active_tab === 'stats' ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html__('Statistics', Simple_SMTP_Constants::DOMAIN); ?>
                    </a>
                    <a href="?page=<?php echo esc_attr(Simple_SMTP_Constants::SETTINGS_PAGE); ?>&tab=test"
                        class="nav-tab <?php echo $active_tab === 'test' ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html__('Testing', Simple_SMTP_Constants::DOMAIN); ?>
                    </a>
                </h2>

                <?php
                if ($active_tab === 'general') {
                    $this->render_general_settings_tab();
                } elseif ($active_tab === 'log') {
                    Simple_SMTP_Mail_Log_Settings::get_instance()->render_tab();
                } elseif ($active_tab === 'stats') {
                    Simple_SMTP_Mail_Statistics::get_instance()->render_tab();
                } elseif ($active_tab === 'test' && defined('SIMPLE_SMTP_TESTING_MODE')) {
                    Simple_SMTP_Mail_Test_Settings::get_instance()->render_tab();
                }
                ?>
            </div>
            <?php
        }

        public function render_general_settings_tab() {
            Simple_SMTP_Mail_General_Settings::get_instance()->render_tab();
        }

        public function register_settings() {
            Simple_SMTP_Mail_General_Settings::get_instance()->register_settings();
        }

        public function edit_profiles() {
            $profile_id = isset($_GET['profile']) ? sanitize_text_field($_GET['profile']) : null;
            Simple_SMTP_Mail_Profile_Page::get_instance()->display_profile($profile_id);
        }

        public function ajax_start_scheduler() {
            check_ajax_referer('simple-smtp-mail-scheduler-start', 'ajax_nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Permission denied']);
            }
        
            simple_smtp_schedule_cron_event();

            wp_send_json_success(['message' => 'Scheduler started successfully!']);
        }

        public function add_settings_link($links) {
            $settings_link = '<a href="options-general.php?page='.Simple_SMTP_Constants::SETTINGS_PAGE.'">' . __('Settings', Simple_SMTP_Constants::DOMAIN) . '</a>';
            array_unshift($links, $settings_link);
            return $links;
        }
    }
}

new Simple_SMTP_Mail_Settings();
?>