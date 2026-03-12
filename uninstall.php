<?php
// If uninstall is not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Read cookies option BEFORE deleting it
$custom_cookies = get_option('cutm_custom_cookies', array());

// Delete all plugin options
delete_option('cutm_custom_cookies');
delete_option('cutm_link_history');

// Now clear the browser cookies that were set by the plugin
foreach ($custom_cookies as $key => $cookie) {
    setcookie($key, '', time() - 3600, '/');
    unset($_COOKIE[$key]);
}
