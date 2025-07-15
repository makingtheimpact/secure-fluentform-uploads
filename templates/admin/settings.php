<?php
if (!defined('ABSPATH')) {
    exit;
}
$settings = get_option('sffu_settings', array());
?>
<div class="wrap">
    <h1><?php echo esc_html__('Secure FluentForm Uploads', 'secure-fluentform-uploads'); ?></h1>

    <?php if (isset($_GET['settings-updated'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Settings saved successfully.', 'secure-fluentform-uploads'); ?></p>
        </div>
    <?php endif; ?>

    <h2 class="nav-tab-wrapper sffu-tabs">
        <a href="#sffu-settings-general" class="nav-tab nav-tab-active sffu-tab" data-target="sffu-settings-general">
            <?php echo esc_html__('Settings', 'secure-fluentform-uploads'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=secure-fluentform-uploads-files')); ?>" class="nav-tab">
            <?php echo esc_html__('Uploaded Files', 'secure-fluentform-uploads'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=secure-fluentform-uploads-logs')); ?>" class="nav-tab">
            <?php echo esc_html__('Activity Logs', 'secure-fluentform-uploads'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=secure-fluentform-uploads-instructions')); ?>" class="nav-tab">
            <?php echo esc_html__('Instructions', 'secure-fluentform-uploads'); ?>
        </a>
    </h2>

    <form method="post" action="options.php" class="sffu-settings-form">
        <?php settings_fields('sffu_settings'); ?>

        <div id="sffu-settings-general" class="sffu-tab-content active">
            <div class="sffu-settings-section">
                <h2><?php echo esc_html__('General Settings', 'secure-fluentform-uploads'); ?></h2>
                <div class="sffu-settings-row">
                    <label for="sffu_upload_dir"><?php echo esc_html__('Upload Directory', 'secure-fluentform-uploads'); ?></label>
                    <input type="text" id="sffu_upload_dir" name="sffu_settings[upload_dir]" value="<?php echo esc_attr($settings['upload_dir'] ?? WP_CONTENT_DIR . '/secure-uploads/'); ?>" class="regular-text">
                    <span class="description"><?php echo esc_html__('Directory where files will be stored', 'secure-fluentform-uploads'); ?></span>
                    <p class="description"><?php echo esc_html__('Default directory:', 'secure-fluentform-uploads') . ' ' . esc_html(WP_CONTENT_DIR . '/secure-uploads/'); ?></p>
                </div>
                <div class="sffu-settings-row">
                    <label for="sffu_max_file_size"><?php echo esc_html__('Maximum File Size', 'secure-fluentform-uploads'); ?></label>
                    <input type="number" id="sffu_max_file_size" name="sffu_settings[max_file_size]" value="<?php echo esc_attr($settings['max_file_size'] ?? min(wp_max_upload_size() / 1024 / 1024, 10)); ?>" min="1" max="<?php echo esc_attr(wp_max_upload_size() / 1024 / 1024); ?>">
                    <span class="description"><?php echo esc_html__('Maximum file size in MB (Server limit: ', 'secure-fluentform-uploads') . esc_html(round(wp_max_upload_size() / 1024 / 1024, 1)) . 'MB)'; ?></span>
                </div>
                <div class="sffu-settings-row">
                    <label for="sffu_allowed_types" style="vertical-align: top;"><?php echo esc_html__('Allowed File Types', 'secure-fluentform-uploads'); ?></label>
                    <div class="sffu-file-types">
                        <div class="sffu-file-type-actions">
                            <button type="button" class="button" id="sffu-select-all"><?php echo esc_html__('Select All', 'secure-fluentform-uploads'); ?></button>
                            <button type="button" class="button" id="sffu-unselect-all"><?php echo esc_html__('Unselect All', 'secure-fluentform-uploads'); ?></button>
                        </div>
                        <div class="sffu-allowed-types">
                            <?php
                            $file_types = array(
                                'Images' => array('jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'ico'),
                                'Documents' => array('pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf', 'odt', 'ods', 'odp'),
                                'Archives' => array('zip', 'rar', '7z', 'tar', 'gz'),
                                'Audio' => array('mp3', 'wav', 'ogg', 'm4a', 'wma'),
                                'Video' => array('mp4', 'avi', 'mov', 'wmv', 'flv', 'webm'),
                                'Executable' => array('exe', 'msi'),
                                'Other' => array('csv', 'xml', 'json')
                            );
                            
                            foreach ($file_types as $category => $types) {
                                echo '<div class="sffu-file-category">';
                                echo '<h4>' . esc_html($category) . '</h4>';
                                foreach ($types as $type) {
                                    $warning = ($category === 'Executable') ? ' <span class="warning">(risk of malicious uploads)</span>' : '';
                                    printf(
                                        '<label><input type="checkbox" name="sffu_settings[allowed_types][]" value="%s" %s> %s%s</label>',
                                        esc_attr($type),
                                        in_array($type, $settings['allowed_types'] ?? array()) ? 'checked' : '',
                                        esc_html(strtoupper($type)),
                                        $warning
                                    );
                                }
                                echo '</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="sffu-settings-security" class="sffu-tab-content">
            <div class="sffu-settings-section">
                <h2><?php echo esc_html__('Security Settings', 'secure-fluentform-uploads'); ?></h2>
                <div class="sffu-settings-row">
                    <label for="sffu_allowed_roles"><?php echo esc_html__('Allowed Roles', 'secure-fluentform-uploads'); ?></label>
                    <select id="sffu_allowed_roles" name="sffu_settings[allowed_roles][]" multiple="multiple" class="regular-text">
                        <?php
                        $wp_roles = wp_roles();
                        $allowed_roles = $settings['allowed_roles'] ?? array('administrator');
                        foreach ($wp_roles->get_names() as $role => $name) {
                            printf(
                                '<option value="%s" %s>%s</option>',
                                esc_attr($role),
                                in_array($role, $allowed_roles) ? 'selected' : '',
                                esc_html($name)
                            );
                        }
                        ?>
                    </select>
                    <span class="description"><?php echo esc_html__('User roles that can access uploaded files', 'secure-fluentform-uploads'); ?></span>
                </div>
                <div class="sffu-settings-row">
                    <label for="sffu_encryption_key"><?php echo esc_html__('Encryption Key', 'secure-fluentform-uploads'); ?></label>
                    <input type="text" id="sffu_encryption_key" name="sffu_settings[encryption_key]" value="<?php echo esc_attr($settings['encryption_key'] ?? ''); ?>" class="regular-text">
                    <span class="description"><?php echo esc_html__('Leave empty to generate automatically', 'secure-fluentform-uploads'); ?></span>
                </div>
                <div class="sffu-settings-row">
                    <label for="sffu_file_expiry"><?php echo esc_html__('File Expiry', 'secure-fluentform-uploads'); ?></label>
                    <input type="number" id="sffu_file_expiry" name="sffu_settings[file_expiry]" value="<?php echo esc_attr($settings['file_expiry'] ?? 30); ?>" min="1" max="365">
                    <span class="description"><?php echo esc_html__('Days until files are automatically deleted', 'secure-fluentform-uploads'); ?></span>
                </div>
                <div class="sffu-settings-row">
                    <label for="sffu_link_expiry_enabled"><?php echo esc_html__('Enable Link Expiry', 'secure-fluentform-uploads'); ?></label>
                    <input type="checkbox" id="sffu_link_expiry_enabled" name="sffu_settings[link_expiry_enabled]" value="1" <?php checked(isset($settings['link_expiry_enabled']) ? $settings['link_expiry_enabled'] : false); ?>>
                </div>
                <div class="sffu-settings-row">
                    <label for="sffu_link_expiry_interval"><?php echo esc_html__('Link Expiry', 'secure-fluentform-uploads'); ?></label>
                    <input type="number" id="sffu_link_expiry_interval" name="sffu_settings[link_expiry_interval]" value="<?php echo esc_attr($settings['link_expiry_interval'] ?? 24); ?>" min="1">
                    <select name="sffu_settings[link_expiry_unit]">
                        <option value="hours" <?php selected($settings['link_expiry_unit'] ?? 'hours', 'hours'); ?>><?php _e('Hours', 'secure-fluentform-uploads'); ?></option>
                        <option value="days" <?php selected($settings['link_expiry_unit'] ?? 'hours', 'days'); ?>><?php _e('Days', 'secure-fluentform-uploads'); ?></option>
                    </select>
                    <span class="description"><?php echo esc_html__('How long download links remain valid', 'secure-fluentform-uploads'); ?></span>
                </div>
            </div>
        </div>

        <div id="sffu-settings-advanced" class="sffu-tab-content">
            <div class="sffu-settings-section">
                <h2><?php echo esc_html__('Advanced Settings', 'secure-fluentform-uploads'); ?></h2>
                <div class="sffu-settings-row">
                    <label for="sffu_chunk_size"><?php echo esc_html__('Chunk Size', 'secure-fluentform-uploads'); ?></label>
                    <input type="number" id="sffu_chunk_size" name="sffu_settings[chunk_size]" value="<?php echo esc_attr($settings['chunk_size'] ?? 2); ?>" min="1" max="10">
                    <span class="description"><?php echo esc_html__('Size of file chunks in MB for processing large files', 'secure-fluentform-uploads'); ?></span>
                </div>
                <div class="sffu-settings-row">
                    <label for="sffu_cleanup_enabled"><?php echo esc_html__('Enable Cleanup', 'secure-fluentform-uploads'); ?></label>
                    <input type="checkbox" id="sffu_cleanup_enabled" name="sffu_settings[cleanup_enabled]" value="1" <?php checked(isset($settings['cleanup_enabled']) ? $settings['cleanup_enabled'] : true); ?>>
                    <span class="description"><?php echo esc_html__('Automatically delete expired files', 'secure-fluentform-uploads'); ?></span>
                </div>
                <div class="sffu-settings-row">
                    <label for="sffu_cleanup_interval"><?php echo esc_html__('Cleanup Interval', 'secure-fluentform-uploads'); ?></label>
                    <input type="number" id="sffu_cleanup_interval" name="sffu_settings[cleanup_interval]" value="<?php echo esc_attr($settings['cleanup_interval'] ?? 30); ?>" min="1" max="365">
                    <select name="sffu_settings[cleanup_unit]">
                        <option value="days" <?php selected($settings['cleanup_unit'] ?? 'days', 'days'); ?>><?php _e('Days', 'secure-fluentform-uploads'); ?></option>
                        <option value="hours" <?php selected($settings['cleanup_unit'] ?? 'days', 'hours'); ?>><?php _e('Hours', 'secure-fluentform-uploads'); ?></option>
                    </select>
                    <span class="description"><?php echo esc_html__('How often to run the cleanup process', 'secure-fluentform-uploads'); ?></span>
                </div>
                <div class="sffu-settings-row">
                    <label for="sffu_cleanup_on_uninstall"><?php echo esc_html__('Cleanup on Uninstall', 'secure-fluentform-uploads'); ?></label>
                    <input type="checkbox" id="sffu_cleanup_on_uninstall" name="sffu_settings[cleanup_on_uninstall]" value="1" <?php checked(isset($settings['cleanup_on_uninstall']) ? $settings['cleanup_on_uninstall'] : false); ?>>
                    <span class="description"><?php echo esc_html__('Delete all files and settings when uninstalling the plugin', 'secure-fluentform-uploads'); ?></span>
                </div>
                <div class="sffu-settings-row">
                    <label><?php echo esc_html__('Reset Settings', 'secure-fluentform-uploads'); ?></label>
                    <button type="button" class="button" id="sffu-reset-settings"><?php echo esc_html__('Reset to Default Settings', 'secure-fluentform-uploads'); ?></button>
                    <span class="description"><?php echo esc_html__('This will reset all settings to their default values. This action cannot be undone.', 'secure-fluentform-uploads'); ?></span>
                </div>
            </div>
        </div>

        <div id="sffu-settings-form-selection" class="sffu-tab-content">
            <div class="sffu-settings-section">
                <h2><?php echo esc_html__('Form Selection', 'secure-fluentform-uploads'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Cleanup on Uninstall', 'secure-fluentform-uploads'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="sffu_settings[cleanup_on_uninstall]" value="1" <?php checked(isset($settings['cleanup_on_uninstall']) ? $settings['cleanup_on_uninstall'] : false); ?>>
                                <?php _e('Delete all uploaded files when uninstalling the plugin', 'secure-fluentform-uploads'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Secure Forms', 'secure-fluentform-uploads'); ?></th>
                        <td>
                            <div class="sffu-form-selection">
                                <div class="sffu-form-selection-header">
                                    <label>
                                        <input type="radio" name="sffu_settings[enabled_forms]" value="all" <?php checked(!isset($settings['enabled_forms']) || $settings['enabled_forms'] === 'all'); ?>>
                                        <?php _e('Secure all forms', 'secure-fluentform-uploads'); ?>
                                    </label>
                                    <label>
                                        <input type="radio" name="sffu_settings[enabled_forms]" value="selected" <?php checked(isset($settings['enabled_forms']) && is_array($settings['enabled_forms'])); ?>>
                                        <?php _e('Secure selected forms only', 'secure-fluentform-uploads'); ?>
                                    </label>
                                </div>
                                
                                <div class="sffu-form-list" style="<?php echo (!isset($settings['enabled_forms']) || $settings['enabled_forms'] === 'all') ? 'display: none;' : ''; ?>">
                                    <div class="sffu-form-list-header">
                                        <input type="text" id="sffu-form-search" placeholder="<?php esc_attr_e('Search forms...', 'secure-fluentform-uploads'); ?>">
                                        <div class="sffu-form-actions">
                                            <button type="button" class="button" id="sffu-select-all-forms"><?php _e('Select All', 'secure-fluentform-uploads'); ?></button>
                                            <button type="button" class="button" id="sffu-unselect-all-forms"><?php _e('Unselect All', 'secure-fluentform-uploads'); ?></button>
                                        </div>
                                    </div>
                                    <div class="sffu-form-list-container">
                                        <?php
                                        if (class_exists('FluentForm\App\Modules\Form\Form')) {
                                            $forms = wpFluent()->table('fluentform_forms')
                                                ->select(['id', 'title'])
                                                ->orderBy('id', 'DESC')
                                                ->get();
                                            
                                            if (!empty($forms)) {
                                                $enabled_forms = isset($settings['enabled_forms']) && is_array($settings['enabled_forms']) ? $settings['enabled_forms'] : array();
                                                foreach ($forms as $form) {
                                                    ?>
                                                    <label class="sffu-form-item">
                                                        <input type="checkbox" 
                                                               name="sffu_settings[enabled_forms][]" 
                                                               value="<?php echo esc_attr($form->id); ?>"
                                                               <?php checked(in_array($form->id, $enabled_forms)); ?>>
                                                        <?php echo esc_html($form->title); ?>
                                                    </label>
                                                    <?php
                                                }
                                            } else {
                                                echo '<p>' . __('No forms found.', 'secure-fluentform-uploads') . '</p>';
                                            }
                                        } else {
                                            echo '<p>' . __('FluentForm is not active.', 'secure-fluentform-uploads') . '</p>';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <?php submit_button(); ?>
    </form>
</div>
<script>
jQuery(document).ready(function($) {
    $('.sffu-tab').on('click', function(e) {
        e.preventDefault();
        var target = $(this).data('target');
        $('.sffu-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        $('.sffu-tab-content').removeClass('active');
        $('#' + target).addClass('active');
    });
});
</script> 