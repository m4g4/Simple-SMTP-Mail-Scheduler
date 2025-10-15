<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('Simple_SMTP_Mail_Scheduler_Mailer')) {

    class Simple_SMTP_Mail_Scheduler_Mailer {
        /**
         * WPDB table name (with prefix)
         *
         * @var string
         */
        private $table;

        public function __construct() {
            $this->table = simple_smtp_prepare_db_name();

            add_filter('pre_wp_mail', array($this, 'queue_mail_instead_of_sending'), 10, 2);
            add_action('simple_smtp_mail_send_emails_event', array($this, 'cron_send_mails'));
        }

        /**
         * Trigger cron processing (entry point)
         */
        public function cron_send_mails() {
            // The scheduler class is expected to call the provided callback
            // with a number (emails per run). Keep backward-compatible call.
            $scheduler = new Simple_SMTP_Mail_Scheduler(array($this, 'cron_send_mails_tick'));
            $scheduler->tick();
        }

        /**
         * Cron tick worker: send a batch of queued emails.
         *
         * @param int $emails_per_minute
         * @return void
         */
        public function cron_send_mails_tick($emails_per_minute) {
            global $wpdb;

            $max_retries = 3;
            $emails_per_minute = (int) max(1, $emails_per_minute);

            // Prepared query: select queued or retryable failed emails and not testing
            $query = $wpdb->prepare(
                "SELECT * FROM {$this->table}
                 WHERE (status = %s OR (status = %s AND retries < %d))
                 AND testing = %d
                 ORDER BY priority DESC, created_at ASC
                 LIMIT %d",
                'queued',
                'failed',
                $max_retries,
                0, // testing = 0 only send real emails
                $emails_per_minute
            );

            $emails = $wpdb->get_results($query);

            if (empty($emails)) {
                return;
            }

            foreach ($emails as $email) {
                // Validate required fields
                if (empty($email->email_id)) {
                    continue;
                }

                // Try to mark as processing; include status in WHERE to avoid stealing concurrently processed rows
                $updated = $wpdb->update(
                    $this->table,
                    array('status' => 'processing', 'last_attempt_at' => current_time('mysql')),
                    array('email_id' => $email->email_id, 'status' => $email->status),
                    array('%s', '%s'),
                    array('%d', '%s')
                );

                if ($updated === false || $updated === 0) {
                    // Someone else processed it or update failed — skip
                    continue;
                }

                // Send the email
                $send_result = $this->send_email_from_record($email);

                $current_time = current_time('mysql');

                if ($send_result === true) {
                    // success
                    $wpdb->update(
                        $this->table,
                        array(
                            'status'          => 'sent',
                            'last_attempt_at' => $current_time,
                            'error_message'   => null,
                        ),
                        array('email_id' => $email->email_id),
                        array('%s', '%s', '%s'),
                        array('%d')
                    );
                } else {
                    // failure; increase retries
                    $retry_count = isset($email->retries) ? ((int)$email->retries + 1) : 1;
                    $error_msg = is_string($send_result) ? $send_result : 'Unknown error';

                    $wpdb->update(
                        $this->table,
                        array(
                            'status'          => 'failed',
                            'retries'         => $retry_count,
                            'last_attempt_at' => $current_time,
                            'error_message'   => mb_substr($error_msg, 0, 1024), // cap length
                        ),
                        array('email_id' => $email->email_id),
                        array('%s', '%d', '%s', '%s'),
                        array('%d')
                    );
                }
            }

            // prune old log rows if necessary
            $this->remove_exceeding_emails();
        }

        /**
         * Build PHPMailer from DB record and send the mail.
         *
         * Returns true on success, or an error string on failure.
         *
         * @param object $email DB row object
         * @return true|string
         */
        protected function send_email_from_record($email) {
    // Extract and normalize fields
    $to_serialized = isset($email->recipient_email) ? $email->recipient_email : '';
    $to = maybe_unserialize($to_serialized);
    if (!is_array($to)) {
        $to = array_map('trim', (array)$to);
    }

    $subject = isset($email->subject) ? (string)$email->subject : '';
    $message = isset($email->message) ? (string)$email->message : '';
    $headers = isset($email->headers) ? maybe_unserialize($email->headers) : array();
    $attachments = isset($email->attachments) ? maybe_unserialize($email->attachments) : array();

    // Profile settings are JSON encoded in DB
    $profile_settings_raw = isset($email->profile_settings) ? $email->profile_settings : '';
    $profile_settings = json_decode($profile_settings_raw, true);
    if (!is_array($profile_settings)) {
        return 'Invalid SMTP profile settings';
    }

    // Detect HTML content
    $is_html = $this->is_html($headers) ||
               stripos($message, '<html') !== false ||
               stripos($message, '<body') !== false ||
               stripos($message, '<table') !== false;

    try {
        $mailer = $this->prepare_mailer($profile_settings);
        if ($mailer === null) {
            return 'Failed to initialize mailer';
        }

        // Ensure recipients exist
        if (empty($to)) {
            return 'No recipients specified';
        }

        // Add recipients (validating email addresses before adding)
        foreach ($to as $recipient) {
            $recipient = is_array($recipient) ? array_values($recipient)[0] : $recipient;
            $recipient = trim((string)$recipient);
            if (!is_email($recipient)) {
                continue; // skip invalid
            }
            $mailer->addAddress($recipient);
        }

        if (empty($mailer->getToAddresses())) {
            return 'No valid recipient addresses';
        }

        // === Message basics ===
        $mailer->CharSet = 'UTF-8';
        $mailer->Subject = wp_strip_all_tags($subject);
        $mailer->Body    = $message;
        $mailer->AltBody = wp_strip_all_tags($message); // plain-text fallback
        $mailer->isHTML((bool)$is_html);                // sets Content-Type correctly
        $mailer->XMailer = '';                          // hide PHPMailer signature

        // === Process headers ===
        if (!empty($headers)) {
            if (is_string($headers)) {
                $headers = array_filter(array_map('trim', preg_split("/\r\n|\n|\r/", $headers)));
            }

            // Skip headers that must be unique and are already set by PHPMailer
            $skip = ['from', 'content-type', 'mime-version', 'to', 'subject'];

            if ($this->is_assoc_array($headers)) {
                foreach ($headers as $hname => $hvalue) {
                    $hname_l = strtolower(trim((string)$hname));
                    $hvalue = trim((string)$hvalue);

                    if (in_array($hname_l, $skip, true)) {
                        continue; // <-- important: skip duplicate content-type etc.
                    }

                    if ($hname_l === 'reply-to') {
                        $mailer->addReplyTo($hvalue);
                    } elseif ($hname_l === 'cc') {
                        foreach (array_map('trim', explode(',', $hvalue)) as $cc) {
                            if (is_email($cc)) {
                                $mailer->addCC($cc);
                            }
                        }
                    } elseif ($hname_l === 'bcc') {
                        foreach (array_map('trim', explode(',', $hvalue)) as $bcc) {
                            if (is_email($bcc)) {
                                $mailer->addBCC($bcc);
                            }
                        }
                    } else {
                        $mailer->addCustomHeader($hname . ': ' . $hvalue);
                    }
                }
            } else {
                // numeric-indexed header lines
                foreach ($headers as $header_line) {
                    $header_line = trim($header_line);
                    if ($header_line === '') continue;

                    $header_lower = strtolower($header_line);
                    if (preg_match('/^(from|content-type|mime-version|to|subject):/i', $header_lower)) {
                        continue; // skip these to avoid duplicates
                    }

                    if (stripos($header_line, 'reply-to:') === 0) {
                        $addr = trim(substr($header_line, 9));
                        if (is_email($addr)) {
                            $mailer->addReplyTo($addr);
                        }
                    } elseif (stripos($header_line, 'cc:') === 0) {
                        foreach (array_map('trim', explode(',', substr($header_line, 3))) as $cc) {
                            if (is_email($cc)) {
                                $mailer->addCC($cc);
                            }
                        }
                    } elseif (stripos($header_line, 'bcc:') === 0) {
                        foreach (array_map('trim', explode(',', substr($header_line, 4))) as $bcc) {
                            if (is_email($bcc)) {
                                $mailer->addBCC($bcc);
                            }
                        }
                    } else {
                        $mailer->addCustomHeader($header_line);
                    }
                }
            }
        }

        // === Attachments ===
        if (!empty($attachments) && is_array($attachments)) {
            foreach ($attachments as $attachment) {
                $attachment = (string)$attachment;
                if (!empty($attachment) && file_exists($attachment)) {
                    $mailer->addAttachment($attachment);
                }
            }
        }

        // === Send ===
        return $mailer->send() ? true : 'Failed to send (unknown PHPMailer failure)';

    } catch (\PHPMailer\PHPMailer\Exception $e) {
        return 'PHPMailer error: ' . $e->getMessage();
    } catch (\Exception $e) {
        return 'Mailer exception: ' . $e->getMessage();
    }
}


        /**
         * Intercepts wp_mail() and queues the message instead of sending.
         * Return null to let WordPress send the message; return true/false to short-circuit.
         *
         * @param null|bool $pre_wp_mail
         * @param array     $atts
         * @return null|bool
         */
        public function queue_mail_instead_of_sending($pre_wp_mail, $atts) {
            // Respect a bypass constant to allow immediate sending
            if (defined('SMTP_MAIL_BYPASS_QUEUE') && SMTP_MAIL_BYPASS_QUEUE === true) {
                return null;
            }

            // Normalize arguments coming from wp_mail
            $to = isset($atts['to']) ? $atts['to'] : '';
            $subject = isset($atts['subject']) ? $atts['subject'] : '';
            $message = isset($atts['message']) ? $atts['message'] : '';
            $headers = isset($atts['headers']) ? $atts['headers'] : array();
            $attachments = isset($atts['attachments']) ? $atts['attachments'] : array();

            // Convert recipients to an array of email strings
            $recipients = (array) $to;
            $parsed_recipients = array();
            foreach ($recipients as $r) {
                // parse "Name <email@example.com>" or "email@example.com"
                if (is_string($r)) {
                    list($email, $name) = $this->parseAddress($r);
                    if (is_email($email)) {
                        $parsed_recipients[] = $email;
                    }
                }
            }

            if (empty($parsed_recipients)) {
                // Nothing to queue; let WP handle (or fail later)
                return null;
            }

            // Determine testing flag from options
            $testing_flag = (int) get_option(Simple_SMTP_Constants::EMAILS_TESTING, 0);

            // Queue
            $queued = $this->mail_enqueue_email($parsed_recipients, $subject, $message, $headers, $attachments, $testing_flag);

            // If enqueue succeeded, short-circuit wp_mail (return true)
            return $queued ? true : null;
        }

        /**
         * Insert email into queue table
         *
         * @param array|string $to
         * @param string $subject
         * @param string $message
         * @param array|string $headers
         * @param array $attachments
         * @param int $testing
         * @return bool
         */
        public function mail_enqueue_email($to, $subject, $message, $headers, $attachments, $testing = 0) {
            global $wpdb;

            $active_profile = function_exists('simple_smtp_get_active_profile') ? simple_smtp_get_active_profile() : null;
            if (empty($active_profile) || !is_array($active_profile)) {
                error_log('Simple SMTP Mail Scheduler: No valid SMTP profile available for email queuing.');
                return false;
            }

            $data = array(
                'recipient_email' => maybe_serialize($to),
                'subject'         => $subject,
                'message'         => $message,
                'headers'         => maybe_serialize($headers),
                'attachments'     => maybe_serialize($attachments),
                'scheduled_at'    => current_time('mysql'),
                'profile_settings'=> wp_json_encode($active_profile),
                'status'          => 'queued',
                'created_at'      => current_time('mysql'),
                'testing'         => (int) $testing,
            );

            $formats = array('%s','%s','%s','%s','%s','%s','%s','%s','%d');

            $inserted = $wpdb->insert($this->table, $data, $formats);

            // After inserting, prune if necessary
            if ($inserted !== false) {
                $this->remove_exceeding_emails();
                return true;
            }

            error_log('Simple SMTP Mail Scheduler: Failed to insert queued email. DB error: ' . $wpdb->last_error);
            return false;
        }

        /**
         * Initialize PHPMailer using profile settings array.
         *
         * @param array $profile
         * @return \PHPMailer\PHPMailer\PHPMailer|null
         */
        public function prepare_mailer($profile) {
            if (empty($profile) || !is_array($profile)) {
                return null;
            }

            try {
                $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);

                $mailer->isSMTP();
                $mailer->Host       = isset($profile['host']) ? (string)$profile['host'] : '';
                $mailer->Port       = isset($profile['port']) ? (int)$profile['port'] : 25;
                $mailer->From       = isset($profile['from_email']) ? (string)$profile['from_email'] : '';
                $mailer->FromName   = isset($profile['from_name']) ? (string)$profile['from_name'] : '';
                $mailer->SMTPAuth   = true;
                $mailer->Username   = isset($profile['username']) ? (string)$profile['username'] : '';
                $mailer->Password   = isset($profile['password']) ? simple_smtp_mail_decrypt_password($profile['password']) : '';
                $mailer->Timeout    = 10;
                $mailer->SMTPAutoTLS = !empty($profile['autotls']);
                $encryption = isset($profile['encryption']) ? strtolower((string)$profile['encryption']) : '';
                $mailer->SMTPSecure = in_array($encryption, array('ssl','tls'), true) ? $encryption : '';

                // Optional: allow plugins to further configure mailer
                /**
                 * Filter: simple_smtp_mail_configure_mailer
                 * @param \PHPMailer\PHPMailer\PHPMailer $mailer
                 * @param array $profile
                 */
                $mailer = apply_filters('simple_smtp_mail_configure_mailer', $mailer, $profile);

                return $mailer;
            } catch (\Exception $e) {
                error_log('Simple SMTP Mail Scheduler: prepare_mailer exception: ' . $e->getMessage());
                return null;
            }
        }

        /**
         * Remove oldest rows when exceeding the configured maximum.
         *
         * @return void
         */
        private function remove_exceeding_emails() {
            global $wpdb;

            $limit = (int) Simple_SMTP_Constants::EMAILS_LOG_MAX_ROWS;

            $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->table}"));

            if ($total <= $limit) {
                return;
            }

            $to_delete = $total - $limit;

            // Delete oldest sent/failed rows (use subquery to target rows by ID to be safe)
            $sql = $wpdb->prepare(
                "DELETE FROM {$this->table}
                 WHERE email_id IN (
                     SELECT email_id FROM (
                         SELECT email_id FROM {$this->table}
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

        /**
         * Detect if the content-type header indicates HTML
         *
         * @param mixed $headers
         * @return bool
         */
        private function is_html($headers) {
            if (!empty($headers) && is_array($headers)) {
                foreach ($headers as $hname => $hvalue) {
                    // headers might be numeric lines or associative array
                    if (is_string($hname) && stripos($hname, 'content-type') !== false) {
                        if (stripos($hvalue, 'text/html') !== false) {
                            return true;
                        }
                    } elseif (is_string($hvalue) && stripos($hvalue, 'content-type:') !== false) {
                        if (stripos($hvalue, 'text/html') !== false) {
                            return true;
                        }
                    }
                }
            }

            $content_type = apply_filters('wp_mail_content_type', 'text/plain');
            if (stripos($content_type, 'text/html') !== false) {
                return true;
            }

            return false;
        }

        /**
         * Parse "Name <email@domain>" or "email@domain" -> returns [email, name]
         *
         * @param string $address
         * @return array [string $email, string $name]
         */
        private function parseAddress($address) {
            $address = (string)$address;
            if (preg_match('/^(.*)<(.+?)>$/', trim($address), $matches)) {
                $name = trim(trim($matches[1]), "\"' ");
                $email = trim($matches[2]);
            } else {
                $name = '';
                $email = trim($address);
            }
            return array($email, $name);
        }

        /**
         * Helper: check if array is associative
         *
         * @param array $arr
         * @return bool
         */
        private function is_assoc_array($arr) {
            if (!is_array($arr)) {
                return false;
            }
            return array_keys($arr) !== range(0, count($arr) - 1);
        }
    }
}

$simple_smtp_mail_scheduler_mailer = new Simple_SMTP_Mail_Scheduler_Mailer();
