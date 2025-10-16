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
            $carry = (int) get_option( Simple_SMTP_Constants::EMAILS_SCHEDULER_CARRY, 0 );

            $toSend = floor($this->rate + $carry);
            $new_carry = ($this->rate + $carry) - $toSend;

            if ($toSend > 0 && is_callable($this->callback)) {
                ($this->callback)($toSend);
            }

            error_log("Sending new batch of emails: " + $toSend);

            update_option( Simple_SMTP_Constants::EMAILS_SCHEDULER_CARRY, $new_carry );

            if (!Simple_SMTP_Email_Queue::get_instance()->has_email_entries_for_sending()) {
                simple_stmp_unschedule_cron_event();
            }
        }
    }
}

function simple_stmp_schedule_cron_event() {
    if (!wp_next_scheduled(Simple_SMTP_Constants::SCHEDULER_EVENT_NAME)) {
        wp_schedule_event(time(), 'minute', Simple_SMTP_Constants::SCHEDULER_EVENT_NAME);
    }
}

function simple_stmp_unschedule_cron_event() {
    $timestamp = wp_next_scheduled(Simple_SMTP_Constants::SCHEDULER_EVENT_NAME);
    if ($timestamp) {
        wp_unschedule_event($timestamp, Simple_SMTP_Constants::SCHEDULER_EVENT_NAME);
    }
}
