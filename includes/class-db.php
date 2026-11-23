<?php
if (!defined('ABSPATH')) exit;

class CoreSpect_DB {

    /*
    ---------------------------------
    Get all table stats + totals
    ---------------------------------
    */
    public static function get_db_stats() {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- live table sizes are the entire point of a site-health report; caching would show stale data. The full report is already cached for 5 minutes via the corespect_last_report transient.
        $tables = $wpdb->get_results("SHOW TABLE STATUS", ARRAY_A);

        $data = [];
        $total_size = 0;
        $total_overhead = 0;
        $total_rows = 0;

        foreach($tables as $table){

            $size = $table['Data_length'] + $table['Index_length'];
            $overhead = $table['Data_free'];
            $rows = $table['Rows'];

            $total_size += $size;
            $total_overhead += $overhead;
            $total_rows += $rows;

            $data[] = [
                'name' => $table['Name'],
                'rows' => $table['Rows'],
                'size' => $size,
                'overhead' => $overhead
            ];
        }

        return [
            'tables' => $data,
            'total_size' => $total_size,
            'total_overhead' => $total_overhead,
            'total_rows' => $total_rows
        ];
    }


    /*
    ---------------------------------
    Core WordPress tables
    ---------------------------------
    */
    private static function get_core_tables() {
        global $wpdb;

        $core = array_values($wpdb->tables('all'));

        if (is_multisite()) {
            $core = array_merge($core, array_values($wpdb->tables('ms_global')));
        }

        return $core;
    }


    /*
    ---------------------------------
    Table-name prefixes of well-known plugins (after the WordPress prefix).
    Best-effort only: table names cannot prove who owns a table.
    Extend with the 'corespect_known_plugin_tables' filter.
    'shared' = a library used by several plugins, never "leftover".
    ---------------------------------
    */
    public static function get_known_plugin_tables() {

        $known = [
            'actionscheduler_' => ['name' => 'Action Scheduler',  'slugs' => [], 'shared' => true],
            'wpforms_'         => ['name' => 'WPForms',           'slugs' => ['wpforms-lite', 'wpforms']],
            'wpmailsmtp_'      => ['name' => 'WP Mail SMTP',      'slugs' => ['wp-mail-smtp', 'wp-mail-smtp-pro']],
            'rank_math_'       => ['name' => 'Rank Math SEO',     'slugs' => ['seo-by-rank-math', 'seo-by-rank-math-pro']],
            'e_events'         => ['name' => 'Elementor',         'slugs' => ['elementor', 'elementor-pro']],
            'e_notes'          => ['name' => 'Elementor',         'slugs' => ['elementor', 'elementor-pro']],
            'e_submissions'    => ['name' => 'Elementor',         'slugs' => ['elementor', 'elementor-pro']],
            'apcm_'            => ['name' => 'APCu Manager',      'slugs' => ['apcu-manager']],
            'woocommerce_'     => ['name' => 'WooCommerce',       'slugs' => ['woocommerce']],
            'wc_'              => ['name' => 'WooCommerce',       'slugs' => ['woocommerce']],
            'yoast_'           => ['name' => 'Yoast SEO',         'slugs' => ['wordpress-seo', 'wordpress-seo-premium']],
            'redirection_'     => ['name' => 'Redirection',       'slugs' => ['redirection']],
            'litespeed_'       => ['name' => 'LiteSpeed Cache',   'slugs' => ['litespeed-cache']],
            'aioseo_'          => ['name' => 'All in One SEO',    'slugs' => ['all-in-one-seo-pack', 'all-in-one-seo-pack-pro']],
            'nf3_'             => ['name' => 'Ninja Forms',       'slugs' => ['ninja-forms']],
            'tec_'             => ['name' => 'The Events Calendar', 'slugs' => ['the-events-calendar']],
            'bp_'              => ['name' => 'BuddyPress',        'slugs' => ['buddypress']],
            'ngg_'             => ['name' => 'NextGEN Gallery',   'slugs' => ['nextgen-gallery']],
            'icl_'             => ['name' => 'WPML',              'slugs' => ['sitepress-multilingual-cms']],
            'cmplz_'           => ['name' => 'Complianz',         'slugs' => ['complianz-gdpr', 'complianz-gdpr-premium']],
            'ewwwio_'          => ['name' => 'EWWW Image Optimizer', 'slugs' => ['ewww-image-optimizer']],
        ];

        $known = apply_filters('corespect_known_plugin_tables', $known);

        // longest prefix first so specific entries win over general ones
        uksort($known, fn($a, $b) => strlen($b) <=> strlen($a));

        return $known;
    }

    /** Folder slugs of installed plugins (active or not) and of active ones. */
    private static function get_plugin_slug_sets() {

        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $to_slugs = function ($files) {
            $slugs = [];
            foreach ($files as $file) {
                $dir = dirname($file);
                if ($dir !== '.') {
                    $slugs[] = $dir;
                }
            }
            return array_unique($slugs);
        };

        $active = (array) get_option('active_plugins', []);

        if (is_multisite()) {
            $active = array_merge($active, array_keys((array) get_site_option('active_sitewide_plugins', [])));
        }

        return [
            'installed' => $to_slugs(array_keys(get_plugins())),
            'active'    => $to_slugs($active)
        ];
    }


    /*
    ---------------------------------
    Tables that are not WordPress core tables, matched to a plugin where
    possible. status: in_use | inactive | leftover | shared | unknown
    ---------------------------------
    */
    public static function get_plugin_tables() {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- listing current tables; caching would risk missing a table added/removed since the cache was set. No core WP API returns the raw table list.
        $all_tables = $wpdb->get_col("SHOW TABLES");
        $core_tables = self::get_core_tables();
        $known = self::get_known_plugin_tables();
        $slugs = self::get_plugin_slug_sets();

        $result = [];

        foreach ($all_tables as $table) {

            if (in_array($table, $core_tables, true)) {
                continue;
            }

            // other sites' tables on a multisite network are not plugin tables
            if (is_multisite() && preg_match('/^' . preg_quote($wpdb->base_prefix, '/') . '\d+_/', $table)) {
                continue;
            }

            $entry = ['table' => $table, 'plugin' => '', 'status' => 'unknown'];

            if (strpos($table, $wpdb->prefix) === 0) {

                $suffix = substr($table, strlen($wpdb->prefix));

                foreach ($known as $prefix => $info) {

                    if (strpos($suffix, $prefix) !== 0) {
                        continue;
                    }

                    $entry['plugin'] = $info['name'];

                    if (!empty($info['shared'])) {
                        $entry['status'] = 'shared';
                    } elseif (array_intersect($info['slugs'], $slugs['active'])) {
                        $entry['status'] = 'in_use';
                    } elseif (array_intersect($info['slugs'], $slugs['installed'])) {
                        $entry['status'] = 'inactive';
                    } else {
                        $entry['status'] = 'leftover';
                    }

                    break;
                }
            }

            $result[] = $entry;
        }

        return $result;
    }


    /*
    ---------------------------------
    Counts
    ---------------------------------
    */
    public static function get_post_revisions() {
        global $wpdb;

        $count = wp_cache_get('corespect_post_revisions', 'corespect');
        if (false === $count) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- no core WP API returns a revision count; result is cached below.
            $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}posts WHERE post_type='revision'");
            wp_cache_set('corespect_post_revisions', $count, 'corespect', 60);
        }

        return $count;
    }

    public static function get_transients() {
        global $wpdb;

        $count = wp_cache_get('corespect_transients_count', 'corespect');
        if (false === $count) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- no core WP API returns a transient count; result is cached below.
            $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'");
            wp_cache_set('corespect_transients_count', $count, 'corespect', 60);
        }

        return $count;
    }

    public static function get_spam_comments() {
        global $wpdb;

        $count = wp_cache_get('corespect_spam_comments', 'corespect');
        if (false === $count) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- no core WP API returns a spam comment count; result is cached below.
            $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved='spam'");
            wp_cache_set('corespect_spam_comments', $count, 'corespect', 60);
        }

        return $count;
    }
}