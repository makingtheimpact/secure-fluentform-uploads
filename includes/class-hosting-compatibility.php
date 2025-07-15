<?php
/**
 * Hosting compatibility handling for Secure FluentForm Uploads
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFU_Hosting_Compatibility {
    private static $instance = null;
    private $hosting_type;
    private $restrictions = array();
    private $fallbacks = array();

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->detect_hosting_environment();
        $this->init_restrictions();
        $this->init_fallbacks();
        add_action('admin_notices', array($this, 'display_hosting_notices'));
    }

    private function detect_hosting_environment() {
        if (defined('WP_ENGINE')) {
            $this->hosting_type = 'wp_engine';
        } elseif (defined('EASYWP')) {
            $this->hosting_type = 'easywp';
        } elseif (defined('KINSTA_CACHE_ZONE')) {
            $this->hosting_type = 'kinsta';
        } elseif (defined('PANTHEON_BINDING')) {
            $this->hosting_type = 'pantheon';
        } else {
            $this->hosting_type = 'standard';
        }
    }

    private function init_restrictions() {
        $this->restrictions = array(
            'wp_engine' => array(
                'file_operations' => true,
                'memory_limit' => '256M',
                'max_execution_time' => 30,
                'upload_max_filesize' => '10M'
            ),
            'easywp' => array(
                'file_operations' => true,
                'memory_limit' => '128M',
                'max_execution_time' => 30,
                'upload_max_filesize' => '8M'
            ),
            'kinsta' => array(
                'file_operations' => true,
                'memory_limit' => '256M',
                'max_execution_time' => 60,
                'upload_max_filesize' => '64M'
            ),
            'pantheon' => array(
                'file_operations' => true,
                'memory_limit' => '256M',
                'max_execution_time' => 120,
                'upload_max_filesize' => '100M'
            ),
            'standard' => array(
                'file_operations' => false,
                'memory_limit' => '256M',
                'max_execution_time' => 30,
                'upload_max_filesize' => '64M'
            )
        );
    }

    private function init_fallbacks() {
        $this->fallbacks = array(
            'file_operations' => array(
                'method' => 'stream',
                'chunk_size' => 1024 * 1024 // 1MB
            ),
            'memory_limit' => array(
                'method' => 'chunked_processing',
                'chunk_size' => 512 * 1024 // 512KB
            ),
            'max_execution_time' => array(
                'method' => 'background_processing',
                'timeout' => 25
            )
        );
    }

    public function check_restrictions() {
        $current_restrictions = $this->restrictions[$this->hosting_type];
        $issues = array();

        // Check file operations
        if ($current_restrictions['file_operations']) {
            if (!$this->check_file_operations()) {
                $issues[] = 'File operations are restricted';
            }
        }

        // Check memory limit
        $memory_limit = wp_convert_hr_to_bytes($current_restrictions['memory_limit']);
        if (memory_get_usage(true) > $memory_limit) {
            $issues[] = 'Memory limit exceeded';
        }

        // Check execution time
        $max_execution_time = $current_restrictions['max_execution_time'];
        if (ini_get('max_execution_time') < $max_execution_time) {
            $issues[] = 'Execution time limit too low';
        }

        // Check upload size - only warn if server limit is lower than hosting type limit
        $upload_max_filesize = wp_convert_hr_to_bytes($current_restrictions['upload_max_filesize']);
        $server_upload_limit = wp_max_upload_size();
        if ($server_upload_limit < $upload_max_filesize) {
            $issues[] = 'Upload size limit too low';
        }

        return $issues;
    }

    private function check_file_operations() {
        $test_file = sffu_get_upload_dir() . 'test_' . time() . '.txt';
        $result = false;

        try {
            if (file_put_contents($test_file, 'test') !== false) {
                $result = true;
                @unlink($test_file);
            }
        } catch (Exception $e) {
            $result = false;
        }

        return $result;
    }

    public function get_fallback_method($operation) {
        return isset($this->fallbacks[$operation]) ? $this->fallbacks[$operation] : null;
    }

    public function display_hosting_notices() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $issues = $this->check_restrictions();
        if (!empty($issues)) {
            echo '<div class="notice notice-warning">';
            echo '<p><strong>Secure FluentForm Uploads - Hosting Compatibility Notice:</strong></p>';
            echo '<ul>';
            foreach ($issues as $issue) {
                echo '<li>' . esc_html($issue) . '</li>';
            }
            echo '</ul>';
            echo '<p>Some features may be limited or unavailable due to hosting restrictions.</p>';
            echo '</div>';
        }
    }

    public function adjust_settings() {
        $current_restrictions = $this->restrictions[$this->hosting_type];
        
        // Adjust chunk size based on memory limit
        $memory_limit = wp_convert_hr_to_bytes($current_restrictions['memory_limit']);
        $chunk_size = min($memory_limit * 0.1, 2 * 1024 * 1024); // 10% of memory limit or 2MB
        update_option('sffu_chunk_size', $chunk_size);

        // Adjust max file size based on hosting limits
        $upload_max_filesize = wp_convert_hr_to_bytes($current_restrictions['upload_max_filesize']);
        update_option('sffu_max_file_size', $upload_max_filesize);

        // Adjust execution time
        $max_execution_time = $current_restrictions['max_execution_time'];
        if (function_exists('set_time_limit')) {
            @set_time_limit($max_execution_time);
        }
    }

    public function cleanup_on_uninstall() {
        if (get_option('sffu_delete_files_on_uninstall', false)) {
            $this->recursive_delete(sffu_get_upload_dir());
        }
    }

    private function recursive_delete($dir) {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->recursive_delete($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
} 