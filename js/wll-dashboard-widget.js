jQuery(document).ready(function($) {
    // Target the correct widget ID based on user's HTML inspection
    var widget = $('#when_last_login_activity_widget'); 

    // Exit if widget or localized data is missing
    if (widget.length === 0 || typeof wll_widget_data === 'undefined') {
        return; 
    }

    var tableWrapper = widget.find('#wll-widget-table-body-wrapper');
    var loadingIndicator = widget.find('.wll-widget-loading');
    var nonce = wll_widget_data.nonce;
    var ajaxUrl = wll_widget_data.ajax_url;

    // Use event delegation - listen on the widget for changes to the select element
    widget.on('change', '#wll-widget-sort-select', function(e) {
        var sortBy = $(this).val(); // Get selected value ('count' or 'time')

        // Show loading indicator and fade out current content
        loadingIndicator.show();
        tableWrapper.css('opacity', 0.5);

        // Perform AJAX request
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'wll_fetch_widget_data', // Matches PHP hook
                _ajax_nonce: nonce,
                sort_order: sortBy // 'count' or 'time'
            },
            success: function(response) {
                // Check for html content before updating
                if (response.success && typeof response.data !== 'undefined' && typeof response.data.html !== 'undefined' && response.data.html !== null) { 
                    // Replace table body content with new HTML
                    tableWrapper.html(response.data.html);
                } else {
                    // Handle error - maybe show a message in the widget
                    tableWrapper.html('<tr><td colspan="4">' + (wll_widget_data.error_message || 'Error loading data.') + '</td></tr>');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                 // Handle error - maybe show a message in the widget
                 tableWrapper.html('<tr><td colspan="4">' + (wll_widget_data.error_message || 'Error loading data.') + '</td></tr>');
            },
            complete: function() {
                // Hide loading indicator and restore opacity
                loadingIndicator.hide();
                tableWrapper.css('opacity', 1);
            }
        });
    });
});
