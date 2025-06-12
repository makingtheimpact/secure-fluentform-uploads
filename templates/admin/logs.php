<?php
if (!defined('ABSPATH')) {
    exit;
}

// Get current page
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 20;

// Get logs
global $wpdb;
$table = $wpdb->prefix . 'sffu_logs';
$total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table");
$total_pages = ceil($total_items / $per_page);
$offset = ($current_page - 1) * $per_page;

$logs = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM $table ORDER BY time DESC LIMIT %d OFFSET %d",
        $per_page,
        $offset
    )
);
?>

<div class="wrap">
    <h1>Secure FluentForm Uploads - Activity Logs</h1>

    <h2 class="nav-tab-wrapper sffu-tabs">
        <a href="<?php echo esc_url(admin_url('admin.php?page=secure-fluentform-uploads')); ?>" 
           class="nav-tab">
            Settings
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=secure-fluentform-uploads-files')); ?>" 
           class="nav-tab">
            Uploaded Files
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=secure-fluentform-uploads-logs')); ?>" 
           class="nav-tab nav-tab-active">
            Activity Logs
        </a>
    </h2>

    <?php if (empty($logs)): ?>

        <p>No logs found.</p>

    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Action</th>
                    <th>File</th>
                    <th>User</th>
                    <th>IP Address</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo esc_html(wp_date('Y-m-d H:i:s', strtotime($log->time))); ?></td>
                        <td><?php echo esc_html($log->action); ?></td>
                        <td><?php echo esc_html($log->file); ?></td>
                        <td>
                            <?php 
                            if ($log->user_id) {
                                $user = get_user_by('id', $log->user_id);
                                echo $user ? esc_html($user->display_name) : esc_html($log->user_login);
                            } else {
                                echo 'System';
                            }
                            ?>
                        </td>
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
                        <?php printf(_n('%s item', '%s items', $total_items, 'secure-fluentform-uploads'), number_format_i18n($total_items)); ?>
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