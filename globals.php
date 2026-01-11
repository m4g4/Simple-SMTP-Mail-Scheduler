<?php

namespace Ssmptms;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

final class Constants {

    public const PLUGIN_VERSION     = '1.7.0';
    public const VERSION     = '1.2';

    /** Queue table */
    public const QUEUE_DB_NAME_OLD = 'simple_smtp_mail_scheduler_queue';
    public const QUEUE_DB_NAME     = 'ssmptms_queue';

    // Plugin
    public const DOMAIN      = 'wo-smtp-mail-scheduler';
    public const CIPHER      = 'aes-256-cbc';

    /** DB version for migration control */
    public const DB_VERSION_OLD = 'simple_smtp_mail_db_version';
    public const DB_VERSION     = 'ssmptms_db_version';

    /** Option keys */
    public const PROFILES_OLD        = 'simple_smtp_mail_scheduler_profiles';
    public const PROFILES            = 'ssmptms_profiles';

    public const PROFILE_ACTIVE_OLD  = 'simple_smtp_mail_scheduler_profile_active';
    public const PROFILE_ACTIVE      = 'ssmptms_profile_active';

    public const EMAILS_PER_UNIT_OLD = 'simple_smtp_mail_scheduler_emails_per_unit';
    public const EMAILS_PER_UNIT     = 'ssmptms_emails_per_unit';

    public const EMAILS_UNIT_OLD     = 'simple_smtp_mail_scheduler_emails_unit';
    public const EMAILS_UNIT         = 'ssmptms_emails_unit';

    public const DISABLE_OLD         = 'simple_smtp_mail_scheduler_disable';
    public const DISABLE             = 'ssmptms_disable';
    
    /** Stored values */
    public const EMAILS_SCHEDULER_LAST_TICK_OLD = 'simple_smtp_mail_scheduler_last_tick';
    public const EMAILS_SCHEDULER_LAST_TICK     = 'ssmptms_last_tick';

    public const EMAILS_SCHEDULER_CARRY_OLD = 'simple_smtp_mail_scheduler_carry';
    public const EMAILS_SCHEDULER_CARRY     = 'ssmptms_carry';

    public const CURRENT_QUEUE_COUNT_OLD = 'simple_smtp_mail_scheduler_current_queue_count';
    public const CURRENT_QUEUE_COUNT     = 'ssmptms_current_queue_count';

    // Limits
    public const EMAILS_LOG_MAX_ROWS = 100000;

    public const MAX_EMAIL_RETRIES = 3;

    // Admin pages
    public const SETTINGS_PAGE = 'ssmptms_settings';
    public const PROFILE_EDIT_PAGE = 'ssmptms_profile_edit';

    // Admin page sections
    public const SECTION_BASIC = 'basic';
    public const SETTINGS_SECTION_BASIC = 'ssmptms_settings_' . self::SECTION_BASIC;

    public const SECTION_SCHEDULER = 'scheduler';
    public const SETTINGS_SECTION_SCHEDULER = 'ssmptms_settings_' . self::SECTION_SCHEDULER;

    // Option groups
    public const GENERAL_OPTION_GROUP = 'ssmptms_option_group';

    // Scheduler
    public const SCHEDULER_EVENT_NAME = 'ssmptms_send_emails_event';

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
