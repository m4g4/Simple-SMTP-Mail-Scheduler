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
            
            $labels = [];
            $data = [];

            $utcTimezone = new DateTimeZone('UTC');
                    
            foreach ($results as $row) {
                $dateTimeObject = new DateTime($row->time_slot, $utcTimezone);
                $labels[] = $dateTimeObject->format('c');
                $data[] = (int) $row->count;
            }

            wp_add_inline_script('chartjs', 'const smtpMailHourlyData = ' . wp_json_encode([
                'labels' => $labels,
                'values' => $data,
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
                                    type: 'time',
                                    time: {
                                        unit: 'hour',
                                        displayFormats: {
                                            hour: 'HH:mm'
                                        }
                                    }
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