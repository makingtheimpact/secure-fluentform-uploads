<?php
if (!defined('ABSPATH')) {
    exit;
}

// Enqueue admin CSS directly
wp_enqueue_style(
    'sffu-admin',
    SFFU_PLUGIN_URL . 'assets/css/admin.css',
    array(),
    SFFU_VERSION
);
?>
<div class="wrap">
    <h1><?php echo esc_html__('Secure FluentForm Uploads', 'secure-fluentform-uploads'); ?></h1>

    <h2 class="nav-tab-wrapper sffu-tabs">
        <a href="<?php echo esc_url(admin_url('admin.php?page=secure-fluentform-uploads')); ?>" class="nav-tab">
            <?php echo esc_html__('Settings', 'secure-fluentform-uploads'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=secure-fluentform-uploads-files')); ?>" class="nav-tab">
            <?php echo esc_html__('Uploaded Files', 'secure-fluentform-uploads'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=secure-fluentform-uploads-logs')); ?>" class="nav-tab">
            <?php echo esc_html__('Activity Logs', 'secure-fluentform-uploads'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=secure-fluentform-uploads-instructions')); ?>" class="nav-tab nav-tab-active sffu-tab">
            <?php echo esc_html__('Instructions', 'secure-fluentform-uploads'); ?>
        </a>
    </h2>

    <div class="sffu-settings-section">
        <h2><?php echo esc_html__('Plugin Instructions', 'secure-fluentform-uploads'); ?></h2>
        
        <div class="sffu-instructions">
            <div class="sffu-background">
                <h2>Getting Started</h2>
                <p>Secure FluentForm Uploads enhances the security of file uploads in your FluentForm forms. Here's how to use it:</p>
                <ol>
                    <li>Go to Settings → Secure Uploads to configure the plugin</li>
                    <li>Set your preferred upload directory (default is wp-content/secure-uploads/)</li>
                    <li>Select which file types are allowed for upload</li>
                    <li>Choose which user roles can access uploaded files</li>
                    <li>Configure link expiry and cleanup settings if needed</li>
                    <li>Insert the shortcode [sffu_download_links] in the email notification template to include download links in the email notifications</li>
                </ol>
                <p>Once you've configured the plugin, all files uploaded with the form will be stored in the upload directory and can be accessed by the users with the allowed roles.</p>

                <hr />

                <h2>About the Plugin</h2>
                <p>Secure FluentForm Uploads is a plugin that enhances the security of file uploads in your FluentForm forms. It allows you to configure the plugin to store files in a secure directory and to restrict access to the files to only the users with the allowed roles.</p>
                <p>Secure FluentForm Uploads is a self-hosted secure file storage solution made easy, without the need for server access or the need to use a third party service.</p>

                <h3>Features</h3>
                <ul>
                    <li>Secure file upload handling and storage</li>
                    <li>File access restriction to only the users with the allowed roles</li>
                    <li>File encryption and decryption</li>
                    <li>File type validation</li>
                    <li>File size restriction settings</li>
                    <li>Activity logging to monitor file access</li>
                    <li>Cleanup options to auto delete files</li>
                    <li>Download link expiry</li>
                    <li>Email notifications with download links</li>
                    <li>Security best practices</li>
                </ul>

                <h3>File Upload Security</h3>
                <ul>
                    <li>All uploaded files are automatically encrypted and stored securely</li>
                    <li>Files are renamed with random strings to prevent direct access</li>
                    <li>Only users with allowed roles can download files</li>
                    <li>All file access attempts are logged for security</li>
                </ul>

                <h3>Accessing Uploaded Files</h3>
                <ul>
                    <li>View all uploaded files under Secure Uploads → Uploaded Files</li>
                    <li>Access files for specific form entries through the entry detail page</li>
                    <li>Use the "View Downloads" button in the admin bar when viewing entries</li>
                    <li>Check the Activity Logs to monitor file access</li>
                </ul>

                <h3>Email Notifications</h3>
                <p>To include download links in email notifications:</p>
                <ol>
                    <li>Go to your FluentForm form settings</li>
                    <li>Edit the email notification template</li>
                    <li>Add the following shortcode where you want the download links to appear:</li>
                    <code>[sffu_download_links]</code>
                    <li>This will automatically list all files uploaded with the form submission</li>
                </ol>

                <h3>Security Best Practices</h3>
                <ul>
                    <li>Regularly review the Activity Logs for suspicious activity</li>
                    <li>Keep the list of allowed file types as restrictive as possible</li>
                    <li>Only grant file access to roles that absolutely need it</li>
                    <li>Enable link expiry for sensitive files</li>
                    <li>Use the cleanup feature to remove old files automatically</li>
                </ul>

                <h3>Troubleshooting</h3>
                <ul>
                    <li>If files aren't uploading, check the allowed file types in settings</li>
                    <li>If users can't access files, verify their role has permission</li>
                    <li>Check the Activity Logs for any error messages</li>
                    <li>Ensure the upload directory is writable by the web server</li>
                </ul>
                <p>If you experience issues getting the plugin to work correctly, please contact us at <a href="mailto:pluginsupport@makingtheimpact.com">pluginsupport@makingtheimpact.com</a> to report bugs. Customization and support is available for a fee.</p>
            </div>
        </div>
    </div>
</div> 