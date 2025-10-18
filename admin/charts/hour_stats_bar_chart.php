<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('Simple_SMTP_Mail_Hour_Stats_Bar_Chart')) {

    class Simple_SMTP_Mail_Hour_Stats_Bar_Chart {

        public function display() {
            ?>
            <canvas id="smtpMailHourlyChart"></canvas>
            <?php

            $results = Simple_SMTP_Email_Queue::get_instance()->get_last_day_emails_grouped_by_hour();

            $hours = [];

            for ($i = 0; $i < 24; $i++) {
                $hour = sprintf('%02d:00', $i);
                $hours[$hour] = 0;
            }
        
            foreach ($results as $row) {
                $hours[$row->hour_label] = (int) $row->total;
            }

            wp_add_inline_script('chartjs', 'const smtpMailHourlyData = ' . wp_json_encode([
                'labels' => array_keys($hours),
                'values' => array_values($hours),
            ]) . ';', 'before');

            wp_add_inline_script('chartjs', "
                document.addEventListener('DOMContentLoaded', function() {
                    const ctx = document.getElementById('smtpMailHourlyChart').getContext('2d');
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: smtpMailHourlyData.labels,
                            datasets: [{
                                label: '" . __('Emails per hour', Simple_SMTP_Constants::DOMAIN) . "',
                                data: smtpMailHourlyData.values,
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            scales: {
                                x: {
                                    ticks: { stepSize: 1 }
                                },
                                y: {
                                    beginAtZero: true,
                                    ticks: { stepSize: 1 }
                                }
                            },
                            plugins: {
                                legend: { display: false },
                                title: {
                                    display: true,
                                    text: '" . __('Emails Sent per Hour (Last 24h)', Simple_SMTP_Constants::DOMAIN) . "'
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