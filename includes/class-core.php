<?php
/**
 * Core functionality for Secure FluentForm Uploads
 */

if (!defined('ABSPATH')) {
    exit;
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

class SFFU_Core {
    private static $instance = null;
    private $upload_dir;
    private $cipher_key;
    private $file_expiry;
    private $cleanup_expiry;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        // Admin notices
        add_action('admin_notices', array($this, 'check_requirements'));
        
        // File handling - direct hooks
        add_filter('fluentform_upload_file_path', array($this, 'modify_upload_path'), 10, 2);
        add_filter('fluentform_upload_file_url', array($this, 'modify_upload_url'), 10, 2);
        add_filter('fluentform_upload_file_name', array($this, 'modify_upload_filename'), 10, 2);
        
        // Add direct upload handling
        add_action('fluentform_before_file_upload', array($this, 'before_file_upload'), 10, 2);
        add_action('fluentform_after_file_upload', array($this, 'after_file_upload'), 10, 2);
        
        // Add form submission hooks
        add_action('fluentform_submission_inserted', array($this, 'handle_submission'), 10, 3);
        add_filter('fluentform_submission_data', array($this, 'process_submission_data'), 10, 2);
        
        // Add AJAX handlers
        add_action('wp_ajax_sffu_download', array($this, 'handle_download'));
        add_action('wp_ajax_nopriv_sffu_download', array($this, 'handle_download'));
        
        // Cleanup
        add_action('sffu_cleanup_files', array($this, 'cleanup_files'));
        if (!wp_next_scheduled('sffu_cleanup_files')) {
            wp_schedule_event(time(), 'daily', 'sffu_cleanup_files');
        }

        // Directory management
        add_action('admin_init', array($this, 'check_upload_directory'));
        
        // Initialize constants
        $this->init_constants();

        // Ensure logs table exists
        $this->ensure_logs_table();
    }

    private function init_constants() {
        $this->upload_dir = get_option('sffu_upload_dir', WP_CONTENT_DIR . '/secure-uploads/');
        $this->cipher_key = defined('SFFU_CIPHER_KEY') ? SFFU_CIPHER_KEY : wp_generate_password(64, true, true);
        $this->file_expiry = defined('SFFU_FILE_EXPIRY') ? SFFU_FILE_EXPIRY : 7 * 24 * 60 * 60;
        $this->cleanup_expiry = defined('SFFU_CLEANUP_EXPIRY') ? SFFU_CLEANUP_EXPIRY : 30 * 24 * 60 * 60;
    }

    public function activate() {
        // Create upload directory
        if (!file_exists($this->upload_dir)) {
            if (!wp_mkdir_p($this->upload_dir)) {
                add_action('admin_notices', function() {
                    echo '<div class="error"><p>Failed to create upload directory. Please check permissions.</p></div>';
                });
                return;
            }
        }

        // Create .htaccess
        $htaccess = $this->upload_dir . '.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Order deny,allow\nDeny from all");
        }

        // Create index.php
        $index = $this->upload_dir . 'index.php';
        if (!file_exists($index)) {
            file_put_contents($index, '<?php // Silence is golden');
        }

        // Create both tables
        $this->create_log_table();
        $this->ensure_logs_table();
    }

    private function create_log_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'sffu_logs';
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS $table (
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

        // Create files table
        $files_table = $wpdb->prefix . 'sffu_files';
        $sql = "CREATE TABLE IF NOT EXISTS $files_table (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            file_size BIGINT NOT NULL,
            upload_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            upload_user_id BIGINT NOT NULL,
            encryption_key VARCHAR(255) NOT NULL,
            iv VARCHAR(255) NOT NULL,
            status VARCHAR(20) DEFAULT 'active',
            UNIQUE KEY filename (filename)
        ) $charset_collate;";
        dbDelta($sql);
    }

    public function check_requirements() {
        if (!function_exists('openssl_encrypt') || !function_exists('openssl_decrypt')) {
            echo '<div class="error"><p>Secure FluentForm Uploads requires OpenSSL PHP extension to be installed.</p></div>';
        }

        // Check FluentForms upload settings
        $upload_settings = get_option('_fluentform_global_form_settings');
        if ($upload_settings && isset($upload_settings['file_upload_storage']) && $upload_settings['file_upload_storage'] === 'media_library') {
            echo '<div class="error"><p>Secure FluentForm Uploads is not compatible with FluentForms Media Library storage option. Please change the storage setting to "Local Storage" in FluentForms settings.</p></div>';
        }
    }

    private function cleanup_temp_files($original_path) {
        // Get the temp directory
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/fluentform/temp/';
        
        if (!is_dir($temp_dir)) {
            return;
        }

        // Get all files in temp directory
        $temp_files = glob($temp_dir . '*');
        if (empty($temp_files)) {
            return;
        }

        // Get the original file's basename
        $original_basename = basename($original_path);
        
        foreach ($temp_files as $temp_file) {
            if (!is_file($temp_file)) {
                continue;
            }

            $temp_basename = basename($temp_file);
            
            // Check if this is a temp file for our original file
            if (strpos($temp_basename, $original_basename) !== false || 
                strpos($original_basename, $temp_basename) !== false) {
                @unlink($temp_file);
                sffu_log('cleanup', $temp_basename, 'Removed temp file');
            }
        }

        // Also clean up any old temp files (older than 1 hour)
        foreach ($temp_files as $temp_file) {
            if (!is_file($temp_file)) {
                continue;
            }

            if (time() - filemtime($temp_file) > 3600) { // 1 hour
                @unlink($temp_file);
                sffu_log('cleanup', basename($temp_file), 'Removed old temp file');
            }
        }
    }

    public function handle_submission($submission_id, $form_data, $form) {
        if (empty($form_data)) {
            return;
        }

        foreach ($form_data as $key => $value) {
            if (is_array($value) && isset($value[0]) && filter_var($value[0], FILTER_VALIDATE_URL)) {
                // This is a file upload field with URL
                $file_url = $value[0];
                $upload_dir = wp_upload_dir();
                $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $file_url);
                
                if (file_exists($file_path)) {
                    $new_path = $this->modify_upload_path($file_path, array('url' => $file_url));
                    if ($new_path !== $file_path) {
                        // Update the submission data
                        global $wpdb;
                        $table = $wpdb->prefix . 'fluentform_submissions';
                        $wpdb->update(
                            $table,
                            array('response' => json_encode($form_data)),
                            array('id' => $submission_id)
                        );

                        // Clean up temp files
                        $this->cleanup_temp_files($file_path);
                    }
                } else {
                    sffu_log('error', basename($file_path), 'File not found during submission');
                }
            }
        }
    }

    public function process_submission_data($submission_data, $form) {
        if (empty($submission_data)) {
            return $submission_data;
        }

        foreach ($submission_data as $key => $value) {
            if (is_array($value) && isset($value[0]) && filter_var($value[0], FILTER_VALIDATE_URL)) {
                // This is a file upload field with URL
                $file_url = $value[0];
                $upload_dir = wp_upload_dir();
                $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $file_url);
                
                if (file_exists($file_path)) {
                    $new_path = $this->modify_upload_path($file_path, array('url' => $file_url));
                    if ($new_path !== $file_path) {
                        $submission_data[$key][0] = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $new_path);
                    }
                }
            }
        }

        return $submission_data;
    }

    public function modify_upload_path($path, $file) {
        if (empty($path)) {
            sffu_log('error', 'empty_path', 'Empty path provided to modify_upload_path');
            return $path;
        }
        
        // Get the original filename and extension
        $filename = basename($path);
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        
        // Debug log upload directory
        error_log('Debug: Upload directory: ' . $this->upload_dir);
        
        // Verify upload directory exists and is writable
        if (!file_exists($this->upload_dir)) {
            sffu_log('error', 'dir_missing', 'Upload directory does not exist: ' . $this->upload_dir);
            return $path;
        }
        
        if (!is_writable($this->upload_dir)) {
            sffu_log('error', 'write_check', 'Upload directory not writable: ' . $this->upload_dir);
            return $path;
        }
        
        // Debug log
        error_log('Debug: Starting file processing for ' . $filename);
        
        // Verify file exists and is readable
        if (!is_readable($path)) {
            sffu_log('error', $filename, 'Source file not readable: ' . $path);
            return $path;
        }
        
        // Read the original file content
        error_log('Debug: Attempting to read file: ' . $path);
        $file_content = file_get_contents($path);
        if ($file_content === false) {
            sffu_log('error', $filename, 'Failed to read file content');
            return $path;
        }
        error_log('Debug: Successfully read file content, size: ' . strlen($file_content));

        // Generate new secure filename with timestamp and random bytes
        $timestamp = time();
        $random_bytes = bin2hex(random_bytes(16));
        $new_filename = $random_bytes . '_' . $timestamp . '.php';
        $new_path = $this->upload_dir . $new_filename;
        
        error_log('Debug: Generated new filename: ' . $new_filename);
        error_log('Debug: New file path: ' . $new_path);
        
        // Generate encryption key and IV
        $encryption_key = bin2hex(random_bytes(32));
        $iv = openssl_random_pseudo_bytes(16);
        
        // Debug log
        error_log('Debug: Generated IV length: ' . strlen($iv));
        
        // Encrypt the file content with error checking
        error_log('Debug: Attempting encryption');
        $encrypted_content = openssl_encrypt($file_content, 'aes-256-cbc', $encryption_key, OPENSSL_RAW_DATA, $iv);
        if ($encrypted_content === false) {
            sffu_log('error', $filename, 'Encryption failed: ' . openssl_error_string());
            return $path;
        }
        error_log('Debug: Encryption successful, encrypted size: ' . strlen($encrypted_content));
        
        // Encode the encrypted content and IV
        $encoded_content = '<?php exit; ?>' . base64_encode($iv . $encrypted_content);

        error_log('Debug: Encoded content length: ' . strlen($encoded_content));
        
        // Proceed with encryption and save the encrypted file
        $new_filename = $random_bytes . '_' . $timestamp . '.php';
        $new_path = $this->upload_dir . $new_filename;
        error_log('Debug: Attempting to save encrypted file to: ' . $new_path);
        if (file_put_contents($new_path, $encoded_content) === false) {
            error_log('Error: Failed to write encrypted file: ' . $new_path);
            return $path;
        }
        error_log('Debug: Successfully wrote encrypted file');
        
        // Verify file existence after writing
        if (!file_exists($new_path)) {
            sffu_log('error', $filename, 'File does not exist after writing: ' . $new_path);
            return $path;
        }
        
        // Set proper file permissions
        if (!chmod($new_path, 0600)) {
            sffu_log('error', $filename, 'Failed to set file permissions');
            @unlink($new_path);
            return $path;
        }
        
        // Store file information in database
        global $wpdb;
        $table = $wpdb->prefix . 'sffu_files';
        $result = $wpdb->insert(
            $table,
            array(
                'filename' => $new_filename,
                'original_name' => $filename,
                'file_path' => $new_path,
                'mime_type' => mime_content_type($path),
                'file_size' => filesize($path),
                'upload_user_id' => get_current_user_id(),
                'encryption_key' => $encryption_key,
                'iv' => base64_encode($iv),
                'status' => 'active'
            ),
            array('%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            sffu_log('error', $filename, 'Failed to store file metadata: ' . $wpdb->last_error);
            @unlink($new_path);
            return $path;
        }
        error_log('Debug: File metadata stored successfully in database');
        
        // Log the action
        sffu_log('upload', $new_filename);
        
        // Only remove original file if we successfully moved and encrypted it
        if (file_exists($new_path) && filesize($new_path) > 0) {
            @unlink($path);
            error_log('Debug: Successfully moved and encrypted file');
        } else {
            sffu_log('error', $filename, 'New file not created successfully, keeping original');
            return $path;
        }
        
        return $new_path;
    }

    public function modify_upload_url($url, $file) {
        if (empty($url)) return $url;
        
        $filename = basename($file);
        $nonce = wp_create_nonce('sffu_download_' . $filename);
        return admin_url('admin-ajax.php?action=sffu_download&file=' . urlencode($filename) . '&_wpnonce=' . $nonce . '&target=_blank');
    }

    public function handle_download() {
        // Get allowed roles
        $allowed_roles = get_option('sffu_allowed_roles', array('administrator'));
        
        // Check if user has any of the allowed roles
        $user = wp_get_current_user();
        $has_permission = false;
        
        foreach ($allowed_roles as $role) {
            if (in_array($role, $user->roles)) {
                $has_permission = true;
                break;
            }
        }
        
        if (!$has_permission) {
            wp_die('Unauthorized access', 'Security Error', array('response' => 403));
        }

        $file = isset($_GET['file']) ? sanitize_file_name($_GET['file']) : '';
        if (empty($file)) {
            wp_die('Invalid file', 'Security Error', array('response' => 400));
        }

        // Verify nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'sffu_download_' . $file)) {
            wp_die('Security check failed', 'Security Error', array('response' => 403));
        }

        // Get file information from database
        global $wpdb;
        $table = $wpdb->prefix . 'sffu_files';
        $file_info = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE filename = %s AND status = 'active'",
            $file
        ));

        if (!$file_info) {
            wp_die('File not found', 'Error', array('response' => 404));
        }

        $file_path = $file_info->file_path;
        if (!file_exists($file_path) || !is_readable($file_path)) {
            wp_die('File not found or not readable', 'Error', array('response' => 404));
        }

        // Read the encrypted content
        $content = file_get_contents($file_path);
        if ($content === false) {
            wp_die('Failed to read file', 'Error', array('response' => 500));
        }

        // Remove PHP exit code and decode content
        $content = substr($content, strpos($content, '?>') + 2);
        $decoded = base64_decode($content);
        if ($decoded === false) {
            wp_die('Invalid file format', 'Error', array('response' => 400));
        }

        // Extract IV and encrypted content
        $iv = substr($decoded, 0, 16);
        $encrypted_content = substr($decoded, 16);
        if (!$encrypted_content || !$iv) {
            wp_die('Invalid file format', 'Error', array('response' => 400));
        }

        // Decrypt the content with error checking
        $decrypted_content = openssl_decrypt(
            $encrypted_content,
            'aes-256-cbc',
            $file_info->encryption_key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($decrypted_content === false) {
            sffu_log('error', $file, 'Decryption failed: ' . openssl_error_string());
            wp_die('Failed to decrypt file', 'Error', array('response' => 500));
        }

        // Log the download with original file name
        sffu_log('download', $file_info->original_name, 'Downloaded file: ' . $file_info->original_name);

        // Set security headers
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Content-Type: ' . $file_info->mime_type);
        header('Content-Disposition: attachment; filename="' . $file_info->original_name . '"');
        header('Content-Length: ' . strlen($decrypted_content));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Output decrypted file
        echo $decrypted_content;
        exit;
    }

    public function cleanup_files() {
        if (!get_option('sffu_cleanup_enabled', '1')) {
            return;
        }

        $files = glob($this->upload_dir . '*.php');
        $now = time();

        foreach ($files as $file) {
            if (is_file($file)) {
                $file_time = filemtime($file);
                if ($now - $file_time > $this->cleanup_expiry) {
                    unlink($file);
                    sffu_log('cleanup', basename($file));
                }
            }
        }
    }

    public function check_upload_directory() {
        $upload_dir = get_option('sffu_upload_dir', WP_CONTENT_DIR . '/secure-uploads/');
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            if (!wp_mkdir_p($upload_dir)) {
                add_action('admin_notices', function() use ($upload_dir) {
                    echo '<div class="error"><p>Failed to create upload directory: ' . esc_html($upload_dir) . '. Please check permissions.</p></div>';
                });
                return false;
            }
        }

        // Set proper permissions
        chmod($upload_dir, 0755);

        // Create or update .htaccess
        $htaccess = $upload_dir . '.htaccess';
        $htaccess_content = "Order deny,allow\nDeny from all\n<Files ~ \"\\.php$\">\nDeny from all\n</Files>";
        if (!file_exists($htaccess) || filesize($htaccess) === 0) {
            if (file_put_contents($htaccess, $htaccess_content) === false) {
                add_action('admin_notices', function() {
                    echo '<div class="error"><p>Failed to create .htaccess file. Please check permissions.</p></div>';
                });
                return false;
            }
            chmod($htaccess, 0644);
        }

        // Create or update index.php
        $index = $upload_dir . 'index.php';
        $index_content = <<<'EOD'
<?php
// Silence is golden
if (!defined("ABSPATH")) {
    exit;
}
header("HTTP/1.0 403 Forbidden");
exit;
EOD;
        if (!file_exists($index) || filesize($index) === 0) {
            if (file_put_contents($index, $index_content) === false) {
                add_action('admin_notices', function() {
                    echo '<div class="error"><p>Failed to create index.php file. Please check permissions.</p></div>';
                });
                return false;
            }
            chmod($index, 0644);
        }

        return true;
    }

    public function get_uploaded_files() {
        global $wpdb;
        
        // Get files with user information
        $query = $wpdb->prepare(
            "SELECT f.*, u.display_name as uploader_name, s.id as submission_id 
            FROM {$wpdb->prefix}sffu_files f 
            LEFT JOIN {$wpdb->users} u ON f.upload_user_id = u.ID 
            LEFT JOIN {$wpdb->prefix}fluentform_submissions s ON f.upload_user_id = s.user_id 
            WHERE f.status = %s 
            ORDER BY f.upload_time DESC",
            'active'
        );
            
        $files = $wpdb->get_results($query);

        $formatted_files = array();
        foreach ($files as $file) {
            // Check if file physically exists
            if (!file_exists($file->file_path)) {
                // Update status to 'missing' in database
                $wpdb->update(
                    $wpdb->prefix . 'sffu_files',
                    array('status' => 'missing'),
                    array('id' => $file->id)
                );
                continue;
            }

            $formatted_files[] = array(
                'filename' => $file->filename,
                'original_name' => $file->original_name,
                'upload_time' => strtotime($file->upload_time),
                'size' => $file->file_size,
                'mime_type' => $file->mime_type,
                'uploader' => $file->uploader_name,
                'submission_id' => $file->submission_id
            );
        }

        return $formatted_files;
    }

    private function get_wp_filesystem() {
        global $wp_filesystem;
        
        if (!$wp_filesystem) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            WP_Filesystem();
        }
        
        return $wp_filesystem;
    }

    public function before_file_upload($file, $form_id) {
        // Ensure upload directory exists and is writable
        if (!$this->check_upload_directory()) {
            wp_die('Upload directory is not properly configured. Please check the plugin settings and directory permissions.');
        }
        
        // Verify file type
        if (isset($file['type'])) {
            $allowed_types = get_option('sffu_allowed_types', array_keys(SFFU_ALLOWED_MIME_TYPES));
            if (!in_array($file['type'], $allowed_types)) {
                wp_die('File type not allowed.');
            }
        }
    }

    public function after_file_upload($file, $form_id) {
        if (isset($file['tmp_name']) && file_exists($file['tmp_name'])) {
            $this->modify_upload_path($file['tmp_name'], $file);
        }
    }

    public function modify_upload_filename($filename, $file) {
        if (empty($filename)) return $filename;
        
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        return bin2hex(random_bytes(16)) . '.' . $ext;
    }

    private function ensure_logs_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'sffu_logs';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
        
        if (!$table_exists) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE IF NOT EXISTS $table (
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
    }

    public function uninstall_cleanup() {
        global $wpdb;
        
        // Check if we should keep records
        $keep_records = get_option('sffu_keep_records_on_uninstall', '0');
        
        if ($keep_records === '0') {
            // Drop tables
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}sffu_files");
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}sffu_logs");
            
            // Delete options
            delete_option('sffu_keep_records_on_uninstall');
            delete_option('sffu_allowed_roles');
            delete_option('sffu_allowed_types');
            delete_option('sffu_cleanup_enabled');
        }
    }
} 