<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1>Secure FluentForm Uploads Settings</h1>
    
    <?php if (isset($_GET['settings-updated'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Settings saved successfully.', 'secure-fluentform-uploads'); ?></p>
        </div>
    <?php endif; ?>

    <h2 class="nav-tab-wrapper sffu-tabs">
        <a href="<?php echo esc_url(add_query_arg('tab', 'settings')); ?>" 
           class="nav-tab nav-tab-active">
            Settings
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=secure-fluentform-uploads-files')); ?>" 
           class="nav-tab">
            Uploaded Files
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=secure-fluentform-uploads-logs')); ?>" 
           class="nav-tab">
            Activity Logs
        </a>
    </h2>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('sffu_settings');
        do_settings_sections('sffu_settings');
        submit_button();
        ?>
    </form>
</div> 