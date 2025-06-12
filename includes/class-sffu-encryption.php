<?php
/**
 * Encryption handling for Secure FluentForm Uploads
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFU_Encryption {
    private static $instance = null;
    private $cipher_key;
    private $file_cipher_key;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->cipher_key = defined('SFFU_CIPHER_KEY') ? SFFU_CIPHER_KEY : wp_generate_password(64, true, true);
        $this->file_cipher_key = $this->get_file_cipher_key();
    }

    private function get_file_cipher_key() {
        $key = get_option('sffu_file_cipher_key');
        if (!$key) {
            $key = wp_generate_password(64, true, true);
            update_option('sffu_file_cipher_key', $key);
        }
        
        // Add key rotation check
        $key_created = get_option('sffu_file_cipher_key_created');
        if (!$key_created) {
            update_option('sffu_file_cipher_key_created', time());
        } elseif (time() - $key_created > (30 * DAY_IN_SECONDS)) {
            // Rotate key every 30 days
            $new_key = wp_generate_password(64, true, true);
            update_option('sffu_file_cipher_key_previous', $key);
            update_option('sffu_file_cipher_key', $new_key);
            update_option('sffu_file_cipher_key_created', time());
            $key = $new_key;
        }
        
        return $key;
    }

    public function encrypt_metadata($data) {
        try {
            $iv = random_bytes(16);
            $ciphertext = openssl_encrypt(
                json_encode($data),
                'AES-256-CBC',
                $this->cipher_key,
                OPENSSL_RAW_DATA,
                $iv
            );
            if ($ciphertext === false) {
                throw new Exception('Encryption failed');
            }
            return base64_encode($iv . $ciphertext);
        } catch (Exception $e) {
            error_log('SFFU Encryption error: ' . $e->getMessage());
            return false;
        }
    }

    public function decrypt_metadata($encrypted) {
        try {
            $raw = base64_decode($encrypted);
            if ($raw === false) {
                throw new Exception('Invalid base64 data');
            }
            $iv = substr($raw, 0, 16);
            $cipher = substr($raw, 16);
            $json = openssl_decrypt(
                $cipher,
                'AES-256-CBC',
                $this->cipher_key,
                OPENSSL_RAW_DATA,
                $iv
            );
            if ($json === false) {
                throw new Exception('Decryption failed');
            }
            return json_decode($json, true);
        } catch (Exception $e) {
            error_log('SFFU Decryption error: ' . $e->getMessage());
            return false;
        }
    }

    public function encrypt_file_data($data) {
        $iv = random_bytes(16);
        $ciphertext = openssl_encrypt(
            $data,
            'AES-256-CBC',
            $this->file_cipher_key,
            OPENSSL_RAW_DATA,
            $iv
        );
        return base64_encode($iv . $ciphertext);
    }

    public function decrypt_file_data($data) {
        $raw = base64_decode($data);
        $iv = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        return openssl_decrypt(
            $cipher,
            'AES-256-CBC',
            $this->file_cipher_key,
            OPENSSL_RAW_DATA,
            $iv
        );
    }
} 