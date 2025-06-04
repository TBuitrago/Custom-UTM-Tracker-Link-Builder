<?php
/**
 * GitHub Updater Class
 * 
 * Handles automatic updates from GitHub repository
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CUTM_GitHub_Updater {
    
    private $plugin_slug;
    private $version;
    private $plugin_path;
    private $plugin_file;
    private $github_username;
    private $github_repo;
    private $github_api_result;
    private $access_token;

    public function __construct($plugin_file, $github_username, $github_repo, $access_token = '') {
        add_filter('pre_set_site_transient_update_plugins', array($this, 'modify_transient'), 10, 1);
        add_filter('plugins_api', array($this, 'plugin_popup'), 10, 3);
        add_filter('upgrader_post_install', array($this, 'after_install'), 10, 3);

        $this->plugin_file = $plugin_file;
        $this->plugin_slug = basename($plugin_file, '.php');
        $this->version = $this->get_plugin_version();
        $this->plugin_path = plugin_basename($plugin_file);
        $this->github_username = $github_username;
        $this->github_repo = $github_repo;
        $this->access_token = $access_token;
    }

    private function get_plugin_version() {
        if (!function_exists('get_plugin_data')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        $plugin_data = get_plugin_data($this->plugin_file);
        return $plugin_data['Version'];
    }

    private function get_repository_info() {
        if (is_null($this->github_api_result)) {
            $url = "https://api.github.com/repos/{$this->github_username}/{$this->github_repo}/releases/latest";
            
            $args = array(
                'timeout' => 30,
                'headers' => array(
                    'Accept' => 'application/vnd.github.v3+json',
                    'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url')
                )
            );

            if (!empty($this->access_token)) {
                $args['headers']['Authorization'] = 'token ' . $this->access_token;
            }

            $request = wp_remote_get($url, $args);

            if (!is_wp_error($request) && wp_remote_retrieve_response_code($request) === 200) {
                $this->github_api_result = json_decode(wp_remote_retrieve_body($request), true);
            } else {
                $this->github_api_result = false;
            }
        }

        return $this->github_api_result;
    }

    public function modify_transient($transient) {
        if (property_exists($transient, 'checked')) {
            if ($checked = $transient->checked) {
                $this->get_repository_info();

                if ($this->github_api_result && version_compare($this->version, $this->github_api_result['tag_name'], '<')) {
                    $plugin_data = array(
                        'url' => $this->plugin_data['PluginURI'],
                        'slug' => $this->plugin_slug,
                        'package' => $this->github_api_result['zipball_url'],
                        'new_version' => $this->github_api_result['tag_name']
                    );
                    $transient->response[$this->plugin_path] = (object) $plugin_data;
                }
            }
        }

        return $transient;
    }

    public function plugin_popup($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return false;
        }

        if (!empty($args->slug)) {
            if ($args->slug == $this->plugin_slug) {
                $this->get_repository_info();

                $plugin = array(
                    'name' => $this->plugin_data['Name'],
                    'slug' => $this->plugin_slug,
                    'version' => $this->github_api_result['tag_name'],
                    'author' => $this->plugin_data['AuthorName'],
                    'author_profile' => $this->plugin_data['AuthorURI'],
                    'last_updated' => $this->github_api_result['published_at'],
                    'homepage' => $this->plugin_data['PluginURI'],
                    'short_description' => $this->plugin_data['Description'],
                    'sections' => array(
                        'Description' => $this->plugin_data['Description'],
                        'Updates' => $this->github_api_result['body'],
                    ),
                    'download_link' => $this->github_api_result['zipball_url']
                );

                return (object) $plugin;
            }
        }
        return $result;
    }

    public function after_install($response, $hook_extra, $result) {
        global $wp_filesystem;

        $install_directory = plugin_dir_path($this->plugin_file);
        $wp_filesystem->move($result['destination'], $install_directory);
        $result['destination'] = $install_directory;

        if ($this->active) {
            activate_plugin($this->plugin_path);
        }

        return $result;
    }

    private function get_plugin_data() {
        if (!function_exists('get_plugin_data')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        return get_plugin_data($this->plugin_file);
    }

    public function __get($name) {
        if ($name === 'plugin_data') {
            return $this->get_plugin_data();
        }
        return null;
    }
}
