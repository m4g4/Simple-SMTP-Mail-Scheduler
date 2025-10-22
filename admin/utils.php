<?php 

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Inline styles for test/send result messages.
 */
function simple_smtp_echo_message_styles(): void {
    ?>
    <style>
        .smtp-mail-message {
            border: 1px solid #ccd0d4;
            border-left-width: 4px;
            padding: 12px;
            background: #f9f9f9;
            font-size: 14px;
        }
    
        .smtp-mail-success {
            border-left-color: #46b450;
            background: #f1fdf6;
        }
    
        .smtp-mail-error {
            border-left-color: #dc3232;
            background: #fdf4f4;
        }
    </style>
    <?php
}

/**
 * Generate a UUID v4 string.
 */
function simple_smtp_guidv4(?string $data = null): string {
    $data = $data ?? random_bytes(16);
    if ( strlen( $data ) !== 16 ) {
        throw new InvalidArgumentException( 'GUIDv4 requires 16 bytes of data' );
    }

    // Set version to 0100
    $data[6] = chr( ord( $data[6] ) & 0x0f | 0x40 );
    // Set bits 6-7 to 10
    $data[8] = chr( ord( $data[8] ) & 0x3f | 0x80 );

    return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
}

/**
 * Derive encryption key for SMTP profile passwords.
 */
function simple_smtp_mail_get_encryption_key(): string {
    return hash( 'sha256', AUTH_KEY . SECURE_AUTH_KEY, true ); // raw 32 bytes
}

/**
 * Encrypt an SMTP password using OpenSSL AES.
 */
function simple_smtp_mail_encrypt_password( string $plaintext ): string {
    $iv  = random_bytes(16);
    $key = simple_smtp_mail_get_encryption_key();

    $encrypted = openssl_encrypt(
        $plaintext,
        Simple_SMTP_Constants::CIPHER,
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );

    if ( $encrypted === false ) {
        return '';
    }

    return base64_encode( $iv . $encrypted );
}

/**
 * Decrypt an SMTP password.
 */
function simple_smtp_mail_decrypt_password( string $ciphertext ): string {
    $data = base64_decode( $ciphertext, true );
    if ( $data === false || strlen( $data ) < 17 ) {
        return '';
    }

    $iv        = substr( $data, 0, 16 );
    $encrypted = substr( $data, 16 );
    $key       = simple_smtp_mail_get_encryption_key();

    $decrypted = openssl_decrypt(
        $encrypted,
        Simple_SMTP_Constants::CIPHER,
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );

    return $decrypted !== false ? $decrypted : '';
}

/**
 * Retrieve the active SMTP profile (array) or null if invalid.
 */
function simple_smtp_get_active_profile(): ?array {
    $active_profile_id = get_option( Simple_SMTP_Constants::PROFILE_ACTIVE, null );
    $profiles          = get_option( Simple_SMTP_Constants::PROFILES, [] );

    if ( ! $active_profile_id || empty( $profiles ) || ! isset( $profiles[ $active_profile_id ] ) ) {
        return null;
    }

    return $profiles[ $active_profile_id ];
}

function simple_smtp_get_profile_by_mail($from_email) {
    $profiles = get_option( Simple_SMTP_Constants::PROFILES, [] );

    if (empty($profiles) || !is_array($profiles)) {
        return null;
    }

    foreach ($profiles as $profile_id => $profile) {
        if (
            !empty($profile['from_email']) &&
            strtolower($profile['from_email']) === strtolower($from_email)
        ) {
            return $profile;
        }
    }

    return null;
}

function simple_stmp_scheduler_status_callback() {
    $next = wp_next_scheduled(Simple_SMTP_Constants::SCHEDULER_EVENT_NAME);
    $should_be_running = Simple_SMTP_Email_Queue::get_instance()->has_email_entries_for_sending();

    echo "<div class='s-smtp-status-bar'>";
    if ($should_be_running) {
        if ($next) {
            echo '<span class="s-smtp-status-bar-running">✅ ' . esc_html__('Sending in progress', Simple_SMTP_Constants::DOMAIN) . '</span>';
            echo '<p class="description">' . sprintf(
                esc_html__('Next run: %s', Simple_SMTP_Constants::DOMAIN),
                esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next))
            ) . '</p>';
        } else {
            echo '<span class="s-smtp-status-bar-not-running">🚫 ' . esc_html__('Not running!', Simple_SMTP_Constants::DOMAIN) . '</span>';
            echo '<p class="description">' . esc_html__('There was an error activating the scheduler. Try reactivating the plugin.', Simple_SMTP_Constants::DOMAIN) . '</p>';
        }
    } else {
        echo '<span class="s-smtp-status-bar-idle">⏸ ' . esc_html__('Idle', Simple_SMTP_Constants::DOMAIN) . '</span>';
        echo '<p class="description">' . esc_html__('No emails scheduled for sending.', Simple_SMTP_Constants::DOMAIN) . '</p>';
    }

    echo "</div>";
}