<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('Simple_SMTP_Mail_Scheduler_Mailer')) {

    class Simple_SMTP_Mail_Scheduler_Mailer {
        private static $instance;
        public function __construct() {
            add_filter('pre_wp_mail', array($this, 'queue_mail_instead_of_sending'), 10, 2);
            add_action(Simple_SMTP_Constants::SCHEDULER_EVENT_NAME, array($this, 'cron_send_mails'));
        }

        public static function get_instance() {
		    if ( null === self::$instance ) {
			    self::$instance = new self();
		    }

		    return self::$instance;
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
            $emails_per_minute = (int) max(1, $emails_per_minute);

            $emails = Simple_SMTP_Email_Queue::get_instance()->get_emails_to_process($emails_per_minute);
            if (empty($emails)) {
                return;
            }

            foreach ($emails as $email) {
                // Validate required fields
                if (empty($email->email_id)) {
                    continue;
                }

                $updated = Simple_SMTP_Email_Queue::get_instance()->update_email_status($email->email_id, $email->status);

                if ($updated === false || $updated === 0) {
                    // Someone else processed it or update failed — skip
                    continue;
                }

                // Send the email
                $send_result = $this->send_email_from_record($email);

                $current_time = current_time('mysql');

                if ($send_result === true) {
                    Simple_SMTP_Email_Queue::get_instance()->update_email_sent($email->email_id, $current_time);
                } else {
                    // failure; increase retries
                    $retry_count = isset($email->retries) ? ((int)$email->retries + 1) : 1;
                    $error_msg = is_string($send_result) ? $send_result : 'Unknown error';

                    Simple_SMTP_Email_Queue::get_instance()->update_email_failed($email->email_id, $current_time, $retry_count, $error_msg);
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
                    $h = $this->process_headers($headers);
                                
                    // Reply-To
                    if (!empty($h['reply_to']) && is_email($h['reply_to'])) {
                        $mailer->addReplyTo($h['reply_to']);
                    }
                
                    // CC
                    if (!empty($h['cc'])) {
                        $ccs = (array) $h['cc'];
                        foreach ($ccs as $cc) {
                            if (is_email($cc)) {
                                $mailer->addCC($cc);
                            }
                        }
                    }
                
                    // BCC
                    if (!empty($h['bcc'])) {
                        $bccs = (array) $h['bcc'];
                        foreach ($bccs as $bcc) {
                            if (is_email($bcc)) {
                                $mailer->addBCC($bcc);
                            }
                        }
                    }
                
                    // Custom headers
                    if (!empty($h['custom_header'])) {
                        $custom_headers = (array) $h['custom_header'];
                        foreach ($custom_headers as $header_line) {
                            $mailer->addCustomHeader($header_line);
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

            error_log('Email scheduled ' . print_r($queued, true) . '| Has emails: ' . print_r(Simple_SMTP_Email_Queue::get_instance()->has_email_entries_for_sending(), true));
            //if ($queued && Simple_SMTP_Email_Queue::get_instance()->has_email_entries_for_sending()) {
                simple_stmp_schedule_cron_event();
            //}

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
            $profile = null;
            $from = $this->parse_from_header($headers);
                
            // Try to match profile by 'From' email
            if (!empty($from['email'])) {
                $profile = simple_smtp_get_profile_by_mail($from['email']);
            
                // Override "from_name" if provided
                if (!empty($from['name']) && is_array($profile)) {
                    $profile['from_name'] = $from['name'];
                }
            }
        
            // Fallback: use active profile
            if (empty($profile) || !is_array($profile)) {
                if (function_exists('simple_smtp_get_active_profile')) {
                    $profile = simple_smtp_get_active_profile();
                }
                if (empty($profile) || !is_array($profile)) {
                    error_log('Simple SMTP Mail Scheduler: No valid SMTP profile available for email queuing.');
                    return false;
                }
            }
        
            // Queue the email
            $inserted = Simple_SMTP_Email_Queue::get_instance()->queue_email(
                $to,
                $subject,
                $message,
                $headers,
                $attachments,
                current_time('mysql'),
                $profile,
                $testing
            );
        
            // After inserting, prune if necessary
            if ($inserted !== false) {
                $this->remove_exceeding_emails();
                return true;
            }
        
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
            $limit = (int) Simple_SMTP_Constants::EMAILS_LOG_MAX_ROWS;

            $total = Simple_SMTP_Email_Queue::get_instance()->get_total_emails();

            if ($total <= $limit) {
                return;
            }

            $to_delete = $total - $limit;

            Simple_SMTP_Email_Queue::get_instance()->remove_exceeding_emails($to_delete);
        }

        private function process_headers($headers) {
            if (is_string($headers)) {
                $headers = array_filter(array_map('trim', preg_split("/\r\n|\n|\r/", $headers)));
            }
        
            $result = [
                'cc' => [],
                'bcc' => [],
                'custom_header' => [],
            ];
        
            // Skip headers that are managed by PHPMailer directly
            $skip = ['from', 'content-type', 'mime-version', 'to', 'subject'];
        
            if ($this->is_assoc_array($headers)) {
                // Associative array format
                foreach ($headers as $hname => $hvalue) {
                    $hname_l = strtolower(trim((string)$hname));
                    $hvalue = trim((string)$hvalue);
                
                    if (in_array($hname_l, $skip, true)) {
                        continue;
                    }
                
                    switch ($hname_l) {
                        case 'reply-to':
                            if (is_email($hvalue)) {
                                $result['reply_to'] = $hvalue;
                            }
                            break;
                        
                        case 'cc':
                            foreach (array_map('trim', explode(',', $hvalue)) as $cc) {
                                if (is_email($cc)) {
                                    $result['cc'][] = $cc;
                                }
                            }
                            break;
                        
                        case 'bcc':
                            foreach (array_map('trim', explode(',', $hvalue)) as $bcc) {
                                if (is_email($bcc)) {
                                    $result['bcc'][] = $bcc;
                                }
                            }
                            break;
                        
                        default:
                            $result['custom_header'][] = $hname . ': ' . $hvalue;
                            break;
                    }
                }
            } else {
                // Numeric-indexed header lines (like "CC: john@example.com")
                foreach ($headers as $header_line) {
                    $header_line = trim($header_line);
                    if ($header_line === '') continue;
                
                    if (preg_match('/^(from|content-type|mime-version|to|subject):/i', $header_line)) {
                        continue; // Skip duplicates
                    }
                
                    if (stripos($header_line, 'reply-to:') === 0) {
                        $addr = trim(substr($header_line, 9));
                        if (is_email($addr)) {
                            $result['reply_to'] = $addr;
                        }
                    } elseif (stripos($header_line, 'cc:') === 0) {
                        foreach (array_map('trim', explode(',', substr($header_line, 3))) as $cc) {
                            if (is_email($cc)) {
                                $result['cc'][] = $cc;
                            }
                        }
                    } elseif (stripos($header_line, 'bcc:') === 0) {
                        foreach (array_map('trim', explode(',', substr($header_line, 4))) as $bcc) {
                            if (is_email($bcc)) {
                                $result['bcc'][] = $bcc;
                            }
                        }
                    } else {
                        $result['custom_header'][] = $header_line;
                    }
                }
            }
        
            // Remove empty arrays for cleaner results
            return array_filter($result);
        }


        private function parse_from_header($headers) {
            // Normalize headers to an array of lines
            if (is_string($headers)) {
                $headers = array_filter(array_map('trim', preg_split("/\r\n|\n|\r/", $headers)));
            }
        
            $from_line = '';
        
            if ($this->is_assoc_array($headers)) {
                // Associative headers array
                foreach ($headers as $hname => $hvalue) {
                    if (strtolower(trim((string)$hname)) === 'from') {
                        $from_line = trim($hvalue);
                        break;
                    }
                }
            } else {
                // Numeric array of header lines
                foreach ($headers as $header_line) {
                    if (stripos($header_line, 'from:') === 0) {
                        $from_line = trim(substr($header_line, 5));
                        break;
                    }
                }
            }
        
            if (empty($from_line)) {
                return ['name' => '', 'email' => ''];
            }
        
            $name  = '';
            $email = '';
            
            if (preg_match('/(.*)<(.+)>/', $from_line, $matches)) {
                $name  = trim(str_replace(['"', "'"], '', $matches[1]));
                $email = sanitize_email(trim($matches[2]));
            } elseif (is_email($from_line)) {
                $email = sanitize_email($from_line);
            } else {
                // Sometimes plugins pass weird forms like "John Doe john@example.com"
                if (preg_match('/([^\s]+@[^\s]+)/', $from_line, $matches)) {
                    $email = sanitize_email($matches[1]);
                    $name  = trim(str_replace($matches[1], '', $from_line));
                }
            }
        
            return [
                'name'  => $name,
                'email' => $email,
            ];
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

Simple_SMTP_Mail_Scheduler_Mailer::get_instance();
