<?php
/**
 * Automatic updates from GitHub releases.
 *
 * The main plugin file declares `Update URI: https://github.com/...`, so
 * WordPress (5.8+) never asks WordPress.org about this plugin. Instead it calls
 * the `update_plugins_github.com` filter, which this class answers with the
 * latest published GitHub release. From there the native update flow takes
 * over: update notices, "Update now", bulk updates and background
 * auto-updates (WP-Cron), including the automatic rollback WordPress performs
 * when an auto-update causes a fatal error.
 *
 * Optional wp-config.php constants:
 * - CUTM_GITHUB_TOKEN          Personal access token (only needed if the repository becomes private).
 * - CUTM_DISABLE_AUTO_UPDATE   Set to true to stop forcing auto-updates on a site.
 *
 * @package Custom_UTM_Tracker
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CUTM_Updater')) :

class CUTM_Updater {

    /** Site transient holding the last GitHub release lookup. */
    const CACHE_KEY = 'cutm_github_release';

    /** How long a successful lookup is reused (seconds). */
    const CACHE_TTL = 14400; // 4 hours.

    /** How long a failed lookup is remembered, to avoid hammering the GitHub API (seconds). */
    const ERROR_TTL = 3600; // 1 hour.

    /** Name of the release asset built by .github/workflows/release.yml. */
    const ASSET_NAME = 'custom-utm-tracker.zip';

    /** Max stored length of the release notes. */
    const MAX_CHANGELOG_LENGTH = 20000;

    /** @var string Absolute path of the main plugin file. */
    private $file;

    /** @var string Plugin basename, e.g. "custom-utm-tracker/custom-utm-tracker.php". */
    private $basename;

    /** @var string Plugin folder name, used as slug. */
    private $slug;

    /** @var string */
    private $owner;

    /** @var string */
    private $repo;

    /**
     * @param string $file  Absolute path of the main plugin file.
     * @param string $owner GitHub user or organization.
     * @param string $repo  GitHub repository name.
     */
    public function __construct($file, $owner, $repo) {
        $this->file     = $file;
        $this->basename = plugin_basename($file);
        $this->slug     = dirname($this->basename);
        $this->owner    = $owner;
        $this->repo     = $repo;
    }

    /**
     * Hooks the updater into WordPress. Must run in every context (admin,
     * WP-Cron, WP-CLI): background auto-updates happen outside wp-admin.
     */
    public function register() {
        add_filter('update_plugins_github.com', array($this, 'check_for_update'), 10, 4);
        add_filter('plugins_api', array($this, 'plugin_information'), 20, 3);
        add_filter('upgrader_source_selection', array($this, 'normalize_source_dir'), 10, 4);
        add_filter('auto_update_plugin', array($this, 'enable_auto_update'), 20, 2);
        add_filter('http_request_args', array($this, 'authorize_github_requests'), 10, 2);
    }

    /**
     * Answers WordPress' update check for this plugin.
     *
     * WordPress compares the returned version with the installed one and files
     * the plugin under "response" (update available) or "no_update".
     *
     * @param array|false $update      Update data from earlier callbacks.
     * @param array       $plugin_data Installed plugin headers.
     * @param string      $plugin_file Plugin basename being checked.
     * @param string[]    $locales     Installed locales.
     * @return array|false
     */
    public function check_for_update($update, $plugin_data, $plugin_file, $locales = array()) {
        if ($plugin_file !== $this->basename) {
            return $update;
        }

        $release = $this->get_release();
        if (empty($release)) {
            return $update;
        }

        return array(
            'slug'         => $this->slug,
            'version'      => $release['version'],
            'url'          => !empty($plugin_data['PluginURI']) ? $plugin_data['PluginURI'] : $release['html_url'],
            'package'      => $release['package'],
            'requires'     => $release['requires'],
            'requires_php' => $release['requires_php'],
            'tested'       => $release['tested'],
            'icons'        => array(),
            'banners'      => array(),
            'banners_rtl'  => array(),
            'translations' => array(),
        );
    }

    /**
     * Provides the "View details" popup for this plugin.
     *
     * @param false|object|array $result
     * @param string             $action
     * @param object             $args
     * @return false|object|array
     */
    public function plugin_information($result, $action, $args) {
        if ('plugin_information' !== $action || !is_object($args) || empty($args->slug)) {
            return $result;
        }
        if (strtolower((string) $args->slug) !== strtolower($this->slug)) {
            return $result;
        }

        $release = $this->get_release();
        if (empty($release)) {
            return $result;
        }

        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugin = get_plugin_data($this->file, false, false);

        $author = $plugin['Author'];
        if (!empty($plugin['AuthorURI'])) {
            $author = sprintf('<a href="%s">%s</a>', esc_url($plugin['AuthorURI']), esc_html($plugin['Author']));
        }

        return (object) array(
            'name'           => $plugin['Name'],
            'slug'           => $this->slug,
            'version'        => $release['version'],
            'author'         => $author,
            'author_profile' => $plugin['AuthorURI'],
            'homepage'       => $plugin['PluginURI'],
            'requires'       => $release['requires'],
            'requires_php'   => $release['requires_php'],
            'tested'         => $release['tested'],
            'last_updated'   => $release['published_at'],
            'download_link'  => $release['package'],
            'sections'       => array(
                'description' => wpautop(esc_html($plugin['Description'])),
                'changelog'   => $this->format_changelog($release['changelog'], $release['html_url']),
            ),
        );
    }

    /**
     * Renames the extracted package folder to the folder the plugin is
     * installed in, so the plugin keeps its basename and stays active.
     *
     * GitHub archives extract to "Owner-Repo-<sha>/" and the release asset to
     * "custom-utm-tracker/", while sites may have the plugin in any folder
     * (e.g. "Custom-UTM-Tracker-Link-Builder-main/").
     *
     * @param string|WP_Error $source        Extracted package folder.
     * @param string          $remote_source Temporary working folder.
     * @param WP_Upgrader     $upgrader
     * @param array           $hook_extra
     * @return string|WP_Error
     */
    public function normalize_source_dir($source, $remote_source, $upgrader, $hook_extra = array()) {
        global $wp_filesystem;

        if (is_wp_error($source) || empty($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->basename) {
            return $source;
        }
        if (!$wp_filesystem || '.' === $this->slug) {
            return $source;
        }

        $source = trailingslashit($source);

        if (!$wp_filesystem->exists($source . basename($this->file))) {
            return new WP_Error(
                'cutm_invalid_package',
                __('The update package does not contain Custom UTM Tracker & Link Builder.', 'custom-utm-tracker')
            );
        }

        $target = trailingslashit($remote_source) . $this->slug . '/';

        // Already in place, or files sit at the archive root (nothing to rename safely).
        if ($source === $target || untrailingslashit($source) === untrailingslashit($remote_source)) {
            return $source;
        }

        if ($wp_filesystem->exists($target)) {
            $wp_filesystem->delete($target, true);
        }

        if (function_exists('move_dir')) {
            $moved = move_dir(untrailingslashit($source), untrailingslashit($target), true);
        } else {
            $moved = $wp_filesystem->move(untrailingslashit($source), untrailingslashit($target), true);
        }

        if (is_wp_error($moved)) {
            return $moved;
        }
        if (!$moved) {
            return new WP_Error(
                'cutm_rename_failed',
                __('Could not prepare the update package folder.', 'custom-utm-tracker')
            );
        }

        return $target;
    }

    /**
     * Turns on background auto-updates for this plugin on every site.
     *
     * Opt out per site with `define('CUTM_DISABLE_AUTO_UPDATE', true);` in
     * wp-config.php (the regular "Enable/Disable auto-updates" link then
     * applies) or with the `cutm_enable_auto_update` filter.
     *
     * @param bool|null $update
     * @param object    $item
     * @return bool|null
     */
    public function enable_auto_update($update, $item) {
        if (!is_object($item) || empty($item->plugin) || $item->plugin !== $this->basename) {
            return $update;
        }
        if (defined('CUTM_DISABLE_AUTO_UPDATE') && CUTM_DISABLE_AUTO_UPDATE) {
            return $update;
        }

        return (bool) apply_filters('cutm_enable_auto_update', true, $item);
    }

    /**
     * Adds the optional token to package downloads from this repository
     * (only relevant for private repositories).
     *
     * @param array  $args
     * @param string $url
     * @return array
     */
    public function authorize_github_requests($args, $url) {
        $token = $this->get_token();
        if ('' === $token || !is_string($url)) {
            return $args;
        }

        $prefix = sprintf('https://api.github.com/repos/%s/%s/', $this->owner, $this->repo);
        if (0 !== stripos($url, $prefix)) {
            return $args;
        }

        if (!isset($args['headers']) || !is_array($args['headers'])) {
            $args['headers'] = array();
        }
        $args['headers']['Authorization'] = 'Bearer ' . $token;

        return $args;
    }

    /**
     * Returns the latest release, from cache when possible.
     *
     * "Check again" on Dashboard > Updates bypasses the cache.
     *
     * @return array|false
     */
    private function get_release() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only flag, only skips the cache.
        $force = is_admin() && !empty($_GET['force-check']) && current_user_can('update_plugins');

        if (!$force) {
            $cached = get_site_transient(self::CACHE_KEY);
            if (is_array($cached) && array_key_exists('release', $cached)) {
                return $cached['release'];
            }
        }

        $release = $this->fetch_release();
        set_site_transient(self::CACHE_KEY, array('release' => $release), $release ? self::CACHE_TTL : self::ERROR_TTL);

        return $release;
    }

    /**
     * Queries GitHub for the latest published release.
     *
     * The version is read from the plugin header inside the tagged code (not
     * only from the tag name): a tag whose code carries another version can
     * never cause an endless update loop.
     *
     * @return array|false
     */
    private function fetch_release() {
        $body = $this->remote_get(
            sprintf('https://api.github.com/repos/%s/%s/releases/latest', $this->owner, $this->repo),
            'application/vnd.github+json'
        );
        if (false === $body) {
            return false;
        }

        $data = json_decode($body, true);
        if (!is_array($data) || empty($data['tag_name']) || !empty($data['draft']) || !empty($data['prerelease'])) {
            return false;
        }

        $tag     = (string) $data['tag_name'];
        $headers = $this->fetch_remote_headers($tag);
        $version = isset($headers['Version']) && $this->is_valid_version($headers['Version'])
            ? $headers['Version']
            : ltrim($tag, 'vV');

        if (!$this->is_valid_version($version)) {
            return false;
        }

        $package = $this->find_package($data);
        if ('' === $package) {
            return false;
        }

        $changelog = isset($data['body']) ? (string) $data['body'] : '';
        if (strlen($changelog) > self::MAX_CHANGELOG_LENGTH) {
            $changelog = substr($changelog, 0, self::MAX_CHANGELOG_LENGTH);
        }

        return array(
            'version'      => $version,
            'tag'          => $tag,
            'package'      => $package,
            'html_url'     => isset($data['html_url']) ? (string) $data['html_url'] : '',
            'published_at' => isset($data['published_at']) ? (string) $data['published_at'] : '',
            'changelog'    => $changelog,
            'requires'     => isset($headers['RequiresWP']) ? $headers['RequiresWP'] : '',
            'requires_php' => isset($headers['RequiresPHP']) ? $headers['RequiresPHP'] : '',
            'tested'       => isset($headers['TestedUpTo']) ? $headers['TestedUpTo'] : '',
        );
    }

    /**
     * Picks the download: the prebuilt release asset when available (public
     * repository), otherwise GitHub's source archive of the tag.
     *
     * @param array $data Release data from the GitHub API.
     * @return string
     */
    private function find_package(array $data) {
        if ('' === $this->get_token() && !empty($data['assets']) && is_array($data['assets'])) {
            foreach ($data['assets'] as $asset) {
                if (is_array($asset) && isset($asset['name'], $asset['browser_download_url']) && self::ASSET_NAME === $asset['name']) {
                    return (string) $asset['browser_download_url'];
                }
            }
        }

        return isset($data['zipball_url']) ? (string) $data['zipball_url'] : '';
    }

    /**
     * Reads the plugin headers of the main file at the given tag.
     *
     * @param string $tag
     * @return array
     */
    private function fetch_remote_headers($tag) {
        $body = $this->remote_get(sprintf(
            'https://raw.githubusercontent.com/%s/%s/%s/%s',
            $this->owner,
            $this->repo,
            rawurlencode($tag),
            rawurlencode(basename($this->file))
        ));
        if (false === $body) {
            return array();
        }

        return self::parse_headers(substr($body, 0, 8192));
    }

    /**
     * Extracts plugin headers from file contents (same rules as get_file_data()).
     *
     * @param string $contents
     * @return array
     */
    public static function parse_headers($contents) {
        $map = array(
            'Version'     => 'Version',
            'RequiresWP'  => 'Requires at least',
            'RequiresPHP' => 'Requires PHP',
            'TestedUpTo'  => 'Tested up to',
        );

        $contents = str_replace("\r", "\n", (string) $contents);
        $headers  = array();

        foreach ($map as $key => $label) {
            if (preg_match('/^(?:[ \t]*<\?php)?[ \t\/*#@]*' . preg_quote($label, '/') . ':(.*)$/mi', $contents, $match) && $match[1]) {
                $value = trim(preg_replace('/\s*(?:\*\/|\?>).*/', '', $match[1]));
                if ('' !== $value) {
                    $headers[$key] = $value;
                }
            }
        }

        return $headers;
    }

    /**
     * @param string $version
     * @return bool
     */
    private function is_valid_version($version) {
        return is_string($version) && (bool) preg_match('/^\d+(\.\d+){0,3}([.-][0-9A-Za-z]+)*$/', $version);
    }

    /**
     * @param string $url
     * @param string $accept Optional Accept header.
     * @return string|false Body on HTTP 200, false otherwise.
     */
    private function remote_get($url, $accept = '') {
        $args = array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'Custom-UTM-Tracker/' . (defined('CUTM_VERSION') ? CUTM_VERSION : '0') . ' (WordPress)',
            ),
        );
        if ('' !== $accept) {
            $args['headers']['Accept'] = $accept;
        }

        $token = $this->get_token();
        if ('' !== $token) {
            $args['headers']['Authorization'] = 'Bearer ' . $token;
        }

        $response = wp_remote_get($url, $args);
        if (is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response)) {
            return false;
        }

        return (string) wp_remote_retrieve_body($response);
    }

    /**
     * @return string
     */
    private function get_token() {
        return defined('CUTM_GITHUB_TOKEN') ? trim((string) CUTM_GITHUB_TOKEN) : '';
    }

    /**
     * Converts GitHub release notes (Markdown) into the small HTML subset the
     * update popup allows. Everything is escaped first.
     *
     * @param string $markdown
     * @param string $url Release page on GitHub.
     * @return string
     */
    private function format_changelog($markdown, $url) {
        $html = '';
        $list = false;

        foreach (preg_split('/\R/', trim((string) $markdown)) as $line) {
            $line = trim($line);

            if (preg_match('/^[-*+]\s+(.+)$/', $line, $match)) {
                if (!$list) {
                    $html .= '<ul>';
                    $list  = true;
                }
                $html .= '<li>' . $this->format_inline($match[1]) . '</li>';
                continue;
            }

            if ($list) {
                $html .= '</ul>';
                $list  = false;
            }

            if ('' === $line) {
                continue;
            }

            if (preg_match('/^#{1,6}\s+(.+)$/', $line, $match)) {
                $html .= '<h4>' . $this->format_inline($match[1]) . '</h4>';
            } else {
                $html .= '<p>' . $this->format_inline($line) . '</p>';
            }
        }

        if ($list) {
            $html .= '</ul>';
        }

        if ('' !== $url) {
            $html .= sprintf(
                '<p><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>',
                esc_url($url),
                esc_html__('View this release on GitHub', 'custom-utm-tracker')
            );
        }

        return $html;
    }

    /**
     * @param string $text
     * @return string
     */
    private function format_inline($text) {
        $text = esc_html($text);
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);

        return make_clickable($text);
    }
}

endif;
