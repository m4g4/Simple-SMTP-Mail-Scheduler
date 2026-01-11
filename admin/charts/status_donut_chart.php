<?php
namespace Ssmptms;

if (!defined('ABSPATH')) exit;

if (!class_exists('Donut_Chart')) {

    class Donut_Chart {

        private static $action = 'ssmptms-get-status-donut-data';
        private static $script_handle = 'ssmptms-status-donut-chart';
        private $chart_id;

        public static function initialize_assets() {
            add_action('admin_enqueue_scripts', ['Ssmptms\Donut_Chart', 'enqueue_scripts']);
            add_action('wp_ajax_' . self::$action, ['Ssmptms\Donut_Chart', 'get_data']);
        }

        public static function enqueue_scripts() {
            wp_enqueue_script(
		    	self::$script_handle,
		    	plugins_url('js/status_donut_chart.js', __FILE__),
		    	array('jquery'),
				Constants::PLUGIN_VERSION
		    );
        }

        public function __construct() {
            $this->chart_id = uniqid();
        }

        public static function get_data() {
            check_ajax_referer(self::$action, 'ajax_nonce');

            $date_raw = sanitize_text_field($_POST['date'] ?? '');

            $date_obj = \DateTime::createFromFormat('d.m.Y', $date_raw);

            if (!$date_obj) {
                wp_send_json_error(['message' => 'Invalid date']);
            }

            $date_mysql = $date_obj->format('Y-m-d H:i:s');

            $results = Email_Queue::get_instance()->get_status_data_by_date($date_mysql);

            if (empty($results)) {
                wp_send_json_success([
                    'labels' => [],
                    'values' => [],
                    'colors' => [],
                ]);
            }

            $color_map = [
                'sent'       => '#22c55e',
                'failed'     => '#ef4444',
                'queued'     => '#f97316',
                'processing' => '#3b82f6',
            ];

            $data = [
                'labels' => [],
                'values' => [],
                'colors' => [],
            ];

            foreach ($results as $row) {
                $data['labels'][] = Constants::get_status_text($row->status) ?? ucfirst($row->status);
                $data['values'][] = (int)$row->count;
                $data['colors'][] = $color_map[$row->status] ?? '#9ca3af';
            }

            wp_send_json_success($data);

            wp_die();
        }

        public function display() {
            ?>
            <div class="ssmptms-mail-chart ssmptms-donut-chart">
                <div class="ssmptms-mail-chart-header">
                    <h4><?php echo __('Email Status Distribution', Constants::DOMAIN); ?></h4>
                    <input 
                        id="<?php echo esc_attr($this->date_picker_id()) ?>" 
                        class="ssmptms-date-picker"
                        type="text"
                    />
                </div>
                <div class="ssmptms-mail-chart-wrapper">
                    <?php echo $this->display_chart(); ?>
                </div>
            </div>
            <?php
        }

        private function display_chart() {
            $chart_wrapper_id = 'status_donut_chart_' . esc_attr($this->chart_id);
            echo '
            <div id="'.$chart_wrapper_id.'">
                <canvas id="' . esc_attr($this->canvas_id()) . '"></canvas>
            </div>';

            echo '
            <script>
                jQuery(document).ready(function($) {
                    const options = {
                        canvas_id: "'.$this->canvas_id().'",
                        date_picker_id: "'.$this->date_picker_id().'",
                        chart_wrapper_id: "'.$chart_wrapper_id.'",
                        create_chart: ssmptmsCreateStatusDonutChart,
                        ajax_action: "' . self::$action . '",
                        ajax_nonce:  "' . wp_create_nonce( self::$action ) . '",
                        text_no_data: "'.__('No data to display.', Constants::DOMAIN).'"
                    }
                    ssmptmsCreateChartWithDatePicker(options);
                });
            </script>
            ';
        }

        private function canvas_id() {
            return "canvas_" . $this->chart_id;
        }

        private function date_picker_id() {
            return "date-picker_" . $this->chart_id;
        }
    }

    Donut_Chart::initialize_assets();
}
