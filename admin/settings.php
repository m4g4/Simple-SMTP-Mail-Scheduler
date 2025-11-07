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

            wp_localize_script( 
                'simple-smtp-mail-scheduler-admin-js',
                'ajax_params',
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
                <div class="s-smtp-headline-wrapper">
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
                    <?php if (defined('SIMPLE_SMTP_TESTING_MODE') && SIMPLE_SMTP_TESTING_MODE == 1) : ?>
                        <a href="?page=<?php echo esc_attr(Simple_SMTP_Constants::SETTINGS_PAGE); ?>&tab=test"
                           class="nav-tab <?php echo $active_tab === 'test' ? 'nav-tab-active' : ''; ?>">
                            <?php echo esc_html__('Testing', Simple_SMTP_Constants::DOMAIN); ?>
                        </a>
                    <?php endif; ?>
                </h2>

                <?php
                if ($active_tab === 'general') {
                    $this->render_general_settings_tab();
                } elseif ($active_tab === 'log') {
                    Simple_SMTP_Mail_Log_Settings::get_instance()->render_tab();
                } elseif ($active_tab === 'stats') {
                    Simple_SMTP_Mail_Statistics::get_instance()->render_tab();
                } elseif ($active_tab === 'test' && defined('SIMPLE_SMTP_TESTING_MODE') && SIMPLE_SMTP_TESTING_MODE == 1) {
                    Simple_SMTP_Mail_Test_Settings::get_instance()->render_tab();
                }
                ?>
            </div>
            <?php
        }

        public function render_general_settings_tab() {
            ?>
            <div class="wrap">
                <form method="post" action="options.php">
                    <?php
                    settings_fields('simple_smtp_mail_settings_group');
                    $this->show_profiles();
                    do_settings_sections(Simple_SMTP_Constants::SETTINGS_PAGE);
                    submit_button();
                    ?>
                </form>
            </div>
            <?php
            if (function_exists('simple_smtp_echo_message_styles')) {
                simple_smtp_echo_message_styles();
            }
        }

        public function register_settings() {
            add_settings_section(
                'scheduler_section',
                __( 'Email Scheduler Settings', Simple_SMTP_Constants::DOMAIN ),
                [ $this, 'scheduler_section_text' ],
                Simple_SMTP_Constants::SETTINGS_PAGE
            );

            add_settings_section(
                'main_section',
                '',//__('General Settings', Simple_SMTP_Constants::DOMAIN),
                [$this, 'main_section_text'],
                Simple_SMTP_Constants::SETTINGS_PAGE
            );

            $this->register_units_settings();
            $this->register_testing_settings();
        }

        private function register_units_settings() {
            register_setting(
                'simple_smtp_mail_settings_group',
                Simple_SMTP_Constants::EMAILS_PER_UNIT,
                [
                    'type'              => 'integer',
                    'sanitize_callback' => [ $this, 'sanitize_emails_per_unit' ],
                    'default'           => 5,
                ]
            );

            register_setting(
                'simple_smtp_mail_settings_group',
                Simple_SMTP_Constants::EMAILS_UNIT,
                [
                    'type'              => 'string',
                    'sanitize_callback' => [ $this, 'sanitize_emails_unit' ],
                    'default'           => 'minute',
                ]
            );

            add_settings_field(
                'emails_per_unit',
                __( 'Emails per unit', Simple_SMTP_Constants::DOMAIN ),
                [ $this, 'emails_per_unit_callback' ],
                Simple_SMTP_Constants::SETTINGS_PAGE,
                'scheduler_section'
            );
        }

        private function register_testing_settings() {
            register_setting(
                'simple_smtp_mail_settings_group',
                Simple_SMTP_Constants::EMAILS_TESTING,
                [
                    'type' => 'boolean',
                    'sanitize_callback' => [$this, 'sanitize_emails_testing'],
                    'default' => false
                ]
            );

            // Conditionally register testing field if SIMPLE_SMTP_TESTING_MODE is 1
            if (defined('SIMPLE_SMTP_TESTING_MODE') && SIMPLE_SMTP_TESTING_MODE == 1) {
                add_settings_field(
                    'testing',
                    __('Testing Mode', Simple_SMTP_Constants::DOMAIN),
                    [$this, 'testing_callback'],
                    Simple_SMTP_Constants::SETTINGS_PAGE,
                    'main_section'
                );
            }
        }

        public function main_section_text() {
            //echo '<p>' . esc_html__('Configure general settings for the SMTP Mail Scheduler.', Simple_SMTP_Constants::DOMAIN) . '</p>';
        }

        public function scheduler_section_text(): void {
            echo '<p>' . esc_html__( 'Configure the email sending limits and scheduler settings below.', Simple_SMTP_Constants::DOMAIN ) . '</p>';
        }

        public function edit_profiles() {
            $profile_id = isset($_GET['profile']) ? sanitize_text_field($_GET['profile']) : null;
            Simple_SMTP_Mail_Profile_Page::get_instance()->display_profile($profile_id);
        }

        /**
         * Emails per unit + unit selector field.
         */
        public function emails_per_unit_callback(): void {
            $value = (int) get_option( Simple_SMTP_Constants::EMAILS_PER_UNIT, 5 );
            $unit  = get_option( Simple_SMTP_Constants::EMAILS_UNIT, 'minute' );
            ?>
            <input type="number" name="<?php echo esc_attr( Simple_SMTP_Constants::EMAILS_PER_UNIT ); ?>" value="<?php echo esc_attr( $value ); ?>" min="1" />
            <select name="<?php echo esc_attr( Simple_SMTP_Constants::EMAILS_UNIT ); ?>">
                <?php
                foreach (Simple_SMTP_Constants::UNITS as $unit_key ) {
                    $label = Simple_SMTP_Constants::get_unit_text($unit_key);
                    printf(
                        '<option value="%s" %s>%s</option>',
                        esc_attr( $unit_key ),
                        selected( $unit, $unit_key, false ),
                        esc_html( $label )
                    );
                }
                ?>
            </select>
            <p class="description"><?php esc_html_e( 'Maximum number of emails that can be sent per time unit.', Simple_SMTP_Constants::DOMAIN ); ?></p>
            <?php
        }

        /**
         * Sanitize emails per unit.
         */
        public function sanitize_emails_per_unit( $value ): int {
            $value = absint( $value );
            if ( $value < 1 ) {
                add_settings_error(
                    Simple_SMTP_Constants::EMAILS_PER_UNIT,
                    'invalid_emails_per_unit',
                    __( 'Emails per unit must be a positive number.', Simple_SMTP_Constants::DOMAIN ),
                    'error'
                );
                return (int) get_option( Simple_SMTP_Constants::EMAILS_PER_UNIT, 5 );
            }
            return $value;
        }

        /**
         * Sanitize time unit.
         */
        public function sanitize_emails_unit( $value ): string {
            if ( ! in_array( $value, Simple_SMTP_Constants::UNITS, true ) ) {
                add_settings_error(
                    Simple_SMTP_Constants::EMAILS_UNIT,
                    'invalid_emails_unit',
                    __( 'Invalid time unit selected.', Simple_SMTP_Constants::DOMAIN ),
                    'error'
                );
                return get_option( Simple_SMTP_Constants::EMAILS_UNIT, 'minute' );
            }
            return $value;
        }
        public function testing_callback() {
            $value = get_option(Simple_SMTP_Constants::EMAILS_TESTING, false);
            ?>
            <label>
                <input type="checkbox" name="<?php echo esc_attr(Simple_SMTP_Constants::EMAILS_TESTING); ?>"
                       value="1" <?php checked($value, true); ?> />
                <?php esc_html_e('Enable testing mode (emails will not be sent)', Simple_SMTP_Constants::DOMAIN); ?>
            </label>
            <p class="description">
                <?php esc_html_e('When enabled, emails are logged but not sent to recipients.', Simple_SMTP_Constants::DOMAIN); ?>
            </p>
            <?php
        }
        public function show_profiles() {
            $active_profile = get_option(Simple_SMTP_Constants::PROFILE_ACTIVE, null);
            $profiles = get_option(Simple_SMTP_Constants::PROFILES, []);

            ?>
            <h2><?php echo esc_html__('SMTP Profiles', Simple_SMTP_Constants::DOMAIN); ?></h2>
            <table class="widefat">
                <thead>
                <tr>
                    <th><?php echo esc_html__('Label', Simple_SMTP_Constants::DOMAIN); ?></th>
                    <th><?php echo esc_html__('Host', Simple_SMTP_Constants::DOMAIN); ?></th>
                    <th><?php echo esc_html__('From Email', Simple_SMTP_Constants::DOMAIN); ?></th>
                    <th><?php echo esc_html__('Active', Simple_SMTP_Constants::DOMAIN); ?></th>
                    <th><?php echo esc_html__('Actions', Simple_SMTP_Constants::DOMAIN); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if (!empty($profiles)) : ?>
                    <?php foreach ($profiles as $key => $profile) : ?>
                        <?php
                        $label = isset($profile['label']) ? esc_html($profile['label']) : '';
                        $host  = isset($profile['host']) ? esc_html($profile['host']) : '';
                        $email = isset($profile['from_email']) ? esc_html($profile['from_email']) : '';

                        $is_active = ($active_profile === $key) ? '✅ ' . esc_html__('Active', Simple_SMTP_Constants::DOMAIN) : '';

                        $edit_url = admin_url('admin.php?page=' . Simple_SMTP_Constants::PROFILE_EDIT_PAGE . '&profile=' . urlencode($key));
                        $activate_url = wp_nonce_url(
                            admin_url("admin-post.php?action=simple_smtp_mail_profile_activate&profile=$key"),
                            'simple_smtp_mail_profile_activate'
                        );
                        $delete_url = wp_nonce_url(
                            admin_url("admin-post.php?action=simple_smtp_mail_profile_delete&profile=$key"),
                            'simple_smtp_mail_profile_delete'
                        );

                        $activate = !$is_active ? ' | <a href="' . esc_url($activate_url) . '">' . esc_html__('Set Active', Simple_SMTP_Constants::DOMAIN) . '</a>' : '';
                        $delete   = !$is_active ? ' | <a href="' . esc_url($delete_url) . '" onclick="return confirm(\'' . esc_js(__('Are you sure you want to delete this profile?', Simple_SMTP_Constants::DOMAIN)) . '\');">' . esc_html__('Delete', Simple_SMTP_Constants::DOMAIN) . '</a>' : '';
                        ?>
                        <tr>
                            <td><?php echo $label; ?></td>
                            <td><?php echo $host; ?></td>
                            <td><?php echo $email; ?></td>
                            <td><?php echo $is_active; ?></td>
                            <td>
                                <a href="<?php echo esc_url($edit_url); ?>"><?php echo esc_html__('Edit', Simple_SMTP_Constants::DOMAIN); ?></a>
                                <?php echo $activate; ?>
                                <?php echo $delete; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="5" style="text-align:center;"><?php echo esc_html__('No SMTP profiles found.', Simple_SMTP_Constants::DOMAIN); ?></td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
            <br />
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . Simple_SMTP_Constants::PROFILE_EDIT_PAGE . '&new=1')); ?>"
               class="button button-secondary">
                <?php echo esc_html__('Add new profile', Simple_SMTP_Constants::DOMAIN); ?>
            </a>
            <?php
        }

        public function sanitize_emails_testing($value) {
            return (bool) $value;
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