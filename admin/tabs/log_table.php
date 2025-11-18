<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Simple_SMTP_Mail_Scheduler_Log_Table extends WP_List_Table {
    private $per_page = 50;

    public function __construct() {
        parent::__construct([
            'singular' => __('Email', Ssmptms_Constants::DOMAIN),
            'plural'   => __('Emails', Ssmptms_Constants::DOMAIN),
            'ajax'     => false,
        ]);
    }

    public function get_columns(): array {
        $columns = [
            'cb'              => '<input type="checkbox" />',
            'recipient_email' => __('Recipient', Ssmptms_Constants::DOMAIN),
            'subject'         => __('Subject', Ssmptms_Constants::DOMAIN),
            'profile'         => __('Profile', Ssmptms_Constants::DOMAIN),
            'status'          => __('Status', Ssmptms_Constants::DOMAIN),
            'scheduled_at'    => __('Scheduled At', Ssmptms_Constants::DOMAIN),
            'last_attempt_at' => __('Last Attempt At', Ssmptms_Constants::DOMAIN),
            'priority'        => __('Priority', Ssmptms_Constants::DOMAIN),
            'actions'         => __('Actions', Ssmptms_Constants::DOMAIN),
        ];

        return $columns;
    }

    public function get_sortable_columns(): array {
        return [
            'status'          => ['status', false],
            'priority'        => ['priority', false],
            'scheduled_at'    => ['scheduled_at', true],
            'last_attempt_at' => ['last_attempt_at', true],
            'recipient_email' => ['recipient_email', true],
            'subject'         => ['subject', false],
            'profile'         => ['profile_settings', false],
        ];
    }

    public function get_bulk_actions() {
        return [
            'retry'   => __('Retry', Ssmptms_Constants::DOMAIN),
            'delete'  => __('Delete', Ssmptms_Constants::DOMAIN),
            'front'   => __('Put to Front', Ssmptms_Constants::DOMAIN),
        ];
    }

    protected function prepare_column_headers(): void {
        $this->_column_headers = [
            $this->get_columns(),
            [],
            $this->get_sortable_columns(),
        ];
    }

    public function extra_tablenav($which) {
        if ($which !== 'top') {
            return;
        }

        // Filters
        $status_filter  = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : '';
        $profile_filter = isset($_GET['profile_filter']) ? sanitize_text_field($_GET['profile_filter']) : '';
        $search_query   = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

        $statuses = [
            'queued'     => __('Queued', Ssmptms_Constants::DOMAIN),
            'processing' => __('Processing', Ssmptms_Constants::DOMAIN),
            'sent'       => __('Sent', Ssmptms_Constants::DOMAIN),
            'failed'     => __('Failed', Ssmptms_Constants::DOMAIN),
        ];

        echo '<div class="alignleft actions">';

        echo '<select name="status_filter" id="status_filter">';
        echo '<option value="">' . esc_html__('All Statuses', Ssmptms_Constants::DOMAIN) . '</option>';
        foreach ($statuses as $value => $label) {
            echo '<option value="' . esc_attr($value) . '"' . selected($status_filter, $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';

        // Profile filter dropdown
        $profile_labels = Simple_SMTP_Email_Queue::get_instance()->get_profile_labels();
        $profile_labels = array_unique(array_filter($profile_labels));

        echo '<select name="profile_filter" id="profile_filter">';
        echo '<option value="">' . esc_html__('All Profiles', Ssmptms_Constants::DOMAIN) . '</option>';
        foreach ($profile_labels as $label) {
            echo '<option value="' . esc_attr($label) . '"' . selected($profile_filter, $label, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';

        // Search input
        echo '<input type="search" name="s" placeholder="' . esc_attr__('Search email or subject...', Ssmptms_Constants::DOMAIN) . '" value="' . esc_attr($search_query) . '" />';

        // Submit button
        submit_button(__('Filter', Ssmptms_Constants::DOMAIN), 'button', 'filter_action', false);

        echo '</div>';
    }


    public function prepare_items(): void {
        $this->prepare_column_headers();

        $search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $current_page = $this->get_pagenum();
        $offset       = ($current_page - 1) * $this->per_page;

        $orderby = !empty($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'scheduled_at';
        $order   = !empty($_GET['order']) && in_array(strtolower($_GET['order']), ['asc', 'desc']) ? strtolower($_GET['order']) : 'desc';

        // Status filter
        $status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : '';

        // Profile filter
        $profile_filter = isset($_GET['profile_filter']) ? sanitize_text_field($_GET['profile_filter']) : '';

        $result = Simple_SMTP_Email_Queue::get_instance()->get_emails($this->per_page, $offset, $orderby, $order, $status_filter, $profile_filter, $search_query);

        $total_items = $result[1];

        $this->items = $result[0];

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $this->per_page,
            'total_pages' => ceil($total_items / $this->per_page),
        ]);
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'recipient_email':
                $emails = maybe_unserialize($item->recipient_email);
                return esc_html(is_array($emails) ? implode(', ', $emails) : $emails);
            case 'subject':
            case 'scheduled_at':
            case 'last_attempt_at':
                return esc_html($item->$column_name);
            case 'profile':
                $profile = json_decode($item->profile_settings, true);
                return esc_html($profile['label'] ?? __('Unknown Profile', Ssmptms_Constants::DOMAIN));
            case 'priority':
                $colors = ['0' => 'gray', '1' => 'blue', '2' => 'orange', '3' => 'red'];
                $color  = $colors[$item->priority] ?? 'black';
                return '<span style="color:' . esc_attr($color) . '">' . esc_html($item->priority) . '</span>';
            case 'status':
                $status = Ssmptms_Constants::get_status_text($item->status) ?? ucfirst($item->status);
                $status_colors = [
                    'queued'     => 'orange',
                    'processing' => 'orange',
                    'sent'       => 'green',
                    'failed'     => 'red',
                ];
                $color = $status_colors[$item->status] ?? 'gray';
                return '<span style="color:' . esc_attr($color) . '">' . esc_html($status) . '</span>';
            case 'actions':
                return $this->row_actions($this->get_row_actions($item));
            default:
                return '';
        }
    }

    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="email_ids[]" value="%d" />',
            absint($item->email_id)
        );
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
                __('Retry', Ssmptms_Constants::DOMAIN)
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
            __('Remove', Ssmptms_Constants::DOMAIN)
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
                __('Put to Front', Ssmptms_Constants::DOMAIN)
            );
        }

        // Send now (queued only)
        if ($item->status === 'queued') {
            $actions['send-now'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url(add_query_arg([
                    'action'   => 'simple_smtp_mail_send_now',
                    'email_id' => $item->email_id,
                    '_wpnonce' => wp_create_nonce('simple_smtp_mail_send_now_' . $item->email_id),
                ])),
                __('Send Now', Ssmptms_Constants::DOMAIN)
            );
        }

        return $actions;
    }
}
?>