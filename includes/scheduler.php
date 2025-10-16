<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

if ( ! class_exists( 'Simple_SMTP_Mail_Scheduler' ) ) {

    class Simple_SMTP_Mail_Scheduler {
        private int $emailsPerUnit;
        private string $unit;   // "minute", "hour", "day"
        private float $rate;    // emails per minute
        private $callback;

        public function __construct( callable $callback ) {
            $this->emailsPerUnit = (int) get_option( Simple_SMTP_Constants::EMAILS_PER_UNIT, 0 );
            $this->unit          = get_option( Simple_SMTP_Constants::EMAILS_UNIT, 'minute' );
            $this->rate          = $this->calculateRate( $this->emailsPerUnit, $this->unit );
            $this->callback      = $callback;
        }

        private function calculateRate( int $emailsPerUnit, string $unit ): float {
            switch ( $unit ) {
                case 'minute':
                    return (float) $emailsPerUnit;
                case 'hour':
                    return $emailsPerUnit > 0 ? $emailsPerUnit / 60.0 : 0.0;
                case 'day':
                    return $emailsPerUnit > 0 ? $emailsPerUnit / 1440.0 : 0.0; // 24 * 60
                default:
                    return 0.0; // invalid config → don’t send
            }
        }

        public function tick(): void {
            $iteration = (int) get_option( Simple_SMTP_Constants::EMAILS_ITERATION, 0 );
            $totalSent = (int) get_option( Simple_SMTP_Constants::EMAILS_TOTAL_SENT, 0 );
            $resetTime = (int) get_option( Simple_SMTP_Constants::EMAILS_RESET_TIME, 0 );

            $now = current_time( 'timestamp' );

            // Reset counters when interval expires
            if ( $resetTime === 0 || $now >= $resetTime ) {
                $iteration = 0;
                $totalSent = 0;

                switch ( $this->unit ) {
                    case 'minute':
                        $resetTime = strtotime( '+1 minute', $now );
                        break;
                    case 'hour':
                        $resetTime = strtotime( '+1 hour', $now );
                        break;
                    case 'day':
                        $resetTime = strtotime( '+1 day', $now );
                        break;
                    default:
                        $resetTime = $now + 60; // safe fallback → reset every minute
                        break;
                }
            }

            $iteration++;
            $shouldHaveSent = (int) ceil( $iteration * $this->rate );
            $emailsToSend   = $shouldHaveSent - $totalSent;

            if ( $emailsToSend > 0 && is_callable( $this->callback ) ) {
                ( $this->callback )( $emailsToSend );
                $totalSent = $shouldHaveSent;
            }

            update_option( Simple_SMTP_Constants::EMAILS_ITERATION, $iteration );
            update_option( Simple_SMTP_Constants::EMAILS_TOTAL_SENT, $totalSent );
            update_option( Simple_SMTP_Constants::EMAILS_RESET_TIME, $resetTime );

            if (!Simple_SMTP_Email_Queue::get_instance()->has_email_entries_for_sending()) {
                simple_stmp_unschedule_cron_event();
            }
        }
    }
}

function simple_stmp_schedule_cron_event() {
    if (!wp_next_scheduled('simple_smtp_mail_send_emails_event')) {
        wp_schedule_event(time(), 'minute', 'simple_smtp_mail_send_emails_event');
    }
}

function simple_stmp_unschedule_cron_event() {
    $timestamp = wp_next_scheduled('simple_smtp_mail_send_emails_event');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'simple_smtp_mail_send_emails_event');
    }
}
