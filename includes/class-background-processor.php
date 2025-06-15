<?php
if (!defined('ABSPATH')) {
    exit;
}

class SFFU_Background_Processor {
    private static $instance = null;
    private $chunk_size = 1048576; // 1MB chunks
    private $memory_limit = 0.8; // Use 80% of available memory
    private $time_limit = 25; // 25 seconds per batch
    private $low_resource_mode = false;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_sffu_process_file', array($this, 'process_file_chunk'));
        add_action('wp_ajax_sffu_get_file_status', array($this, 'get_file_status'));
        add_action('init', array($this, 'check_server_resources'));
    }

    public function check_server_resources() {
        // Check if we're in a low-resource environment
        $memory_limit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
        $max_execution_time = ini_get('max_execution_time');
        
        // Adjust settings based on server capabilities
        if ($memory_limit < 256 * 1024 * 1024 || $max_execution_time < 30) { // Less than 256MB RAM or 30s execution time
            $this->low_resource_mode = true;
            $this->chunk_size = 524288; // 512KB chunks
            $this->memory_limit = 0.5; // Use 50% of available memory
            $this->time_limit = 15; // 15 seconds per batch
        }
    }

    public function process_file_chunk() {
        check_ajax_referer('sffu_process_file', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $file_id = isset($_POST['file_id']) ? sanitize_text_field($_POST['file_id']) : '';
        $chunk = isset($_POST['chunk']) ? intval($_POST['chunk']) : 0;
        
        if (empty($file_id)) {
            wp_send_json_error('Invalid file ID');
        }

        try {
            $task_id = 'file_' . $file_id;
            $status = get_option('sffu_task_' . $task_id, array(
                'status' => 'running',
                'progress' => 0,
                'message' => 'Starting file processing...',
                'total_chunks' => 0,
                'processed_chunks' => 0,
                'operation' => 'encrypt' // or 'decrypt'
            ));

            // Get file information
            $file_path = SFFU_UPLOAD_DIR . $file_id;
            if (!file_exists($file_path)) {
                throw new Exception('File not found');
            }

            $file_size = filesize($file_path);
            $total_chunks = ceil($file_size / $this->chunk_size);
            
            if ($chunk === 0) {
                // Initialize task
                $status['total_chunks'] = $total_chunks;
                $status['processed_chunks'] = 0;
                update_option('sffu_task_' . $task_id, $status);
            }

            // Process chunk
            $start_time = microtime(true);
            $start_memory = memory_get_usage();
            
            $handle = fopen($file_path, 'r+b');
            fseek($handle, $chunk * $this->chunk_size);
            
            $data = fread($handle, $this->chunk_size);
            if ($data === false) {
                throw new Exception('Error reading file chunk');
            }

            // Encrypt/decrypt the chunk
            $processed_data = $this->process_chunk($data, $status['operation']);
            
            fseek($handle, $chunk * $this->chunk_size);
            fwrite($handle, $processed_data);
            fclose($handle);

            // Update progress
            $status['processed_chunks'] = $chunk + 1;
            $status['progress'] = ($status['processed_chunks'] / $status['total_chunks']) * 100;
            $status['message'] = sprintf(
                'Processing chunk %d of %d (%.1f%%)',
                $status['processed_chunks'],
                $status['total_chunks'],
                $status['progress']
            );

            // Check if we're using too many resources
            $end_time = microtime(true);
            $end_memory = memory_get_usage();
            $time_used = $end_time - $start_time;
            $memory_used = $end_memory - $start_memory;

            if ($this->low_resource_mode && 
                ($time_used > $this->time_limit || 
                 $memory_used > $this->memory_limit * wp_convert_hr_to_bytes(ini_get('memory_limit')))) {
                $status['message'] .= ' (Low resource mode: Pausing to prevent server overload)';
                $status['paused'] = true;
            }

            if ($status['processed_chunks'] >= $status['total_chunks']) {
                $status['status'] = 'completed';
                $status['message'] = 'File processing completed successfully';
            }

            update_option('sffu_task_' . $task_id, $status);
            wp_send_json_success($status);

        } catch (Exception $e) {
            $status['status'] = 'error';
            $status['message'] = 'Error: ' . $e->getMessage();
            update_option('sffu_task_' . $task_id, $status);
            wp_send_json_error($status);
        }
    }

    private function process_chunk($data, $operation) {
        // Implement your encryption/decryption logic here
        // This is a placeholder - you should use proper encryption
        if ($operation === 'encrypt') {
            return $this->encrypt_chunk($data);
        } else {
            return $this->decrypt_chunk($data);
        }
    }

    private function encrypt_chunk($data) {
        // Implement your encryption logic
        // This should use proper encryption like AES-256-GCM
        return $data; // Placeholder
    }

    private function decrypt_chunk($data) {
        // Implement your decryption logic
        // This should use proper encryption like AES-256-GCM
        return $data; // Placeholder
    }

    public function get_file_status() {
        check_ajax_referer('sffu_process_file', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $file_id = isset($_GET['file_id']) ? sanitize_text_field($_GET['file_id']) : '';
        if (empty($file_id)) {
            wp_send_json_error('Invalid file ID');
        }

        $task_id = 'file_' . $file_id;
        $status = get_option('sffu_task_' . $task_id, array(
            'status' => 'unknown',
            'progress' => 0,
            'message' => '',
            'total_chunks' => 0,
            'processed_chunks' => 0
        ));

        wp_send_json_success($status);
    }
} 