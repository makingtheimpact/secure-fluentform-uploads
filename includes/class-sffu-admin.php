<?php
/**
 * Admin interface for Secure FluentForm Uploads
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFU_Admin {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_menu_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_sffu_download', array($this, 'handle_download'));
    }

    public function add_menu_page() {
        add_submenu_page(
            'fluent_forms',
            'Secure Uploads',
            'Secure Uploads',
            'manage_options',
            'secure-fluentform-uploads',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        register_setting('sffu_settings', 'sffu_upload_dir');
        register_setting('sffu_settings', 'sffu_link_expiry_enabled');
        register_setting('sffu_settings', 'sffu_link_expiry_interval');
        register_setting('sffu_settings', 'sffu_link_expiry_unit');
        register_setting('sffu_settings', 'sffu_cleanup_enabled');
        register_setting('sffu_settings', 'sffu_cleanup_interval');
        register_setting('sffu_settings', 'sffu_cleanup_unit');
        register_setting('sffu_settings', 'sffu_allowed_types');

        add_settings_section(
            'sffu_types_section',
            'File Type Settings',
            null,
            'sffu_settings'
        );

        add_settings_field(
            'sffu_upload_dir',
            'Private Upload Directory',
            array($this, 'render_upload_dir_field'),
            'sffu_settings',
            'sffu_types_section'
        );

        add_settings_field(
            'sffu_allowed_types',
            'Allowed File Types',
            array($this, 'render_allowed_types_field'),
            'sffu_settings',
            'sffu_types_section'
        );
    }

    public function enqueue_scripts($hook) {
        if ('fluent-forms_page_secure-fluentform-uploads' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'sffu-admin',
            plugins_url('assets/css/admin.css', dirname(__FILE__)),
            array(),
            SFFU_VERSION
        );

        wp_enqueue_script(
            'sffu-admin',
            plugins_url('assets/js/admin.js', dirname(__FILE__)),
            array('jquery'),
            SFFU_VERSION,
            true
        );
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        include SFFU_PLUGIN_DIR . 'templates/admin/settings.php';
    }

    public function render_upload_dir_field() {
        $val = esc_attr(get_option('sffu_upload_dir', SFFU_UPLOAD_DIR));
        echo '<input type="text" name="sffu_upload_dir" value="' . $val . '" size="60">';
        echo '<p class="description">Default: ' . esc_html(SFFU_UPLOAD_DIR) . '</p>';
    }

    public function render_allowed_types_field() {
        $allowed_types = get_option('sffu_allowed_types', array(
            'jpg','jpeg','gif','png','bmp','mp3','wav','ogg','oga','wma','mka','m4a','ra','mid','midi',
            'avi','divx','flv','mov','ogv','mkv','mp4','m4v','mpg','mpeg','mpe','pdf','doc','ppt','pps',
            'xls','mdb','docx','xlsx','pptx','odt','odp','ods','odg','odc','odb','odf','rtf','txt','zip',
            'gz','gzip','rar','7z','exe','csv'
        ));

        echo '<div class="sffu-allowed-types">';
        foreach ($allowed_types as $type) {
            echo '<label><input type="checkbox" name="sffu_allowed_types[]" value="' . esc_attr($type) . '" ' . 
                 checked(in_array($type, $allowed_types), true, false) . '> ' . 
                 esc_html($type) . '</label><br>';
        }
        echo '</div>';
    }

    public function handle_download() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access');
        }

        $file = isset($_GET['file']) ? sanitize_file_name($_GET['file']) : '';
        if (empty($file)) {
            wp_die('Invalid file');
        }

        $file_path = SFFU_UPLOAD_DIR . $file;
        if (!file_exists($file_path)) {
            wp_die('File not found');
        }

        // Log the download
        sffu_log('download', $file);

        // Get the original filename from metadata
        $metadata = get_option('sffu_file_' . md5($file));
        $original_name = $metadata ? $metadata['original_name'] : $file;

        // Set headers
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $original_name . '"');
        header('Content-Length: ' . filesize($file_path));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Output file
        readfile($file_path);
        exit;
    }
} 