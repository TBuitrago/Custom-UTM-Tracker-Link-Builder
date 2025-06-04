<?php
// If uninstall is not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete all plugin options
delete_option('cutm_custom_cookies');

// Delete all cookies set by the plugin
if (isset($_COOKIE)) {
    $custom_cookies = get_option('cutm_custom_cookies', array());
    foreach ($custom_cookies as $key => $cookie) {
        if (isset($_COOKIE[$key])) {
            setcookie($key, '', time() - 3600, '/');
            unset($_COOKIE[$key]);
        }
    }
}
