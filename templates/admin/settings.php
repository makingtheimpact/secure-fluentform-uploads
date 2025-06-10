<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1>Secure FluentForm Uploads Settings</h1>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('sffu_settings');
        do_settings_sections('sffu_settings');
        ?>
        
        <h2>Link Expiry Settings</h2>
        <table class="form-table">
            <tr>
                <th scope="row">Enable Link Expiry</th>
                <td>
                    <label>
                        <input type="checkbox" name="sffu_link_expiry_enabled" value="1" 
                            <?php checked(get_option('sffu_link_expiry_enabled', '1')); ?>>
                        Enable automatic link expiry
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">Expiry Interval</th>
                <td>
                    <input type="number" name="sffu_link_expiry_interval" 
                        value="<?php echo esc_attr(get_option('sffu_link_expiry_interval', '7')); ?>" 
                        min="1" max="365">
                    <select name="sffu_link_expiry_unit">
                        <option value="days" <?php selected(get_option('sffu_link_expiry_unit', 'days'), 'days'); ?>>Days</option>
                        <option value="hours" <?php selected(get_option('sffu_link_expiry_unit', 'days'), 'hours'); ?>>Hours</option>
                        <option value="minutes" <?php selected(get_option('sffu_link_expiry_unit', 'days'), 'minutes'); ?>>Minutes</option>
                    </select>
                </td>
            </tr>
        </table>

        <h2>Cleanup Settings</h2>
        <table class="form-table">
            <tr>
                <th scope="row">Enable Cleanup</th>
                <td>
                    <label>
                        <input type="checkbox" name="sffu_cleanup_enabled" value="1" 
                            <?php checked(get_option('sffu_cleanup_enabled', '1')); ?>>
                        Enable automatic file cleanup
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">Cleanup Interval</th>
                <td>
                    <input type="number" name="sffu_cleanup_interval" 
                        value="<?php echo esc_attr(get_option('sffu_cleanup_interval', '30')); ?>" 
                        min="1" max="365">
                    <select name="sffu_cleanup_unit">
                        <option value="days" <?php selected(get_option('sffu_cleanup_unit', 'days'), 'days'); ?>>Days</option>
                        <option value="hours" <?php selected(get_option('sffu_cleanup_unit', 'days'), 'hours'); ?>>Hours</option>
                        <option value="minutes" <?php selected(get_option('sffu_cleanup_unit', 'days'), 'minutes'); ?>>Minutes</option>
                    </select>
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>

    <h2>Uploaded Files</h2>
    <div class="sffu-files-list">
        <?php
        $files = glob(SFFU_UPLOAD_DIR . '*.php');
        if (!empty($files)) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th>File</th>';
            echo '<th>Size</th>';
            echo '<th>Upload Date</th>';
            echo '<th>Actions</th>';
            echo '</tr></thead><tbody>';
            
            foreach ($files as $file) {
                $filename = basename($file);
                $metadata = get_option('sffu_file_' . md5($filename));
                $original_name = $metadata ? $metadata['original_name'] : $filename;
                $size = size_format(filesize($file));
                $date = date('Y-m-d H:i:s', filemtime($file));
                
                echo '<tr>';
                echo '<td>' . esc_html($original_name) . '</td>';
                echo '<td>' . esc_html($size) . '</td>';
                echo '<td>' . esc_html($date) . '</td>';
                echo '<td>';
                echo '<a href="' . esc_url(admin_url('admin-ajax.php?action=sffu_download&file=' . urlencode($filename))) . '" class="button">Download</a> ';
                echo '<a href="#" class="button sffu-delete-file" data-file="' . esc_attr($filename) . '">Delete</a>';
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        } else {
            echo '<p>No files uploaded yet.</p>';
        }
        ?>
    </div>
</div> 