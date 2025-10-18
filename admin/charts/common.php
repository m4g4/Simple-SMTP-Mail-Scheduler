<?php

add_action('admin_enqueue_scripts', 'simple_smtp_mail_scheduler_enqueue_charts');

function simple_smtp_mail_scheduler_enqueue_charts() {
    // TODO Local asset with proper version should be used instead.
    wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true);
}

?>