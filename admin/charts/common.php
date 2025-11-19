<?php
namespace Ssmptms;

add_action('admin_enqueue_scripts', 'Ssmptms\enqueue_charts');

function enqueue_charts() {
    // TODO Local assets with proper version should be used instead.
    wp_enqueue_script('luxon3', 'https://cdn.jsdelivr.net/npm/luxon@3', [], null, true);
    wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true);
    wp_enqueue_script('chartjs-adapter-luxon', 'https://cdn.jsdelivr.net/npm/chartjs-adapter-luxon@1', ['luxon3', 'chartjs'], null, true);

    wp_enqueue_script('jquery-ui-datepicker');

    wp_enqueue_style(
        'jquery-ui-css',
        'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css'
    );

    wp_enqueue_script(
		'charts',
		plugins_url('js/chart.js', __FILE__),
		array('jquery'),
		Constants::PLUGIN_VERSION
	);

    wp_localize_script( 
        'charts',
        'ssmptms_chart_params',
        array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
            )
        );
}

?>