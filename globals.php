<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Centralized constants for Simple SMTP Mail Scheduler
 */
final class Simple_SMTP_Constants {

    public const PLUGIN_VERSION     = '1.5.0';
    public const VERSION     = '1.2';

    // Database
    public const QUEUE_DB_NAME     = 'simple_smtp_mail_scheduler_queue';

    // Plugin
    public const DOMAIN      = 'simple-smtp-mail-scheduler';
    public const CIPHER      = 'aes-256-cbc';

    // Option keys
    public const DB_VERSION  = 'simple_smtp_mail_db_version';
    public const PROFILES         = 'simple_smtp_mail_scheduler_profiles';
    public const PROFILE_ACTIVE   = 'simple_smtp_mail_scheduler_profile_active';
    public const EMAILS_PER_UNIT  = 'simple_smtp_mail_scheduler_emails_per_unit';
    public const EMAILS_UNIT      = 'simple_smtp_mail_scheduler_emails_unit';
    public const DISABLE      = 'simple_smtp_mail_scheduler_disable';
    
    // Stored values
    public const EMAILS_SCHEDULER_LAST_TICK = 'simple_smtp_mail_scheduler_last_tick';
    public const EMAILS_SCHEDULER_CARRY = 'simple_smtp_mail_scheduler_carry';
    public const CURRENT_QUEUE_COUNT   = 'simple_smtp_mail_scheduler_current_queue_count';

    // Limits
    public const EMAILS_LOG_MAX_ROWS = 100000;

    public const MAX_EMAIL_RETRIES = 3;

    // Admin pages
    public const SETTINGS_PAGE = 'simple_smtp_mail_scheduler_settings';
    public const PROFILE_EDIT_PAGE = 'simple_smtp_mail_profile_edit';

    // Admin page sections
    public const SECTION_BASIC = 'basic';
    public const SETTINGS_SECTION_BASIC = 'simple_smtp_mail_scheduler_settings_' . self::SECTION_BASIC;

    public const SECTION_SCHEDULER = 'scheduler';
    public const SETTINGS_SECTION_SCHEDULER = 'simple_smtp_mail_scheduler_settings_' . self::SECTION_SCHEDULER;

    // Option groups
    public const GENERAL_OPTION_GROUP = 'simple_smtp_mail_option_group';

    // Scheduler
    public const SCHEDULER_EVENT_NAME = 'simple_smtp_mail_send_emails_event';

    public const ALL_STATUSES = ['queued', 'processing', 'sent', 'failed'];

    public static function get_status_text(string $status): string {
        switch ($status) {
            case 'queued': return __('Queued', self::DOMAIN);
            case 'processing': return __('Processing', self::DOMAIN);
            case 'sent': return __('Sent', self::DOMAIN);
            case 'failed': return __('Failed', self::DOMAIN);
            default: return '';
        }
    }

    public const UNITS = [ 'minute', 'hour', 'day' ];

    public static function get_unit_text(string $status): string {
        switch ($status) {
            case 'minute': return __('per Minute', self::DOMAIN);
            case 'hour': return __('per Hour', self::DOMAIN);
            case 'day': return __('per Day', self::DOMAIN);
            default: return '';
        }
    }
}
