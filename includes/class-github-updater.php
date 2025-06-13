<?php
/**
 * GitHub Updater for Secure FluentForm Uploads
 */

if (!defined('ABSPATH')) {
    exit;
}

class SFFU_GitHub_Updater {
    private $file;
    private $plugin;
    private $basename;
    private $active;
    private $github_username;
    private $github_repository;
    private $github_response;
    private $access_token;
    private $plugin_data;

    public function __construct($file, $plugin, $version, $github_username) {
        $this->file = $file;
        $this->plugin = $plugin;
        $this->basename = plugin_basename($file);
        $this->active = is_plugin_active($this->basename);
        $this->github_username = $github_username;
        $this->github_repository = $plugin;
        $this->access_token = get_option('sffu_github_token', '');

        add_filter('pre_set_site_transient_update_plugins', array($this, 'modify_transient'), 10, 1);
        add_filter('plugins_api', array($this, 'plugin_popup'), 10, 3);
        add_filter('upgrader_post_install', array($this, 'after_install'), 10, 3);
        add_filter('upgrader_process_complete', array($this, 'upgrader_process_complete'), 10, 2);
    }

    public function modify_transient($transient) {
        if (!$transient) {
            return $transient;
        }

        if (!$this->github_response) {
            $this->github_response = $this->get_repository_info();
        }

        if (!$this->github_response) {
            return $transient;
        }

        $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $this->basename);
        $version = $plugin_data['Version'];

        if (version_compare($version, $this->github_response['tag_name'], '<')) {
            $transient->response[$this->basename] = (object) array(
                'slug' => $this->plugin,
                'new_version' => $this->github_response['tag_name'],
                'url' => $this->github_response['html_url'],
                'package' => $this->github_response['zipball_url'],
                'tested' => $this->github_response['tested'],
                'requires' => $this->github_response['requires'],
                'requires_php' => $this->github_response['requires_php']
            );
        }

        return $transient;
    }

    public function plugin_popup($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (!isset($args->slug) || $args->slug !== $this->plugin) {
            return $result;
        }

        if (!$this->github_response) {
            $this->github_response = $this->get_repository_info();
        }

        if (!$this->github_response) {
            return $result;
        }

        $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $this->basename);

        $result = (object) array(
            'name' => $plugin_data['Name'],
            'slug' => $this->plugin,
            'version' => $this->github_response['tag_name'],
            'author' => $plugin_data['Author'],
            'author_profile' => $plugin_data['AuthorURI'],
            'last_updated' => $this->github_response['published_at'],
            'homepage' => $plugin_data['PluginURI'],
            'short_description' => $plugin_data['Description'],
            'sections' => array(
                'description' => $this->github_response['body'],
                'changelog' => $this->github_response['body']
            ),
            'download_link' => $this->github_response['zipball_url']
        );

        return $result;
    }

    public function after_install($response, $hook_extra, $result) {
        global $wp_filesystem;

        $install_directory = plugin_dir_path($this->file);
        $wp_filesystem->move($result['destination'], $install_directory);
        $result['destination'] = $install_directory;

        if ($this->active) {
            activate_plugin($this->basename);
        }

        return $result;
    }

    public function upgrader_process_complete($upgrader_object, $options) {
        if ($options['action'] === 'update' && $options['type'] === 'plugin') {
            delete_transient('sffu_github_response');
        }
    }

    private function get_repository_info() {
        $transient = get_transient('sffu_github_response');
        if ($transient) {
            return $transient;
        }

        $url = sprintf(
            'https://api.github.com/repos/%s/%s/releases/latest',
            $this->github_username,
            $this->github_repository
        );

        $args = array(
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version')
            )
        );

        if ($this->access_token) {
            $args['headers']['Authorization'] = 'token ' . $this->access_token;
        }

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!$body) {
            return false;
        }

        $release_info = array(
            'tag_name' => $body['tag_name'],
            'html_url' => $body['html_url'],
            'zipball_url' => $body['zipball_url'],
            'published_at' => $body['published_at'],
            'body' => $body['body'],
            'tested' => '5.8',
            'requires' => '5.0',
            'requires_php' => '7.2'
        );

        set_transient('sffu_github_response', $release_info, 3600);

        return $release_info;
    }
} 