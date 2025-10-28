<?php

add_action('admin_enqueue_scripts', 'simple_smtp_mail_scheduler_enqueue_charts');

function simple_smtp_mail_scheduler_enqueue_charts() {
    // TODO Local assets with proper version should be used instead.
    wp_enqueue_script('luxon3', 'https://cdn.jsdelivr.net/npm/luxon@3', [], null, true);
    wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true);
    wp_enqueue_script('chartjs-adapter-luxon', 'https://cdn.jsdelivr.net/npm/chartjs-adapter-luxon@1', ['luxon3', 'chartjs'], null, true);
}

?>