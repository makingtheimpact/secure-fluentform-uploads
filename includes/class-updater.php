<?php
/**
 * GitHub Updater for Secure FluentForm Uploads
 */

if (!defined('ABSPATH')) {
    exit;
}

// Add constant for cache time
define('SFFU_UPDATE_CACHE_TIME', 12 * HOUR_IN_SECONDS); // Check every 12 hours

class SFFU_Updater {
    private static $instance = null;
    private $cache_time = 12 * HOUR_IN_SECONDS; // Check every 12 hours

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_filter('site_transient_update_plugins', array($this, 'check_for_updates'));
        add_action('upgrader_process_complete', array($this, 'clear_update_cache'), 10, 0);
        add_action('deleted_plugin', array($this, 'clear_update_cache'));
        add_action('activated_plugin', array($this, 'clear_update_cache'));
        add_action('deactivated_plugin', array($this, 'clear_update_cache'));
    }

    public function check_for_updates($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        // Check if we already have cached update data
        $cached_response = get_transient('sffu_update_check');
        if ($cached_response !== false) {
            if ($cached_response === 'no_update') {
                return $transient;
            }
            if (is_object($cached_response)) {
                $transient->response['secure-fluentform-uploads/secure-fluentform-uploads.php'] = $cached_response;
                return $transient;
            }
        }

        // Get the actual plugin directory name
        $plugin_dir = dirname(dirname(__FILE__));
        $plugin_basename = basename($plugin_dir);
        $plugin_file = $plugin_dir . '/secure-fluentform-uploads.php';

        // Check if plugin file exists
        if (!file_exists($plugin_file)) {
            error_log('Plugin file not found at: ' . $plugin_file);
            set_transient('sffu_update_check', 'no_update', 30 * MINUTE_IN_SECONDS);
            return $transient;
        }

        // Get plugin data
        $plugin_data = get_file_data($plugin_file, array('Version' => 'Version'), 'plugin');
        $current_version = $plugin_data['Version'];

        if (empty($current_version)) {
            error_log('Could not determine current plugin version from: ' . $plugin_file);
            set_transient('sffu_update_check', 'no_update', 30 * MINUTE_IN_SECONDS);
            return $transient;
        }

        $proxy_url = add_query_arg(array(
            'plugin_slug' => 'secure-fluentform-uploads',
            'version' => $current_version,
            'key' => 'nJ8pHP2xBGeHR23GMuFUuwkzIeCfQ9GXhMGd2tP32xoW3b51BpQbbwzaDsBPstWO',
        ), 'https://update.makingtheimpact.com/');

        try {
            $response = wp_remote_get(
                $proxy_url,
                array(
                    'timeout' => 5,
                    'headers' => array(
                        'Accept' => 'application/json',
                        'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url')
                    )
                )
            );

            if (is_wp_error($response)) {
                error_log('Update check failed: ' . $response->get_error_message());
                set_transient('sffu_update_check', 'no_update', 30 * MINUTE_IN_SECONDS);
                return $transient;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                error_log('Proxy server response code: ' . $response_code);
                set_transient('sffu_update_check', 'no_update', 30 * MINUTE_IN_SECONDS);
                return $transient;
            }

            $body = wp_remote_retrieve_body($response);
            $update_data = json_decode($body, true);

            // Handle "up to date" response
            if (isset($update_data['up_to_date']) && $update_data['up_to_date'] === true) {
                set_transient('sffu_update_check', 'no_update', $this->cache_time);
                return $transient;
            }

            // Handle error response
            if (isset($update_data['error'])) {
                error_log('Update server error: ' . $update_data['error']);
                set_transient('sffu_update_check', 'no_update', 30 * MINUTE_IN_SECONDS);
                return $transient;
            }

            // Validate update data
            if (!empty($update_data['new_version']) && !empty($update_data['package'])) {
                if (version_compare($update_data['new_version'], $current_version, '>')) {
                    $update_object = (object) array(
                        'slug' => 'secure-fluentform-uploads',
                        'new_version' => $update_data['new_version'],
                        'url' => $update_data['url'] ?? '',
                        'package' => $update_data['package'],
                        'plugin' => 'secure-fluentform-uploads/secure-fluentform-uploads.php'
                    );

                    set_transient('sffu_update_check', $update_object, $this->cache_time);
                    $transient->response['secure-fluentform-uploads/secure-fluentform-uploads.php'] = $update_object;
                } else {
                    set_transient('sffu_update_check', 'no_update', $this->cache_time);
                }
            } else {
                error_log('Invalid response structure from proxy server: ' . $body);
                set_transient('sffu_update_check', 'no_update', 30 * MINUTE_IN_SECONDS);
            }
        } catch (Exception $e) {
            error_log('Exception in update check: ' . $e->getMessage());
            set_transient('sffu_update_check', 'no_update', 30 * MINUTE_IN_SECONDS);
        }

        return $transient;
    }

    public function clear_update_cache() {
        delete_transient('sffu_update_check');
    }
}

// Function to handle the update process
function sffu_update_plugin($transient) {
    if (isset($transient->response[plugin_basename(__FILE__)])) {
        $update = $transient->response[plugin_basename(__FILE__)];
        $result = wp_remote_get($update->package);

        if (!is_wp_error($result) && wp_remote_retrieve_response_code($result) === 200) {
            // Unzip and install the plugin
            $zip = $result['body'];
            $temp_file = tempnam(sys_get_temp_dir(), 'secure_fluentform_uploads');
            file_put_contents($temp_file, $zip);

            // Use the WordPress function to update the plugin
            $upgrader = new Plugin_Upgrader();
            $upgrader->install($temp_file);
            unlink($temp_file); // Clean up the temp file
        }
    }
} 