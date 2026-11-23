<?php
if (!defined('ABSPATH')) exit;

class CoreSpect_Performance {

    public static function get_performance_data() {

        // What PHP actually allows (not WP_MEMORY_LIMIT, which is only a
        // configured ceiling for front-end requests).
        $memory_raw = ini_get('memory_limit');
        $unlimited  = ((string) $memory_raw === '-1');
        $memory_mb  = null;
        $memory_txt = 'Unknown';

        if ($unlimited) {
            $memory_mb  = PHP_INT_MAX;
            $memory_txt = 'Unlimited';
        } elseif ($memory_raw !== false && $memory_raw !== '') {
            $bytes = wp_convert_hr_to_bytes($memory_raw);
            if ($bytes > 0) {
                $memory_mb  = (int) round($bytes / MB_IN_BYTES);
                $memory_txt = size_format($bytes);
            }
        }

        return [
            'largest'         => self::get_largest_assets(5),
            'cache'           => self::detect_cache(),
            'php_version'     => PHP_VERSION,
            'memory_limit'    => $memory_txt,
            'memory_limit_mb' => $memory_mb
        ];
    }

    /**
     * Largest JavaScript and CSS files shipped by installed plugins, themes
     * and mu-plugins. This is a static scan of what is installed on disk,
     * not a measurement of what any page loads.
     */
    private static function get_largest_assets($limit = 5) {

        $roots = [
            'plugins'    => WP_PLUGIN_DIR,
            'themes'     => get_theme_root(),
            'mu-plugins' => WPMU_PLUGIN_DIR
        ];

        $skip  = ['node_modules', '.git', '.svn'];
        $files = [];

        foreach ($roots as $label => $root) {

            if (!is_dir($root)) {
                continue;
            }

            $root_path = wp_normalize_path($root);

            try {
                $dir = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);

                $filter = new RecursiveCallbackFilterIterator($dir, function ($current) use ($skip) {
                    if ($current->isDir()) {
                        return !in_array($current->getFilename(), $skip, true);
                    }
                    $ext = strtolower($current->getExtension());
                    return $ext === 'js' || $ext === 'css';
                });

                foreach (new RecursiveIteratorIterator($filter) as $file) {

                    $files[] = [
                        'name' => $file->getFilename(),
                        'path' => $label . '/' . ltrim(substr(wp_normalize_path($file->getPathname()), strlen($root_path)), '/'),
                        'size' => $file->getSize()
                    ];

                    // keep memory use flat on sites with very many assets
                    if (count($files) > 500) {
                        usort($files, fn($a, $b) => $b['size'] <=> $a['size']);
                        $files = array_slice($files, 0, $limit);
                    }
                }

            } catch (Exception $e) {
                continue; // unreadable folder: skip it, report what we could read
            }
        }

        usort($files, fn($a, $b) => $b['size'] <=> $a['size']);

        return array_slice($files, 0, $limit);
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