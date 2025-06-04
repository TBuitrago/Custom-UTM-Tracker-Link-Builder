<?php
/**
 * Plugin Name: Custom UTM Tracker & Link Builder
 * Plugin URI: https://github.com/TBuitrago/Custom-UTM-Tracker-Link-Builder
 * Description: Captures specific URL parameters (utm_source, utm_campaign, etc.) and stores them in cookies for up to 30 days. Includes admin interface for managing custom cookies and generating shareable links.
 * Version: 1.0.1
 * GitHub Plugin URI: https://github.com/TBuitrago/Custom-UTM-Tracker-Link-Builder
 * GitHub Branch: main
 * Author: Tomas Buitrago
 * Author URI: https://surchdigital.com
 * Company: Surch Digital
 * License: GPL v2 or later
 * Text Domain: custom-utm-tracker
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include GitHub updater class
require_once plugin_dir_path(__FILE__) . 'includes/class-github-updater.php';

// Define plugin constants
define('CUTM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CUTM_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('CUTM_VERSION', '1.0.1');

/**
 * Main plugin class
 */
class CustomUTMTracker {
    
    /**
     * Tracking parameters to monitor
     */
    private $tracking_params = array(
        'utm_source',
        'utm_campaign',
        'utm_medium',
        'utm_term',
        'utm_adgroup',
        'utm_content'
    );

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'store_utm_in_cookies'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * Store UTM parameters in cookies and handle redirects
     */
    public function store_utm_in_cookies() {
        // Skip if in admin or doing AJAX
        if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
            return;
        }

        // Check if any UTM parameters are present in the URL
        $has_utm = false;
        foreach ($this->tracking_params as $param) {
            if (!empty($_GET[$param])) {
                $has_utm = true;
                break;
            }
        }

        if ($has_utm) {
            // Store UTM parameters in cookies
            foreach ($this->tracking_params as $param) {
                if (!empty($_GET[$param])) {
                    $clean_value = sanitize_text_field($_GET[$param]);
                    setcookie($param, $clean_value, time() + (30 * DAY_IN_SECONDS), '/');
                    $_COOKIE[$param] = $clean_value;
                }
            }
        } else {
            // Check if we have UTM parameters in cookies but not in URL
            $params_from_cookie = array();
            foreach ($this->tracking_params as $param) {
                if (!empty($_COOKIE[$param])) {
                    $params_from_cookie[$param] = sanitize_text_field($_COOKIE[$param]);
                }
            }

            // If we have stored parameters, redirect to include them
            if (!empty($params_from_cookie)) {
                $clean_url = remove_query_arg($this->tracking_params);
                $redirect_url = add_query_arg($params_from_cookie, $clean_url);

                $current_url = home_url(add_query_arg(null, null));
                if ($redirect_url !== $current_url) {
                    wp_safe_redirect($redirect_url);
                    exit;
                }
            }
        }
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
     * Render the link builder admin page
     */
    public function render_link_builder_page() {
        include_once CUTM_PLUGIN_PATH . 'admin/link-builder-page.php';
    }

    /**
     * Get all published pages for dropdown
     */
    public function get_published_pages() {
        $pages = get_pages(array(
            'post_status' => 'publish',
            'sort_column' => 'post_title',
            'sort_order' => 'ASC'
        ));

        $page_options = array();
        foreach ($pages as $page) {
            $page_options[] = array(
                'value' => get_permalink($page->ID),
                'label' => $page->post_title,
                'relative' => str_replace(home_url(), '', get_permalink($page->ID))
            );
        }

        return $page_options;
    }

    /**
     * AJAX handler to add a new custom cookie
     */
    public function ajax_add_cookie() {
        check_ajax_referer('cutm_add_cookie_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $key = isset($_POST['cookie_key']) ? sanitize_key($_POST['cookie_key']) : '';
        $description = isset($_POST['cookie_description']) ? sanitize_text_field($_POST['cookie_description']) : '';

        if (empty($key)) {
            wp_send_json_error(array('message' => 'Cookie key is required'));
        }

        // Validate key format
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
            wp_send_json_error(array('message' => 'Invalid cookie key format'));
        }

        $custom_cookies = get_option('cutm_custom_cookies', array());

        if (isset($custom_cookies[$key])) {
            wp_send_json_error(array('message' => 'Cookie key already exists'));
        }

        $custom_cookies[$key] = array(
            'description' => $description
        );

        update_option('cutm_custom_cookies', $custom_cookies);

        wp_send_json_success();
    }

    /**
     * AJAX handler to update cookie value (set cookie)
     */
    public function ajax_update_cookie_value() {
        check_ajax_referer('cutm_add_cookie_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $key = isset($_POST['cookie_key']) ? sanitize_key($_POST['cookie_key']) : '';
        $value = isset($_POST['cookie_value']) ? sanitize_text_field($_POST['cookie_value']) : '';

        if (empty($key)) {
            wp_send_json_error(array('message' => 'Cookie key is required'));
        }

        // Set cookie for 30 days
        setcookie($key, $value, time() + (30 * DAY_IN_SECONDS), '/');
        $_COOKIE[$key] = $value;

        wp_send_json_success();
    }

    /**
     * AJAX handler to delete a custom cookie
     */
    public function ajax_delete_cookie() {
        check_ajax_referer('cutm_add_cookie_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $key = isset($_POST['cookie_key']) ? sanitize_key($_POST['cookie_key']) : '';

        if (empty($key)) {
            wp_send_json_error(array('message' => 'Cookie key is required'));
        }

        $custom_cookies = get_option('cutm_custom_cookies', array());

        if (!isset($custom_cookies[$key])) {
            wp_send_json_error(array('message' => 'Cookie key does not exist'));
        }

        unset($custom_cookies[$key]);
        update_option('cutm_custom_cookies', $custom_cookies);

        // Delete cookie by setting expiration in the past
        setcookie($key, '', time() - 3600, '/');
        unset($_COOKIE[$key]);

        wp_send_json_success();
    }

    /**
     * Set custom cookies on frontend based on saved options
     */
    public function set_custom_cookies() {
        if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
            return;
        }

        $custom_cookies = get_option('cutm_custom_cookies', array());

        foreach ($custom_cookies as $key => $cookie) {
            if (!empty($_COOKIE[$key])) {
                // Refresh cookie expiration
                setcookie($key, sanitize_text_field($_COOKIE[$key]), time() + (30 * DAY_IN_SECONDS), '/');
            }
        }
    }
}

/**
 * Initialize the plugin
 */
function cutm_init() {
    $plugin = new CustomUTMTracker();

    // Register AJAX handlers
    add_action('wp_ajax_cutm_add_cookie', array($plugin, 'ajax_add_cookie'));
    add_action('wp_ajax_cutm_update_cookie_value', array($plugin, 'ajax_update_cookie_value'));
    add_action('wp_ajax_cutm_delete_cookie', array($plugin, 'ajax_delete_cookie'));

    // Set custom cookies on frontend
    add_action('init', array($plugin, 'set_custom_cookies'));

    // Initialize GitHub updater
    if (is_admin()) {
        new CUTM_GitHub_Updater(
            __FILE__,
            'TBuitrago',
            'Custom-UTM-Tracker-Link-Builder'
        );
    }
}
add_action('plugins_loaded', 'cutm_init');

/**
 * Activation hook
 */
function cutm_activate() {
    // Nothing to do on activation for now
}
register_activation_hook(__FILE__, 'cutm_activate');

/**
 * Deactivation hook
 */
function cutm_deactivate() {
    // Nothing to do on deactivation for now
}
register_deactivation_hook(__FILE__, 'cutm_deactivate');

/**
 * Uninstall hook
 */
function cutm_uninstall() {
    // Clean up any stored options if needed
    // For now, we're only using cookies which will expire naturally
}
register_uninstall_hook(__FILE__, 'cutm_uninstall');
