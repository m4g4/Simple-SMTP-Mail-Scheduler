<?php
namespace Ssmptms;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'General_Settings' ) ) {

	class General_Settings {

        private static $instance;

        public static function get_instance() {
		    if ( null === self::$instance ) {
			    self::$instance = new self();
		    }

		    return self::$instance;
	    }

        public function register_settings() {
            add_settings_section(
                Constants::SECTION_BASIC,
                '',
                function() {},
                Constants::SETTINGS_SECTION_BASIC
            );

            add_settings_section(
                Constants::SECTION_SCHEDULER,
                __( 'Email Scheduler Settings', Constants::DOMAIN ),
                [ $this, 'scheduler_section_text' ],
                Constants::SETTINGS_SECTION_SCHEDULER
            );

            $this->register_basic_settings();
            $this->register_scheduler_settings();
        }

        public function render_tab() {
            ?>
            <div class="wrap">
                <form method="post" action="options.php">
                    <?php
                    settings_fields(Constants::GENERAL_OPTION_GROUP);

                    $this->show_profiles();

                    do_settings_sections(Constants::SETTINGS_SECTION_BASIC);
                    do_settings_sections(Constants::SETTINGS_SECTION_SCHEDULER);
                    
                    submit_button();
                    ?>
                </form>
            </div>
            <?php
            if (function_exists('echo_message_styles')) {
                echo_message_styles();
            }
        }

        private function register_basic_settings() {
            register_setting(
                Constants::GENERAL_OPTION_GROUP,
                Constants::DISABLE,
                [
                    'type' => 'boolean',
                    'sanitize_callback' => fn($value) => (bool) $value,
                    'default' => false
                ]
            );
            add_settings_field(
                'disable',
                __('Disable plugin functionality', Constants::DOMAIN),
                [$this, 'disable_callback'],
                Constants::SETTINGS_SECTION_BASIC,
                Constants::SECTION_BASIC
            );
        }

        private function register_scheduler_settings() {
            register_setting(
                Constants::GENERAL_OPTION_GROUP,
                Constants::EMAILS_PER_UNIT,
                [
                    'type'              => 'integer',
                    'sanitize_callback' => [ $this, 'sanitize_emails_per_unit' ],
                    'default'           => 5,
                ]
            );

            register_setting(
                Constants::GENERAL_OPTION_GROUP,
                Constants::EMAILS_UNIT,
                [
                    'type'              => 'string',
                    'sanitize_callback' => [ $this, 'sanitize_emails_unit' ],
                    'default'           => 'minute',
                ]
            );

            add_settings_field(
                'emails_per_unit',
                __( 'Emails per unit', Constants::DOMAIN ),
                [ $this, 'emails_per_unit_callback' ],
                Constants::SETTINGS_SECTION_SCHEDULER,
                Constants::SECTION_SCHEDULER
            );
        }

        public function scheduler_section_text(): void {
            echo '<p>' . esc_html__( 'Configure the email sending limits and scheduler settings below.', Constants::DOMAIN ) . '</p>';
        }

        /**
         * Emails per unit + unit selector field.
         */
        public function emails_per_unit_callback(): void {
            $value = (int) get_option( Constants::EMAILS_PER_UNIT, 5 );
            $unit  = get_option( Constants::EMAILS_UNIT, 'minute' );
            ?>
            <input type="number" name="<?php echo esc_attr( Constants::EMAILS_PER_UNIT ); ?>" value="<?php echo esc_attr( $value ); ?>" min="1" />
            <select name="<?php echo esc_attr( Constants::EMAILS_UNIT ); ?>">
                <?php
                foreach (Constants::UNITS as $unit_key ) {
                    $label = Constants::get_unit_text($unit_key);
                    printf(
                        '<option value="%s" %s>%s</option>',
                        esc_attr( $unit_key ),
                        selected( $unit, $unit_key, false ),
                        esc_html( $label )
                    );
                }
                ?>
            </select>
            <p class="description"><?php esc_html_e( 'Maximum number of emails that can be sent per time unit.', Constants::DOMAIN ); ?></p>
            <?php
        }

        /**
         * Sanitize emails per unit.
         */
        public function sanitize_emails_per_unit( $value ): int {
            $value = absint( $value );
            if ( $value < 1 ) {
                add_settings_error(
                    Constants::EMAILS_PER_UNIT,
                    'invalid_emails_per_unit',
                    __( 'Emails per unit must be a positive number.', Constants::DOMAIN ),
                    'error'
                );
                return (int) get_option( Constants::EMAILS_PER_UNIT, 5 );
            }
            return $value;
        }

        /**
         * Sanitize time unit.
         */
        public function sanitize_emails_unit( $value ): string {
            if ( ! in_array( $value, Constants::UNITS, true ) ) {
                add_settings_error(
                    Constants::EMAILS_UNIT,
                    'invalid_emails_unit',
                    __( 'Invalid time unit selected.', Constants::DOMAIN ),
                    'error'
                );
                return get_option( Constants::EMAILS_UNIT, 'minute' );
            }
            return $value;
        }

        public function disable_callback() {
            $value = get_option(Constants::DISABLE, false);
            ?>
            <label>
                <input type="checkbox" name="<?php echo esc_attr(Constants::DISABLE); ?>"
                       value="1" <?php checked($value, true); ?> />
                <?php _e('When enabled, the plugin will stop processing emails sent through wp_mail().', Constants::DOMAIN); ?>
            </label>
            <?php
        }
        
        public function show_profiles() {
            $active_profile = get_option(Constants::PROFILE_ACTIVE, null);
            $profiles = get_option(Constants::PROFILES, []);

            ?>
            <h2><?php echo esc_html__('SMTP Profiles', Constants::DOMAIN); ?></h2>
            <table class="widefat">
                <thead>
                <tr>
                    <th><?php echo esc_html__('Label', Constants::DOMAIN); ?></th>
                    <th><?php echo esc_html__('Host', Constants::DOMAIN); ?></th>
                    <th><?php echo esc_html__('From Email', Constants::DOMAIN); ?></th>
                    <th><?php echo esc_html__('Active', Constants::DOMAIN); ?></th>
                    <th><?php echo esc_html__('Actions', Constants::DOMAIN); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if (!empty($profiles)) : ?>
                    <?php foreach ($profiles as $key => $profile) : ?>
                        <?php
                        $label = isset($profile['label']) ? esc_html($profile['label']) : '';
                        $host  = isset($profile['host']) ? esc_html($profile['host']) : '';
                        $email = isset($profile['from_email']) ? esc_html($profile['from_email']) : '';

                        $is_active = ($active_profile === $key) ? '✅ ' . esc_html__('Active', Constants::DOMAIN) : '';

                        $edit_url = admin_url('admin.php?page=' . Constants::PROFILE_EDIT_PAGE . '&profile=' . urlencode($key));
                        $activate_url = wp_nonce_url(
                            admin_url("admin-post.php?action=ssmptms_profile_activate&profile=$key"),
                            'ssmptms_profile_activate'
                        );
                        $delete_url = wp_nonce_url(
                            admin_url("admin-post.php?action=ssmptms_profile_delete&profile=$key"),
                            'ssmptms_profile_delete'
                        );

                        $activate = !$is_active ? ' | <a href="' . esc_url($activate_url) . '">' . esc_html__('Set Active', Constants::DOMAIN) . '</a>' : '';
                        $delete   = !$is_active ? ' | <a href="' . esc_url($delete_url) . '" onclick="return confirm(\'' . esc_js(__('Are you sure you want to delete this profile?', Constants::DOMAIN)) . '\');">' . esc_html__('Delete', Constants::DOMAIN) . '</a>' : '';
                        ?>
                        <tr>
                            <td><?php echo $label; ?></td>
                            <td><?php echo $host; ?></td>
                            <td><?php echo $email; ?></td>
                            <td><?php echo $is_active; ?></td>
                            <td>
                                <a href="<?php echo esc_url($edit_url); ?>"><?php echo esc_html__('Edit', Constants::DOMAIN); ?></a>
                                <?php echo $activate; ?>
                                <?php echo $delete; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="5" style="text-align:center;"><?php echo esc_html__('No SMTP profiles found.', Constants::DOMAIN); ?></td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
            <br />
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . Constants::PROFILE_EDIT_PAGE . '&new=1')); ?>"
               class="button button-secondary">
                <?php echo esc_html__('Add new profile', Constants::DOMAIN); ?>
            </a>
            <?php
        }
    }
}
