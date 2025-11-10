jQuery(document).ready(function($) {
    const form = $('.simple-smtp-profile-form');

    if (!form.length) return; // Safe check if form doesn't exist

    function updateCheckboxes() {
        const fromEmail = $.trim(form.find('#from_email').val());
        const fromName  = $.trim(form.find('#from_name').val());

        const forceEmail     = form.find('#force_from_email');
        const returnPath     = form.find('#match_return_path');
        const forceName      = form.find('#force_from_name');

        // From Email → Force From Email + Return Path
        if (fromEmail === '') {
            forceEmail.prop('disabled', true).prop('checked', false);
            returnPath.prop('disabled', true).prop('checked', false);
        } else {
            forceEmail.prop('disabled', false);
            returnPath.prop('disabled', false);
        }

        // From Name → Force From Name
        if (fromName === '') {
            forceName.prop('disabled', true).prop('checked', false);
        } else {
            forceName.prop('disabled', false);
        }
    }

    // Run once on page load
    updateCheckboxes();

    // Run whenever user types or changes input
    form.on('input change', '#from_email, #from_name', updateCheckboxes);
});
