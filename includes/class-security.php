<?php
/**
 * Security handling for Secure FluentForm Uploads
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFU_Security {
    private static $instance = null;
    private $rate_limits = array();
    private $allowed_mime_types;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_allowed_mime_types();
        add_action('init', array($this, 'init_security_headers'));
    }

    private function init_allowed_mime_types() {
        $this->allowed_mime_types = array(
            // Images
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/bmp' => 'bmp',
            'image/webp' => 'webp',
            
            // Documents
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            
            // Archives
            'application/zip' => 'zip',
            'application/x-rar-compressed' => 'rar',
            'application/x-7z-compressed' => '7z',
            
            // Audio
            'audio/mpeg' => 'mp3',
            'audio/wav' => 'wav',
            'audio/ogg' => 'ogg',
            
            // Video
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/ogg' => 'ogv'
        );
    }

    public function init_security_headers() {
        if (!is_admin()) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: ' . apply_filters('sffu_frame_options', 'SAMEORIGIN'));
            header('X-XSS-Protection: 1; mode=block');
            header('Referrer-Policy: strict-origin-when-cross-origin');
        }
    }

    public function validate_file_type($file) {
        if (!isset($file['tmp_name']) || !file_exists($file['tmp_name'])) {
            return false;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        return isset($this->allowed_mime_types[$mime_type]);
    }

    public function check_rate_limit($user_id, $action = 'download') {
        $transient_key = 'sffu_rate_limit_' . $action . '_' . $user_id;
        $limit = get_transient($transient_key) ?: 0;
        
        $max_attempts = $this->get_rate_limit($action);
        if ($limit >= $max_attempts) {
            return false;
        }
        
        set_transient($transient_key, $limit + 1, HOUR_IN_SECONDS);
        return true;
    }

    private function get_rate_limit($action) {
        $limits = array(
            'download' => 10, // 10 downloads per hour
            'upload' => 20,   // 20 uploads per hour
            'api' => 100      // 100 API calls per hour
        );
        
        return isset($limits[$action]) ? $limits[$action] : 10;
    }

    public function validate_file_path($path) {
        $real_path = realpath($path);
        $upload_dir = realpath(SFFU_UPLOAD_DIR);
        
        if ($real_path === false || $upload_dir === false) {
            return false;
        }
        
        return strpos($real_path, $upload_dir) === 0;
    }

    public function generate_secure_filename($original_name) {
        $ext = pathinfo($original_name, PATHINFO_EXTENSION);
        return bin2hex(random_bytes(16)) . '_' . time() . '.' . $ext;
    }

    public function verify_file_integrity($file_path) {
        if (!file_exists($file_path)) {
            return false;
        }

        $stored_hash = get_option('sffu_file_hash_' . md5($file_path));
        if (!$stored_hash) {
            return false;
        }

        $current_hash = hash_file('sha256', $file_path);
        return hash_equals($stored_hash, $current_hash);
    }

    public function sanitize_filename($filename) {
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
        $filename = strtolower($filename);
        return $filename;
    }

    public function validate_user_capability($user_id, $capability = 'manage_options') {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }
        
        return user_can($user, $capability);
    }

    public function log_security_event($event_type, $details) {
        if (!function_exists('sffu_log')) {
            return;
        }
        
        sffu_log('security', $event_type, $details);
    }
} 