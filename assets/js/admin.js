jQuery(document).ready(function($) {
    // Handle file deletion
    $('.sffu-delete-file').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to delete this file? This action cannot be undone.')) {
            return;
        }
        
        var $button = $(this);
        var file = $button.data('file');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sffu_delete_file',
                file: file,
                nonce: sffu_admin.nonce
            },
            beforeSend: function() {
                $button.prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    $button.closest('tr').fadeOut(400, function() {
                        $(this).remove();
                        if ($('.sffu-files-list tbody tr').length === 0) {
                            $('.sffu-files-list').html('<p>No files uploaded yet.</p>');
                        }
                    });
                } else {
                    alert('Error deleting file: ' + response.data);
                    $button.prop('disabled', false);
                }
            },
            error: function() {
                alert('Error deleting file. Please try again.');
                $button.prop('disabled', false);
            }
        });
    });

    // Handle link expiry settings
    var $linkExpiryEnabled = $('input[name="sffu_link_expiry_enabled"]');
    var $linkExpiryFields = $('input[name="sffu_link_expiry_interval"], select[name="sffu_link_expiry_unit"]');
    
    function toggleLinkExpiryFields() {
        $linkExpiryFields.prop('disabled', !$linkExpiryEnabled.is(':checked'));
    }
    
    $linkExpiryEnabled.on('change', toggleLinkExpiryFields);
    toggleLinkExpiryFields();

    // Handle cleanup settings
    var $cleanupEnabled = $('input[name="sffu_cleanup_enabled"]');
    var $cleanupFields = $('input[name="sffu_cleanup_interval"], select[name="sffu_cleanup_unit"]');
    
    function toggleCleanupFields() {
        $cleanupFields.prop('disabled', !$cleanupEnabled.is(':checked'));
    }
    
    $cleanupEnabled.on('change', toggleCleanupFields);
    toggleCleanupFields();
}); 