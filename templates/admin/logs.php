<?php
if (!defined('ABSPATH')) exit;

$admin = SFFU_Admin::get_instance();
$logs = $admin->get_logs();
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 20;
$total_items = count($logs);
$total_pages = ceil($total_items / $per_page);
$offset = ($current_page - 1) * $per_page;
$logs = array_slice($logs, $offset, $per_page);
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
        <a href="<?php echo esc_url(admin_url('admin.php?page=secure-fluentform-uploads-logs')); ?>" class="nav-tab nav-tab-active sffu-tab">
            <?php echo esc_html__('Activity Logs', 'secure-fluentform-uploads'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=secure-fluentform-uploads-instructions')); ?>" class="nav-tab">
            <?php echo esc_html__('Instructions', 'secure-fluentform-uploads'); ?>
        </a>
    </h2>

    <div class="sffu-settings-section">
        <?php if (empty($logs)): ?>
            <p><?php echo esc_html__('No activity logs found.', 'secure-fluentform-uploads'); ?></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Time', 'secure-fluentform-uploads'); ?></th>
                        <th><?php echo esc_html__('Action', 'secure-fluentform-uploads'); ?></th>
                        <th><?php echo esc_html__('File', 'secure-fluentform-uploads'); ?></th>
                        <th><?php echo esc_html__('User', 'secure-fluentform-uploads'); ?></th>
                        <th><?php echo esc_html__('IP Address', 'secure-fluentform-uploads'); ?></th>
                        <th><?php echo esc_html__('Details', 'secure-fluentform-uploads'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->time)); ?></td>
                            <td><?php echo esc_html($log->action); ?></td>
                            <td><?php echo esc_html($log->file); ?></td>
                            <td><?php echo esc_html($log->user_login); ?></td>
                            <td><?php echo esc_html($log->ip); ?></td>
                            <td><?php echo esc_html($log->details); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="displaying-num">
                            <?php printf(
                                _n('%s item', '%s items', $total_items, 'secure-fluentform-uploads'),
                                number_format_i18n($total_items)
                            ); ?>
                        </span>
                        <span class="pagination-links">
                            <?php
                            echo paginate_links(array(
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'prev_text' => __('&laquo;'),
                                'next_text' => __('&raquo;'),
                                'total' => $total_pages,
                                'current' => $current_page
                            ));
                            ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div> 