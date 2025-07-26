jQuery(document).ready(function ($) {
    $('select[name="status"]').change(function () {
        let status = $(this).val();

        $.ajax({
            url: cpm_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'cpm_filter_projects',
                nonce: cpm_ajax_object.nonce,
                status: status
            },
            success: function (response) {
                $('.client-projects-grid').html(response);
            }
        });
    });
});
