<?php
/**
 * Core functionality for Secure FluentForm Uploads
 */

if (!defined('ABSPATH')) {
    exit;
}

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

    private function __construct() {
        $this->init_constants();
        $this->init_hooks();
    }

    private function init_constants() {
        $this->upload_dir = sffu_get_upload_dir();
        $this->cipher_key = defined('SFFU_CIPHER_KEY') ? SFFU_CIPHER_KEY : wp_generate_password(64, true, true);
        $this->file_expiry = defined('SFFU_FILE_EXPIRY') ? SFFU_FILE_EXPIRY : 7 * 24 * 60 * 60;
        $this->cleanup_expiry = defined('SFFU_CLEANUP_EXPIRY') ? SFFU_CLEANUP_EXPIRY : 30 * 24 * 60 * 60;
    }

    private function init_hooks() {
        // Activation hooks
        register_activation_hook(SFFU_PLUGIN_FILE, array($this, 'activate'));
        
        // Admin notices
        add_action('admin_notices', array($this, 'check_requirements'));
        
        // File handling
        add_filter('fluentform_upload_file_path', array($this, 'modify_upload_path'), 10, 2);
        add_filter('fluentform_upload_file_url', array($this, 'modify_upload_url'), 10, 2);
        
        // Cleanup
        add_action('sffu_cleanup_files', array($this, 'cleanup_files'));
        if (!wp_next_scheduled('sffu_cleanup_files')) {
            wp_schedule_event(time(), 'daily', 'sffu_cleanup_files');
        }
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

        // Create log table
        $this->create_log_table();
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
    }

    public function check_requirements() {
        if (!current_user_can('manage_options')) return;
        
        if (!function_exists('openssl_encrypt') || !function_exists('openssl_decrypt')) {
            echo '<div class="error"><p>Secure FluentForm Uploads requires OpenSSL PHP extension to be installed.</p></div>';
        }
        
        if (!file_exists($this->upload_dir) && !wp_mkdir_p($this->upload_dir)) {
            echo '<div class="notice notice-error"><p>Secure FF Uploads: Private upload directory does not exist and could not be created. Please check permissions.</p></div>';
        } elseif (!is_writable($this->upload_dir)) {
            echo '<div class="notice notice-error"><p>Secure FF Uploads: Private upload directory is not writable. Please check permissions.</p></div>';
        }
    }

    public function modify_upload_path($path, $file) {
        if (empty($path)) return $path;
        
        $filename = basename($path);
        $new_filename = bin2hex(random_bytes(16)) . '.php';
        $new_path = $this->upload_dir . $new_filename;
        
        // Move the file
        if (rename($path, $new_path)) {
            // Log the action
            sffu_log('upload', $new_filename);
            return $new_path;
        }
        
        return $path;
    }

    public function modify_upload_url($url, $file) {
        if (empty($url)) return $url;
        
        $filename = basename($file);
        return admin_url('admin-ajax.php?action=sffu_download&file=' . urlencode($filename));
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
} 