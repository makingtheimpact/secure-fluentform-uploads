<?php
// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Check if we should delete records
$delete_records = get_option('sffu_keep_records_on_uninstall', false);
$delete_files = get_option('sffu_delete_files_on_uninstall', false);

// Delete plugin options
delete_option('sffu_upload_dir');
delete_option('sffu_link_expiry_enabled');
delete_option('sffu_link_expiry_interval');
delete_option('sffu_link_expiry_unit');
delete_option('sffu_cleanup_enabled');
delete_option('sffu_cleanup_interval');
delete_option('sffu_cleanup_unit');
delete_option('sffu_allowed_types');
delete_option('sffu_allowed_roles');
delete_option('sffu_file_cipher_key');
delete_option('sffu_keep_records_on_uninstall');
delete_option('sffu_delete_files_on_uninstall');

// Delete all file metadata
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'sffu_file_%'");

// Only delete tables if the option is checked (delete_records is true)
if ($delete_records) {
    // Delete logs table
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}sffu_logs");
    // Delete files table
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}sffu_files");
}

// Remove scheduled events
wp_clear_scheduled_hook('sffu_cleanup_files');

// Delete upload directory and its contents if enabled
if ($delete_files) {
    $upload_dir = get_option('sffu_upload_dir', WP_CONTENT_DIR . '/secure-uploads/');
    if (is_dir($upload_dir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($upload_dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        rmdir($upload_dir);
    }
} 