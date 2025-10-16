<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Centralized constants for Simple SMTP Mail Scheduler
 */
final class Simple_SMTP_Constants {

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
    public const EMAILS_TESTING   = 'simple_smtp_mail_scheduler_testing';
    public const EMAILS_SCHEDULER_CARRY = 'simple_smtp_mail_scheduler_carry';

    // Limits
    public const EMAILS_LOG_MAX_ROWS = 100000;

    public const MAX_EMAIL_RETRIES = 3;

    // Admin pages
    public const SETTINGS_PAGE = 'simple_smtp_mail_scheduler_settings';
    public const PROFILE_EDIT_PAGE = 'simple_smtp_mail_profile_edit';

    // Scheduler
    public const SCHEDULER_EVENT_NAME = 'simple_smtp_mail_send_emails_event';

    public const ALL_STATUSES = ['queued', 'processing', 'sent', 'failed'];
}
