<?php
/**
 * File processing for Secure FluentForm Uploads
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFU_File_Processor {
    private static $instance = null;
    private $chunk_size;
    private $max_memory;
    private $security;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->chunk_size = get_option('sffu_chunk_size', 2 * 1024 * 1024); // 2MB default
        $this->max_memory = wp_convert_hr_to_bytes(ini_get('memory_limit')) * 0.8; // 80% of memory limit
        $this->security = SFFU_Security::get_instance();
    }

    public function process_large_file($file_path, $operation = 'encrypt') {
        if (!file_exists($file_path)) {
            return false;
        }

        $file_size = filesize($file_path);
        $total_chunks = ceil($file_size / $this->chunk_size);
        $processed_size = 0;
        $temp_file = $file_path . '.tmp';

        try {
            $source = fopen($file_path, 'rb');
            $target = fopen($temp_file, 'wb');

            while (!feof($source)) {
                $chunk = fread($source, $this->chunk_size);
                if ($chunk === false) {
                    throw new Exception('Error reading file chunk');
                }

                $processed = $this->process_chunk($chunk, $operation);
                if ($processed === false) {
                    throw new Exception('Error processing chunk');
                }

                if (fwrite($target, $processed) === false) {
                    throw new Exception('Error writing processed chunk');
                }

                $processed_size += strlen($chunk);
                $this->check_memory_usage();
            }

            fclose($source);
            fclose($target);

            // Verify the processed file
            if (filesize($temp_file) !== $file_size) {
                throw new Exception('Processed file size mismatch');
            }

            // Replace original with processed file
            if (!rename($temp_file, $file_path)) {
                throw new Exception('Error replacing original file');
            }

            return true;

        } catch (Exception $e) {
            if (isset($source) && is_resource($source)) {
                fclose($source);
            }
            if (isset($target) && is_resource($target)) {
                fclose($target);
            }
            if (file_exists($temp_file)) {
                @unlink($temp_file);
            }
            $this->security->log_security_event('error', $e->getMessage());
            return false;
        }
    }

    private function process_chunk($chunk, $operation) {
        if ($operation === 'encrypt') {
            return $this->encrypt_chunk($chunk);
        } else {
            return $this->decrypt_chunk($chunk);
        }
    }

    private function encrypt_chunk($chunk) {
        $key = $this->get_encryption_key();
        $iv = openssl_random_pseudo_bytes(16);
        
        $encrypted = openssl_encrypt(
            $chunk,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($encrypted === false) {
            return false;
        }

        return $iv . $tag . $encrypted;
    }

    private function decrypt_chunk($chunk) {
        if (strlen($chunk) < 32) { // IV (16) + Tag (16)
            return false;
        }

        $key = $this->get_encryption_key();
        $iv = substr($chunk, 0, 16);
        $tag = substr($chunk, 16, 16);
        $encrypted = substr($chunk, 32);

        $decrypted = openssl_decrypt(
            $encrypted,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return $decrypted;
    }

    private function get_encryption_key() {
        $key = get_option('sffu_encryption_key');
        if (!$key) {
            $key = wp_generate_password(64, true, true);
            update_option('sffu_encryption_key', $key);
        }
        return $key;
    }

    private function check_memory_usage() {
        $memory_usage = memory_get_usage(true);
        if ($memory_usage > $this->max_memory) {
            wp_cache_flush();
            gc_collect_cycles();
        }
    }

    public function cleanup_temp_files() {
        $temp_files = glob(SFFU_UPLOAD_DIR . '*.tmp');
        foreach ($temp_files as $file) {
            if (filemtime($file) < time() - 3600) { // Older than 1 hour
                @unlink($file);
            }
        }
    }

    public function verify_file_processing($file_path) {
        if (!file_exists($file_path)) {
            return false;
        }

        $file_size = filesize($file_path);
        $chunk_size = min($this->chunk_size, $file_size);
        $handle = fopen($file_path, 'rb');
        
        if (!$handle) {
            return false;
        }

        $hash = hash_init('sha256');
        while (!feof($handle)) {
            $chunk = fread($handle, $chunk_size);
            if ($chunk === false) {
                fclose($handle);
                return false;
            }
            hash_update($hash, $chunk);
        }
        
        fclose($handle);
        return hash_final($hash);
    }
} 