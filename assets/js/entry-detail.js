jQuery(document).ready(function($) {
    console.log('SFFU Entry Detail script loaded');
    
    // Only proceed if user has access
    if (!sffuEntry.canAccess) {
        console.log('User does not have access to secure downloads');
        return;
    }

    // Get form ID from PHP
    const formId = sffuEntry.formId;
    let currentSubmissionId = null;

    console.log('Form ID:', formId);
    console.log('Current URL:', window.location.href);

    if (!formId) {
        console.log('Missing form ID');
        return;
    }

    // Function to get submission ID from URL hash
    function getSubmissionIdFromHash() {
        const hash = window.location.hash;
        console.log('Current hash:', hash);
        const match = hash.match(/\/entries\/(\d+)/);
        const id = match ? match[1] : null;
        console.log('Extracted submission ID:', id);
        return id;
    }

    // Function to create the downloads modal
    function createDownloadsModal(files) {
        console.log('Creating downloads modal with files:', files);
        
        if (!files || files.length === 0) {
            console.log('No files to display');
            return;
        }

        // Remove existing modal if it exists
        $('#sffu-downloads-modal').remove();

        const modal = $('<div id="sffu-downloads-modal" class="sffu-modal">' +
            '<div class="sffu-modal-content">' +
            '<span class="sffu-close">&times;</span>' +
            '<h2>Secure Downloads</h2>' +
            '<table class="wp-list-table widefat fixed striped">' +
            '<thead><tr>' +
            '<th>File Name</th>' +
            '<th>Upload Date</th>' +
            '<th>Size</th>' +
            '<th>Actions</th>' +
            '</tr></thead>' +
            '<tbody></tbody>' +
            '</table>' +
            '</div>' +
            '</div>');

        const tbody = modal.find('tbody');
        
        files.forEach(function(file) {
            const row = $('<tr></tr>');
            row.append('<td>' + file.original_name + '</td>');
            row.append('<td>' + new Date(file.upload_time).toLocaleString() + '</td>');
            row.append('<td>' + formatFileSize(file.file_size) + '</td>');
            
            const downloadBtn = $('<a href="#" class="button button-small">Download</a>');
            // Get file-specific nonce via AJAX
            $.ajax({
                url: sffuEntry.ajaxurl,
                type: 'POST',
                data: {
                    action: 'sffu_get_download_nonce',
                    nonce: sffuEntry.nonce,
                    filename: file.filename
                },
                success: function(response) {
                    if (response.success) {
                        const downloadUrl = sffuEntry.ajaxurl + '?action=sffu_download&file=' + encodeURIComponent(file.filename) + '&_wpnonce=' + response.data.nonce;
                        downloadBtn.attr('href', downloadUrl);
                        downloadBtn.attr('target', '_blank');
                    } else {
                        console.error('Failed to get download nonce:', response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error getting nonce:', error);
                }
            });
            
            row.append($('<td></td>').append(downloadBtn));
            tbody.append(row);
        });

        // Add modal to body
        $('body').append(modal);

        // Show modal immediately
        modal.css({
            'display': 'block',
            'z-index': '999999',
            'background': 'rgba(0,0,0,0.7)'
        });

        // Add close button functionality
        modal.find('.sffu-close').on('click', function() {
            modal.remove();
        });

        // Close modal when clicking outside
        $(window).on('click.sffu', function(event) {
            if (event.target === modal[0]) {
                modal.remove();
                $(window).off('click.sffu');
            }
        });

        return modal;
    }

    // Function to format file size
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Function to create admin bar button
    function createAdminBarButton() {
        // Remove existing button if it exists
        $('#wp-admin-bar-sffu-view-downloads').remove();

        if (!currentSubmissionId) {
            console.log('No submission ID available for button creation');
            return;
        }

        console.log('Creating button with submission ID:', currentSubmissionId);

        // Create new menu item
        var menuItem = $('<li id="wp-admin-bar-sffu-view-downloads"><a href="#" class="ab-item">View Downloads</a></li>');

        // Insert after Fluent Forms Pro menu if present, else at end
        var fluentMenu = $('#wp-admin-bar-fluent_forms_pro');
        if (fluentMenu.length) {
            fluentMenu.after(menuItem);
        } else {
            $('#wp-admin-bar-top-secondary, #wp-admin-bar-root-default').append(menuItem);
        }

        // Test click handler immediately
        console.log('Testing click handler...');
        menuItem.find('a').trigger('click');

        // Click handler
        menuItem.find('a').on('click', function(e) {
            console.log('Click handler triggered');
            e.preventDefault();
            e.stopPropagation();
            
            console.log('Button clicked');
            console.log('Current submission ID:', currentSubmissionId);
            console.log('Form ID:', formId);
            console.log('AJAX URL:', sffuEntry.ajaxurl);
            console.log('Nonce:', sffuEntry.nonce);
            
            if (!currentSubmissionId) {
                console.error('No submission ID available');
                return;
            }

            if (!sffuEntry.ajaxurl) {
                console.error('AJAX URL not defined');
                return;
            }

            if (!sffuEntry.nonce) {
                console.error('Nonce not defined');
                return;
            }
            
            $.ajax({
                url: sffuEntry.ajaxurl,
                type: 'POST',
                data: {
                    action: 'sffu_get_submission_files',
                    nonce: sffuEntry.nonce,
                    submission_id: currentSubmissionId,
                    form_id: formId
                },
                beforeSend: function() {
                    console.log('Sending AJAX request...');
                },
                success: function(response) {
                    console.log('AJAX response received:', response);
                    if (response.success && response.data) {
                        console.log('Creating modal with data:', response.data);
                        createDownloadsModal(response.data);
                    } else {
                        console.error('AJAX response error:', response);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', {
                        status: status,
                        error: error,
                        response: xhr.responseText
                    });
                }
            });
        });

        // Also bind click handler to document for event delegation
        $(document).on('click', '#wp-admin-bar-sffu-view-downloads a', function(e) {
            console.log('Document click handler triggered');
            e.preventDefault();
            e.stopPropagation();
            
            console.log('Button clicked via document handler');
            console.log('Current submission ID:', currentSubmissionId);
            
            if (!currentSubmissionId) {
                console.error('No submission ID available');
                return;
            }

            $.ajax({
                url: sffuEntry.ajaxurl,
                type: 'POST',
                data: {
                    action: 'sffu_get_submission_files',
                    nonce: sffuEntry.nonce,
                    submission_id: currentSubmissionId,
                    form_id: formId
                },
                beforeSend: function() {
                    console.log('Sending AJAX request via document handler...');
                },
                success: function(response) {
                    console.log('AJAX response received via document handler:', response);
                    if (response.success && response.data) {
                        console.log('Creating modal with data:', response.data);
                        createDownloadsModal(response.data);
                    } else {
                        console.error('AJAX response error:', response);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', {
                        status: status,
                        error: error,
                        response: xhr.responseText
                    });
                }
            });
        });
    }

    // Watch for URL hash changes
    let lastHash = window.location.hash;
    currentSubmissionId = getSubmissionIdFromHash();
    
    // Create button on initial load if we have a submission ID
    if (currentSubmissionId) {
        createAdminBarButton();
    }

    setInterval(function() {
        const currentHash = window.location.hash;
        if (currentHash !== lastHash) {
            console.log('URL hash changed from', lastHash, 'to', currentHash);
            lastHash = currentHash;
            
            // Get new submission ID
            const newSubmissionId = getSubmissionIdFromHash();
            if (newSubmissionId) {
                currentSubmissionId = newSubmissionId;
                createAdminBarButton();
            }
        }
    }, 100);

    // Add CSS for modal
    const style = $('<style>' +
        '.sffu-modal { display: none; position: fixed; z-index: 999999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); }' +
        '.sffu-modal-content { background-color: #fefefe; margin: 5% auto; padding: 20px; border-radius: 20px; border: 1px solid #888; width: 80%; max-width: 800px; position: relative; }' +
        '.sffu-close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }' +
        '.sffu-close:hover { color: black; }' +
        '.sffu-modal-content .wp-list-table { margin-top: 20px; }' +
        '</style>');
    $('head').append(style);
}); 