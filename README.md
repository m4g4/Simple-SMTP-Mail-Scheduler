# Simple-SMTP-Mail-Scheduler

**Simple SMTP Mail Scheduler** is a WordPress plugin that allows you to queue and send emails using custom SMTP profiles. It intercepts `wp_mail` calls, stores emails in a database, and sends them asynchronously via a cron job, ensuring reliable email delivery with configurable SMTP settings.

## Features

- **Email Queuing**: Replaces `wp_mail` to queue emails for scheduled sending.
- **SMTP Profiles**: Configure multiple SMTP profiles with settings like host, port, and authentication.
- **Profile Snapshots**: Stores a snapshot of SMTP settings for each email to prevent issues from profile changes or deletions.
- **Priority and Scheduling**: Assign priorities to emails and schedule them for specific times.
- **Testing Mode**: Log emails without sending them for testing purposes.
- **Admin Log Interface**: View queued, sent, and failed emails with details like recipient, subject, profile, and status.
- **Retry Mechanism**: Automatically retries failed emails up to a configurable limit.
- **Clean Logging**: Limits the number of stored email logs to prevent database bloat.

## Installation

1. **Download the Plugin**:
   - Clone the repository or download the ZIP file:
     ```bash
     git clone https://github.com/your-username/smtp-mail-scheduler.git
     ```
   - Or upload the plugin folder to `wp-content/plugins/smtp-mail-scheduler/`.

2. **Activate the Plugin**:
   - In the WordPress admin dashboard, go to **Plugins** > **Installed Plugins**.
   - Find **SMTP Mail Scheduler** and click **Activate**.

3. **Configure SMTP Profiles**:
   - Navigate to the plugin’s settings page in the WordPress admin (e.g., **Settings > SMTP Mail Scheduler**).
   - Add one or more SMTP profiles with details like host, port, username, password, and encryption.
   - Set an active profile for sending emails.

## Usage

1. **Queue Emails**:
   - Use WordPress’s `wp_mail` function as usual. The plugin will intercept and queue emails using the active SMTP profile.
   - Example:
     ```php
     wp_mail('recipient@example.com', 'Test Subject', 'Test Message');
     ```

2. **View Email Logs**:
   - Go to the plugin’s log page in the WordPress admin to view queued, processing, sent, or failed emails.
   - Use actions to retry failed emails, remove emails, or prioritize queued emails.

3. **Manage SMTP Profiles**:
   - Add, edit, or delete SMTP profiles in the plugin settings.
   - Each queued email stores a snapshot of the profile settings to ensure consistent sending even if profiles change.

4. **Testing Mode**:
   - Enable testing mode in the settings to log emails without sending them.
   - Check the logs to verify email content and settings.

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.7 or higher (for JSON support in `profile_settings`)

## Development

- **Repository**: [https://github.com/your-username/smtp-mail-scheduler](https://github.com/your-username/smtp-mail-scheduler)
- **Contributing**: Fork the repository, make changes, and submit a pull request.
- **Issues**: Report bugs or suggest features via the GitHub Issues page.

## License

This plugin is licensed under the [GPLv3 or later](https://www.gnu.org/licenses/gpl-3.0.html).

## Credits

Developed by [Your Name or Organization]. Inspired by the need for reliable, scheduled email delivery in WordPress.