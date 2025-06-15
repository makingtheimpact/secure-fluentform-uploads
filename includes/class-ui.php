<?php
/**
 * UI handling for Secure FluentForm Uploads
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFU_UI {
    private static $instance = null;
    private $security;
    private $file_processor;
    private $hosting_compatibility;

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

        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_sffu_get_progress', array($this, 'ajax_get_progress'));
        add_action('wp_ajax_sffu_cancel_operation', array($this, 'ajax_cancel_operation'));
    }

    public function enqueue_scripts($hook) {
        if (!$this->is_plugin_page($hook)) {
            return;
        }

        wp_enqueue_script(
            'sffu-admin',
            SFFU_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            SFFU_VERSION,
            true
        );

        wp_localize_script('sffu-admin', 'sffuUI', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sffu_ui'),
            'i18n' => array(
                'processing' => __('Processing...', 'secure-fluentform-uploads'),
                'error' => __('Error', 'secure-fluentform-uploads'),
                'success' => __('Success', 'secure-fluentform-uploads'),
                'cancel' => __('Cancel', 'secure-fluentform-uploads'),
                'confirm' => __('Are you sure?', 'secure-fluentform-uploads')
            )
        ));
    }

    private function is_plugin_page($hook) {
        $valid_hooks = array(
            'toplevel_page_secure-fluentform-uploads',
            'secure-fluentform-uploads_page_secure-fluentform-uploads',
            'secure-fluentform-uploads_page_secure-fluentform-uploads-files',
            'secure-fluentform-uploads_page_secure-fluentform-uploads-logs',
            'fluent-forms_page_secure-fluentform-uploads'
        );
        return in_array($hook, $valid_hooks, true);
    }

    public function render_progress_bar($task_id) {
        $status = get_option('sffu_task_' . $task_id);
        if (!$status) {
            return;
        }

        $progress = isset($status['progress']) ? $status['progress'] : 0;
        $message = isset($status['message']) ? $status['message'] : '';
        $status_type = isset($status['status']) ? $status['status'] : 'running';

        echo '<div class="sffu-progress-container">';
        echo '<div class="sffu-progress-bar">';
        echo '<div class="sffu-progress" style="width: ' . esc_attr($progress) . '%"></div>';
        echo '</div>';
        echo '<div class="sffu-progress-status">';
        echo '<span class="sffu-progress-text">' . esc_html($message) . '</span>';
        echo '<span class="sffu-progress-percentage">' . esc_html($progress) . '%</span>';
        echo '</div>';
        
        if ($status_type === 'running') {
            echo '<button class="button sffu-cancel-operation" data-task-id="' . esc_attr($task_id) . '">';
            echo esc_html__('Cancel', 'secure-fluentform-uploads');
            echo '</button>';
        }
        
        echo '</div>';
    }

    public function render_file_list($files) {
        if (empty($files)) {
            echo '<p>' . esc_html__('No files found.', 'secure-fluentform-uploads') . '</p>';
            return;
        }

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . esc_html__('File', 'secure-fluentform-uploads') . '</th>';
        echo '<th>' . esc_html__('Size', 'secure-fluentform-uploads') . '</th>';
        echo '<th>' . esc_html__('Type', 'secure-fluentform-uploads') . '</th>';
        echo '<th>' . esc_html__('Uploaded', 'secure-fluentform-uploads') . '</th>';
        echo '<th>' . esc_html__('Actions', 'secure-fluentform-uploads') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($files as $file) {
            echo '<tr>';
            echo '<td>' . esc_html($file->original_name) . '</td>';
            echo '<td>' . esc_html(size_format($file->file_size)) . '</td>';
            echo '<td>' . esc_html($file->mime_type) . '</td>';
            echo '<td>' . esc_html(human_time_diff(strtotime($file->created_at), current_time('timestamp'))) . '</td>';
            echo '<td>';
            echo '<a href="' . esc_url(wp_nonce_url(admin_url('admin-ajax.php?action=sffu_download&file=' . urlencode($file->filename)), 'sffu_download_' . $file->filename)) . '" class="button button-small">' . esc_html__('Download', 'secure-fluentform-uploads') . '</a>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
    }

    public function render_log_list($logs) {
        if (empty($logs)) {
            echo '<p>' . esc_html__('No logs found.', 'secure-fluentform-uploads') . '</p>';
            return;
        }

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . esc_html__('Action', 'secure-fluentform-uploads') . '</th>';
        echo '<th>' . esc_html__('File', 'secure-fluentform-uploads') . '</th>';
        echo '<th>' . esc_html__('User', 'secure-fluentform-uploads') . '</th>';
        echo '<th>' . esc_html__('IP', 'secure-fluentform-uploads') . '</th>';
        echo '<th>' . esc_html__('Time', 'secure-fluentform-uploads') . '</th>';
        echo '<th>' . esc_html__('Details', 'secure-fluentform-uploads') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($logs as $log) {
            echo '<tr>';
            echo '<td>' . esc_html($log->action) . '</td>';
            echo '<td>' . esc_html($log->file) . '</td>';
            echo '<td>' . esc_html($log->user_login) . '</td>';
            echo '<td>' . esc_html($log->ip) . '</td>';
            echo '<td>' . esc_html(human_time_diff(strtotime($log->created_at), current_time('timestamp'))) . '</td>';
            echo '<td>' . esc_html($log->details) . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
    }

    public function ajax_get_progress() {
        check_ajax_referer('sffu_ui', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $task_id = isset($_GET['task_id']) ? sanitize_text_field($_GET['task_id']) : '';
        if (empty($task_id)) {
            wp_send_json_error('Invalid task ID');
        }

        $status = get_option('sffu_task_' . $task_id);
        if (!$status) {
            wp_send_json_error('Task not found');
        }

        wp_send_json_success($status);
    }

    public function ajax_cancel_operation() {
        check_ajax_referer('sffu_ui', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $task_id = isset($_POST['task_id']) ? sanitize_text_field($_POST['task_id']) : '';
        if (empty($task_id)) {
            wp_send_json_error('Invalid task ID');
        }

        $status = get_option('sffu_task_' . $task_id);
        if (!$status) {
            wp_send_json_error('Task not found');
        }

        $status['status'] = 'cancelled';
        update_option('sffu_task_' . $task_id, $status);

        wp_send_json_success(array('message' => 'Operation cancelled'));
    }
} 