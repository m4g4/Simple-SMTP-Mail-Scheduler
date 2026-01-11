<?php

namespace Ssmptms;

// TODO remove me!
function migrate() {

    // If already migrated, stop
    if (!get_option(Constants::DB_VERSION_OLD, false)) {
        return;
    }

    // Map old option => new option
    $option_map = [
        Constants::PROFILES_OLD
            => Constants::PROFILES,

        Constants::PROFILE_ACTIVE_OLD
            => Constants::PROFILE_ACTIVE,

        Constants::EMAILS_PER_UNIT_OLD
            => Constants::EMAILS_PER_UNIT,

        Constants::EMAILS_UNIT_OLD
            => Constants::EMAILS_UNIT,

        Constants::DISABLE_OLD
            => Constants::DISABLE,

        Constants::EMAILS_SCHEDULER_LAST_TICK_OLD
            => Constants::EMAILS_SCHEDULER_LAST_TICK,

        Constants::EMAILS_SCHEDULER_CARRY_OLD
            => Constants::EMAILS_SCHEDULER_CARRY,

        Constants::CURRENT_QUEUE_COUNT_OLD
            => Constants::CURRENT_QUEUE_COUNT,
    ];

    // Migrate options
    foreach ($option_map as $old_key => $new_key) {
        if (get_option($new_key, null) === null) {
            $value = get_option($old_key, null);
            if ($value !== null) {
                update_option($new_key, $value);
                delete_option($old_key);
            }
        }
    }

    // Migrate DB version option
    $old_version = get_option(Constants::DB_VERSION_OLD);
    if ($old_version !== false) {
        update_option(Constants::DB_VERSION, $old_version);
        delete_option(Constants::DB_VERSION_OLD);
    } else {
        update_option(Constants::DB_VERSION, '1.0.0');
    }

    migrate_queue_table();
}

/**
 * Safely migrate scheduler queue table
 */
function migrate_queue_table() {
    global $wpdb;

    $old_table = $wpdb->prefix . Constants::QUEUE_DB_NAME_OLD;
    $new_table = $wpdb->prefix . Constants::QUEUE_DB_NAME;

    $old_exists = ($wpdb->get_var(
        $wpdb->prepare("SHOW TABLES LIKE %s", $old_table)
    ) === $old_table);

    $new_exists = ($wpdb->get_var(
        $wpdb->prepare("SHOW TABLES LIKE %s", $new_table)
    ) === $new_table);

    if ($old_exists && !$new_exists) {
        $wpdb->query("RENAME TABLE {$old_table} TO {$new_table}");
        return;
    }

    if ($old_exists && $new_exists) {

        $columns = $wpdb->get_col("DESCRIBE {$new_table}", 0);
        $column_list = implode(',', array_map(fn($c) => "`{$c}`", $columns));

        $wpdb->query("
            INSERT IGNORE INTO {$new_table} ({$column_list})
            SELECT {$column_list}
            FROM {$old_table}
        ");

        // Drop old table after successful merge
        $wpdb->query("DROP TABLE {$old_table}");
        return;
    }
}
