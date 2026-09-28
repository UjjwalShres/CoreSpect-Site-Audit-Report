<?php
/*
Plugin Name: CoreSpect Site Audit & Report
Plugin URI:  https://github.com/UjjwalShres/CoreSpect-Site-Audit-Report
Description: Audits your WordPress site for database, performance, security, and file system stats, with one-click export to HTML, PDF, JSON, CSV, or TXT.
Version:     1.0.0
Author:      Ujjwal Shrestha
Author URI:  https://ujjwal-shrestha.com.np/
License:     GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: corespect-site-audit-report
*/

if (!defined('ABSPATH')) exit;

define('CORESPECT_PATH', plugin_dir_path(__FILE__));
define('CORESPECT_URL', plugin_dir_url(__FILE__));

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