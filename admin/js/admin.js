jQuery(function ($) {
    $(document).on('click', '.ssmptms-start-scheduler:not(.started)', function(e) {
        e.preventDefault();
        const $btn = jQuery(this);
        $btn.text(`⏳ ${ssmptms_admin_ajax_params.starting_text} ...`);
        $btn.addClass('started');

        jQuery.post(
            ssmptms_admin_ajax_params.ajax_url, { 
                'action': ssmptms_admin_ajax_params.start_action,
                'ajax_nonce': ssmptms_admin_ajax_params.ajax_nonce,
            }, function(response) {
                if (response.success) {
                    $btn.text(`✅ ${ssmptms_admin_ajax_params.started_text}`);
                    setTimeout(() => location.reload(), 1000);
                } else {
                    console.error('Failed to start the scheduler!');
                }
        }).fail(() => {
            console.error('Failed to start the scheduler!');
        });
    });
});