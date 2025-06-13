<?php
/**
 * Plugin Name: Secure FluentForm Uploads
 * Description: Moves FluentForm uploads to a private folder, renames them, encrypts metadata, and allows admin-only access.
 * Version: 1.0.1
 * Author: Making The Impact LLC
 */

/* 
TO DO: 
* Uploaded files page: fix bug showing duplicate entries, fix broken links to form entry submissions - DONE
* Try to save the form ID when saving the file to create working links for uploaded files to form submissions - DONE
* Test the plugin thoroughly with different options, file types, file sizes, etc.
* When plugin is removed, delete the upload directory and all files in it (add option to settings page for this) - DONE
* Use javascript or something to add a button to the entry page to display the uploaded files - use a new page or popupthat will display the uploads based on the entry ID for the form so it can display a list of all the files or that entry. 
*/



defined('ABSPATH') || exit;

// Define plugin constants
define('SFFU_VERSION', '1.0');
define('SFFU_PLUGIN_FILE', __FILE__);
define('SFFU_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SFFU_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SFFU_UPLOAD_DIR', WP_CONTENT_DIR . '/secure-uploads/');
define('SFFU_FILE_EXPIRY', 7 * 24 * 60 * 60);
define('SFFU_CLEANUP_EXPIRY', 30 * 24 * 60 * 60);
define('SFFU_ALLOWED_MIME_TYPES', [
    // Images
    'image/jpeg', 'image/pjpeg', 'image/png', 'image/gif', 'image/bmp',
    // Audio
    'audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/x-wav', 'audio/ogg', 'audio/vorbis', 'audio/x-vorbis', 'audio/oga', 'audio/x-ms-wma', 'audio/x-m4a', 'audio/mp4', 'audio/x-m4a', 'audio/x-realaudio', 'audio/x-pn-realaudio', 'audio/mid', 'audio/midi', 'audio/x-midi',
    // Video
    'video/x-msvideo', 'video/avi', 'video/msvideo', 'video/x-flv', 'video/quicktime', 'video/ogg', 'video/x-matroska', 'video/mp4', 'video/x-m4v', 'video/mpeg', 'video/x-mpeg', 'video/mpe', 'video/mpg', 'video/x-ms-wmv',
    // PDF
    'application/pdf',
    // Docs
    'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-access', 'application/vnd.oasis.opendocument.text', 'application/vnd.oasis.opendocument.presentation', 'application/vnd.oasis.opendocument.spreadsheet', 'application/vnd.oasis.opendocument.graphics', 'application/vnd.oasis.opendocument.chart', 'application/vnd.oasis.opendocument.database', 'application/vnd.oasis.opendocument.formula', 'application/rtf', 'text/plain',
    // CSV
    'text/csv',
    // Zip Archives
    'application/zip', 'application/x-zip-compressed', 'application/x-gzip', 'application/gzip', 'application/x-rar-compressed', 'application/x-7z-compressed',
    // Executable
    'application/x-msdownload', 'application/x-msdos-program', 'application/x-ms-installer', 'application/x-exe', 'application/x-executable', 'application/x-dosexec', 'application/octet-stream'
]);

// Autoload classes
spl_autoload_register(function($class) {
    // Ensure WordPress is loaded
    if (!function_exists('wp_get_current_user')) {
        return;
    }

    $prefix = 'SFFU_';
    $base_dir = SFFU_PLUGIN_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . 'class-' . strtolower(str_replace('_', '-', $relative_class)) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// Initialize plugin
function sffu_init() {
    // Check if WordPress is fully loaded
    if (!function_exists('wp_get_current_user')) {
        return;
    }
    
    // Check if FluentForms is active
    if (!class_exists('FluentForm\App\Modules\Form\Form')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>Secure FluentForm Uploads requires FluentForms to be installed and activated. <a href="' . 
                 esc_url(admin_url('plugin-install.php?s=fluentform&tab=search&type=term')) . 
                 '">Install FluentForms</a> or <a href="' . 
                 esc_url('https://wordpress.org/plugins/fluentform/') . 
                 '" target="_blank">get it here</a>.</p></div>';
        });
        return;
    }
    
    // Check requirements
    if (!function_exists('openssl_encrypt') || !function_exists('openssl_decrypt')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>Secure FluentForm Uploads requires OpenSSL PHP extension to be installed.</p></div>';
        });
        return;
    }

    // Define cipher key after WordPress is loaded
    if (!defined('SFFU_CIPHER_KEY')) {
        define('SFFU_CIPHER_KEY', wp_generate_password(64, true, true));
    }

    // Check if required classes exist
    $required_classes = array('SFFU_Core', 'SFFU_Admin', 'SFFU_Updater');
    $missing_classes = array();
    
    foreach ($required_classes as $class) {
        if (!class_exists($class)) {
            $missing_classes[] = $class;
        }
    }
    
    if (!empty($missing_classes)) {
        add_action('admin_notices', function() use ($missing_classes) {
            echo '<div class="error"><p>Secure FluentForm Uploads: The following required classes are missing: ' . 
                 esc_html(implode(', ', $missing_classes)) . '. Please reinstall the plugin.</p></div>';
        });
        return;
    }

    // Initialize components
    try {
        SFFU_Core::get_instance();
        SFFU_Admin::get_instance();
        SFFU_Updater::get_instance();
    } catch (Exception $e) {
        add_action('admin_notices', function() use ($e) {
            echo '<div class="error"><p>Secure FluentForm Uploads Error: ' . esc_html($e->getMessage()) . '</p></div>';
        });
    }
}
add_action('plugins_loaded', 'sffu_init');

// Add settings link to plugins page
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=secure-fluentform-uploads') . '">' . __('Settings') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
});

// Utility function to get upload directory
function sffu_get_upload_dir() {
    $custom = get_option('sffu_upload_dir');
    if ($custom && is_string($custom)) {
        $dir = trailingslashit($custom);
    } else {
        $upload = wp_upload_dir();
        $dir = trailingslashit($upload['basedir']) . 'fluentform-uploads/';
    }
    return $dir;
}

// Utility function to log actions
function sffu_log($action, $file = '', $details = '') {
    global $wpdb;
    $table = $wpdb->prefix . 'sffu_logs';
    
    $wpdb->insert(
        $table,
        array(
            'action' => $action,
            'file' => $file,
            'user_id' => get_current_user_id(),
            'user_login' => wp_get_current_user()->user_login,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'details' => $details
        ),
        array('%s', '%s', '%d', '%s', '%s', '%s')
    );
}

// Register activation hook
register_activation_hook(__FILE__, function() {
    // Create upload directory
    if (!file_exists(SFFU_UPLOAD_DIR)) {
        if (!wp_mkdir_p(SFFU_UPLOAD_DIR)) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p>Failed to create upload directory. Please check permissions.</p></div>';
            });
            return;
        }
    }

    // Create .htaccess
    $upload_dir = sffu_get_upload_dir();
    if (!file_exists($upload_dir)) {
        if (!wp_mkdir_p($upload_dir)) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p>Failed to create upload directory. Please check permissions.</p></div>';
            });
            return;
        }
    }

    $htaccess = $upload_dir . '.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Order deny,allow\nDeny from all");
    }

    // Create index.php
    $index = $upload_dir . 'index.php';
    if (!file_exists($index)) {
        file_put_contents($index, '<?php // Silence is golden');
    }

    // Create log table
    global $wpdb;
    $log_table = $wpdb->prefix . 'sffu_logs';
    if ($wpdb->get_var("SHOW TABLES LIKE '$log_table'") != $log_table) {
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $log_table (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            action VARCHAR(32) NOT NULL,
            file VARCHAR(255),
            user_id BIGINT,
            user_login VARCHAR(60),
            ip VARCHAR(45),
            time DATETIME DEFAULT CURRENT_TIMESTAMP,
            details TEXT
        ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    // Create files table
    $files_table = $wpdb->prefix . 'sffu_files';
    if ($wpdb->get_var("SHOW TABLES LIKE '$files_table'") != $files_table) {
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $files_table (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            upload_user_id BIGINT,
            file_name VARCHAR(255) NOT NULL,
            filename VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            mime_type VARCHAR(100),
            file_size BIGINT,
            encryption_key VARCHAR(255),
            iv VARCHAR(255),
            upload_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(20) DEFAULT 'active'
        ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
});

// Add deactivation hook to clean up
register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('sffu_cleanup_files');
});