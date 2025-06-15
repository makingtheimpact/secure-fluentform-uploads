<?php
if (!defined('ABSPATH')) exit;

$files = SFFU_Core::get_instance()->get_uploaded_files();
?>

<div class="wrap">
    <h1><?php echo esc_html__('Secure FluentForm Uploads', 'secure-fluentform-uploads'); ?></h1>

    <h2 class="nav-tab-wrapper sffu-tabs">
        <a href="<?php echo esc_url(admin_url('admin.php?page=secure-fluentform-uploads')); ?>" class="nav-tab">
            <?php echo esc_html__('Settings', 'secure-fluentform-uploads'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=secure-fluentform-uploads-files')); ?>" class="nav-tab nav-tab-active sffu-tab">
            <?php echo esc_html__('Uploaded Files', 'secure-fluentform-uploads'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=secure-fluentform-uploads-logs')); ?>" class="nav-tab">
            <?php echo esc_html__('Activity Logs', 'secure-fluentform-uploads'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=secure-fluentform-uploads-instructions')); ?>" class="nav-tab">
            <?php echo esc_html__('Instructions', 'secure-fluentform-uploads'); ?>
        </a>
    </h2>

    <div class="sffu-settings-section">
        <?php if (empty($files)): ?>
            <p><?php echo esc_html__('No files have been uploaded yet.', 'secure-fluentform-uploads'); ?></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('File Name', 'secure-fluentform-uploads'); ?></th>
                        <th><?php echo esc_html__('Original Name', 'secure-fluentform-uploads'); ?></th>
                        <th><?php echo esc_html__('Size', 'secure-fluentform-uploads'); ?></th>
                        <th><?php echo esc_html__('Type', 'secure-fluentform-uploads'); ?></th>
                        <th><?php echo esc_html__('Uploaded By', 'secure-fluentform-uploads'); ?></th>
                        <th><?php echo esc_html__('Form Entry', 'secure-fluentform-uploads'); ?></th>
                        <th><?php echo esc_html__('Date', 'secure-fluentform-uploads'); ?></th>
                        <th><?php echo esc_html__('Actions', 'secure-fluentform-uploads'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($files as $file): ?>
                        <tr>
                            <td><?php echo esc_html($file['filename']); ?></td>
                            <td><?php echo esc_html($file['original_name']); ?></td>
                            <td><?php echo size_format($file['size']); ?></td>
                            <td><?php echo esc_html($file['mime_type']); ?></td>
                            <td><?php echo esc_html($file['uploader']); ?></td>
                            <td><a href="<?php echo esc_url(admin_url('admin.php?page=fluent_forms&route=entries&form_id=' . $file['form_id'] . '#/entries/' . $file['submission_id'])); ?>">
                                <?php echo esc_html($file['submission_id']); ?>
                            </a></td>
                            <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $file['upload_time']); ?></td>
                            <td>
                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-ajax.php?action=sffu_download&file=' . urlencode($file['filename'])), 'sffu_download_' . $file['filename'])); ?>" 
                                class="button button-small">
                                    Download
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div> 