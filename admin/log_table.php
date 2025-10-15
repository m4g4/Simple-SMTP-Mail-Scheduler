<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Simple_SMTP_Mail_Scheduler_Log_Table extends WP_List_Table {
    private $emails;
    private $total_items;

    public function __construct() {
        parent::__construct([
            'singular' => __('Email', Simple_SMTP_Constants::DOMAIN),
            'plural'   => __('Emails', Simple_SMTP_Constants::DOMAIN),
            'ajax'     => false,
        ]);
    }

    public function get_columns(): array {
        return [
            'recipient_email' => __('Recipient', Simple_SMTP_Constants::DOMAIN),
            'subject'         => __('Subject', Simple_SMTP_Constants::DOMAIN),
            'profile'         => __('Profile', Simple_SMTP_Constants::DOMAIN),
            'status'          => __('Status', Simple_SMTP_Constants::DOMAIN),
            'testing'         => __('Testing', Simple_SMTP_Constants::DOMAIN),
            'scheduled_at'    => __('Scheduled At', Simple_SMTP_Constants::DOMAIN),
            'priority'        => __('Priority', Simple_SMTP_Constants::DOMAIN),
            'actions'         => __('Actions', Simple_SMTP_Constants::DOMAIN),
        ];
    }

    public function get_sortable_columns(): array {
        return [
            'recipient_email' => ['recipient_email', true],
            'subject'         => ['subject', false],
            'profile'         => ['profile_settings', false],
            'status'          => ['status', false],
            'scheduled_at'    => ['scheduled_at', true],
        ];
    }

    protected function prepare_column_headers(): void {
        $this->_column_headers = [
            $this->get_columns(),
            [],
            $this->get_sortable_columns(),
        ];
    }

    public function prepare_items(): void {
        global $wpdb;

        $table = simple_smtp_prepare_db_name();

        $this->prepare_column_headers();

        $per_page     = 10;
        $current_page = $this->get_pagenum();
        $offset       = ($current_page - 1) * $per_page;

        $orderby = !empty($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'scheduled_at';
        $order   = !empty($_GET['order']) && in_array(strtolower($_GET['order']), ['asc', 'desc']) ? strtolower($_GET['order']) : 'desc';

        // Handle sorting for profile column (extract label from JSON)
        $orderby_sql = ($orderby === 'profile_settings')
            ? "JSON_UNQUOTE(JSON_EXTRACT(profile_settings, '$.label')) $order"
            : "$orderby $order";

        $this->emails = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table ORDER BY $orderby_sql LIMIT %d OFFSET %d",
                $per_page,
                $offset
            )
        );

        $this->total_items = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $this->items       = $this->emails;

        $this->set_pagination_args([
            'total_items' => $this->total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($this->total_items / $per_page),
        ]);
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'recipient_email':
                $emails = maybe_unserialize($item->recipient_email);
                return esc_html(is_array($emails) ? implode(', ', $emails) : $emails);
            case 'subject':
            case 'scheduled_at':
                return esc_html($item->$column_name);
            case 'profile':
                $profile = json_decode($item->profile_settings, true);
                return esc_html($profile['label'] ?? __('Unknown Profile', Simple_SMTP_Constants::DOMAIN));
            case 'priority':
                $colors = ['0' => 'gray', '1' => 'blue', '2' => 'orange', '3' => 'red'];
                $color  = $colors[$item->priority] ?? 'black';
                return '<span style="color:' . esc_attr($color) . '">' . esc_html($item->priority) . '</span>';
            case 'status':
                $status_colors = [
                    'sent'       => 'green',
                    'failed'     => 'red',
                    'queued'     => 'orange',
                    'processing' => 'orange',
                ];
                $color = $status_colors[$item->status] ?? 'gray';
                return '<span style="color:' . esc_attr($color) . '">' . esc_html(ucfirst($item->status)) . '</span>';
            case 'testing':
                return $item->testing ? '<span style="color: blue;">' . __('Yes', Simple_SMTP_Constants::DOMAIN) . '</span>' : __('No', Simple_SMTP_Constants::DOMAIN);
            case 'actions':
                return $this->row_actions($this->get_row_actions($item));
            default:
                return '';
        }
    }

    public function get_row_actions($item): array {
        $actions = [];

        // Retry (only for failed)
        if ($item->status === 'failed') {
            $actions['retry'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url(add_query_arg([
                    'action'   => 'simple_smtp_mail_retry',
                    'email_id' => $item->email_id,
                    '_wpnonce' => wp_create_nonce('simple_smtp_mail_retry_' . $item->email_id),
                ])),
                __('Retry', Simple_SMTP_Constants::DOMAIN)
            );
        }

        // Remove
        $actions['remove'] = sprintf(
            '<a href="%s" style="color:red;">%s</a>',
            esc_url(add_query_arg([
                'action'   => 'simple_smtp_mail_remove',
                'email_id' => $item->email_id,
                '_wpnonce' => wp_create_nonce('simple_smtp_mail_remove_' . $item->email_id),
            ])),
            __('Remove', Simple_SMTP_Constants::DOMAIN)
        );

        // Put to front (queued only)
        if ($item->status === 'queued') {
            $actions['front'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url(add_query_arg([
                    'action'   => 'simple_smtp_mail_front',
                    'email_id' => $item->email_id,
                    '_wpnonce' => wp_create_nonce('simple_smtp_mail_front_' . $item->email_id),
                ])),
                __('Put to Front', Simple_SMTP_Constants::DOMAIN)
            );
        }

        return $actions;
    }
}
