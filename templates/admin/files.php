<?php
if (!defined('ABSPATH')) exit;

// Enqueue admin CSS
wp_enqueue_style('sffu-admin-css', plugin_dir_url(__FILE__) . '../../assets/css/admin.css');

$files = SFFU_Core::get_instance()->get_uploaded_files();
?>

<div class="wrap">
    <h1>Secure FluentForm Uploads - Files</h1>

    <h2 class="nav-tab-wrapper sffu-tabs">
        <a href="<?php echo esc_url(admin_url('admin.php?page=secure-fluentform-uploads')); ?>" 
           class="nav-tab">
            Settings
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=secure-fluentform-uploads-files')); ?>" 
           class="nav-tab nav-tab-active">
            Uploaded Files
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=secure-fluentform-uploads-logs')); ?>" 
           class="nav-tab">
            Activity Logs
        </a>
    </h2>
    
    <?php if (empty($files)): ?>
        <p>No files have been uploaded yet.</p>
    <?php else: ?>
        <table class="sffu-files-table">
            <thead>
                <tr>
                    <th>Original Name</th>
                    <th>Upload Date</th>
                    <th>Size</th>
                    <th>Type</th>
                    <th>Form Entry</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($files as $file): ?>
                    <tr>
                        <td><?php echo esc_html($file['original_name']); ?></td>
                        <td><?php echo esc_html(date('Y-m-d H:i:s', $file['upload_time'])); ?></td>
                        <td><?php echo esc_html(size_format($file['size'])); ?></td>
                        <td><?php echo esc_html($file['mime_type']); ?></td>
                        <td>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=fluentform&route=entries&form_id=' . $file['submission_id'])); ?>">
                                <?php echo esc_html($file['submission_id']); ?>
                            </a>
                        </td>
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