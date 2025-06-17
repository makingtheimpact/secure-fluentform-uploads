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

    // Task status polling
    let taskStatusInterval = null;

    function updateTaskStatus(taskId) {
        $.ajax({
            url: sffuTaskStatus.ajaxurl,
            data: {
                action: 'sffu_get_task_status',
                task_id: taskId,
                nonce: sffuTaskStatus.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    const status = response.data;
                    const $statusDiv = $('#sffu-cleanup-status');
                    const $progressBar = $statusDiv.find('.sffu-progress');
                    const $message = $statusDiv.find('.sffu-status-message');

                    $statusDiv.show();
                    $progressBar.css('width', status.progress + '%');
                    $message.text(status.message);

                    if (status.status === 'completed' || status.status === 'error') {
                        clearInterval(taskStatusInterval);
                        if (status.status === 'completed') {
                            $statusDiv.addClass('completed');
                        } else {
                            $statusDiv.addClass('error');
                        }
                    }
                }
            }
        });
    }

    // Start cleanup process
    $('#sffu-start-cleanup').on('click', function(e) {
        e.preventDefault();
        const taskId = 'cleanup_' + Math.floor(Date.now() / 1000);
        
        // Clear any existing interval
        if (taskStatusInterval) {
            clearInterval(taskStatusInterval);
        }

        // Start polling for status
        taskStatusInterval = setInterval(function() {
            updateTaskStatus(taskId);
        }, sffuTaskStatus.checkInterval);

        // Trigger the cleanup process
        $.ajax({
            url: sffuTaskStatus.ajaxurl,
            data: {
                action: 'sffu_cleanup_files_cron',
                nonce: sffuTaskStatus.nonce
            }
        });
    });

    // File processing
    function processFile(fileId, operation) {
        const taskId = 'file_' + fileId;
        let currentChunk = 0;
        let isPaused = false;

        function processNextChunk() {
            if (isPaused) {
                setTimeout(processNextChunk, 5000); // Wait 5 seconds before retrying
                return;
            }

            $.ajax({
                url: sffuTaskStatus.ajaxurl,
                data: {
                    action: 'sffu_process_file',
                    file_id: fileId,
                    chunk: currentChunk,
                    nonce: sffuTaskStatus.nonce
                },
                method: 'POST',
                success: function(response) {
                    if (response.success && response.data) {
                        const status = response.data;
                        updateFileProgress(status);

                        if (status.paused) {
                            isPaused = true;
                            setTimeout(processNextChunk, 5000);
                        } else if (status.status === 'running') {
                            currentChunk++;
                            processNextChunk();
                        }
                    }
                },
                error: function() {
                    isPaused = true;
                    setTimeout(processNextChunk, 5000);
                }
            });
        }

        // Start processing
        processNextChunk();
    }

    function updateFileProgress(status) {
        const $statusDiv = $('#sffu-file-status-' + status.file_id);
        const $progressBar = $statusDiv.find('.sffu-progress');
        const $message = $statusDiv.find('.sffu-status-message');

        $statusDiv.show();
        $progressBar.css('width', status.progress + '%');
        $message.text(status.message);

        if (status.status === 'completed') {
            $statusDiv.addClass('completed');
        } else if (status.status === 'error') {
            $statusDiv.addClass('error');
        }
    }

    // Handle file upload
    $('#sffu-file-upload').on('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;

        const $statusDiv = $('<div id="sffu-file-status-' + file.name + '" class="sffu-file-status">' +
            '<div class="sffu-progress-bar"><div class="sffu-progress"></div></div>' +
            '<p class="sffu-status-message">Preparing to process file...</p>' +
            '</div>').insertAfter(this);

        // Start processing the file
        processFile(file.name, 'encrypt');
    });

    // Tab handling
    $('.sffu-tab').on('click', function(e) {
        e.preventDefault();
        var target = $(this).data('target');
        
        $('.sffu-tab').removeClass('active');
        $('.sffu-tab-content').removeClass('active');
        
        $(this).addClass('active');
        $('#' + target).addClass('active');
    });

    // Progress bar updates
    function updateProgress(taskId) {
        $.ajax({
            url: sffuUI.ajaxurl,
            type: 'GET',
            data: {
                action: 'sffu_get_progress',
                task_id: taskId,
                nonce: sffuUI.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    var status = response.data;
                    var $container = $('.sffu-progress-container[data-task-id="' + taskId + '"]');
                    
                    $container.find('.sffu-progress').css('width', status.progress + '%');
                    $container.find('.sffu-progress-text').text(status.message);
                    $container.find('.sffu-progress-percentage').text(status.progress + '%');
                    
                    if (status.status === 'completed' || status.status === 'error' || status.status === 'cancelled') {
                        clearInterval(progressInterval);
                        if (status.status === 'completed') {
                            $container.find('.sffu-cancel-operation').remove();
                        }
                    }
                }
            }
        });
    }

    // Cancel operation
    $('.sffu-cancel-operation').on('click', function() {
        var taskId = $(this).data('task-id');
        var $button = $(this);
        
        if (!confirm(sffuUI.i18n.confirm)) {
            return;
        }
        
        $button.prop('disabled', true);
        
        $.ajax({
            url: sffuUI.ajaxurl,
            type: 'POST',
            data: {
                action: 'sffu_cancel_operation',
                task_id: taskId,
                nonce: sffuUI.nonce
            },
            success: function(response) {
                if (response.success) {
                    $button.closest('.sffu-progress-container').find('.sffu-progress-text')
                        .text(sffuUI.i18n.cancelled);
                    $button.remove();
                }
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });

    // File upload handling
    $('.sffu-file-upload').on('change', function() {
        var $form = $(this).closest('form');
        var $submit = $form.find('input[type="submit"]');
        var $progress = $form.find('.sffu-progress-container');
        
        if (this.files.length > 0) {
            $submit.prop('disabled', false);
        } else {
            $submit.prop('disabled', true);
        }
    });

    // Settings form handling
    $('.sffu-settings-form').on('submit', function(e) {
        // Let the form submit normally - WordPress will handle it
        return true;
    });

    // Notice handling
    function showNotice(type, message) {
        var $notice = $('<div class="sffu-notice ' + type + '"><p>' + message + '</p></div>');
        $('.wrap h1').after($notice);
        
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
    }

    // Initialize any active progress bars
    $('.sffu-progress-container[data-task-id]').each(function() {
        var taskId = $(this).data('task-id');
        var progressInterval = setInterval(function() {
            updateProgress(taskId);
        }, 1000);
    });

    // Handle Select All button
    $('#sffu-select-all').on('click', function() {
        $('.sffu-allowed-types input[type="checkbox"]').prop('checked', true);
    });

    // Handle Unselect All button
    $('#sffu-unselect-all').on('click', function() {
        $('.sffu-allowed-types input[type="checkbox"]').prop('checked', false);
    });

    // Reset settings
    $('#sffu-reset-settings').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to reset all settings to their default values? This action cannot be undone.')) {
            return;
        }
        
        var $button = $(this);
        $button.prop('disabled', true);
        
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sffu_reset_settings',
                nonce: sffu_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', 'Settings have been reset to default values.');
                    // Update form fields with default values from response
                    if (response.data && response.data.settings) {
                        Object.keys(response.data.settings).forEach(function(key) {
                            var $field = $('[name="' + key + '"]');
                            if ($field.length) {
                                if ($field.is(':checkbox')) {
                                    $field.prop('checked', response.data.settings[key]);
                                } else {
                                    $field.val(response.data.settings[key]);
                                }
                            }
                        });
                    }
                } else {
                    showNotice('error', 'Error resetting settings: ' + response.data);
                }
            },
            error: function() {
                showNotice('error', 'Error resetting settings. Please try again.');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });

    // Form Selection
    // Toggle form list visibility
    $('input[name="sffu_settings[enabled_forms]"]').on('change', function() {
        if ($(this).val() === 'all') {
            $('.sffu-form-list').hide();
            $('.sffu-form-list input[type="checkbox"]').prop('checked', false);
        } else {
            $('.sffu-form-list').show();
        }
    });

    // Form search functionality
    $('#sffu-form-search').on('input', function() {
        const searchTerm = $(this).val().toLowerCase();
        $('.sffu-form-item').each(function() {
            const formTitle = $(this).text().toLowerCase();
            if (formTitle.includes(searchTerm)) {
                $(this).removeClass('hidden').addClass('highlight');
            } else {
                $(this).addClass('hidden').removeClass('highlight');
            }
        });
    });

    // Select/Unselect all forms
    $('#sffu-select-all-forms').on('click', function() {
        $('.sffu-form-item:not(.hidden) input[type="checkbox"]').prop('checked', true);
    });

    $('#sffu-unselect-all-forms').on('click', function() {
        $('.sffu-form-item:not(.hidden) input[type="checkbox"]').prop('checked', false);
    });

    // Click anywhere on the form item to toggle checkbox
    $('.sffu-form-item').on('click', function(e) {
        if (!$(e.target).is('input[type="checkbox"]')) {
            const checkbox = $(this).find('input[type="checkbox"]');
            checkbox.prop('checked', !checkbox.prop('checked'));
        }
    });
}); 