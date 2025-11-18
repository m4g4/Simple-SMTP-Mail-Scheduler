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
        Ssmptms_Constants::CIPHER,
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
        Ssmptms_Constants::CIPHER,
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
    $active_profile_id = get_option( Ssmptms_Constants::PROFILE_ACTIVE, null );
    $profiles          = get_option( Ssmptms_Constants::PROFILES, [] );

    if ( ! $active_profile_id || empty( $profiles ) || ! isset( $profiles[ $active_profile_id ] ) ) {
        return null;
    }

    return $profiles[ $active_profile_id ];
}

function simple_smtp_get_profile_by_mail($from_email) {
    $profiles = get_option( Ssmptms_Constants::PROFILES, [] );

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
    $disable_plugin = get_option(Ssmptms_Constants::DISABLE, false);
    if ($disable_plugin) {
        echo "<div class='ssmptms-status-bar'>";
        echo '<span class="ssmptms-status-bar-disabled">🛑 ' . esc_html__('Disabled', Ssmptms_Constants::DOMAIN) . '</span>';
        echo '<p class="description">' . esc_html__('Email processing is turned off. Emails sent through wp_mail() are not handled by the plugin.', Ssmptms_Constants::DOMAIN) . '</p>';
        echo '</div>';
        return;
    }

    $next = wp_next_scheduled(Ssmptms_Constants::SCHEDULER_EVENT_NAME);
    $queued_emails = Simple_SMTP_Email_Queue::get_instance()->get_email_entry_count_for_sending();

    echo "<div class='ssmptms-status-bar'>";

    if ($queued_emails > 0) {
        if ($next) {
            $rate = (int) get_option(Ssmptms_Constants::EMAILS_PER_UNIT, 0);
            $unit = get_option(Ssmptms_Constants::EMAILS_UNIT, 'minute');
            $current_queue_count  = (int) get_option(Ssmptms_Constants::CURRENT_QUEUE_COUNT,$queued_emails);
            $sent = $current_queue_count - $queued_emails;

            $progress = min(100, ($sent / $current_queue_count) * 100);

            $eta_timestamp = simple_stmp_scheduler_calculate_eta($queued_emails, $rate, $unit);
            $eta_human = date_i18n(get_option('time_format'), $eta_timestamp);

            $duration = simple_stmp_scheduler_format_duration($eta_timestamp - time());

            echo '<span class="ssmptms-status-bar-running">✅ ' . esc_html__('Sending in progress', Ssmptms_Constants::DOMAIN) . '</span>';
            simple_stmp_scheduler_progress_bar($progress);
            echo '<p class="description">'
                . sprintf(
                    esc_html__('%1$d emails queued • ETA %2$s (%3$s)', Ssmptms_Constants::DOMAIN),
                    $queued_emails,
                    esc_html($eta_human),
                    esc_html($duration)
                )
                . '</p>';
        } else {
            echo '<span class="ssmptms-status-bar-not-running">🚫 ' . esc_html__('Not running!', Ssmptms_Constants::DOMAIN) . '</span>';
            echo ' <a class="ssmptms-start-scheduler">▶️ ' . esc_html__('Start', Ssmptms_Constants::DOMAIN) . '</a>';
            echo '<p class="description">' . esc_html__('There was an error activating the scheduler. Try reactivating the plugin.', Ssmptms_Constants::DOMAIN) . '</p>';
        }
    } else {
        echo '<span class="ssmptms-status-bar-idle">⏸ ' . esc_html__('Idle', Ssmptms_Constants::DOMAIN) . '</span>';
        echo '<p class="description">' . esc_html__('No emails scheduled for sending.', Ssmptms_Constants::DOMAIN) . '</p>';
    }

    echo "</div>";
}

function simple_stmp_scheduler_calculate_eta(int $queued, float $rate, string $unit): int {
    if ($rate <= 0) {
        return time();
    }
    $slots = $queued / $rate;
    $duration_seconds = $slots * simple_stmp_scheduler_slot_time_seconds($unit);
    return time() + $duration_seconds;
}

function simple_stmp_scheduler_slot_time_seconds(string $unit): float {
    return match ($unit) {
        'day'    => 86400,
        'hour'   => 3600,
        'minute' => 60,
        default  => 0.0,
    };
}

function simple_stmp_scheduler_format_duration(float $seconds): string {
    if ($seconds < 60) {
        return round($seconds) . 's';
    } elseif ($seconds < 3600) {
        return floor($seconds / 60) . 'm';
    } elseif ($seconds < 86400) {
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        return sprintf('%dh %dm', $h, $m);
    } else {
        $d = floor($seconds / 86400);
        $h = floor(($seconds % 86400) / 3600);
        return sprintf('%dd %dh', $d, $h);
    }
}

function simple_stmp_scheduler_progress_bar($progress) {
    echo '<div class="ssmptms-progress-bar">';
    echo '<div class="ssmptms-progress" style="width:' . esc_attr($progress) . '%;"></div>';
    echo '</div>';
}
