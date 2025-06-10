<?php
/**
 * Plugin Name: Secure FluentForm Uploads
 * Description: Moves FluentForm uploads to a private folder, renames them, encrypts metadata, and allows admin-only access.
 * Version: 1.0
 * Author: Making The Impact LLC
 */

defined('ABSPATH') || exit;

// Define plugin constants
define('SFFU_VERSION', '1.0');
define('SFFU_PLUGIN_FILE', __FILE__);
define('SFFU_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SFFU_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SFFU_UPLOAD_DIR', WP_CONTENT_DIR . '/private-uploads/fluentform/');
define('SFFU_CIPHER_KEY', wp_generate_password(64, true, true));
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
    $prefix = 'SFFU_';
    $base_dir = SFFU_PLUGIN_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . 'class-' . strtolower(str_replace('_', '-', $relative_class)) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Initialize plugin
function sffu_init() {
    // Check requirements
    if (!function_exists('openssl_encrypt') || !function_exists('openssl_decrypt')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>Secure FluentForm Uploads requires OpenSSL PHP extension to be installed.</p></div>';
        });
        return;
    }

    // Initialize components
    SFFU_Core::get_instance();
    SFFU_Admin::get_instance();
    SFFU_Updater::get_instance();
}
add_action('plugins_loaded', 'sffu_init');

// Utility function to get upload directory
function sffu_get_upload_dir() {
    $custom = get_option('sffu_upload_dir');
    if ($custom && is_string($custom)) {
        $dir = trailingslashit($custom);
    } else {
        $upload = wp_upload_dir();
        $dir = trailingslashit($upload['basedir']) . 'private-fluentform/';
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
    $htaccess = SFFU_UPLOAD_DIR . '.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Order deny,allow\nDeny from all");
    }

    // Create index.php
    $index = SFFU_UPLOAD_DIR . 'index.php';
    if (!file_exists($index)) {
        file_put_contents($index, '<?php // Silence is golden');
    }

    // Schedule cleanup
    if (!wp_next_scheduled('sffu_cleanup_files')) {
        wp_schedule_event(time(), 'daily', 'sffu_cleanup_files');
    }
});