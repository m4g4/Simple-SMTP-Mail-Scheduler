<?php

namespace Ssmptms;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

final class Constants {

    // Do not forget to change the header in m4w-smtp-mail-scheduler as well when changing the plugin version.
    public const PLUGIN_VERSION     = '1.11.1';
    public const VERSION     = '1.2';

    /** Queue table */
    public const QUEUE_DB_NAME_OLD = 'simple_smtp_mail_scheduler_queue';
    public const QUEUE_DB_NAME     = 'ssmptms_queue';

    /** Filter rules table */
    public const FILTER_DB_NAME    = 'ssmptms_filters';

    // Plugin
    public const DOMAIN      = 'm4w-smtp-mail-scheduler';
    public const CIPHER      = 'aes-256-cbc';

    public const DB_VERSION     = 'ssmptms_db_version';

    /** Option keys */
    public const PROFILES            = 'ssmptms_profiles';

    public const PROFILE_ACTIVE      = 'ssmptms_profile_active';
    public const EMAILS_PER_UNIT     = 'ssmptms_emails_per_unit';
    public const EMAILS_UNIT         = 'ssmptms_emails_unit';
    public const DISABLE             = 'ssmptms_disable';
    public const ENABLE_SCHEDULER    = 'ssmptms_enable_scheduler';
    
    /** Stored values */
    public const EMAILS_SCHEDULER_LAST_TICK     = 'ssmptms_last_tick';

    public const EMAILS_SCHEDULER_CARRY     = 'ssmptms_carry';
    public const CURRENT_QUEUE_COUNT     = 'ssmptms_current_queue_count';

    // Limits
    public const EMAILS_LOG_MAX_ROWS = 'ssmptms_emails_log_max_rows';
    public const CLEAN_SENT_EMAIL_CONTENT = 'ssmptms_clean_sent_email_content';

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

    public const ALL_STATUSES = ['queued', 'processing', 'sent', 'failed', 'filtered'];

    public static function get_status_text(string $status): string {
        switch ($status) {
            case 'queued': return __('Queued', self::DOMAIN);
            case 'processing': return __('Processing', self::DOMAIN);
            case 'sent': return __('Sent', self::DOMAIN);
            case 'failed': return __('Failed', self::DOMAIN);
            case 'filtered': return __('Filtered', self::DOMAIN);
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
