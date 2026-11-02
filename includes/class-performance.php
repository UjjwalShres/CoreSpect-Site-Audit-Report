<?php
if (!defined('ABSPATH')) exit;

class CoreSpect_Performance {

    public static function get_performance_data() {

        global $wp_scripts, $wp_styles;

        $requests = 0;
        $total_size = 0;
        $resources = [];

        // Helper function to safely get local file path.
        // Maps the asset's URL onto WP_CONTENT_DIR via content_url(),
        // rather than assuming the site's URL structure mirrors ABSPATH —
        // that assumption breaks whenever WP_CONTENT_DIR/WP_CONTENT_URL
        // is customized (moved content dir, CDN-served assets, etc.).
        $content_url = content_url('/');

        $get_local_file = function($src) use ($content_url) {

            if (strpos($src, $content_url) !== 0) {
                return false; // not a wp-content asset (e.g. wp-includes core script)
            }

            $relative = str_replace($content_url, '', $src);
            $path = WP_CONTENT_DIR . '/' . $relative;

            return file_exists($path) ? $path : false;
        };

        // Scripts
        foreach ($wp_scripts->registered as $script) {
            if (!empty($script->src)) {
                $file = $get_local_file($script->src);
                if ($file) {
                    $size = filesize($file);
                    $resources[] = ['name' => basename($file), 'size' => $size];
                    $total_size += $size;
                    $requests++;
                }
            }
        }

        // Styles
        foreach ($wp_styles->registered as $style) {
            if (!empty($style->src)) {
                $file = $get_local_file($style->src);
                if ($file) {
                    $size = filesize($file);
                    $resources[] = ['name' => basename($file), 'size' => $size];
                    $total_size += $size;
                    $requests++;
                }
            }
        }

        usort($resources, fn($a, $b) => $b['size'] <=> $a['size']);

        return [
            'requests' => $requests,
            'size' => $total_size,
            'largest' => array_slice($resources, 0, 5),
            'cache' => self::detect_cache(),
            'php_version' => PHP_VERSION,
            'memory_limit' => WP_MEMORY_LIMIT
        ];
    }


        /**
     * Known caching plugins, keyed by plugin FOLDER slug.
     * type: 'page' (full-page cache) or 'object' (Redis/Memcached etc.)
     * Add more here, or from another plugin/theme via the filter below.
     */
    public static function get_known_cache_plugins() {

        $known = [
            'litespeed-cache'          => ['name' => 'LiteSpeed Cache',      'type' => 'page'],
            'wp-super-cache'           => ['name' => 'WP Super Cache',       'type' => 'page'],
            'w3-total-cache'           => ['name' => 'W3 Total Cache',       'type' => 'page'],
            'wp-rocket'                => ['name' => 'WP Rocket',            'type' => 'page'],
            'wp-fastest-cache'         => ['name' => 'WP Fastest Cache',     'type' => 'page'],
            'cache-enabler'            => ['name' => 'Cache Enabler',        'type' => 'page'],
            'comet-cache'              => ['name' => 'Comet Cache',          'type' => 'page'],
            'hummingbird-performance'  => ['name' => 'Hummingbird',          'type' => 'page'],
            'sg-cachepress'            => ['name' => 'SiteGround Optimizer', 'type' => 'page'],
            'breeze'                   => ['name' => 'Breeze',               'type' => 'page'],
            'flying-press'             => ['name' => 'FlyingPress',          'type' => 'page'],
            'nitropack'                => ['name' => 'NitroPack',            'type' => 'page'],
            'powered-cache'            => ['name' => 'Powered Cache',        'type' => 'page'],
            'swift-performance-lite'   => ['name' => 'Swift Performance',    'type' => 'page'],
            'redis-cache'              => ['name' => 'Redis Object Cache',   'type' => 'object'],
            'docket-cache'             => ['name' => 'Docket Cache',         'type' => 'object'],
        ];

        return apply_filters('corespect_cache_plugins', $known);
    }

    /** Folder slugs of active plugins, including network-activated ones. */
    private static function get_active_plugin_slugs() {

        $active = (array) get_option('active_plugins', []);

        if (is_multisite()) {
            $active = array_merge($active, array_keys((array) get_site_option('active_sitewide_plugins', [])));
        }

        // 'wp-rocket/wp-rocket.php' => 'wp-rocket'
        return array_unique(array_map('dirname', $active));
    }

    public static function detect_cache() {

        $active = self::get_active_plugin_slugs();
        $found  = ['page' => [], 'object' => []];

        foreach (self::get_known_cache_plugins() as $slug => $info) {
            if (in_array($slug, $active, true)) {
                $type = (isset($info['type']) && isset($found[$info['type']])) ? $info['type'] : 'page';
                $found[$type][] = $info['name'];
            }
        }

        $page_dropin = file_exists(WP_CONTENT_DIR . '/advanced-cache.php');
        $wp_cache    = defined('WP_CACHE') && WP_CACHE;
        $names       = array_merge($found['page'], $found['object']);

        // Two page-cache plugins fight each other; page + object cache is fine.
        if (count($found['page']) > 1) {
            return ['label' => implode(', ', $names), 'state' => 'warn', 'plugins' => $names];
        }

        if (count($found['page']) === 1) {
            return ['label' => implode(', ', $names), 'state' => 'good', 'plugins' => $names];
        }

        // No recognised page-cache plugin, but something is caching pages.
        if ($page_dropin || $wp_cache) {
            return [
                'label'   => $names ? implode(', ', $names) . ' + unrecognised page cache' : 'Unrecognised page cache',
                'state'   => 'warn',
                'plugins' => $names,
            ];
        }

        return [
            'label'   => $names ? implode(', ', $names) . ' (no page cache)' : 'No Cache Plugin',
            'state'   => 'bad',
            'plugins' => $names,
        ];
    }
}