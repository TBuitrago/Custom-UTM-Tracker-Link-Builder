<?php
/**
 * Removes the plugin's data when it is deleted from wp-admin.
 *
 * Cookies already stored in visitors' browsers expire on their own.
 */

// If uninstall is not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$cutm_delete_site_data = static function () {
    delete_option('cutm_custom_cookies');
    delete_option('cutm_link_history');
};

if (is_multisite() && function_exists('get_sites')) {
    foreach (get_sites(array('fields' => 'ids', 'number' => 0)) as $cutm_site_id) {
        switch_to_blog($cutm_site_id);
        $cutm_delete_site_data();
        restore_current_blog();
    }
} else {
    $cutm_delete_site_data();
}

delete_site_transient('cutm_github_release');
