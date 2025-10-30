<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('Simple_SMTP_Mail_Queue_Status_Bar_Chart')) {

    class Simple_SMTP_Mail_Queue_Status_Bar_Chart {

        public function display() {
            ?>
            <canvas id="smtpMailQueueStatusChart"></canvas>
            <?php

            $count = Simple_SMTP_Email_Queue::get_instance()->get_email_entry_count_for_sending();
            $capacity = get_option(Simple_SMTP_Constants::EMAILS_PER_UNIT, 0);
            $unit = get_option(Simple_SMTP_Constants::EMAILS_UNIT, 0);

            $unitText = Simple_SMTP_Constants::get_unit_text($unit);

            wp_add_inline_script('chartjs', 'const smtpMailQueueStatusData = ' . wp_json_encode([
                'queued' => $count,
                'rate'   => $capacity,
                'unit'   => $unit,
                'unitSlotTime' => simple_stmp_scheduler_slot_time_seconds($unit)
            ]) . ';', 'before');

            wp_add_inline_script('chartjs', '
                document.addEventListener("DOMContentLoaded", function() {
                    const ctx = document.getElementById("smtpMailQueueStatusChart").getContext("2d");

                    const rate = smtpMailQueueStatusData.rate;
                    const queued = smtpMailQueueStatusData.queued;
                    const unit = smtpMailQueueStatusData.unit;

                    const now = new Date();

                    const slotsNeeded = Math.ceil(queued / rate);
                    const visibleSlots = Math.max(slotsNeeded, 4);

                    const labels = [];
                    const data = [];
                    const colors = [];

                    function progressiveColor(percentage) {
                        if (percentage < 20) return "#15fa43";
                        if (percentage < 40) return "rgba(250, 231, 21, 1)";
                        if (percentage < 60) return "rgb(250, 174, 21)";
                        if (percentage < 80) return "rgb(250, 116, 21)";
                        if (percentage >= 80 && percentage < 100) return "rgb(250, 55, 21)";

                        return "rgba(71, 62, 60, 1)"
                    }

                    function getLabel(slotTime, unit) {
                        if (unit === "day") {
                            return slotTime.toLocaleDateString([], {
                                day: "2-digit",
                                month: "2-digit",
                                year: "numeric"
                            });
                        }
                        return slotTime.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
                    }

                    let remaining = queued;
                    const slotTimeInMs = smtpMailQueueStatusData.unitSlotTime * 1000;

                    for (let i = 0; i < visibleSlots; i++) {
                        const slotTime = new Date(now.getTime() + i * slotTimeInMs);
                        const label = getLabel(slotTime, unit);

                        let fill = 0;
                        if (remaining >= rate) {
                            fill = 100;
                        } else if (remaining > 0) {
                            fill = (remaining / rate) * 100;
                        }
                        remaining -= rate;

                        labels.push(label);
                        data.push(fill);
                        colors.push(progressiveColor(fill));
                    }
                
                    new Chart(ctx, {
                        type: "bar",
                        data: {
                            labels: labels,
                            datasets: [{
                                data: data,
                                backgroundColor: colors,
                                borderRadius: 3
                            }]
                        },
                        options: {
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    max: 100,
                                    ticks: {
                                        callback: val => val + "%"
                                    },
                                    title: {
                                        display: true,
                                        text: "' . __('Queue Fill (%)', Simple_SMTP_Constants::DOMAIN) . '"
                                    }
                                },
                                x: {
                                    title: {
                                        display: true,
                                        text: "' . __('Time Slots', Simple_SMTP_Constants::DOMAIN) . '"
                                    },
                                    grid: { color: "#f3f4f6" }
                                }
                            },
                            plugins: {
                                legend: { display: false },
                                title: {
                                    display: true,
                                    text: "' . sprintf(__('Queue Projection: %s queued (Limit: %s %s)', Simple_SMTP_Constants::DOMAIN), $count, $capacity, $unitText) .'"
                                },
                                tooltip: {
                                    callbacks: {
                                        label: ctx => `${ctx.formattedValue}%`
                                    }
                                }
                            }
                        }
                    });
                });
                '
            );
        }
    }
}