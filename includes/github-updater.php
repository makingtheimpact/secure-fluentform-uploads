<?php

// Add constant for cache time
define('MEC_UPDATE_CACHE_TIME', 12 * HOUR_IN_SECONDS); // Check every 12 hours

function mec_check_for_updates($transient) {
    if (empty($transient->checked)) {
        return $transient;
    }

    // Check if we already have cached update data
    $cached_response = get_transient('mec_update_check');
    if ($cached_response !== false) {
        if ($cached_response === 'no_update') {
            return $transient;
        }
        if (is_object($cached_response)) {
            $transient->response['my-events-calendar/my-events-calendar.php'] = $cached_response;
            return $transient;
        }
    }

    // Get the actual plugin directory name (handles both standard and development installations)
    $plugin_dir = dirname(dirname(__FILE__));
    $plugin_basename = basename($plugin_dir);
    $plugin_file = $plugin_dir . '/my-events-calendar.php';

    // Check if plugin file exists
    if (!file_exists($plugin_file)) {
        error_log('Plugin file not found at: ' . $plugin_file);
        set_transient('mec_update_check', 'no_update', 30 * MINUTE_IN_SECONDS);
        return $transient;
    }

    // Get plugin data
    $plugin_data = get_file_data($plugin_file, array('Version' => 'Version'), 'plugin');
    $current_version = $plugin_data['Version'];

    if (empty($current_version)) {
        error_log('Could not determine current plugin version from: ' . $plugin_file);
        set_transient('mec_update_check', 'no_update', 30 * MINUTE_IN_SECONDS);
        return $transient;
    }

    $proxy_url = add_query_arg(array(
        'plugin_slug' => 'my-events-calendar',
        'version' => $current_version,
        'key' => 'nJ8pHP2xBGeHR23GMuFUuwkzIeCfQ9GXhMGd2tP32xoW3b51BpQbbwzaDsBPstWO',
    ), 'https://update.makingtheimpact.com/');

    try {
        $response = wp_remote_get(
            $proxy_url,
            array(
                'timeout' => 5,
                'headers' => array(
                    'Accept' => 'application/json'
                )
            )
        );

        if (is_wp_error($response)) {
            error_log('Update check failed: ' . $response->get_error_message());
            set_transient('mec_update_check', 'no_update', 30 * MINUTE_IN_SECONDS);
            return $transient;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('Proxy server response code: ' . $response_code);
            set_transient('mec_update_check', 'no_update', 30 * MINUTE_IN_SECONDS);
            return $transient;
        }

        $body = wp_remote_retrieve_body($response);
        $update_data = json_decode($body, true);

        // Handle "up to date" response
        if (isset($update_data['up_to_date']) && $update_data['up_to_date'] === true) {
            set_transient('mec_update_check', 'no_update', MEC_UPDATE_CACHE_TIME);
            return $transient;
        }

        // Handle error response
        if (isset($update_data['error'])) {
            error_log('Update server error: ' . $update_data['error']);
            set_transient('mec_update_check', 'no_update', 30 * MINUTE_IN_SECONDS);
            return $transient;
        }

        // Validate update data
        if (!empty($update_data['new_version']) && !empty($update_data['package'])) {
            if (version_compare($update_data['new_version'], $current_version, '>')) {
                $update_object = (object) array(
                    'slug' => 'my-events-calendar',
                    'new_version' => $update_data['new_version'],
                    'url' => $update_data['url'] ?? '',
                    'package' => $update_data['package'],
                );

                set_transient('mec_update_check', $update_object, MEC_UPDATE_CACHE_TIME);
                $transient->response[$plugin_basename . '/my-events-calendar.php'] = $update_object;
            } else {
                set_transient('mec_update_check', 'no_update', MEC_UPDATE_CACHE_TIME);
            }
        } else {
            error_log('Invalid response structure from proxy server: ' . $body);
            set_transient('mec_update_check', 'no_update', 30 * MINUTE_IN_SECONDS);
        }
    } catch (Exception $e) {
        error_log('Exception in update check: ' . $e->getMessage());
        set_transient('mec_update_check', 'no_update', 30 * MINUTE_IN_SECONDS);
    }

    return $transient;
}

add_filter('site_transient_update_plugins', 'mec_check_for_updates');

// Function to handle the update process
function mec_update_plugin($transient) {
    if (isset($transient->response[plugin_basename(__FILE__)])) {
        $update = $transient->response[plugin_basename(__FILE__)];
        $result = wp_remote_get($update->package);

        if (!is_wp_error($result) && wp_remote_retrieve_response_code($result) === 200) {
            // Unzip and install the plugin
            $zip = $result['body'];
            $temp_file = tempnam(sys_get_temp_dir(), 'my_events_calendar');
            file_put_contents($temp_file, $zip);

            // Use the WordPress function to update the plugin
            $upgrader = new Plugin_Upgrader();
            $upgrader->install($temp_file);
            unlink($temp_file); // Clean up the temp file
        }
    }
}

function mec_clear_update_cache() {
    delete_transient('mec_update_check');
}

add_action('upgrader_process_complete', 'mec_clear_update_cache', 10, 0);
add_action('deleted_plugin', 'mec_clear_update_cache');
add_action('activated_plugin', 'mec_clear_update_cache');
add_action('deactivated_plugin', 'mec_clear_update_cache');