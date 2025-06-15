<?php
/**
 * Admin interface for Secure FluentForm Uploads
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFU_Admin {
    private static $instance = null;
    private $security;
    private $file_processor;
    private $hosting_compatibility;
    private $ui;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->security = SFFU_Security::get_instance();
        $this->file_processor = SFFU_File_Processor::get_instance();
        $this->hosting_compatibility = SFFU_Hosting_Compatibility::get_instance();
        $this->ui = SFFU_UI::get_instance();

        add_action('admin_menu', array($this, 'add_menu_pages'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_sffu_download', array($this, 'handle_download'));
        add_action('admin_notices', array($this, 'check_server_compatibility'));
        add_action('wp_ajax_sffu_reset_settings', array($this, 'ajax_reset_settings'));
        
        // Background processing hooks
        add_action('sffu_cleanup_files_cron', array($this, 'process_cleanup_files'));
        add_action('wp_ajax_sffu_get_task_status', array($this, 'get_task_status'));
    }

    public function add_menu_pages() {
        add_menu_page(
            __('Secure Uploads', 'secure-fluentform-uploads'),
            __('Secure Uploads', 'secure-fluentform-uploads'),
            'manage_options',
            'secure-fluentform-uploads',
            array($this, 'render_settings_page'),
            'dashicons-shield',
            30
        );

        add_submenu_page(
            'secure-fluentform-uploads',
            __('Settings', 'secure-fluentform-uploads'),
            __('Settings', 'secure-fluentform-uploads'),
            'manage_options',
            'secure-fluentform-uploads',
            array($this, 'render_settings_page')
        );

        add_submenu_page(
            'secure-fluentform-uploads',
            __('Files', 'secure-fluentform-uploads'),
            __('Files', 'secure-fluentform-uploads'),
            'manage_options',
            'secure-fluentform-uploads-files',
            array($this, 'render_files_page')
        );

        add_submenu_page(
            'secure-fluentform-uploads',
            __('Logs', 'secure-fluentform-uploads'),
            __('Logs', 'secure-fluentform-uploads'),
            'manage_options',
            'secure-fluentform-uploads-logs',
            array($this, 'render_logs_page')
        );

        add_submenu_page(
            'secure-fluentform-uploads',
            __('Instructions', 'secure-fluentform-uploads'),
            __('Instructions', 'secure-fluentform-uploads'),
            'manage_options',
            'secure-fluentform-uploads-instructions',
            array($this, 'render_instructions_page')
        );

        // Add submenu under FluentForms Pro if it exists
        if (class_exists('FluentForm\App\Modules\Form\Form')) {
            add_submenu_page(
                'fluent_forms',
                'Secure FluentForm Uploads',
                'Secure Uploads',
                'manage_options',
                'secure-fluentform-uploads',
                array($this, 'render_settings_page')
            );
        }
    }

    public function register_settings() {
        register_setting('sffu_settings', 'sffu_settings', array(
            'sanitize_callback' => array($this, 'sanitize_settings')
        ));
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        require_once SFFU_PLUGIN_DIR . 'templates/admin/settings.php';
    }

    public function render_files_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        require_once SFFU_PLUGIN_DIR . 'templates/admin/files.php';
    }

    public function render_logs_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        require_once SFFU_PLUGIN_DIR . 'templates/admin/logs.php';
    }

    public function render_instructions_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        require_once SFFU_PLUGIN_DIR . 'templates/admin/instructions.php';
    }

    public function enqueue_scripts($hook) {
        error_log('SFFU Debug - Current hook: ' . $hook);
        
        $valid_hooks = array(
            'toplevel_page_secure-fluentform-uploads',
            'secure-fluentform-uploads_page_secure-fluentform-uploads',
            'secure-fluentform-uploads_page_secure-fluentform-uploads-files',
            'secure-fluentform-uploads_page_secure-fluentform-uploads-logs',
            'secure-fluentform-uploads_page_secure-fluentform-uploads-instructions',
            'fluent-forms_page_secure-fluentform-uploads',
        );
        
        if (!in_array($hook, $valid_hooks, true)) {
            error_log('SFFU Debug - Hook not valid: ' . $hook);
            return;
        }

        error_log('SFFU Debug - Enqueueing admin CSS');
        wp_enqueue_style(
            'sffu-admin',
            SFFU_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            SFFU_VERSION
        );

        wp_enqueue_script(
            'sffu-admin',
            SFFU_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            SFFU_VERSION,
            true
        );

        // Add task status data
        wp_localize_script('sffu-admin', 'sffuTaskStatus', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sffu_task_status'),
            'checkInterval' => 5000, // Check every 5 seconds
        ));

        // Add admin data
        wp_localize_script('sffu-admin', 'sffu_admin', array(
            'nonce' => wp_create_nonce('sffu_admin'),
            'i18n' => array(
                'confirm' => __('Are you sure you want to reset all settings to their default values? This action cannot be undone.', 'secure-fluentform-uploads')
            )
        ));
    }

    private function get_files() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sffu_files';
        return $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY created_at DESC");
    }

    private function get_logs() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sffu_logs';
        return $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY time DESC LIMIT 100");
    }

    public function sanitize_settings($input) {
        $sanitized = array();

        if (isset($input['upload_dir'])) {
            $sanitized['upload_dir'] = trailingslashit(sanitize_text_field($input['upload_dir']));
        }

        if (isset($input['max_file_size'])) {
            $sanitized['max_file_size'] = absint($input['max_file_size']);
        }

        if (isset($input['allowed_types'])) {
            $sanitized['allowed_types'] = array_map('sanitize_text_field', $input['allowed_types']);
        }

        if (isset($input['allowed_roles'])) {
            $sanitized['allowed_roles'] = array_map('sanitize_text_field', $input['allowed_roles']);
        }

        if (isset($input['encryption_key'])) {
            $sanitized['encryption_key'] = sanitize_text_field($input['encryption_key']);
        }

        if (isset($input['file_expiry'])) {
            $sanitized['file_expiry'] = absint($input['file_expiry']);
        }

        if (isset($input['chunk_size'])) {
            $sanitized['chunk_size'] = absint($input['chunk_size']);
        }

        $sanitized['cleanup_enabled'] = isset($input['cleanup_enabled']);
        
        if (isset($input['cleanup_interval'])) {
            $sanitized['cleanup_interval'] = absint($input['cleanup_interval']);
        }

        if (isset($input['cleanup_unit'])) {
            $sanitized['cleanup_unit'] = in_array($input['cleanup_unit'], array('days', 'hours')) 
                ? $input['cleanup_unit'] 
                : 'days';
        }

        // Handle enabled_forms setting
        if (isset($input['enabled_forms'])) {
            if ($input['enabled_forms'] === 'all') {
                $sanitized['enabled_forms'] = 'all';
            } elseif (is_array($input['enabled_forms'])) {
                $sanitized['enabled_forms'] = array_map('absint', $input['enabled_forms']);
            }
        } else {
            $sanitized['enabled_forms'] = 'all'; // Default to 'all' if not set
        }

        return $sanitized;
    }

    public function check_server_compatibility() {
        $issues = $this->hosting_compatibility->check_restrictions();
        
        // Only show warnings if there are actual issues
        if (!empty($issues)) {
            $has_real_issues = false;
            foreach ($issues as $issue) {
                // Skip the upload size limit warning if it's the default setting
                if (strpos($issue, 'Upload size limit too high') !== false) {
                    $settings = get_option('sffu_settings', array());
                    $max_file_size = isset($settings['max_file_size']) ? $settings['max_file_size'] : 10;
                    if ($max_file_size <= 10) { // Default or reasonable size
                        continue;
                    }
                }
                $has_real_issues = true;
                echo '<div class="sffu-notice warning">';
                echo '<p>' . esc_html($issue) . '</p>';
                echo '</div>';
            }
        }
    }

    public function handle_download() {
        try {
            if (!current_user_can('manage_options')) {
                throw new Exception('Unauthorized access');
            }

            $file = isset($_GET['file']) ? sanitize_file_name($_GET['file']) : '';
            if (empty($file)) {
                throw new Exception('Invalid file');
            }

            // Verify nonce
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'sffu_download_' . $file)) {
                throw new Exception('Security check failed');
            }

            $file_path = SFFU_UPLOAD_DIR . $file;
            if (!file_exists($file_path) || !is_readable($file_path)) {
                throw new Exception('File not found or not readable');
            }

            // Verify file is within upload directory
            $real_path = realpath($file_path);
            $upload_dir = realpath(SFFU_UPLOAD_DIR);
            if (strpos($real_path, $upload_dir) !== 0) {
                throw new Exception('Invalid file path');
            }

            // Log the download
            sffu_log('download', $file);

            // Get the original filename from metadata
            $metadata = get_option('sffu_file_' . md5($file));
            $original_name = $metadata ? $metadata['original_name'] : $file;
            $mime_type = $metadata ? $metadata['mime_type'] : 'application/octet-stream';

            // Set headers
            header('Content-Type: ' . $mime_type);
            header('Content-Disposition: attachment; filename="' . $original_name . '"');
            header('Content-Length: ' . filesize($file_path));
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');

            // Output file
            if (readfile($file_path) === false) {
                throw new Exception('Error reading file');
            }
            exit;

        } catch (Exception $e) {
            wp_die($e->getMessage(), 'Download Error', array('response' => 403));
        }
    }

    public function render_entry_files($entry_id) {
        global $wpdb;
        $files = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sffu_files WHERE submission_id = %d",
            $entry_id
        ));

        if (empty($files)) {
            echo '<p>No secure downloads associated with this entry.</p>';
            return;
        }

        echo '<h2>Secure Downloads</h2>';
        echo '<ul>';
        foreach ($files as $file) {
            echo '<li>';
            echo '<a href="' . esc_url(wp_nonce_url(admin_url('admin-ajax.php?action=sffu_download&file=' . urlencode($file->filename)), 'sffu_download_' . $file->filename)) . '">';
            echo esc_html($file->original_name);
            echo '</a>';
            echo '</li>';
        }
        echo '</ul>';
    }

    public function get_task_status() {
        check_ajax_referer('sffu_task_status', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $task_id = isset($_GET['task_id']) ? sanitize_text_field($_GET['task_id']) : '';
        if (empty($task_id)) {
            wp_send_json_error('Invalid task ID');
        }

        $status = get_option('sffu_task_' . $task_id, array(
            'status' => 'unknown',
            'progress' => 0,
            'message' => '',
            'total' => 0,
            'processed' => 0
        ));

        wp_send_json_success($status);
    }

    public function process_cleanup_files() {
        $task_id = 'cleanup_' . time();
        update_option('sffu_task_' . $task_id, array(
            'status' => 'running',
            'progress' => 0,
            'message' => 'Starting cleanup process...',
            'total' => 0,
            'processed' => 0
        ));

        try {
            global $wpdb;
            
            // Get expired files
            $expiry_interval = get_option('sffu_cleanup_interval', 30);
            $expiry_unit = get_option('sffu_cleanup_unit', 'days');
            $expiry_date = date('Y-m-d H:i:s', strtotime("-{$expiry_interval} {$expiry_unit}"));
            
            $files = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}sffu_files WHERE created_at < %s",
                $expiry_date
            ));

            $total = count($files);
            update_option('sffu_task_' . $task_id, array(
                'status' => 'running',
                'progress' => 0,
                'message' => "Found {$total} files to process",
                'total' => $total,
                'processed' => 0
            ));

            $processed = 0;
            foreach ($files as $file) {
                $file_path = SFFU_UPLOAD_DIR . $file->filename;
                if (file_exists($file_path)) {
                    @unlink($file_path);
                }
                
                $wpdb->delete(
                    $wpdb->prefix . 'sffu_files',
                    array('id' => $file->id),
                    array('%d')
                );

                $processed++;
                $progress = ($processed / $total) * 100;
                
                update_option('sffu_task_' . $task_id, array(
                    'status' => 'running',
                    'progress' => $progress,
                    'message' => "Processed {$processed} of {$total} files",
                    'total' => $total,
                    'processed' => $processed
                ));

                // Sleep briefly to prevent server overload
                usleep(100000); // 0.1 second
            }

            update_option('sffu_task_' . $task_id, array(
                'status' => 'completed',
                'progress' => 100,
                'message' => "Cleanup completed. Processed {$processed} files.",
                'total' => $total,
                'processed' => $processed
            ));

        } catch (Exception $e) {
            update_option('sffu_task_' . $task_id, array(
                'status' => 'error',
                'progress' => 0,
                'message' => 'Error: ' . $e->getMessage(),
                'total' => 0,
                'processed' => 0
            ));
        }
    }

    public function render_cleanup_section() {
        echo '<p>Configure automatic cleanup of old files.</p>';
        echo '<div id="sffu-cleanup-status" style="display:none;">';
        echo '<div class="sffu-progress-bar"><div class="sffu-progress"></div></div>';
        echo '<p class="sffu-status-message"></p>';
        echo '</div>';
    }

    public function ajax_reset_settings() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sffu_admin')) {
            wp_send_json_error(__('Security check failed.', 'secure-fluentform-uploads'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'secure-fluentform-uploads'));
        }

        // Get default settings
        $default_settings = array(
            'max_file_size' => min(wp_max_upload_size() / 1024 / 1024, 10),
            'upload_dir' => WP_CONTENT_DIR . '/secure-uploads/',
            'allowed_types' => array(
                'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'ico',
                'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf', 'odt', 'ods', 'odp',
                'zip', 'rar', '7z', 'tar', 'gz',
                'mp3', 'wav', 'ogg', 'm4a', 'wma',
                'mp4', 'avi', 'mov', 'wmv', 'flv', 'webm',
                'csv', 'xml', 'json'
            ),
            'allowed_roles' => array('administrator'),
            'file_expiry' => 30,
            'cleanup_enabled' => true,
            'cleanup_interval' => 30,
            'cleanup_unit' => 'days',
            'cleanup_on_uninstall' => false,
            'enabled_forms' => 'all'
        );

        // Update settings
        update_option('sffu_settings', $default_settings);

        wp_send_json_success(__('Settings have been reset to their default values.', 'secure-fluentform-uploads'));
    }
} 