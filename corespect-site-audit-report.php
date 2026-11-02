<?php
/*
Plugin Name: CoreSpect Site Audit & Report
Plugin URI:  https://github.com/UjjwalShres/CoreSpect-Site-Audit-Report
Description: Audits your WordPress site for database, performance, security, and file system stats, with one-click export to HTML, PDF, JSON, CSV, or TXT.
Version:     1.1.0
Author:      Ujjwal Shrestha
Author URI:  https://ujjwal-shrestha.com.np/
License:     GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: corespect-site-audit-report
*/

if (!defined('ABSPATH')) exit;

define('CORESPECT_PATH', plugin_dir_path(__FILE__));
define('CORESPECT_URL', plugin_dir_url(__FILE__));
define( 'CORESPECT_VERSION', '1.1.0' );

// Include classes
require_once CORESPECT_PATH . 'admin/class-admin.php';
require_once CORESPECT_PATH . 'includes/class-db.php';
require_once CORESPECT_PATH . 'includes/class-performance.php';
require_once CORESPECT_PATH . 'includes/class-health-evaluator.php';
require_once CORESPECT_PATH . 'includes/class-data.php';
require_once CORESPECT_PATH . 'includes/class-report.php';


// initialize menu only
add_action('admin_menu', ['CoreSpect_Admin', 'init']);

// register export EARLY
add_action('admin_post_cs_export', ['CoreSpect_Admin', 'handle_export']);

// quick link next to "Deactivate" on the Plugins list page.
function corespect_add_action_links( $links ) {
    $url = admin_url( 'tools.php?page=corespect-site-audit-report' );

    $report_link = '<a href="' . esc_url( $url ) . '">'
        . esc_html__( 'Settings', 'corespect-site-audit-report' )
        . '</a>';

    array_unshift( $links, $report_link );

    return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'corespect_add_action_links' );

function corespect_clear_report_cache() {
    delete_transient( 'corespect_last_report' );
}
add_action( 'activated_plugin', 'corespect_clear_report_cache' );
add_action( 'deactivated_plugin', 'corespect_clear_report_cache' );
add_action( 'deleted_plugin', 'corespect_clear_report_cache' );
add_action( 'switch_theme', 'corespect_clear_report_cache' );
add_action( 'upgrader_process_complete', 'corespect_clear_report_cache' );

// "Refresh report" button handler.
function corespect_handle_refresh() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'No permission' );
    }

    check_admin_referer( 'corespect_refresh' );

    corespect_clear_report_cache();

    $tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'db';

    wp_safe_redirect( admin_url( 'tools.php?page=corespect-site-audit-report&tab=' . $tab ) );
    exit;
}
add_action( 'admin_post_corespect_refresh', 'corespect_handle_refresh' );