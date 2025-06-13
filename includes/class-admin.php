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
        // Add top-level menu
        add_menu_page(
            'Secure FluentForm Uploads',
            'Secure Uploads',
            'manage_options',
            'secure-fluentform-uploads',
            array($this, 'render_settings_page'),
            'dashicons-lock',
            30
        );

        // Add submenu items
        add_submenu_page(
            'secure-fluentform-uploads',
            'Settings',
            'Settings',
            'manage_options',
            'secure-fluentform-uploads',
            array($this, 'render_settings_page')
        );

        add_submenu_page(
            'secure-fluentform-uploads',
            'Uploaded Files',
            'Uploaded Files',
            'manage_options',
            'secure-fluentform-uploads-files',
            array($this, 'render_files_page')
        );

        add_submenu_page(
            'secure-fluentform-uploads',
            'Activity Logs',
            'Activity Logs',
            'manage_options',
            'secure-fluentform-uploads-logs',
            array($this, 'render_logs_page')
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
        register_setting('sffu_settings', 'sffu_upload_dir');
        register_setting('sffu_settings', 'sffu_link_expiry_enabled');
        register_setting('sffu_settings', 'sffu_link_expiry_interval');
        register_setting('sffu_settings', 'sffu_link_expiry_unit');
        register_setting('sffu_settings', 'sffu_cleanup_enabled');
        register_setting('sffu_settings', 'sffu_cleanup_interval');
        register_setting('sffu_settings', 'sffu_cleanup_unit');
        register_setting('sffu_settings', 'sffu_allowed_types');
        register_setting('sffu_settings', 'sffu_allowed_roles', array(
            'type' => 'array',
            'default' => array('administrator')
        ));
        register_setting('sffu_settings', 'sffu_keep_records_on_uninstall', array(
            'type' => 'boolean',
            'default' => false
        ));
        register_setting('sffu_settings', 'sffu_delete_files_on_uninstall', array(
            'type' => 'boolean',
            'default' => false
        ));

        add_settings_section(
            'sffu_types_section',
            'File Type Settings',
            null,
            'sffu_settings'
        );

        add_settings_section(
            'sffu_roles_section',
            'Access Control',
            array($this, 'render_roles_section'),
            'sffu_settings'
        );

        add_settings_section(
            'sffu_uninstall_section',
            'Uninstall Settings',
            array($this, 'render_uninstall_section'),
            'sffu_settings'
        );

        add_settings_section(
            'sffu_link_expiry_section',
            'Link Expiry Settings',
            null,
            'sffu_settings'
        );

        add_settings_section(
            'sffu_cleanup_section',
            'Cleanup Settings',
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

        add_settings_field(
            'sffu_allowed_roles',
            'Allowed Roles',
            array($this, 'render_allowed_roles_field'),
            'sffu_settings',
            'sffu_roles_section'
        );

        add_settings_field(
            'sffu_keep_records_on_uninstall',
            'Delete file records and logs when uninstalling the plugin',
            array($this, 'render_keep_records_field'),
            'sffu_settings',
            'sffu_uninstall_section'
        );

        add_settings_field(
            'sffu_delete_files_on_uninstall',
            'Delete Files on Uninstall',
            array($this, 'render_delete_files_field'),
            'sffu_settings',
            'sffu_uninstall_section'
        );

        add_settings_field(
            'sffu_link_expiry_enabled',
            'Enable Link Expiry',
            array($this, 'render_link_expiry_enabled_field'),
            'sffu_settings',
            'sffu_link_expiry_section'
        );

        add_settings_field(
            'sffu_link_expiry_interval',
            'Expiry Interval',
            array($this, 'render_link_expiry_interval_field'),
            'sffu_settings',
            'sffu_link_expiry_section'
        );

        add_settings_field(
            'sffu_cleanup_enabled',
            'Enable Cleanup',
            array($this, 'render_cleanup_enabled_field'),
            'sffu_settings',
            'sffu_cleanup_section'
        );

        add_settings_field(
            'sffu_cleanup_interval',
            'Cleanup Interval',
            array($this, 'render_cleanup_interval_field'),
            'sffu_settings',
            'sffu_cleanup_section'
        );
    }

    public function enqueue_scripts($hook) {
        $valid_hooks = array(
            'toplevel_page_secure-fluentform-uploads',
            'secure-fluentform-uploads_page_secure-fluentform-uploads',
            'secure-fluentform-uploads_page_secure-fluentform-uploads-files',
            'fluent-forms_page_secure-fluentform-uploads',
        );
        if (!in_array($hook, $valid_hooks, true)) {
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

    public function render_files_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        include SFFU_PLUGIN_DIR . 'templates/admin/files.php';
    }

    public function render_logs_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        include SFFU_PLUGIN_DIR . 'templates/admin/logs.php';
    }

    public function render_upload_dir_field() {
        $current_dir = get_option('sffu_upload_dir', SFFU_UPLOAD_DIR);
        echo '<input type="text" name="sffu_upload_dir" value="' . esc_attr($current_dir) . '" size="60">';
        echo '<p class="description">Current Directory: ' . esc_html($current_dir) . '</p>';
        echo '<p class="description">The directory will be created automatically if it doesn\'t exist. Make sure the web server has write permissions to the parent directory.</p>';
    }

    public function render_allowed_types_field() {
        $allowed_types = get_option('sffu_allowed_types', array(
            'jpg','jpeg','gif','png','bmp','mp3','wav','ogg','oga','wma','mka','m4a','ra','mid','midi',
            'avi','divx','flv','mov','ogv','mkv','mp4','m4v','mpg','mpeg','mpe','pdf','doc','ppt','pps',
            'xls','mdb','docx','xlsx','pptx','odt','odp','ods','odg','odc','odb','odf','rtf','txt','zip',
            'gz','gzip','rar','7z','exe','csv'
        ));

        echo '<div class="sffu-allowed-types">';
        foreach (
            $allowed_types as $type) {
            echo '<label><input type="checkbox" name="sffu_allowed_types[]" class="sffu-allowed-type" value="' . esc_attr($type) . '" ' . 
                 checked(in_array($type, $allowed_types), true, false) . '> ' . 
                 esc_html($type) . '</label>';
        }
        echo '</div>';
    }

    public function render_roles_section() {
        echo '<p>Select which user roles can download files from FluentForm entries.</p>';
    }

    public function render_allowed_roles_field() {
        $allowed_roles = get_option('sffu_allowed_roles', array('administrator'));
        $wp_roles = wp_roles();
        $roles = $wp_roles->get_names();

        echo '<div class="sffu-allowed-roles">';
        foreach ($roles as $role_id => $role_name) {
            echo '<label><input type="checkbox" name="sffu_allowed_roles[]" value="' . esc_attr($role_id) . '" ' . 
                 checked(in_array($role_id, $allowed_roles), true, false) . '> ' . 
                 esc_html($role_name) . '</label><br>';
        }
        echo '</div>';
    }

    public function render_uninstall_section() {
        echo '<p>Configure what happens when the plugin is uninstalled.</p>';
    }

    public function render_keep_records_field() {
        $delete_records = get_option('sffu_keep_records_on_uninstall', false);
        echo '<label>';
        echo '<input type="checkbox" name="sffu_keep_records_on_uninstall" value="1" ' . checked($delete_records, true, false) . '>';
        echo ' Delete file records and logs when uninstalling the plugin</label>';
        echo '<p class="description">If checked, file records and logs will be deleted when uninstalling the plugin. If unchecked, records will be kept (default).</p>';
    }

    public function render_delete_files_field() {
        $delete_files = get_option('sffu_delete_files_on_uninstall', false);
        echo '<label>';
        echo '<input type="checkbox" name="sffu_delete_files_on_uninstall" value="1" ' . checked($delete_files, true, false) . '>';
        echo ' Delete all uploaded files when uninstalling the plugin</label>';
        echo '<p class="description">If checked, all files in the secure uploads directory will be deleted when the plugin is uninstalled. This action cannot be undone.</p>';
    }

    public function render_link_expiry_enabled_field() {
        $enabled = get_option('sffu_link_expiry_enabled', '1');
        echo '<label>';
        echo '<input type="checkbox" name="sffu_link_expiry_enabled" value="1" ' . checked($enabled, '1', false) . '>';
        echo ' Enable automatic link expiry</label>';
    }

    public function render_link_expiry_interval_field() {
        $interval = get_option('sffu_link_expiry_interval', '7');
        $unit = get_option('sffu_link_expiry_unit', 'days');
        ?>
        <input type="number" name="sffu_link_expiry_interval" value="<?php echo esc_attr($interval); ?>" min="1" max="365">
        <select name="sffu_link_expiry_unit">
            <option value="days" <?php selected($unit, 'days'); ?>>Days</option>
            <option value="hours" <?php selected($unit, 'hours'); ?>>Hours</option>
            <option value="minutes" <?php selected($unit, 'minutes'); ?>>Minutes</option>
        </select>
        <?php
    }

    public function render_cleanup_enabled_field() {
        $enabled = get_option('sffu_cleanup_enabled', '1');
        echo '<label>';
        echo '<input type="checkbox" name="sffu_cleanup_enabled" value="1" ' . checked($enabled, '1', false) . '>';
        echo ' Enable automatic file cleanup</label>';
    }

    public function render_cleanup_interval_field() {
        $interval = get_option('sffu_cleanup_interval', '30');
        $unit = get_option('sffu_cleanup_unit', 'days');
        ?>
        <input type="number" name="sffu_cleanup_interval" value="<?php echo esc_attr($interval); ?>" min="1" max="365">
        <select name="sffu_cleanup_unit">
            <option value="days" <?php selected($unit, 'days'); ?>>Days</option>
            <option value="hours" <?php selected($unit, 'hours'); ?>>Hours</option>
            <option value="minutes" <?php selected($unit, 'minutes'); ?>>Minutes</option>
        </select>
        <?php
    }

    public function handle_download() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access');
        }

        $file = isset($_GET['file']) ? sanitize_file_name($_GET['file']) : '';
        if (empty($file)) {
            wp_die('Invalid file');
        }

        // Verify nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'sffu_download_' . $file)) {
            wp_die('Security check failed');
        }

        $file_path = SFFU_UPLOAD_DIR . $file;
        if (!file_exists($file_path) || !is_readable($file_path)) {
            wp_die('File not found or not readable');
        }

        // Verify file is within upload directory
        $real_path = realpath($file_path);
        $upload_dir = realpath(SFFU_UPLOAD_DIR);
        if (strpos($real_path, $upload_dir) !== 0) {
            wp_die('Invalid file path');
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
        readfile($file_path);
        exit;
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
} 