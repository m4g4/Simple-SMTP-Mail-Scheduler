<?php
namespace Ssmptms;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if (!class_exists('Email_Queue')) {

    /**
     * Handles database operations for the SMTP Mail Scheduler email queue.
     */
    class Email_Queue {
        private static $instance;
        private $table_name;

        public function __construct() {
            global $wpdb;
            $this->table_name = $wpdb->prefix . Constants::QUEUE_DB_NAME;
        }

        public static function get_instance() {
		    if ( null === self::$instance ) {
			    self::$instance = new self();

                // FIX
                self::$instance->db_update_fix_created_at();
		    }

		    return self::$instance;
	    }

        public function create_table() {
            global $wpdb;

            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE $this->table_name (
                email_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                recipient_email VARCHAR(255) NOT NULL,
                subject TEXT NOT NULL,
                message LONGTEXT NOT NULL,
                headers LONGTEXT DEFAULT NULL,
                attachments LONGTEXT DEFAULT NULL,
                scheduled_at DATETIME NOT NULL,
                profile_settings LONGTEXT DEFAULT NULL,
                priority INT DEFAULT 0,
                status ENUM('queued', 'processing', 'sent', 'failed') DEFAULT 'queued',
                retries INT UNSIGNED DEFAULT 0,
                last_attempt_at DATETIME DEFAULT NULL,
                error_message TEXT DEFAULT NULL,
                testing TINYINT(1) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (email_id)
            ) $charset_collate;";

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($sql);
        }

        public function drop_table() {
            global $wpdb;
            $wpdb->query("DROP TABLE IF EXISTS $this->table_name");
        }

        /**
         * Check if there are entries (queued or failed emails) in the database.
         *
         * @return bool True if there are queued or failed emails, false otherwise.
         */
        public function has_email_entries_for_sending() {
            $count = $this->get_email_entry_count_for_sending();
            return (int) $count > 0;
        }

        public function get_email_entry_count_for_sending() {
            global $wpdb;
            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $this->table_name
                    WHERE (status = %s OR (status = %s AND retries < %d))",
                    'queued',
                    'failed',
                    Constants::MAX_EMAIL_RETRIES
                )
            );
        }

        public function get_profile_labels() {
            global $wpdb;
            return $wpdb->get_col(
            "SELECT DISTINCT JSON_UNQUOTE(JSON_EXTRACT(profile_settings, '$.label')) AS label FROM $this->table_name WHERE profile_settings IS NOT NULL ORDER BY label"
            );
        }

        public function queue_email($recipient, $subject, $message, $headers = [], $attachments = [], $scheduled_at = null, $active_profile = null, $testing = false) {
            global $wpdb;

            $data = array(
                'recipient_email' => maybe_serialize($recipient),
                'subject'         => $subject,
                'message'         => $message,
                'headers'         => maybe_serialize($headers),
                'attachments'     => maybe_serialize($attachments),
                'scheduled_at'    => $scheduled_at,
                'profile_settings'=> wp_json_encode($active_profile),
                'status'          => 'queued',
                'testing'         => (int) $testing,
            );

            $formats = array('%s','%s','%s','%s','%s','%s','%s','%s','%d');

            $result = $wpdb->insert($this->table_name, $data, $formats);

            if (false === $result) {
                error_log('Simple SMTP Mail Scheduler: Failed to insert queued email. DB error: ' . $wpdb->last_error);
            }

            return $result;
        }

        public function get_emails_to_process($emails_per_minute) {
            global $wpdb;
            // Prepared query: select queued or retryable failed emails and not testing
            $query = $wpdb->prepare(
                "SELECT * FROM {$this->table_name}
                 WHERE (status = %s OR (status = %s AND retries < %d))
                 AND testing = %d
                 ORDER BY priority DESC, created_at ASC
                 LIMIT %d",
                'queued',
                'failed',
                Constants::MAX_EMAIL_RETRIES,
                0, // testing = 0 only send real emails
                $emails_per_minute
            );

            return $wpdb->get_results($query);
        }

        public function get_emails($per_page, $offset, $orderby, $order = 'asc', $status = '', $profile = '', $search = '') {
            global $wpdb;

            $orderby_sql = ($orderby === 'profile_settings')
                ? "JSON_UNQUOTE(JSON_EXTRACT(profile_settings, '$.label')) $order"
                : "$orderby $order";

            // Handle filters
            $where_clauses = [];
            $query_params = [];

            // Status filter
            if ($status && in_array($status, Constants::ALL_STATUSES)) {
                $where_clauses[] = 'status = %s';
                $query_params[] = $status;
            }

            // Profile filter
            if ($profile) {
                $where_clauses[] = "JSON_UNQUOTE(JSON_EXTRACT(profile_settings, '$.label')) = %s";
                $query_params[] = $profile;
            }

            // Build WHERE clause
            $where_sql = '';
            if (!empty($where_clauses)) {
                $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
            }

            if (!empty($search)) {
                $like = '%' . $wpdb->esc_like($search) . '%';
                
                if (empty($where_sql))
                    $where_sql = 'WHERE 1=1';

                $where_sql .= $wpdb->prepare(" AND (subject LIKE %s OR recipient_email LIKE %s)", $like, $like);
            }

            // Prepare the main query
            $query = "SELECT * FROM $this->table_name $where_sql ORDER BY $orderby_sql LIMIT %d OFFSET %d";
            $query_params[] = $per_page;
            $query_params[] = $offset;

            $result = $wpdb->get_results(
                $wpdb->prepare($query, $query_params)
            );

            // Get total items for pagination (with filters applied)
            $total_items = (int) $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM $this->table_name $where_sql", array_slice($query_params, 0, -2))
            );

            return [$result, $total_items];
        }

        public function get_emails_by_ids($email_ids) {
            global $wpdb;

            $placeholders = implode(',', array_fill(0, count($email_ids), '%d'));
            return $wpdb->get_results(
                $wpdb->prepare("SELECT * FROM $this->table_name WHERE email_id IN ($placeholders)", $email_ids)
            );
        }

        public function get_email_by_id($email_id) {
            $emails = $this->get_emails_by_ids([$email_id]);
            if (empty($emails) || count($emails) !== 1) {
                return null;
            }

            return $emails[0];
        }

        public function get_day_emails_grouped_by_hour($date) {
            global $wpdb;
                
            $dt = new \DateTime($date);
            $start = $dt->format('Y-m-d 00:00:00');
            $end   = $dt->format('Y-m-d 23:59:59');
                
            $sql = $wpdb->prepare("
                SELECT DATE_FORMAT(last_attempt_at, '%%Y-%%m-%%d %%H:00:00') AS time_slot, 
                       COUNT(*) AS count
                FROM {$this->table_name}
                WHERE status = 'sent'
                  AND last_attempt_at BETWEEN %s AND %s
                GROUP BY time_slot
                ORDER BY time_slot ASC
            ", $start, $end);
                
            return $wpdb->get_results($sql);
        }


        public function get_status_data_by_date($date) {
            global $wpdb;

            $dt = new \DateTime($date);
            $start = $dt->format('Y-m-d 00:00:00');
            $end   = $dt->format('Y-m-d 23:59:59');

            $sql = $wpdb->prepare(
                "SELECT status, COUNT(*) as count
                 FROM {$this->table_name}
                 WHERE created_at BETWEEN %s AND %s
                 GROUP BY status",
                $start, $end
            );
        
            return $wpdb->get_results($sql);
        }


        public function retry_sending_email($email_id) {
            global $wpdb;

            $wpdb->update(
                $this->table_name,
                ['status' => 'queued', 'retries' => 0],
                ['email_id' => $email_id],
                ['%s', '%d'],
                ['%d']
            );
        }

        public function retry_sending_emails($email_ids) {
            global $wpdb;

            $wpdb->query("UPDATE $this->table_name SET status = 'queued', retries = 0 WHERE email_id IN (" . implode(',', $email_ids) . ")");
        }

        public function delete_email($email_id) {
            global $wpdb;

            $wpdb->delete($this->table_name, ['email_id' => $email_id], ['%d']);
        }

        public function delete_emails($email_ids) {
            global $wpdb;

            $wpdb->query("DELETE FROM $this->table_name WHERE email_id IN (" . implode(',', $email_ids) . ")");
        }

        public function prioritize_email($email_id) {
            global $wpdb;

            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE $this->table_name SET priority = priority + 1 WHERE email_id = %d",
                    $email_id
                )
            );
        }

        public function prioritize_emails($email_ids) {
            global $wpdb;
            $wpdb->query("UPDATE $this->table_name SET priority = priority + 1 WHERE email_id IN (" . implode(',', $email_ids) . ")");
        }

        public function update_email_status($email_id, $status) {
            global $wpdb;

            return $wpdb->update(
                $this->table_name,
                array('status' => 'processing', 'last_attempt_at' => current_time('mysql')),
                array('email_id' => $email_id, 'status' => $status),
                array('%s', '%s'),
                array('%d', '%s')
            );
        }

        public function update_email_sent($email_id, $last_attempt_at) {
            global $wpdb;

            return $wpdb->update(
                $this->table_name,
                array(
                    'status'          => 'sent',
                    'last_attempt_at' => $last_attempt_at,
                    'error_message'   => null,
                ),
                array('email_id' => $email_id),
                array('%s', '%s', '%s'),
                array('%d')
            );
        }

        public function update_email_failed($email_id, $last_attempt_at, $retry_count, $error_msg) {
            global $wpdb;

            return $wpdb->update(
                $this->table_name,
                array(
                    'status'          => 'failed',
                    'retries'         => $retry_count,
                    'last_attempt_at' => $last_attempt_at,
                    'error_message'   => mb_substr($error_msg, 0, 1024), // cap length
                ),
                array('email_id' => $email_id),
                array('%s', '%d', '%s', '%s'),
                array('%d')
            );
        }

        public function get_total_emails() {
            global $wpdb;
            return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->table_name}"));
        }

        public function remove_exceeding_emails($to_delete) {
            global $wpdb;

            $sql = $wpdb->prepare(
                    "DELETE FROM {$this->table_name}
                     WHERE email_id IN (
                         SELECT email_id FROM (
                             SELECT email_id FROM {$this->table_name}
                             WHERE status IN (%s, %s)
                             ORDER BY created_at ASC
                             LIMIT %d
                         ) AS t
                     )",
                    'sent',
                    'failed',
                    $to_delete
                );

            $wpdb->query($sql);
        }

        public function db_update_fix_created_at() {
            global $wpdb;

            // Only run once – ever
            if (get_option('ssmptms_created_at_fixed_v2') === 'yes') {
                return;
            }
        
            $updated = $wpdb->query("
                UPDATE `{$this->table_name}`
                SET created_at = CASE
                    WHEN scheduled_at IS NOT NULL 
                         AND scheduled_at != '0000-00-00 00:00:00' 
                    THEN scheduled_at

                    WHEN scheduled_at = '0000-00-00 00:00:00' 
                         OR scheduled_at IS NULL 
                    THEN '2025-01-01 00:00:00'   -- fallback date (or use NOW() if you prefer)

                    ELSE created_at
                END
                WHERE created_at = '0000-00-00 00:00:00'
                   OR created_at IS NULL
            ");
        
            if ($updated !== false) {
                error_log("Simple SMTP Mail Scheduler: Fixed {$updated} rows with broken created_at");
            }
        
            update_option('ssmptms_created_at_fixed_v2', 'yes');
        }
    }
}

?>
