<?php
/**
 * Plugin Name:       Custom UTM Tracker & Link Builder
 * Plugin URI:        https://github.com/TBuitrago/Custom-UTM-Tracker-Link-Builder
 * Description:       Captures specific URL parameters (utm_source, utm_campaign, etc.) and stores them in cookies for up to 30 days. Includes admin interface for managing custom cookies and generating shareable links.
 * Version:           2.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.2
 * Author:            Tomas Buitrago
 * Author URI:        https://surchdigital.com
 * Company:           Surch Digital
 * License:           GPL v2 or later
 * Text Domain:       custom-utm-tracker
 * Update URI:        https://github.com/TBuitrago/Custom-UTM-Tracker-Link-Builder
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Another copy of the plugin is already loaded (e.g. installed twice in different folders).
if (defined('CUTM_VERSION')) {
    return;
}

// Define plugin constants
define('CUTM_VERSION', '2.1.0');
define('CUTM_PLUGIN_FILE', __FILE__);
define('CUTM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CUTM_PLUGIN_PATH', plugin_dir_path(__FILE__));

require_once CUTM_PLUGIN_PATH . 'includes/class-cutm-updater.php';

/**
 * Main plugin class
 */
class CustomUTMTracker {

    const OPTION_COOKIES         = 'cutm_custom_cookies';
    const OPTION_HISTORY         = 'cutm_link_history';
    const MAX_HISTORY_ITEMS      = 200;
    const MAX_KEY_LENGTH         = 64;
    const MAX_DESCRIPTION_LENGTH = 200;

    /**
     * Constructor
     */
    public function __construct() {
        // Frontend tracking.
        add_action('init', array($this, 'store_utm_in_cookies'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));

        // WPForms integration.
        add_action('wpforms_frontend_output_before', array($this, 'enqueue_wpforms_scripts'));
        add_filter('wpforms_process_filter', array($this, 'fill_wpforms_fields_from_cookies'), 10, 3);

        // Admin.
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_cutm_add_cookie', array($this, 'ajax_add_cookie'));
        add_action('wp_ajax_cutm_delete_cookie', array($this, 'ajax_delete_cookie'));
        add_action('wp_ajax_cutm_save_link_history', array($this, 'ajax_save_link_history'));
        add_action('wp_ajax_cutm_delete_link_history', array($this, 'ajax_delete_link_history'));
    }

    /**
     * Tracked parameter names (the keys defined under Tools > Custom Cookies).
     *
     * @return string[]
     */
    public static function get_tracking_params() {
        $custom_cookies = get_option(self::OPTION_COOKIES, array());
        if (!is_array($custom_cookies)) {
            return array();
        }

        $params = array();
        foreach (array_keys($custom_cookies) as $key) {
            $key = (string) $key;
            if (preg_match('/^[a-z0-9_]+$/', $key)) {
                $params[] = $key;
            }
        }

        return $params;
    }

    /**
     * Cookie lifetime in seconds (default 30 days).
     *
     * @return int
     */
    public static function get_cookie_lifetime() {
        $lifetime = (int) apply_filters('cutm_cookie_lifetime', 30 * DAY_IN_SECONDS);

        return $lifetime > 0 ? $lifetime : 30 * DAY_IN_SECONDS;
    }

    /**
     * Max characters stored per value. Keeps a crafted link from planting
     * oversized cookies that would break every request of that visitor
     * ("Request Header Too Large").
     *
     * @return int
     */
    public static function get_max_value_length() {
        $length = (int) apply_filters('cutm_cookie_max_length', 200);

        return $length > 0 ? min($length, 1000) : 200;
    }

    /**
     * Sanitizes a raw (slashed) value coming from $_GET or $_COOKIE.
     *
     * @param mixed $raw
     * @return string
     */
    public static function sanitize_value($raw) {
        if (!is_string($raw)) {
            return '';
        }

        $value = sanitize_text_field(wp_unslash($raw));
        $max   = self::get_max_value_length();
        if (mb_strlen($value) > $max) {
            $value = trim(mb_substr($value, 0, $max));
        }

        return $value;
    }

    /**
     * Stored value of a tracked parameter for the current visitor.
     *
     * @param string $key
     * @return string
     */
    public static function get_value($key) {
        return isset($_COOKIE[$key]) ? self::sanitize_value($_COOKIE[$key]) : '';
    }

    /**
     * Sets a first-party cookie readable by JavaScript (GTM, etc.).
     *
     * @param string $name
     * @param string $value
     * @param int    $expires
     * @return bool
     */
    private static function set_cookie($name, $value, $expires) {
        if (headers_sent()) {
            return false;
        }

        $secure = is_ssl();

        if (PHP_VERSION_ID >= 70300) {
            return setcookie($name, $value, array(
                'expires'  => $expires,
                'path'     => '/',
                'secure'   => $secure,
                'httponly' => false,
                'samesite' => 'Lax',
            ));
        }

        return setcookie($name, $value, $expires, '/; samesite=Lax', '', $secure, false);
    }

    /**
     * Store UTM parameters in cookies
     * Cookies are stored silently — UTM params are never added back to the URL.
     *
     * Only runs when the page is generated by PHP. Pages served from a page
     * cache (LiteSpeed, WP Rocket, Cloudflare...) are covered by js/utm-capture.js.
     */
    public function store_utm_in_cookies() {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron() || (defined('WP_CLI') && WP_CLI) || empty($_GET)) {
            return;
        }

        $expires = time() + self::get_cookie_lifetime();

        foreach (self::get_tracking_params() as $param) {
            if (!isset($_GET[$param])) {
                continue;
            }

            $value = self::sanitize_value($_GET[$param]);
            if ('' === $value) {
                continue;
            }

            if (self::set_cookie($param, $value, $expires)) {
                $_COOKIE[$param] = wp_slash($value);
            }
        }
    }

    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts() {
        $params = self::get_tracking_params();
        if (empty($params)) {
            return;
        }

        // Client-side capture: works even when the page comes from a page cache.
        wp_enqueue_script(
            'cutm-capture',
            CUTM_PLUGIN_URL . 'js/utm-capture.js',
            array(),
            CUTM_VERSION,
            array(
                'in_footer' => false,
                'strategy'  => 'defer',
            )
        );

        wp_add_inline_script(
            'cutm-capture',
            'window.cutmTracker = ' . wp_json_encode(array(
                'params'    => $params,
                'lifetime'  => self::get_cookie_lifetime(),
                'maxLength' => self::get_max_value_length(),
            )) . ';',
            'before'
        );
    }

    /**
     * Loads the WPForms datetime helper only on pages that render a form.
     */
    public function enqueue_wpforms_scripts() {
        static $enqueued = false;
        if ($enqueued) {
            return;
        }
        $enqueued = true;

        // ID of the hidden field that receives the submission date/time ('' disables it).
        $field_id = (string) apply_filters('cutm_wpforms_datetime_field_id', '16');
        if (!preg_match('/^\d+$/', $field_id)) {
            $field_id = '';
        }

        wp_enqueue_script(
            'cutm-form-datetime',
            CUTM_PLUGIN_URL . 'js/form-datetime.js',
            array('jquery'),
            CUTM_VERSION,
            true
        );

        wp_add_inline_script(
            'cutm-form-datetime',
            'window.cutmForms = ' . wp_json_encode(array('datetimeFieldId' => $field_id)) . ';',
            'before'
        );
    }

    /**
     * Fills WPForms hidden fields that use {query_var key="..."} with the value
     * stored in the visitor's cookie.
     *
     * {query_var} only reads the current URL, so without this the UTM data is
     * lost as soon as the visitor navigates to another page before submitting,
     * and pages served from a page cache carry whatever value was cached. The
     * cookie (set from the real landing URL) wins when present; otherwise the
     * submitted value is kept.
     *
     * @param array $fields    Processed fields.
     * @param array $entry     Raw submission.
     * @param array $form_data Form settings.
     * @return array
     */
    public function fill_wpforms_fields_from_cookies($fields, $entry, $form_data) {
        if (!is_array($fields) || empty($form_data['fields']) || !is_array($form_data['fields'])) {
            return $fields;
        }

        $tracked = array_flip(self::get_tracking_params());
        if (empty($tracked)) {
            return $fields;
        }

        foreach ($form_data['fields'] as $field_id => $field) {
            if (!isset($fields[$field_id]) || !is_array($fields[$field_id]) || !is_array($field)) {
                continue;
            }
            if (empty($field['type']) || 'hidden' !== $field['type'] || empty($field['default_value']) || !is_string($field['default_value'])) {
                continue;
            }
            if (!preg_match('/^\s*\{query_var\s+key\s*=\s*["\']?([a-zA-Z0-9_]+)["\']?\s*\}\s*$/', $field['default_value'], $match)) {
                continue;
            }

            $key = strtolower($match[1]);
            if (!isset($tracked[$key])) {
                continue;
            }

            $value = self::get_value($key);
            if ('' !== $value) {
                $fields[$field_id]['value'] = $value;
            }
        }

        return $fields;
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_management_page(
            'UTM Link Builder',
            'UTM Link Builder',
            'manage_options',
            'utm-link-builder',
            array($this, 'render_link_builder_page')
        );

        add_submenu_page(
            'tools.php',
            'Custom Cookies',
            'Custom Cookies',
            'manage_options',
            'custom-cookies',
            array($this, 'render_custom_cookies_page')
        );
    }

    /**
     * Render the custom cookies admin page
     */
    public function render_custom_cookies_page() {
        include_once CUTM_PLUGIN_PATH . 'admin/custom-cookies-page.php';
    }

    /**
     * Render the link builder admin page
     */
    public function render_link_builder_page() {
        include_once CUTM_PLUGIN_PATH . 'admin/link-builder-page.php';
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook === 'tools_page_utm-link-builder') {
            wp_enqueue_script(
                'cutm-link-builder',
                CUTM_PLUGIN_URL . 'js/link-builder.js',
                array('jquery'),
                CUTM_VERSION,
                true
            );

            // Localize script for AJAX
            wp_localize_script('cutm-link-builder', 'cutm_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cutm_nonce'),
                'home_url' => home_url()
            ));
        } elseif ($hook === 'tools_page_custom-cookies') {
            wp_enqueue_script(
                'cutm-custom-cookies',
                CUTM_PLUGIN_URL . 'js/custom-cookies.js',
                array('jquery'),
                CUTM_VERSION,
                true
            );

            wp_localize_script('cutm-custom-cookies', 'cutm_cookies', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cutm_add_cookie_nonce'),
                'no_cookies_text' => esc_html__('No custom cookies defined yet.', 'custom-utm-tracker')
            ));
        }
    }

    /**
     * Nonce + capability check shared by all AJAX handlers.
     *
     * @param string $nonce_action
     */
    private function verify_ajax_request($nonce_action) {
        check_ajax_referer($nonce_action, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You are not allowed to do this.', 'custom-utm-tracker')), 403);
        }
    }

    /**
     * Cookie names WordPress or PHP rely on cannot be tracked: visitors could
     * otherwise overwrite them through a crafted link.
     *
     * @param string $key
     * @return bool
     */
    private static function is_reserved_key($key) {
        if (in_array($key, array('phpsessid'), true)) {
            return true;
        }

        foreach (array('wordpress_', 'wp_', 'wp-', 'comment_author') as $prefix) {
            if (0 === strpos($key, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * AJAX handler to add a new custom cookie
     */
    public function ajax_add_cookie() {
        $this->verify_ajax_request('cutm_add_cookie_nonce');

        $key = isset($_POST['cookie_key']) ? sanitize_key(wp_unslash($_POST['cookie_key'])) : '';
        $description = isset($_POST['cookie_description']) ? sanitize_text_field(wp_unslash($_POST['cookie_description'])) : '';

        if ($key === '') {
            wp_send_json_error(array('message' => __('Cookie key is required', 'custom-utm-tracker')));
        }

        // Validate key format
        if (!preg_match('/^[a-z0-9_]+$/', $key) || strlen($key) > self::MAX_KEY_LENGTH) {
            wp_send_json_error(array('message' => __('Invalid cookie key format', 'custom-utm-tracker')));
        }

        if (self::is_reserved_key($key)) {
            wp_send_json_error(array('message' => __('This name is reserved by WordPress. Please choose another one.', 'custom-utm-tracker')));
        }

        $custom_cookies = get_option(self::OPTION_COOKIES, array());
        if (!is_array($custom_cookies)) {
            $custom_cookies = array();
        }

        if (isset($custom_cookies[$key])) {
            wp_send_json_error(array('message' => __('Cookie key already exists', 'custom-utm-tracker')));
        }

        $custom_cookies[$key] = array(
            'description' => mb_substr($description, 0, self::MAX_DESCRIPTION_LENGTH),
        );

        update_option(self::OPTION_COOKIES, $custom_cookies);

        wp_send_json_success();
    }

    /**
     * AJAX handler to delete a custom cookie
     */
    public function ajax_delete_cookie() {
        $this->verify_ajax_request('cutm_add_cookie_nonce');

        $key = isset($_POST['cookie_key']) ? sanitize_key(wp_unslash($_POST['cookie_key'])) : '';

        if ($key === '') {
            wp_send_json_error(array('message' => __('Cookie key is required', 'custom-utm-tracker')));
        }

        $custom_cookies = get_option(self::OPTION_COOKIES, array());

        if (!is_array($custom_cookies) || !isset($custom_cookies[$key])) {
            wp_send_json_error(array('message' => __('Cookie key does not exist', 'custom-utm-tracker')));
        }

        unset($custom_cookies[$key]);
        update_option(self::OPTION_COOKIES, $custom_cookies);

        wp_send_json_success();
    }

    /**
     * AJAX handler to save a generated link to history
     */
    public function ajax_save_link_history() {
        $this->verify_ajax_request('cutm_nonce');

        $url = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url']), array('http', 'https')) : '';

        if ($url === '') {
            wp_send_json_error(array('message' => __('URL is required', 'custom-utm-tracker')));
        }

        $link_history = get_option(self::OPTION_HISTORY, array());
        if (!is_array($link_history)) {
            $link_history = array();
        }

        // Timestamp key, unique even when two links are saved in the same second.
        $timestamp = time();
        while (isset($link_history[$timestamp])) {
            $timestamp++;
        }
        $link_history[$timestamp] = $url;

        // Keep only the most recent entries.
        ksort($link_history, SORT_NUMERIC);
        if (count($link_history) > self::MAX_HISTORY_ITEMS) {
            $link_history = array_slice($link_history, -self::MAX_HISTORY_ITEMS, null, true);
        }

        // Not autoloaded: only the link builder screen needs it.
        update_option(self::OPTION_HISTORY, $link_history, false);

        wp_send_json_success();
    }

    /**
     * AJAX handler to delete a link from history
     */
    public function ajax_delete_link_history() {
        $this->verify_ajax_request('cutm_nonce');

        $timestamp = isset($_POST['timestamp']) ? absint($_POST['timestamp']) : 0;

        if (!$timestamp) {
            wp_send_json_error(array('message' => __('Invalid timestamp', 'custom-utm-tracker')));
        }

        $link_history = get_option(self::OPTION_HISTORY, array());

        if (!is_array($link_history) || !isset($link_history[$timestamp])) {
            wp_send_json_error(array('message' => __('Link not found', 'custom-utm-tracker')));
        }

        unset($link_history[$timestamp]);

        update_option(self::OPTION_HISTORY, $link_history, false);

        wp_send_json_success();
    }
}

/**
 * Updates from GitHub. Registered in every context (admin, WP-Cron, WP-CLI)
 * so that background auto-updates work.
 */
function cutm_register_updater() {
    $updater = new CUTM_Updater(__FILE__, 'TBuitrago', 'Custom-UTM-Tracker-Link-Builder');
    $updater->register();
}
cutm_register_updater();

/**
 * Initialize the plugin
 */
function cutm_init() {
    new CustomUTMTracker();
}
add_action('plugins_loaded', 'cutm_init');
