<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('Simple_SMTP_Mail_Profile_Page')) {

    class Simple_SMTP_Mail_Profile_Page {
        private static $instance;

        public function __construct() {
            add_action('admin_post_simple_smtp_mail_profile_activate', [$this, 'handle_profile_activation']);
            add_action('admin_post_simple_smtp_mail_profile_delete', [$this, 'handle_profile_delete']);
            add_action('admin_post_simple_smtp_mail_profile_save', [$this, 'handle_profile_save']);
        }
        public static function get_instance() {
		    if ( null === self::$instance ) {
			    self::$instance = new self();
		    }

		    return self::$instance;
	    }

        public function display_profile(string $profile_id = null): void {
            $is_new = $profile_id === null;

            // Default profile values
            $profile = [
                'label'      => '',
                'from_email' => '',
                'from_name'  => '',
                'force_from_email' => false,
                'match_return_path' => false,
                'force_from_name' => false,
                'host'       => '',
                'port'       => 465,
                'encryption' => 'ssl',
                'autotls'    => true,
                'username'   => '',
                'password'   => '',
            ];

            // Check for transient data if there was an error
            $transient_data = get_transient('simple_smtp_mail_profile_form_data');
            if ($transient_data && isset($_GET['error']) && $_GET['error'] == 1) {
                $profile = array_merge($profile, $transient_data);
            } elseif (!$is_new) {
                $profiles = get_option(Simple_SMTP_Constants::PROFILES, []);
                if (isset($profiles[$profile_id])) {
                    $profile = $profiles[$profile_id];
                }
            } else {
                $profile_id = simple_smtp_guidv4();
            }

            echo '<div class="wrap"><h2>' . ($is_new ? __('Add SMTP Profile', Simple_SMTP_Constants::DOMAIN) : __('Edit SMTP Profile', Simple_SMTP_Constants::DOMAIN)) . '</h2>';

            simple_smtp_echo_message_styles();
            $this->show_errors();

            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="simple-smtp-profile-form">';
            wp_nonce_field('simple_smtp_mail_profile_save');
            echo '<input type="hidden" name="action" value="simple_smtp_mail_profile_save">';
            echo '<input type="hidden" name="profile_id" value="' . esc_attr($profile_id) . '">';

            echo '<table class="form-table">';
            echo '<tr><th scope="row"><label for="label">' . __('Label', Simple_SMTP_Constants::DOMAIN) . '</label></th>
                  <td><input type="text" class="regular-text" name="label" id="label" value="' . esc_attr($profile['label']) . '"></td></tr>';

            echo '<tr>
                <th scope="row"><label for="from_email">' . __('From Email', Simple_SMTP_Constants::DOMAIN) . '</label></th>
                <td>
                    <input type="email" class="regular-text" name="from_email" id="from_email" value="' . esc_attr($profile['from_email']) . '">
                    <div style="padding-top: 10px;">
                    <label>
                        <input type="checkbox" name="force_from_email" id="force_from_email" value="1" ' . checked(!empty($profile['force_from_email']), true, false) . '>
                        ' . __('Always use the specified "From" email address, even if another is provided by the sender.', Simple_SMTP_Constants::DOMAIN) . '
                    </label>
                    </div>
                    <div>
                    <label>
                        <input type="checkbox" name="match_return_path" id="match_return_path" value="1" ' . checked(!empty($profile['match_return_path']), true, false) . '>
                        ' . __('Automatically set the Return-Path header to match the "From" email address.', Simple_SMTP_Constants::DOMAIN) . '
                    </label>
                    </div>
                </td>
            </tr>';

            echo '<tr><th scope="row"><label for="from_name">' . __('From Name', Simple_SMTP_Constants::DOMAIN) . '</label></th>
                  <td>
                    <input type="text" class="regular-text" name="from_name" id="from_name" value="' . esc_attr($profile['from_name']) . '">  
                    <div style="padding-top: 10px;">
                    <label>
                        <input type="checkbox" name="force_from_name" id="force_from_name" value="1" ' . checked(!empty($profile['force_from_name']), true, false) . '>
                            ' . __('Always use the specified "From" name, even if another is provided by the sender.', Simple_SMTP_Constants::DOMAIN) . '
                    </label>
                    </div>
                  </td>
                  </tr>';

            echo '<tr><th scope="row"><label for="host">' . __('SMTP Host', Simple_SMTP_Constants::DOMAIN) . '</label></th>
                  <td><input type="text" class="regular-text code" name="host" id="host" value="' . esc_attr($profile['host']) . '"></td></tr>';

            echo '<tr><th scope="row"><label for="port">' . __('Port', Simple_SMTP_Constants::DOMAIN) . '</label></th>
                  <td><input type="number" class="small-text" name="port" id="port" value="' . esc_attr($profile['port']) . '"></td></tr>';

            echo '<tr><th scope="row"><label for="encryption">' . __('Encryption', Simple_SMTP_Constants::DOMAIN) . '</label></th>
                  <td><select name="encryption" id="encryption">
                      <option value="tls" ' . selected($profile['encryption'], 'tls', false) . '>TLS</option>
                      <option value="ssl" ' . selected($profile['encryption'], 'ssl', false) . '>SSL</option>
                      <option value="" ' . selected($profile['encryption'], '', false) . '>None</option>
                  </select></td></tr>';

            echo '<tr><th scope="row"><label for="autotls">' . __('Auto TLS', Simple_SMTP_Constants::DOMAIN) . '</label></th>
                <td>
                  <label>
                    <input type="checkbox" name="autotls" id="autotls" value="1" ' . checked(!empty($profile['autotls']), true, false) . '>
                    ' . __('Enable Auto TLS (automatically upgrade to TLS if available)', Simple_SMTP_Constants::DOMAIN) . '
                  </label>
                </td></tr>';

            echo '<tr><th scope="row"><label for="username">' . __('Username', Simple_SMTP_Constants::DOMAIN) . '</label></th>
                  <td><input type="text" class="regular-text" name="username" id="username" value="' . esc_attr($profile['username']) . '"></td></tr>';

            echo '<tr><th scope="row"><label for="password">' . __('Password', Simple_SMTP_Constants::DOMAIN) . '</label></th>
                  <td><input type="password" class="regular-text" name="password" id="password" value="">';
            if (!$is_new) {
                echo $profile['password'] ? '<span style="color: green;">✔ ' . __('Password is set', Simple_SMTP_Constants::DOMAIN) . '</span>'
                                          : '<span style="color: red;">' . __('No password set', Simple_SMTP_Constants::DOMAIN) . '</span>';
                echo '<p class="description">' . __('Enter a new password to change it, or leave blank to keep the current password.', Simple_SMTP_Constants::DOMAIN) . '</p>';
            }
            echo '</td></tr>';

            echo '</table>';

            echo '<div class="s-smtp-profile-button-group">';
            submit_button($is_new ? __('Add Profile', Simple_SMTP_Constants::DOMAIN) : __('Save Profile', Simple_SMTP_Constants::DOMAIN));
            echo '<div class="s-smtp-profile-back-button-wrapper">';
            echo '<a href="' . admin_url('options-general.php?page=' . Simple_SMTP_Constants::SETTINGS_PAGE) . '">';
            echo '<button type="button" class="button button-secondary">&larr; ' . __('Back', Simple_SMTP_Constants::DOMAIN) . '</button>';
            echo '</a>';
            echo '</div>';
            echo '</div>';
            echo '</form>';
            echo '</div>';

            if ($transient_data) {
                delete_transient('simple_smtp_mail_profile_form_data');
            }
        }

        private function show_errors(): void {
            $errors = get_transient('simple_smtp_mail_profile_errors');
            if (!empty($errors)) {
                echo '<div class="smtp-mail-message smtp-mail-error"><ul>';
                foreach ($errors as $error) {
                    echo '<li>' . $error . '</li>';
                }
                echo '</ul></div>';
                delete_transient('simple_smtp_mail_profile_errors');
            }
        }

        public function handle_profile_save() {
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'simple_smtp_mail_profile_save')) {
                wp_die('Security check failed');
            }
        
            // Sanitize input
            $profile_id = sanitize_text_field($_POST['profile_id']);
            $label      = sanitize_text_field($_POST['label']);
            $from_email = sanitize_email($_POST['from_email']);
            $from_name  = sanitize_text_field($_POST['from_name']);
            $host       = sanitize_text_field($_POST['host']);
            $port       = intval($_POST['port']);
            $encryption = sanitize_text_field($_POST['encryption']);
            $autotls    = isset($_POST['autotls']) ? 1 : 0;
            $username   = sanitize_text_field($_POST['username']);
            $password   = $_POST['password'] ?? '';
            $sender     = isset($_POST['match_return_path']) ? $from_email : '';
            $force_from_email = isset($_POST['force_from_email']) ? 1 : 0;
            $force_from_name = isset($_POST['force_from_name']) ? 1 : 0;
        
            $profiles = get_option(Simple_SMTP_Constants::PROFILES, []);
            $existing_profile = !empty($profiles[$profile_id]);
        
            // Store form data for re-population in case of errors
            $form_data = [
                'label'      => $label,
                'from_email' => $from_email,
                'from_name'  => $from_name,
                'sender'     => $sender,
                'host'       => $host,
                'port'       => $port,
                'encryption' => $encryption,
                'autotls'    => $autotls,
                'username'   => $username,
                'force_from_email' => $force_from_email,
                'force_from_name' => $force_from_name,
            ];
        
            // Validate input
            $errors = [];
            if (empty($label)) {
                $errors[] = __('Label is required.', Simple_SMTP_Constants::DOMAIN);
            }
            if (!is_email($from_email)) {
                $errors[] = __('From Email must be a valid email address.', Simple_SMTP_Constants::DOMAIN);
            }
            if (empty($host)) {
                $errors[] = __('SMTP Host is required.', Simple_SMTP_Constants::DOMAIN);
            }
            if ($port <= 0) {
                $errors[] = __('Port must be a positive number.', Simple_SMTP_Constants::DOMAIN);
            }
            if (empty($username)) {
                $errors[] = __('Username is required.', Simple_SMTP_Constants::DOMAIN);
            }
            if (!$existing_profile && empty($password)) {
                $errors[] = __('Password is required.', Simple_SMTP_Constants::DOMAIN);
            }
        
            // If validation failed
            if (!empty($errors)) {
                set_transient('simple_smtp_mail_profile_errors', $errors, 30);
                set_transient('simple_smtp_mail_profile_form_data', $form_data, 30);
                wp_safe_redirect(admin_url("admin.php?page=simple_smtp_mail_profile_edit&profile=$profile_id&error=1"));
                exit;
            }
        
            // Handle password encryption
            if ($existing_profile) {
                // If no new password, reuse old one
                if (empty($password) && isset($profiles[$profile_id]['password'])) {
                    $password = simple_smtp_mail_decrypt_password($profiles[$profile_id]['password']);
                }
            }
            $encrypted_password = simple_smtp_mail_encrypt_password($password);
        
            // Save profile
            $profiles[$profile_id] = [
                'id'        => $profile_id,
                'label'     => $label,
                'from_email'=> $from_email,
                'from_name' => $from_name,
                'sender'    => $sender,
                'host'      => $host,
                'port'      => $port,
                'encryption'=> $encryption,
                'autotls'   => $autotls,
                'username'  => $username,
                'password'  => $encrypted_password,
                'force_from_email' => $force_from_email,
                'force_from_name' => $force_from_name,
            ];
            update_option(Simple_SMTP_Constants::PROFILES, $profiles);
        
            // Set active profile if none exists
            if (get_option(Simple_SMTP_Constants::PROFILE_ACTIVE, null) === null) {
                update_option(Simple_SMTP_Constants::PROFILE_ACTIVE, $profile_id);
            }
        
            // Test SMTP connection
            $test_success = $this->test_connection($profiles[$profile_id]);
            if (!$test_success) {
                $errors[] = wp_kses_post(__('SMTP connection test <strong>failed</strong>. Check credentials or server settings.', Simple_SMTP_Constants::DOMAIN));
            }
        
            if (!empty($errors)) {
                set_transient('simple_smtp_mail_profile_errors', $errors, 30);
                set_transient('simple_smtp_mail_profile_form_data', $form_data, 30);
                wp_safe_redirect(admin_url("admin.php?page=simple_smtp_mail_profile_edit&profile=$profile_id&error=1"));
                exit;
            }
        
            // Redirect to success page
            wp_safe_redirect(admin_url('options-general.php?page=' . Simple_SMTP_Constants::SETTINGS_PAGE . '&saved=1'));
            exit;
        }

        public function handle_profile_activation() {
            $active_key = Simple_SMTP_Constants::PROFILE_ACTIVE;
                
            if (!current_user_can('manage_options')) {
                wp_die('Unauthorized');
            }
        
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'simple_smtp_mail_profile_activate')) {
                wp_die('Security check failed');
            }
        
            $profile_id = isset($_GET['profile']) ? sanitize_text_field($_GET['profile']) : null;
            if (!$profile_id) {
                wp_die('No profile specified');
            }
        
            update_option($active_key, $profile_id);
        
            wp_safe_redirect(admin_url('options-general.php?page=' . Simple_SMTP_Constants::SETTINGS_PAGE . '&activated=1'));
            exit;
        }

        public function handle_profile_delete() {
            $profiles_key = Simple_SMTP_Constants::PROFILES;
        
            if (!current_user_can('manage_options')) {
                wp_die('Unauthorized');
            }
        
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'simple_smtp_mail_profile_delete')) {
                wp_die('Security check failed');
            }
        
            $profile_id = isset($_GET['profile']) ? sanitize_text_field($_GET['profile']) : null;
            if (!$profile_id) {
                wp_die('No profile specified');
            }
        
            $profiles = get_option($profiles_key, []);
            if (isset($profiles[$profile_id])) {
                unset($profiles[$profile_id]);
                update_option($profiles_key, $profiles);
            }
        
            wp_safe_redirect(admin_url('options-general.php?page=' . Simple_SMTP_Constants::SETTINGS_PAGE . '&deleted=1'));
            exit;
        }

        private function test_connection($profile) {
            if (empty($profile) || !is_array($profile)) {
                return false;
            }
        
            $mailer = Simple_SMTP_Mail_Scheduler_Mailer::get_instance()->prepare_mailer($profile);
            if ($mailer === null) {
                return false;
            }
        
            try {
                if (!$mailer->smtpConnect()) {
                    return false;
                }
                $mailer->smtpClose();
                return true;
            } catch (\Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('SMTP connection test failed: ' . $e->getMessage());
                }
                return false;
            }
        }

    }
}

Simple_SMTP_Mail_Profile_Page::get_instance();