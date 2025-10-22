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
            // Use microtime for sub-second accuracy
            $now = microtime(true);

            $lastTime  = (float) get_option(Simple_SMTP_Constants::EMAILS_SCHEDULER_LAST_TICK, $now);
            $carry     = (float) get_option( Simple_SMTP_Constants::EMAILS_SCHEDULER_CARRY, 0.0);
            
            // How many seconds since last run
            $elapsed = max(0.1, $now - $lastTime); // never 0, avoid divide-by-zero
            update_option(Simple_SMTP_Constants::EMAILS_SCHEDULER_LAST_TICK, $now, false);
                
            // Convert your rate (emails per minute) into per-second rate
            $emailsPerSecond = $this->rate / 60.0;
                
            // Total "ideal" emails to send since last tick
            $emailsExact = ($emailsPerSecond * $elapsed) + $carry;
                
            // Integer number of emails to send this tick
            $toSend = (int) floor($emailsExact);
                
            $carry = $emailsExact - $toSend;
                
            if ($toSend > 0 && is_callable($this->callback)) {
                try {
                    ($this->callback)($toSend);
                } catch (\Throwable $e) {
                    error_log('Simple SMTP tick() error: ' . $e->getMessage());
                }
            }
            
            update_option( Simple_SMTP_Constants::EMAILS_SCHEDULER_CARRY, $carry, false );
        
            if (!Simple_SMTP_Email_Queue::get_instance()->has_email_entries_for_sending()) {
                error_log('tick: Unschedule Cron event');
                simple_stmp_unschedule_cron_event();
                delete_option(Simple_SMTP_Constants::EMAILS_SCHEDULER_LAST_TICK);
                delete_option(Simple_SMTP_Constants::EMAILS_SCHEDULER_CARRY);
            }
        }
    }
}

function simple_stmp_schedule_cron_event() {
    error_log('Tried to schedule cron. Is already scheduled? ' . print_r(wp_next_scheduled(Simple_SMTP_Constants::SCHEDULER_EVENT_NAME), true));
    if (!wp_next_scheduled(Simple_SMTP_Constants::SCHEDULER_EVENT_NAME)) {
        error_log('Scheduling cron...');
        wp_schedule_event(time(), 'minute', Simple_SMTP_Constants::SCHEDULER_EVENT_NAME);
    }
}

function simple_stmp_unschedule_cron_event() {
    $timestamp = wp_next_scheduled(Simple_SMTP_Constants::SCHEDULER_EVENT_NAME);
    error_log('Tried to unschedule cron. Is already scheduled? ' . print_r($timestamp));
    if ($timestamp) {
        error_log('Unscheduling cron...');
        wp_unschedule_event($timestamp, Simple_SMTP_Constants::SCHEDULER_EVENT_NAME);
    }
}
