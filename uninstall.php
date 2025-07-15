<?php
// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Get settings
$settings = get_option('sffu_settings', array());

// Only proceed with deletion if explicitly enabled
if (!empty($settings['cleanup_on_uninstall'])) {
    // Delete all files
    $upload_dir = trailingslashit($settings['upload_dir'] ?? WP_CONTENT_DIR . '/secure-uploads/');
    if (is_dir($upload_dir)) {
        $files = glob($upload_dir . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        @rmdir($upload_dir);
    }

    // Delete database tables
    global $wpdb;
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}sffu_files");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}sffu_logs");
}

// Always delete options
delete_option('sffu_settings');
delete_option('sffu_version');
delete_option('sffu_link_expiry_enabled');
delete_option('sffu_link_expiry_interval');
delete_option('sffu_link_expiry_unit');
delete_option('sffu_cleanup_enabled');
delete_option('sffu_cleanup_interval');
delete_option('sffu_cleanup_unit');
delete_option('sffu_allowed_roles');
delete_option('sffu_file_cipher_key');
delete_option('sffu_keep_records_on_uninstall');
delete_option('sffu_delete_files_on_uninstall');

// Remove scheduled events
wp_clear_scheduled_hook('sffu_cleanup_files'); 