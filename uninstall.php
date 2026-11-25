<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 * Removes the settings and the cached report. Nothing else is stored.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (is_multisite()) {

    foreach (get_sites(['fields' => 'ids', 'number' => 0]) as $corespect_site_id) {
        switch_to_blog($corespect_site_id);
        delete_option('corespect_settings');
        delete_transient('corespect_last_report');
        restore_current_blog();
    }

} else {

    delete_option('corespect_settings');
    delete_transient('corespect_last_report');
}
