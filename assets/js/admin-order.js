jQuery(document).ready(function($) {
    $('.wc-moneybird-sync-button').on('click', function(e) {
        e.preventDefault();

        var $button = $(this);
        var $status = $button.siblings('.wc-moneybird-sync-status');
        var orderId = $button.data('order-id');
        var nonce = $button.data('nonce');

        // Disable button and show loading
        $button.prop('disabled', true);
        $status.html('<span class="spinner is-active" style="float: none; margin: 0 5px;"></span>Syncing...');

        // Make AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wc_moneybird_manual_sync',
                order_id: orderId,
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    $status.html('<span style="color: #46b450;">✓ ' + response.data.message + '</span>');
                    // Reload page after 1 second to show updated metabox
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    $status.html('<span style="color: #d63638;">✗ ' + response.data.message + '</span>');
                    $button.prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                $status.html('<span style="color: #d63638;">✗ Request failed</span>');
                $button.prop('disabled', false);
            }
        });
    });
});
