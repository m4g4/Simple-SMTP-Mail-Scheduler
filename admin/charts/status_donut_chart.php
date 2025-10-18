<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('Simple_SMTP_Mail_Status_Donut_Chart')) {

    class Simple_SMTP_Mail_Status_Donut_Chart {
        public function display() {
            $results = Simple_SMTP_Email_Queue::get_instance()->get_status_data_last_24h();

            ?>
            <div class="s-smtp-mail-chart-wrapper">
                <canvas id="smtpMailStatusChart"></canvas>
                <?php
                if (empty($results)) {
                    echo '<div class="s-smtp-mail-no-donut-chart-data">' . __('No data to display.', Simple_SMTP_Constants::DOMAIN) . '</div>';
                }
                ?>
            </div>
            <?php

            $data = [
                'labels' => [],
                'values' => [],
                'colors' => [],
            ];
        
            $color_map = [
                'sent'       => '#22c55e',
                'failed'     => '#ef4444',
                'queued'     => '#f97316',
                'processing' => '#3b82f6',
            ];
        
            foreach ($results as $row) {
                $data['labels'][] = ucfirst($row->status);
                $data['values'][] = (int) $row->count;
                $data['colors'][] = $color_map[$row->status] ?? '#9ca3af'; // gray fallback
            }

            wp_add_inline_script('chartjs', 'const smtpMailStatusData = ' . wp_json_encode($data) . ';');

            wp_add_inline_script('chartjs', "
                document.addEventListener('DOMContentLoaded', () => {
                    const ctx = document.getElementById('smtpMailStatusChart');
                    if (!ctx || typeof smtpMailStatusData === 'undefined') return;

                    new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: smtpMailStatusData.labels,
                            datasets: [{
                                data: smtpMailStatusData.values,
                                backgroundColor: smtpMailStatusData.colors,
                                borderWidth: 1,
                                hoverOffset: 10
                            }]
                        },
                        options: {
                            cutout: '70%', // makes it a donut
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        usePointStyle: true,
                                        padding: 20
                                    }
                                },
                                title: {
                                    display: true,
                                    text: '" . __('Email Status Distribution (Last 24h)', Simple_SMTP_Constants::DOMAIN) . "'
                                }
                            }
                        }
                    });
                });
                "
            );
        }
    }
}