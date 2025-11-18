function ssmptmsCreateChartWithDatePicker(options) {
    const {
        canvas_id,
        date_picker_id,
        chart_wrapper_id,
        create_chart,
        ajax_action,
        ajax_nonce,
        text_no_data
    } = options;

    const date_picker = jQuery('#' + date_picker_id);
    const canvas      = jQuery('#' + canvas_id);
    const wrapper     = jQuery('#' + chart_wrapper_id);

    if (date_picker.length === 0 || canvas.length === 0 || wrapper.length === 0) {
        console.error("Status donut chart: missing canvas, wrapper, or date picker");
        return;
    }

    let chartInstance = null;

    const spinnerHTML = `
        <div class="ssmptms-loading-spinner">
            <span class="spinner is-active" style="float:none;"></span>
        </div>
    `;
    const spinner = jQuery(spinnerHTML);
    spinner.hide();
    wrapper.append(spinner);

    function showSpinner() {
        clearNoDataMessage();
        hideCanvas();

        spinner.show();
    }

    function hideSpinner() {
        spinner.hide();
    }

    function clearNoDataMessage() {
        wrapper.find('.ssmptms-no-chart-data').remove();
    }

    function showNoDataMessage() {
        clearNoDataMessage();
        hideCanvas();

        wrapper.append(
            '<div class="ssmptms-no-chart-data">' + text_no_data + '</div>'
        );
    }

    function hideCanvas() {
        canvas.hide();
    }

    function showCanvas() {
        canvas.show();
    }

    function updateChart(data) {
        if (chartInstance) {
            chartInstance.destroy();
        }
        chartInstance = create_chart(canvas_id, data);
    }

    function onDateSelect(selectedDate) {
        showSpinner();
        jQuery.post(
            ssmptms_chart_params.ajax_url,
            {
                action: ajax_action,
                ajax_nonce: ajax_nonce,
                date_picker_id: date_picker_id,
                date: selectedDate
            },
            function (response) {

                hideSpinner();

                if (!response.success) return;

                const data = response.data;

                if (!data.values || data.values.length === 0) {
                    showNoDataMessage();
                    return;
                }

                clearNoDataMessage();
                showCanvas();
                updateChart(data);
            }
        );
    }

    const dateFormat = 'dd.mm.yy';
    const now = new Date();

    const formattedToday = jQuery.datepicker.formatDate(dateFormat, now);

    date_picker.datepicker({
        dateFormat: dateFormat,
        onSelect: onDateSelect
    });
    date_picker.val(formattedToday); 
    onDateSelect(formattedToday);
}