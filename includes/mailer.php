<?php

namespace Ssmptms;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('Mailer')) {

    class Mailer {
        private static $instance;
        public function __construct() {
            add_filter('pre_wp_mail', array($this, 'queue_mail_instead_of_sending'), 10, 2);
            add_action(Constants::SCHEDULER_EVENT_NAME, array($this, 'cron_send_mails'));
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
            $disable_plugin = get_option(Constants::DISABLE, false);
            if ($disable_plugin) return;
            
            // The scheduler class is expected to call the provided callback
            // with a number (emails per run). Keep backward-compatible call.
            $scheduler = new Scheduler(array($this, 'cron_send_mails_tick'));
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

            $emails = Email_Queue::get_instance()->get_emails_to_process($emails_per_minute);
            if (empty($emails)) {
                return;
            }

            foreach ($emails as $email) {
                if(!$this->send_email($email)) {
                    error_log("Failed to send email " . print_r($email, true));
                }
            }

            // prune old log rows if necessary
            $this->remove_exceeding_emails();
        }

        public function send_email_by_id($email_id) {
            $email = Email_Queue::get_instance()->get_email_by_id($email_id);
            if (!$email) {
                throw new \InvalidArgumentException( 'No such email ' . $email_id );
            }

            if(!$this->send_email($email)) {
                error_log("Failed to send email with id " . $email_id);
            }

            // prune old log rows if necessary
            $this->remove_exceeding_emails();
        }

        protected function send_email($email) {
            // Validate required fields
            if (empty($email->email_id)) {
                return false;
            }

            $updated = Email_Queue::get_instance()->update_email_status($email->email_id, $email->status);

            if ($updated === false || $updated === 0) {
                // Someone else processed it or update failed — skip
                return false;
            }

            // Send the email
            $send_result = $this->send_email_from_record($email);

            $current_time = current_time('mysql');

            if ($send_result === true) {
                Email_Queue::get_instance()->update_email_sent($email->email_id, $current_time);
            } else {
                // failure; increase retries
                $retry_count = isset($email->retries) ? ((int)$email->retries + 1) : 1;
                $error_msg = is_string($send_result) ? $send_result : 'Unknown error';

                Email_Queue::get_instance()->update_email_failed($email->email_id, $current_time, $retry_count, $error_msg);

                return false;
            }

            return true;
        }

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

                list($from_email, $from_name) = $this->determine_from_fields($headers, $profile_settings);
                $sender = $this->determine_sender($profile_settings);

                // === Message basics ===
                $mailer->CharSet = 'UTF-8';
                $mailer->Subject = wp_strip_all_tags($subject);
                $mailer->Body    = $message;
                if ($sender)
                    $mailer->Sender = $sender;
                $mailer->From       = $from_email;
                $mailer->FromName   = $from_name;
                if ($is_html) {
                    $mailer->AltBody = $this->html_to_text($message);
                }
                $mailer->isHTML((bool)$is_html);
                $mailer->XMailer = false;

                // === Process headers ===
                if (!empty($headers)) {
                    $h = $this->process_headers($headers);
                                
                    // Reply-To
                    if (!empty($h['reply-to'])) {
                        list($replyToEmail, $replyToName) = $this->parseAddress($h['reply-to']);
                        $mailer->addReplyTo($replyToEmail, $replyToName);
                    }
                
                    // CC
                    if (!empty($h['cc'])) {
                        $ccs = (array) $h['cc'];
                        foreach ($ccs as $cc) {
                            $mailer->addCC($cc);
                        }
                    }
                
                    // BCC
                    if (!empty($h['bcc'])) {
                        $bccs = (array) $h['bcc'];
                        foreach ($bccs as $bcc) {
                            $mailer->addBCC($bcc);
                        }
                    }
                
                    // Custom headers
                    if (!empty($h['custom-header'])) {
                        $custom_headers = (array) $h['custom-header'];
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

        public function queue_mail_instead_of_sending($pre_wp_mail, $atts) {
            $disable_plugin = get_option(Constants::DISABLE, false);

            // Respect a bypass constant to allow immediate sending
            if ((defined('SMTP_MAIL_BYPASS_QUEUE') && SMTP_MAIL_BYPASS_QUEUE === true) || $disable_plugin) {
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

            // Queue
            $queued_result = $this->mail_enqueue_email($parsed_recipients, $subject, $message, $headers, $attachments, false);

            if ($queued_result) {
                $current_queue_count  = (int) get_option(Constants::CURRENT_QUEUE_COUNT, 0);
                update_option(Constants::CURRENT_QUEUE_COUNT, $current_queue_count + 1);

                schedule_cron_event();
            }

            // If enqueue succeeded, short-circuit wp_mail (return true)
            return $queued_result ? true : null;
        }

        public function mail_enqueue_email($to, $subject, $message, $headers, $attachments, $testing = 0) {        
            $profile = get_active_profile();
            if (empty($profile) || !is_array($profile)) {
                error_log('Simple SMTP Mail Scheduler: No valid SMTP profile available for email queuing.');
                return false;
            }
        
            // Queue the email
            $inserted = Email_Queue::get_instance()->queue_email(
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

        public function prepare_mailer($profile) {
            if (empty($profile) || !is_array($profile)) {
                return null;
            }

            try {
                $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);

                $mailer->isSMTP();
                $mailer->Host       = isset($profile['host']) ? (string)$profile['host'] : '';
                $mailer->Port       = isset($profile['port']) ? (int)$profile['port'] : 25;
                $mailer->SMTPAuth   = true;
                $mailer->Username   = isset($profile['username']) ? (string)$profile['username'] : '';
                $mailer->Password   = isset($profile['password']) ? decrypt_password($profile['password']) : '';
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

        private function remove_exceeding_emails() {
            $limit = (int) Constants::EMAILS_LOG_MAX_ROWS;

            $total = Email_Queue::get_instance()->get_total_emails();

            if ($total <= $limit) {
                return;
            }

            $to_delete = $total - $limit;

            Email_Queue::get_instance()->remove_exceeding_emails($to_delete);
        }

        private function process_headers($headers) {
            if (is_string($headers)) {
                $headers = array_filter(array_map('trim', preg_split("/\r\n|\n|\r/", $headers)));
            }
        
            $result = [
                'cc' => [],
                'bcc' => [],
                'custom-header' => [],
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
                            $result['reply-to'] = $hvalue;
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
                            $result['custom-header'][] = $hname . ': ' . $hvalue;
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
                        $result['reply-to'] = $addr;
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
                        $result['custom-header'][] = $header_line;
                    }
                }
            }
        
            // Remove empty arrays for cleaner results
            return array_filter($result);
        }


        private function parse_from_header($headers) {
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
                return ['', ''];
            }
        
            return $this->parseAddress($from_line);
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
            if (preg_match('/(.*)<(.+)>/', trim($address), $matches)) {
                $name  = trim(str_replace(['"', "'"], '', $matches[1]));
                $email = sanitize_email(trim($matches[2]));
            } elseif (is_email($address)) {
                $name = '';
                $email = sanitize_email($address);
            } else {
                // Sometimes plugins pass weird forms like "John Doe john@example.com"
                if (preg_match('/([^\s]+@[^\s]+)/', $address, $matches)) {
                    $email = sanitize_email($matches[1]);
                    $name  = trim(str_replace($matches[1], '', $address));
                }
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

        private function html_to_text($html) {
            /* ---------------------------------------------------------
               STEP 0: Extract ONLY *clickable* URLs (a href, form action)
               --------------------------------------------------------- */
            preg_match_all('#\b(https?://[^\s"\'<>]+)|(mailto:[^\s"\'<>]+)#i', $html, $raw);
            $clickable_urls = [];
            foreach ($raw[0] as $m) {
                $url = html_entity_decode(trim($m), ENT_QUOTES, 'UTF-8');
                if (
                    preg_match('#^https?://(www\.w3\.org/|urn:schemas|fonts\.googleapis|fonts\.gstatic)#i', $url) ||
                    preg_match('#\.dtd$|\.(css|js|xml|json|svg|ico|png|jpg|jpeg|gif|webp)(\?.*)?$#i', $url) ||
                    $url === '#' || 
                    preg_match('#^javascript:#i', $url)
                ) {
                    continue;
                }
                $clickable_urls[] = $url;
            }
        
            /* ---------------------------------------------------------
               STEP 1: Annotate ONLY hidden clickable URLs
               --------------------------------------------------------- */
            $html = preg_replace_callback(
                '#(<[^>]*\s)(href|action)=(["\'])([^"\']+)(\3[^>]*>)#i',
                function ($m) {
                    $url = trim($m[4]);
                    $tag = $m[0];
                    if (
                        preg_match('#rel=["\']?(stylesheet|icon)#i', $tag) ||
                        preg_match('#\.(css|js|png|jpg|gif|webp|svg|ico)$#i', $url) ||
                        in_array($url, ['#', 'javascript:void(0)'])
                    ) {
                        return $m[0];
                    }
                    if (preg_match('#^<a\s#i', $tag)) return $m[0];
                    return $m[1] . $m[2] . '=' . $m[3] . $url . $m[5] . " [Link: $url]";
                },
                $html
            );
        
            /* ---------------------------------------------------------
               STEP 2: REPLACE IMAGES — NO URL IN PLAIN TEXT
               --------------------------------------------------------- */
            $html = preg_replace('#<img[^>]*alt=["\']([^"\']*)["\'][^>]*>#i', '[Image: $1]', $html);
            $html = preg_replace('#<img[^>]*>#i', '[Image]', $html);
        
            /* ---------------------------------------------------------
               STEP 3: <a> tags — clean label (url)
               --------------------------------------------------------- */
            $html = preg_replace_callback(
                '#<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is',
                function ($m) {
                    $url = trim($m[1]);
                    $inner = $m[2];
                    if (preg_match('#\.(css|js|png|jpg|gif|webp)#i', $url)) return strip_tags($inner);
                
                    $label = trim(strip_tags($inner));
                    $url_clean = preg_replace('#^https?://#i', '', $url);
                    $label_clean = preg_replace('#^https?://#i', '', $label);
                    if (strpos($label_clean, $url_clean) !== false || $label === $url) {
                        return $label;
                    }
                    return "$label ($url)";
                },
                $html
            );
        
            /* ---------------------------------------------------------
               STEP 4-5: Clean up
               --------------------------------------------------------- */
            $html = preg_replace('#<br\s*/?>#i', "\n", $html);
            $html = preg_replace('#</(p|div|section|table|tr)>#i', "\n\n", $html);
            $html = preg_replace('#<!--.*?-->#s', '', $html);
            $text = wp_strip_all_tags($html);
            $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
            $text = preg_replace('#[ \t]+#', ' ', $text);
            $text = preg_replace('#(\n\s*){3,}#', "\n\n", $text);
            $text = trim($text);
        
            return $text;
        }

        private function determine_from_fields($headers, $profile) {
            $from_email_profile = isset($profile['from_email']) ? (string)$profile['from_email'] : '';
            $from_name_profile = isset($profile['from_name']) ? (string)$profile['from_name'] : '';

            $force_from_email = isset($profile['force_from_email']) && $profile['force_from_email'] === 1;
            $force_from_name = isset($profile['force_from_name']) && $profile['force_from_name'] === 1;

            if ($force_from_email && !empty($from_email_profile) && 
                $force_from_name && !empty($from_name_profile)) {
                return [$from_email_profile, $from_name_profile];
            }

            list($from_email_headers, $from_name_headers) = $this->parse_from_header($headers);

            $from_email = $from_email_headers;
            if (empty($from_email) || ($force_from_email && !empty($from_email_profile))) {
                $from_email = $from_email_profile;
            }

            $from_name = $from_name_headers;
            if (empty($from_name) || ($force_from_name && !empty($from_name_profile))) {
                $from_name = $from_name_profile;
            }

            return [$from_email, $from_name];
        }

        private function determine_sender($profile) {
            $from_email = isset($profile['from_email']) ? (string)$profile['from_email'] : null;
            $match_return_path = isset($profile['match_return_path']) && $profile['match_return_path'] === 1;
            
            if ($match_return_path) {
                return $from_email;
            }

            return null;
        }

    }
}

Mailer::get_instance();
