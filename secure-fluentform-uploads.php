<?php
/**
 * Plugin Name: Secure FluentForm Uploads
 * Description: Moves FluentForm uploads to a private folder, renames them, encrypts metadata, and allows admin-only access.
 * Version: 1.0.3
 * Author: Making The Impact LLC
 * Requires at least: 5.6
 * Requires PHP: 7.2
 */

/* 
TO DO: 
* Uploaded files page: fix bug showing duplicate entries, fix broken links to form entry submissions - DONE
* Try to save the form ID when saving the file to create working links for uploaded files to form submissions - DONE
* Test the plugin thoroughly with different options, file types, file sizes, etc.
* When plugin is removed, delete the upload directory and all files in it (add option to settings page for this) - DONE
* Use javascript or something to add a button to the entry page to display the uploaded files - use a new page or popupthat will display the uploads based on the entry ID for the form so it can display a list of all the files or that entry. 
*/

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SFFU_VERSION', '1.0.3');
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

// Autoloader
spl_autoload_register(function($class) {
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
    if (!class_exists('FluentForm') && !class_exists('FluentForm\App\Modules\Form\Form')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>' . 
                __('Secure FluentForm Uploads requires Fluent Forms to be installed and activated.', 'secure-fluentform-uploads') . 
                '</p></div>';
        });
        return;
    }

    if (!extension_loaded('openssl')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>' . 
                __('Secure FluentForm Uploads requires the OpenSSL PHP extension to be installed.', 'secure-fluentform-uploads') . 
                '</p></div>';
        });
        return;
    }

    // Initialize components
    SFFU_Core::get_instance();
    SFFU_Security::get_instance();
    SFFU_File_Processor::get_instance();
    SFFU_Hosting_Compatibility::get_instance();
    SFFU_UI::get_instance();
    SFFU_Admin::get_instance();

    // Register settings
    add_action('admin_init', 'sffu_register_settings');
}
add_action('plugins_loaded', 'sffu_init');

// Register settings
function sffu_register_settings() {
    register_setting('sffu_settings', 'sffu_settings', array(
        'type' => 'array',
        'sanitize_callback' => 'sffu_sanitize_settings'
    ));
}

// Sanitize settings
function sffu_sanitize_settings($input) {
    $sanitized = array();
    
    // Upload directory
    if (isset($input['upload_dir'])) {
        $sanitized['upload_dir'] = sanitize_text_field($input['upload_dir']);
    }
    
    // Max file size
    if (isset($input['max_file_size'])) {
        $sanitized['max_file_size'] = absint($input['max_file_size']);
    }
    
    // Allowed types
    if (isset($input['allowed_types']) && is_array($input['allowed_types'])) {
        $sanitized['allowed_types'] = array_map('sanitize_text_field', $input['allowed_types']);
    }
    
    // Allowed roles
    if (isset($input['allowed_roles']) && is_array($input['allowed_roles'])) {
        $sanitized['allowed_roles'] = array_map('sanitize_text_field', $input['allowed_roles']);
    }
    
    // File expiry
    if (isset($input['file_expiry'])) {
        $sanitized['file_expiry'] = absint($input['file_expiry']);
    }

    // Link expiry settings
    $sanitized['link_expiry_enabled'] = isset($input['link_expiry_enabled']) ? (bool)$input['link_expiry_enabled'] : false;
    if (isset($input['link_expiry_interval'])) {
        $sanitized['link_expiry_interval'] = absint($input['link_expiry_interval']);
    }
    if (isset($input['link_expiry_unit'])) {
        $sanitized['link_expiry_unit'] = sanitize_text_field($input['link_expiry_unit']);
    }
    
    // Cleanup settings
    $sanitized['cleanup_enabled'] = isset($input['cleanup_enabled']) ? (bool)$input['cleanup_enabled'] : false;
    if (isset($input['cleanup_interval'])) {
        $sanitized['cleanup_interval'] = absint($input['cleanup_interval']);
    }
    if (isset($input['cleanup_unit'])) {
        $sanitized['cleanup_unit'] = sanitize_text_field($input['cleanup_unit']);
    }
    
    // Cleanup on uninstall
    $sanitized['cleanup_on_uninstall'] = isset($input['cleanup_on_uninstall']) ? (bool)$input['cleanup_on_uninstall'] : false;
    
    // Enabled forms
    if (isset($input['enabled_forms'])) {
        if ($input['enabled_forms'] === 'all') {
            $sanitized['enabled_forms'] = 'all';
        } elseif (is_array($input['enabled_forms'])) {
            $sanitized['enabled_forms'] = array_map('absint', $input['enabled_forms']);
        }
    }
    
    return $sanitized;
}

// Activation hook
register_activation_hook(__FILE__, 'sffu_activate');
function sffu_activate() {
    // Set default settings
    $default_settings = array(
        'max_file_size' => min(wp_max_upload_size() / 1024 / 1024, 10), // Convert bytes to MB, max 10MB default
        'upload_dir' => WP_CONTENT_DIR . '/secure-uploads/',
        'allowed_types' => array(
            // Images
            'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'ico',
            // Documents
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf', 'odt', 'ods', 'odp',
            // Archives
            'zip', 'rar', '7z', 'tar', 'gz',
            // Audio
            'mp3', 'wav', 'ogg', 'm4a', 'wma',
            // Video
            'mp4', 'avi', 'mov', 'wmv', 'flv', 'webm',
            // Other
            'csv', 'xml', 'json'
        ),
        'allowed_roles' => array('administrator'),
        'file_expiry' => 30,
        'link_expiry_enabled' => false,
        'link_expiry_interval' => 24,
        'link_expiry_unit' => 'hours',
        'cleanup_enabled' => true,
        'cleanup_interval' => 30,
        'cleanup_unit' => 'days',
        'cleanup_on_uninstall' => false,
        'enabled_forms' => 'all' // 'all' or array of form IDs
    );
    
    // Only set defaults if settings don't exist
    if (!get_option('sffu_settings')) {
        update_option('sffu_settings', $default_settings);
    }
    
    // Create upload directory
    $upload_dir = $default_settings['upload_dir'];
    if (!file_exists($upload_dir)) {
        wp_mkdir_p($upload_dir);
    }
    
    // Create .htaccess to protect directory
    $htaccess = $upload_dir . '.htaccess';
    if (!file_exists($htaccess)) {
        $htaccess_content = "Order deny,allow\nDeny from all\n<Files ~ \"\\.php$\">\nDeny from all\n</Files>";
        file_put_contents($htaccess, $htaccess_content);
    }
    
    // Create index.php to prevent directory listing
    $index = $upload_dir . 'index.php';
    if (!file_exists($index)) {
        $index_content = "<?php\n// Silence is golden\nif (!defined('ABSPATH')) {\n    exit;\n}\nheader('HTTP/1.0 403 Forbidden');\nexit;";
        file_put_contents($index, $index_content);
    }

    // Create database tables
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

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
            link_created_at DATETIME NULL,
            status VARCHAR(20) DEFAULT 'active'
        ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }


    // Set version
    update_option('sffu_version', SFFU_VERSION);

}



// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('sffu_cleanup_files');
});

// Register uninstall hook
register_uninstall_hook(__FILE__, 'sffu_uninstall');

function sffu_uninstall() {
    $settings = get_option('sffu_settings', array());
    
    if (!empty($settings['cleanup_on_uninstall'])) {
        // Delete all files
        $upload_dir = sffu_get_upload_dir();
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

        // Delete options
        delete_option('sffu_settings');
        delete_option('sffu_version');
    }
}

// Logging function
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

// Debug logging helper
function sffu_debug_log($message) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log($message);
    }
}

// Add settings link to plugins page
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=secure-fluentform-uploads') . '">' . __('Settings') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
});

// Utility function to get upload directory
function sffu_get_upload_dir() {
    $settings = get_option('sffu_settings', array());
    if (!empty($settings['upload_dir']) && is_string($settings['upload_dir'])) {
        return trailingslashit($settings['upload_dir']);
    }

    return WP_CONTENT_DIR . '/secure-uploads/';
}

// -----------------------------------------------------------------------------
// Shortcode and email helpers
// -----------------------------------------------------------------------------

// Capture the current submission context so the shortcode can access it later.
function sffu_set_current_submission_context( $submission_id, $form_data, $form ) {
    $GLOBALS['sffu_current_submission_id'] = absint( $submission_id );
    $GLOBALS['sffu_current_form_id']       = is_object( $form ) ? absint( $form->id ) : 0;
}
add_action( 'fluentform_submission_inserted', 'sffu_set_current_submission_context', 5, 3 );

// Shortcode to output download links for the current submission.
function sffu_shortcode_download_links( $atts = array() ) {
    $atts = shortcode_atts(
        array(
            'submission_id' => 0,
            'form_id'       => 0,
        ),
        $atts,
        'sffu_download_links'
    );

    if ( ! $atts['submission_id'] && isset( $GLOBALS['sffu_current_submission_id'] ) ) {
        $atts['submission_id'] = absint( $GLOBALS['sffu_current_submission_id'] );
    }

    if ( ! $atts['form_id'] && isset( $GLOBALS['sffu_current_form_id'] ) ) {
        $atts['form_id'] = absint( $GLOBALS['sffu_current_form_id'] );
    }

    // Fallback to request parameters if values are still missing.
    if ( ! $atts['submission_id'] ) {
        if ( isset( $_REQUEST['submission_id'] ) ) {
            $atts['submission_id'] = absint( $_REQUEST['submission_id'] );
        } elseif ( isset( $_REQUEST['entry_id'] ) ) {
            $atts['submission_id'] = absint( $_REQUEST['entry_id'] );
        }
    }

    if ( ! $atts['form_id'] && isset( $_REQUEST['form_id'] ) ) {
        $atts['form_id'] = absint( $_REQUEST['form_id'] );
    }

    if ( ! $atts['submission_id'] || ! $atts['form_id'] ) {
        return '';
    }

    $core  = SFFU_Core::get_instance();
    $files = $core->get_submission_files( $atts['submission_id'], $atts['form_id'] );
    if ( empty( $files ) ) {
        return '';
    }

    $output = '<ul class="sffu-download-links">';
    foreach ( $files as $file ) {
        $url     = $core->modify_upload_url( $file->file_path, $file->file_path );
        $output .= '<li><a href="' . esc_url( $url ) . '">' . esc_html( $file->original_name ) . '</a></li>';
    }
    $output .= '</ul>';

    return $output;
}
add_shortcode( 'sffu_download_links', 'sffu_shortcode_download_links' );

// Ensure shortcodes are parsed in emails.
add_filter( 'wp_mail', function ( $args ) {
    if ( isset( $args['message'] ) ) {
        $args['message'] = do_shortcode( $args['message'] );
    }
    return $args;
} );
