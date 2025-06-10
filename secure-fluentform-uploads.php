<?php
/**
 * Plugin Name: Secure FluentForm Uploads
 * Description: Moves FluentForm uploads to a private folder, renames them, encrypts metadata, and allows admin-only access.
 * Version: 1.0
 * Author: Making The Impact LLC
 */

defined('ABSPATH') || exit;

// Constants
define('SFFU_UPLOAD_DIR', WP_CONTENT_DIR . '/private-uploads/fluentform/');
define('SFFU_CIPHER_KEY', wp_generate_password(64, true, true)); // Generate a strong key on activation
define('SFFU_FILE_EXPIRY', 7 * 24 * 60 * 60); // 7 days
define('SFFU_CLEANUP_EXPIRY', 30 * 24 * 60 * 60); // 30 days
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

// Define a strong file encryption key (change this for your install!)
define('SFFU_FILE_CIPHER_KEY', defined('AUTH_KEY') ? AUTH_KEY : 'change-this-key-please');

// Ensure required functions exist
if (!function_exists('openssl_encrypt') || !function_exists('openssl_decrypt')) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p>Secure FluentForm Uploads requires OpenSSL PHP extension to be installed.</p></div>';
    });
    return;
}

// Activation hook
register_activation_hook(__FILE__, function() {
    // Create upload directory
    if (!file_exists(SFFU_UPLOAD_DIR)) {
        if (!wp_mkdir_p(SFFU_UPLOAD_DIR)) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p>Failed to create upload directory. Please check permissions.</p></div>';
            });
            return;
        }
    }

    // Create .htaccess to prevent direct access
    $htaccess = SFFU_UPLOAD_DIR . '.htaccess';
    if (!file_exists($htaccess)) {
        $content = "Order deny,allow\nDeny from all";
        file_put_contents($htaccess, $content);
    }

    // Create index.php to prevent directory listing
    $index = SFFU_UPLOAD_DIR . 'index.php';
    if (!file_exists($index)) {
        file_put_contents($index, '<?php // Silence is golden');
    }

    // Schedule cleanup
    if (!wp_next_scheduled('sffu_cleanup_files')) {
        wp_schedule_event(time(), 'daily', 'sffu_cleanup_files');
    }

    // Create log table
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
});

// Get/set private upload directory from settings
function sffu_get_upload_dir() {
    $custom = get_option('sffu_upload_dir');
    if ($custom && is_string($custom)) {
        $dir = trailingslashit($custom);
    } else {
        $upload = wp_upload_dir();
        $dir = trailingslashit($upload['basedir']) . 'private-fluentform/';
    }
    return $dir;
}

define('SFFU_UPLOAD_DIR', sffu_get_upload_dir());

// Admin notice if upload dir is not writable
add_action('admin_notices', function() {
    if (!current_user_can('manage_options')) return;
    if (!file_exists(SFFU_UPLOAD_DIR) && !wp_mkdir_p(SFFU_UPLOAD_DIR)) {
        echo '<div class="notice notice-error"><p>Secure FF Uploads: Private upload directory does not exist and could not be created. Please check permissions.</p></div>';
    } elseif (!is_writable(SFFU_UPLOAD_DIR)) {
        echo '<div class="notice notice-error"><p>Secure FF Uploads: Private upload directory is not writable. Please check permissions.</p></div>';
    }
});

// Add upload dir setting to settings page
add_action('admin_init', function() {
    register_setting('sffu_settings', 'sffu_upload_dir');
    add_settings_field('sffu_upload_dir', 'Private Upload Directory', function() {
        $val = esc_attr(get_option('sffu_upload_dir', SFFU_UPLOAD_DIR));
        echo '<input type="text" name="sffu_upload_dir" value="' . $val . '" size="60">';
        echo '<p class="description">Default: ' . esc_html(SFFU_UPLOAD_DIR) . '</p>';
    }, 'sffu_settings', 'sffu_types_section');
});

// Ensure index.php exists in upload dir
function sffu_ensure_index_php() {
    $dir = SFFU_UPLOAD_DIR;
    if (!file_exists($dir)) {
        wp_mkdir_p($dir);
    }
    $index = $dir . 'index.php';
    if (!file_exists($index)) {
        file_put_contents($index, '<?php // Silence is golden');
    }
}
add_action('admin_init', 'sffu_ensure_index_php');
add_action('init', 'sffu_ensure_index_php');

// Utility: Generate random filename with .php extension
function sffu_random_filename($ext) {
    return bin2hex(random_bytes(16)) . '.php';
}

// Utility: Validate file type
function sffu_validate_file($file_path) {
    $mime_type = mime_content_type($file_path);
    $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    $allowed_exts = get_option('sffu_allowed_types');
    if (!$allowed_exts) {
        $allowed_exts = [
            'jpg','jpeg','gif','png','bmp','mp3','wav','ogg','oga','wma','mka','m4a','ra','mid','midi','avi','divx','flv','mov','ogv','mkv','mp4','m4v','mpg','mpeg','mpe','pdf','doc','ppt','pps','xls','mdb','docx','xlsx','pptx','odt','odp','ods','odg','odc','odb','odf','rtf','txt','zip','gz','gzip','rar','7z','exe','csv'
        ];
    }
    if (!in_array($ext, $allowed_exts)) {
        throw new Exception('File extension not allowed: ' . $ext);
    }
    if (!in_array($mime_type, SFFU_ALLOWED_MIME_TYPES)) {
        throw new Exception('File type not allowed: ' . $mime_type);
    }
    return $mime_type;
}

// Utility: Encrypt metadata
function sffu_encrypt($data) {
    try {
        $iv = random_bytes(16);
        $ciphertext = openssl_encrypt(
            json_encode($data),
            'AES-256-CBC',
            SFFU_CIPHER_KEY,
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

// Utility: Decrypt metadata
function sffu_decrypt($encrypted) {
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
            SFFU_CIPHER_KEY,
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

// --- File Cipher Key Management ---
function sffu_get_file_cipher_key() {
    $key = get_option('sffu_file_cipher_key');
    if (!$key) {
        $key = wp_generate_password(64, true, true);
        update_option('sffu_file_cipher_key', $key);
    }
    return $key;
}

// Utility: Encrypt file data
function sffu_encrypt_file_data($data) {
    $key = sffu_get_file_cipher_key();
    $iv = random_bytes(16);
    $ciphertext = openssl_encrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $ciphertext);
}

// Utility: Decrypt file data
function sffu_decrypt_file_data($data) {
    $key = sffu_get_file_cipher_key();
    $raw = base64_decode($data);
    $iv = substr($raw, 0, 16);
    $cipher = substr($raw, 16);
    return openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
}

// Add link expiry and cleanup settings
add_action('admin_init', function() {
    register_setting('sffu_settings', 'sffu_link_expiry_enabled');
    register_setting('sffu_settings', 'sffu_link_expiry_interval');
    register_setting('sffu_settings', 'sffu_link_expiry_unit');
    register_setting('sffu_settings', 'sffu_cleanup_enabled');
    register_setting('sffu_settings', 'sffu_cleanup_interval');
    register_setting('sffu_settings', 'sffu_cleanup_unit');
    add_settings_field('sffu_link_expiry', 'Link Expiry', function() {
        $enabled = get_option('sffu_link_expiry_enabled', '1');
        $interval = get_option('sffu_link_expiry_interval', '7');
        $unit = get_option('sffu_link_expiry_unit', 'days');
        echo '<label><input type="checkbox" name="sffu_link_expiry_enabled" value="1"' . checked($enabled, '1', false) . '> Enable link expiry</label><br>';
        echo 'Expire links after <input type="number" name="sffu_link_expiry_interval" value="' . esc_attr($interval) . '" min="1" style="width:60px;"> ';
        echo '<select name="sffu_link_expiry_unit"><option value="hours"' . selected($unit, 'hours', false) . '>hours</option><option value="days"' . selected($unit, 'days', false) . '>days</option></select>';
    }, 'sffu_settings', 'sffu_types_section');
    add_settings_field('sffu_cleanup', 'File Cleanup', function() {
        $enabled = get_option('sffu_cleanup_enabled', '1');
        $interval = get_option('sffu_cleanup_interval', '30');
        $unit = get_option('sffu_cleanup_unit', 'days');
        echo '<label><input type="checkbox" name="sffu_cleanup_enabled" value="1"' . checked($enabled, '1', false) . '> Enable file cleanup</label><br>';
        echo 'Delete files after <input type="number" name="sffu_cleanup_interval" value="' . esc_attr($interval) . '" min="1" style="width:60px;"> ';
        echo '<select name="sffu_cleanup_unit"><option value="hours"' . selected($unit, 'hours', false) . '>hours</option><option value="days"' . selected($unit, 'days', false) . '>days</option></select>';
    }, 'sffu_settings', 'sffu_types_section');
});

// Helper: get expiry/cleanup seconds
function sffu_get_seconds($interval, $unit) {
    $interval = (int)$interval;
    if ($unit === 'hours') return $interval * 3600;
    return $interval * 86400;
}

// Use settings for link expiry and cleanup
function sffu_get_link_expiry() {
    if (get_option('sffu_link_expiry_enabled', '1') !== '1') return 0;
    $interval = get_option('sffu_link_expiry_interval', '7');
    $unit = get_option('sffu_link_expiry_unit', 'days');
    return sffu_get_seconds($interval, $unit);
}
function sffu_get_cleanup_expiry() {
    if (get_option('sffu_cleanup_enabled', '1') !== '1') return 0;
    $interval = get_option('sffu_cleanup_interval', '30');
    $unit = get_option('sffu_cleanup_unit', 'days');
    return sffu_get_seconds($interval, $unit);
}

// Intercept uploaded file path (encrypt, then store as .php with PHP header)
add_filter('fluentform_uploaded_file_path', function($filePath, $field, $formId, $entryId) {
    $upload_dir = SFFU_UPLOAD_DIR;
    if (!file_exists($upload_dir)) {
        wp_mkdir_p($upload_dir);
    }
    sffu_ensure_index_php();
    if (!file_exists($filePath)) {
        return $filePath;
    }
    try {
        // Validate file type
        $mime_type = sffu_validate_file($filePath);
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $obfuscated = sffu_random_filename($ext); // always .php
        $destination = $upload_dir . $obfuscated;
        $original_name = basename($filePath);
        // Read file contents, encrypt, and prepend PHP header
        $data = file_get_contents($filePath);
        $encrypted_data = sffu_encrypt_file_data($data);
        $php_header = "<?php exit; ?>\n";
        file_put_contents($destination, $php_header . $encrypted_data);
        unlink($filePath);
        chmod($destination, 0600);
        $expiry = sffu_get_link_expiry();
        $metadata = [
            'original' => $original_name,
            'stored_as' => $obfuscated,
            'real_ext' => $ext,
            'time' => time(),
            'expires' => $expiry ? (time() + $expiry) : 0,
            'field' => $field['name'] ?? '',
            'form_id' => $formId,
            'entry_id' => $entryId,
            'mime_type' => $mime_type
        ];
        $encrypted = sffu_encrypt($metadata);
        if ($encrypted === false) {
            throw new Exception('Failed to encrypt metadata');
        }
        return [
            'encrypted_path' => $encrypted,
            'original_name' => $original_name
        ];
    } catch (Exception $e) {
        error_log('SFFU Upload error: ' . $e->getMessage());
        return $filePath; // Fallback to original path
    }
}, 9, 4);

// Secure download endpoint (strip PHP header, decrypt, and serve real file)
add_action('init', function() {
    if (!isset($_GET['sffu-download'])) {
        return;
    }
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        wp_die('Access denied', '403 Forbidden', ['response' => 403]);
    }
    $encrypted = sanitize_text_field($_GET['sffu-download']);
    $meta = sffu_decrypt($encrypted);
    if (!$meta || empty($meta['stored_as'])) {
        wp_die('File not found or reference is invalid.', '404 Not Found', ['response' => 404]);
    }
    if (!empty($meta['expires']) && $meta['expires'] > 0 && time() > $meta['expires']) {
        wp_die('This file link has expired.', '410 Gone', ['response' => 410]);
    }
    $upload_dir = SFFU_UPLOAD_DIR;
    $path = $upload_dir . $meta['stored_as'];
    if (!file_exists($path)) {
        wp_die('This file has been deleted or expired.', '410 Gone', ['response' => 410]);
    }
    // Verify file is within upload directory
    if (strpos(realpath($path), realpath($upload_dir)) !== 0) {
        wp_die('Invalid file path.', '400 Bad Request', ['response' => 400]);
    }
    // Verify file type hasn't changed
    $current_mime = mime_content_type($path);
    if ($current_mime !== $meta['mime_type']) {
        wp_die('File type mismatch.', '400 Bad Request', ['response' => 400]);
    }
    $real_ext = $meta['real_ext'] ?? '';
    $filename = pathinfo($meta['original'], PATHINFO_FILENAME) . ($real_ext ? ('.' . $real_ext) : '');
    $mime_type = $meta['mime_type'];
    header('Content-Type: ' . $mime_type);
    header('Content-Disposition: attachment; filename="' . esc_attr($filename) . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    // Read file, strip PHP header, decrypt, and output
    $data = file_get_contents($path);
    $php_header = "<?php exit; ?>\n";
    if (substr($data, 0, strlen($php_header)) === $php_header) {
        $data = substr($data, strlen($php_header));
    }
    $decrypted = sffu_decrypt_file_data($data);
    sffu_log('download', $meta['original']);
    echo $decrypted;
    exit;
}, 11);

// Add download links to submission email
add_filter('fluentform_submission_email_values', function($values, $form, $entry) {
    foreach ($values as $key => $value) {
        // Handle single file upload
        if (is_array($value) && isset($value['encrypted_path'])) {
            $url = site_url('/?sffu-download=' . urlencode($value['encrypted_path']));
            $values[$key] = sprintf(
                '%s - <a href="%s" class="sffu-download-link">Download</a>',
                esc_html($value['original_name']),
                esc_url($url)
            );
        }
        // Handle multiple file uploads
        elseif (is_array($value)) {
            $download_links = [];
            foreach ($value as $file_data) {
                if (is_array($file_data) && isset($file_data['encrypted_path'])) {
                    $url = site_url('/?sffu-download=' . urlencode($file_data['encrypted_path']));
                    $download_links[] = sprintf(
                        '%s - <a href="%s" class="sffu-download-link">Download</a>',
                        esc_html($file_data['original_name']),
                        esc_url($url)
                    );
                }
            }
            if (!empty($download_links)) {
                $values[$key] = implode('<br>', $download_links);
            }
        }
    }
    return $values;
}, 10, 3);

// Add download buttons to entry view
add_filter('fluentform_entry_value', function($value, $entry, $field) {
    // Handle single file upload
    if (is_array($value) && isset($value['encrypted_path'])) {
        $url = site_url('/?sffu-download=' . urlencode($value['encrypted_path']));
        return sprintf(
            '%s - <a class="button button-primary sffu-download-link" href="%s" target="_blank">Download</a>',
            esc_html($value['original_name']),
            esc_url($url)
        );
    }
    // Handle multiple file uploads
    elseif (is_array($value)) {
        $download_buttons = [];
        foreach ($value as $file_data) {
            if (is_array($file_data) && isset($file_data['encrypted_path'])) {
                $url = site_url('/?sffu-download=' . urlencode($file_data['encrypted_path']));
                $download_buttons[] = sprintf(
                    '%s - <a class="button button-primary sffu-download-link" href="%s" target="_blank">Download</a>',
                    esc_html($file_data['original_name']),
                    esc_url($url)
                );
            }
        }
        if (!empty($download_buttons)) {
            return implode('<br>', $download_buttons);
        }
    }
    return $value;
}, 10, 3);

// Cleanup old files
function sffu_cleanup_files() {
    if (!file_exists(SFFU_UPLOAD_DIR)) {
        return;
    }

    $files = glob(SFFU_UPLOAD_DIR . '*');
    if (empty($files)) {
        return;
    }

    $now = time();
    $batch_size = 50;
    $processed = 0;

    foreach ($files as $file) {
        if ($processed >= $batch_size) {
            wp_schedule_single_event(time() + 60, 'sffu_cleanup_files');
            break;
        }

        if (is_file($file) && filemtime($file) < ($now - SFFU_CLEANUP_EXPIRY)) {
            @unlink($file);
        }
        $processed++;
    }
}

// Register cleanup hook
add_action('sffu_cleanup_files', 'sffu_cleanup_files');

// Cleanup schedule on deactivation
register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('sffu_cleanup_files');
});

// Admin page for managing uploads
add_action('admin_menu', function() {
    add_menu_page(
        'Secure FF Uploads',
        'Secure FF Uploads',
        'manage_options',
        'secure-ff-uploads',
        'sffu_admin_page',
        'dashicons-shield-alt',
        80
    );
});

function sffu_admin_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Access denied');
    }
    echo '<div class="wrap"><h1>Secure FluentForm Uploads</h1>';
    $files = glob(SFFU_UPLOAD_DIR . '*');
    if (!$files) {
        echo '<p>No files found.</p></div>';
        return;
    }
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="action" value="sffu_download_zip">';
    echo '<table class="widefat fixed striped"><thead><tr>';
    echo '<th><input type="checkbox" id="sffu-check-all" onclick="jQuery(\'.sffu-file-checkbox\').prop(\'checked\', this.checked);"></th>';
    echo '<th>Thumbnail</th><th>Original Name</th><th>Upload Date</th><th>Expires</th><th>Form/Entry</th><th>Actions</th>';
    echo '</tr></thead><tbody>';
    foreach ($files as $file) {
        if (!is_file($file)) continue;
        $meta = null;
        $original = basename($file);
        $upload_date = date('Y-m-d H:i', filemtime($file));
        $expires = '-';
        $form_entry = '-';
        $is_image = wp_check_filetype($file)['type'] && strpos(wp_check_filetype($file)['type'], 'image/') === 0;
        echo '<tr>';
        // Checkbox
        echo '<td><input type="checkbox" class="sffu-file-checkbox" name="sffu_files[]" value="' . esc_attr($original) . '"></td>';
        // Thumbnail
        if ($is_image) {
            $thumb_url = sffu_admin_file_url($file);
            echo '<td><img src="' . esc_url($thumb_url) . '" style="max-width:60px;max-height:60px;" /></td>';
        } else {
            echo '<td></td>';
        }
        // Original name
        echo '<td>' . esc_html($original) . '</td>';
        // Upload date
        echo '<td>' . esc_html($upload_date) . '</td>';
        // Expires
        echo '<td>' . esc_html($expires) . '</td>';
        // Form/Entry
        echo '<td>' . esc_html($form_entry) . '</td>';
        // Actions
        echo '<td>';
        echo '<a href="' . esc_url(sffu_admin_file_url($file, true)) . '" class="button">Download</a> ';
        echo '<a href="#" class="button sffu-rename" data-file="' . esc_attr($original) . '">Rename</a> ';
        echo '<a href="#" class="button sffu-delete" data-file="' . esc_attr($original) . '">Delete</a>';
        echo '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '<p><button type="submit" class="button button-primary">Download Selected as ZIP</button></p>';
    echo '</form></div>';
}

function sffu_admin_file_url($file, $download = false) {
    // Generate a secure download URL for admin
    $basename = basename($file);
    $url = admin_url('admin-ajax.php?action=sffu_admin_download&file=' . urlencode($basename));
    if ($download) $url .= '&download=1';
    return $url;
}

// AJAX handler for admin download/preview
add_action('wp_ajax_sffu_admin_download', function() {
    if (!current_user_can('manage_options')) {
        wp_die('Access denied');
    }
    $file = isset($_GET['file']) ? basename($_GET['file']) : '';
    $path = SFFU_UPLOAD_DIR . $file;
    if (!file_exists($path)) {
        wp_die('File not found');
    }
    sffu_log('download', $file);
    $mime = mime_content_type($path);
    if (isset($_GET['download'])) {
        header('Content-Disposition: attachment; filename="' . esc_attr($file) . '"');
    }
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
});

// Settings section for allowed file types
add_action('admin_init', function() {
    register_setting('sffu_settings', 'sffu_allowed_types');
    add_settings_section('sffu_types_section', 'Allowed File Types', null, 'sffu_settings');
    add_settings_field('sffu_types', 'File Types', 'sffu_types_field', 'sffu_settings', 'sffu_types_section');
});

function sffu_types_field() {
    $all_types = [
        'jpg' => 'Images (jpg, jpeg, gif, png, bmp)',
        'jpeg' => '', 'gif' => '', 'png' => '', 'bmp' => '',
        'mp3' => 'Audio (mp3, wav, ogg, oga, wma, mka, m4a, ra, mid, midi)',
        'wav' => '', 'ogg' => '', 'oga' => '', 'wma' => '', 'mka' => '', 'm4a' => '', 'ra' => '', 'mid' => '', 'midi' => '',
        'avi' => 'Video (avi, divx, flv, mov, ogv, mkv, mp4, m4v, mpg, mpeg, mpe, divx)',
        'divx' => '', 'flv' => '', 'mov' => '', 'ogv' => '', 'mkv' => '', 'mp4' => '', 'm4v' => '', 'mpg' => '', 'mpeg' => '', 'mpe' => '',
        'pdf' => 'PDF (pdf)',
        'doc' => 'Docs (doc, ppt, pps, xls, mdb, docx, xlsx, pptx, odt, odp, ods, odg, odc, odb, odf, rtf, txt)',
        'ppt' => '', 'pps' => '', 'xls' => '', 'mdb' => '', 'docx' => '', 'xlsx' => '', 'pptx' => '', 'odt' => '', 'odp' => '', 'ods' => '', 'odg' => '', 'odc' => '', 'odb' => '', 'odf' => '', 'rtf' => '', 'txt' => '',
        'zip' => 'Zip Archives (zip, gz, gzip, rar, 7z)',
        'gz' => '', 'gzip' => '', 'rar' => '', '7z' => '',
        'exe' => 'Executable Files (exe)',
        'csv' => 'CSV (csv)'
    ];
    $selected = get_option('sffu_allowed_types', array_keys($all_types));
    foreach ($all_types as $ext => $label) {
        if ($label) echo '<br><strong>' . esc_html($label) . '</strong><br>';
        echo '<label><input type="checkbox" name="sffu_allowed_types[]" value="' . esc_attr($ext) . '"' . (in_array($ext, $selected) ? ' checked' : '') . '> ' . esc_html($ext) . '</label> ';
    }
}

// Add settings to admin page
add_action('admin_menu', function() {
    add_submenu_page('secure-ff-uploads', 'Settings', 'Settings', 'manage_options', 'sffu_settings', 'sffu_settings_page');
});

// Add export log handler
add_action('admin_post_sffu_export_log', function() {
    if (!current_user_can('manage_options')) {
        wp_die('Access denied');
    }
    global $wpdb;
    $table = $wpdb->prefix . 'sffu_logs';
    $logs = $wpdb->get_results("SELECT * FROM $table ORDER BY time DESC");
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sffu_log_export_'.date('Ymd_His').'.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Time', 'Action', 'File', 'User', 'IP', 'Details']);
    foreach ($logs as $log) {
        fputcsv($out, [$log->time, $log->action, $log->file, $log->user_login, $log->ip, $log->details]);
    }
    fclose($out);
    exit;
});

// Update log display in settings page
function sffu_settings_page() {
    echo '<div class="wrap"><h1>Secure FF Uploads Settings</h1>';
    echo '<form method="post" action="options.php">';
    settings_fields('sffu_settings');
    do_settings_sections('sffu_settings');
    submit_button();
    echo '</form>';
    // Log table with scrollable box and export button
    global $wpdb;
    $table = $wpdb->prefix . 'sffu_logs';
    $logs = $wpdb->get_results("SELECT * FROM $table ORDER BY time DESC LIMIT 1000");
    echo '<h2>File Access & Action Log</h2>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="action" value="sffu_export_log">';
    submit_button('Export Log as CSV', 'secondary', 'submit', false);
    echo '</form>';
    echo '<div style="max-height:400px; overflow-y:auto; border:1px solid #ccc; background:#fff; margin-top:10px;">';
    echo '<table class="widefat fixed striped"><thead><tr><th>Time</th><th>Action</th><th>File</th><th>User</th><th>IP</th><th>Details</th></tr></thead><tbody>';
    foreach ($logs as $log) {
        echo '<tr>';
        echo '<td>' . esc_html($log->time) . '</td>';
        echo '<td>' . esc_html($log->action) . '</td>';
        echo '<td>' . esc_html($log->file) . '</td>';
        echo '<td>' . esc_html($log->user_login) . '</td>';
        echo '<td>' . esc_html($log->ip) . '</td>';
        echo '<td>' . esc_html($log->details) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
    echo '</div>';
}

function sffu_log($action, $file = '', $details = '') {
    global $wpdb;
    $user = wp_get_current_user();
    $wpdb->insert(
        $wpdb->prefix . 'sffu_logs',
        [
            'action' => $action,
            'file' => $file,
            'user_id' => $user->ID,
            'user_login' => $user->user_login,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'details' => $details
        ]
    );
}

// Add cipher key management to settings page
add_action('admin_init', function() {
    register_setting('sffu_settings', 'sffu_file_cipher_key');
    add_settings_field('sffu_file_cipher_key', 'File Encryption Key', function() {
        $key = get_option('sffu_file_cipher_key');
        echo '<input type="password" id="sffu_file_cipher_key" name="sffu_file_cipher_key" value="' . esc_attr($key) . '" size="70" autocomplete="off"> ';
        echo '<button type="button" class="button" onclick="document.getElementById(\'sffu_file_cipher_key\').type = (document.getElementById(\'sffu_file_cipher_key\').type === \'password\' ? \'text\' : \'password\');">Show/Hide</button> ';
        echo '<button type="button" class="button" onclick="navigator.clipboard.writeText(document.getElementById(\'sffu_file_cipher_key\').value);">Copy</button> ';
        echo '<button type="button" class="button" onclick="if(confirm(\'Are you sure? Changing the key will make all previously uploaded files unreadable!\')){document.getElementById(\'sffu_file_cipher_key\').value=\'' . esc_js(wp_generate_password(64, true, true)) . '\';}">Auto-Generate New Key</button>';
        echo '<p class="description" style="color:red;">Warning: Changing this key will make all previously uploaded files unreadable. Save this key securely. It is required to decrypt your files.</p>';
    }, 'sffu_settings', 'sffu_types_section');
});

// AJAX handler for ZIP download
add_action('admin_post_sffu_download_zip', function() {
    if (!current_user_can('manage_options')) {
        wp_die('Access denied');
    }
    if (empty($_POST['sffu_files']) || !is_array($_POST['sffu_files'])) {
        wp_die('No files selected.');
    }
    $files = array_map('basename', $_POST['sffu_files']);
    $zip = new ZipArchive();
    $tmp_zip = tempnam(sys_get_temp_dir(), 'sffu_zip_') . '.zip';
    if ($zip->open($tmp_zip, ZipArchive::CREATE) !== true) {
        wp_die('Could not create ZIP file.');
    }
    foreach ($files as $file) {
        $path = SFFU_UPLOAD_DIR . $file;
        if (!file_exists($path)) continue;
        $data = file_get_contents($path);
        $php_header = "<?php exit; ?>\n";
        if (substr($data, 0, strlen($php_header)) === $php_header) {
            $data = substr($data, strlen($php_header));
        }
        $decrypted = sffu_decrypt_file_data($data);
        // Try to get original filename from metadata (if possible)
        $meta = null;
        // For now, use obfuscated name
        $zip->addFromString($file, $decrypted);
    }
    $zip->close();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="secure-uploads-' . date('Ymd_His') . '.zip"');
    header('Content-Length: ' . filesize($tmp_zip));
    readfile($tmp_zip);
    unlink($tmp_zip);
    exit;
});