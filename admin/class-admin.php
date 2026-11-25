<?php
if (!defined('ABSPATH')) exit;
define('CORESPECT_ADMIN_URL', plugin_dir_url(__FILE__));


class CoreSpect_Admin {

    public static function init() {
        add_management_page(
            'CoreSpect Site Audit & Report',
            'CoreSpect',
            'manage_options',
            'corespect-site-audit-report',
            [__CLASS__, 'render_dashboard']
        );
    }

    private static function badge_class($state) {
        if ($state === 'good') return 'badge-ok';
        if ($state === 'warn') return 'badge-warn';
        return 'badge-bad';
    }

    private static function get_report() {
        $report = get_transient('corespect_last_report');

        if (!$report) {
            $report = CoreSpect_Builder::generate();
            set_transient('corespect_last_report', $report, 300);
        }

        return $report;
    }


    private static function card($title, $value, $state = 'good', $tooltip = null) {

    $tooltip_html = '';

    if ($tooltip && is_array($tooltip)) {

    $tooltip_html = '
    <div class="cs-tooltip">
        <span class="cs-tooltip-icon cs-tooltip-icon-'.esc_attr($state).'">i</span>
        <div class="cs-tooltip-content">';

    foreach ($tooltip as $key => $message) {

        $icon = '';
        if ($key === 'good') {
            $icon = '<span class="good">✓ </span>';
        } elseif ($key === 'warn') {
            $icon = '<span class="warn">! </span>';
        } elseif ($key === 'bad') {
            $icon = '<span class="bad">X </span>';
        }

        $tooltip_html .= '<p>'.$icon.esc_html($message).'</p>';
    }

    $tooltip_html .= '
        </div>
    </div>';
    }


    echo '
    <div class="cs-card">
        <h3>'.esc_html($title).'</h3>
        <span class="cs-value">'.esc_html($value).'</span>
        <div class="cs-card-'.esc_attr($state).'"></div>
        '.$tooltip_html.'
    </div>';
}


    public static function render_dashboard() {

    $tabs = [
        'db' => 'Database',
        'performance' => 'Performance',
        'security' => 'Security',
        'files' => 'Files',
        'plugins' => 'Plugins',
        'export' => 'Export',
        'settings' => 'Settings',
    ];

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab switch, not a form submission or state change.
    $current_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'db';

    /* ======================
       HEADER + LOGO
    ====================== */
    echo '<hr class="wp-header-end">';
    echo '<div class="cs-header-container">
        <div class="cs-header">
            <img src="' . esc_url(CORESPECT_ADMIN_URL . 'assets/corespect-logo-dark.png') . '">
            <div class="cs-brand-name">CoreSpect</div>
          </div>';



    /* ======================
       TABS
    ====================== */

    echo '<div class="cs-tabs">';

    foreach ($tabs as $key => $label) {

        $active = ($current_tab === $key) ? 'cs-tab-active' : '';

        echo '<a class="cs-tab '.esc_attr($active).'" href="'.esc_url('?page=corespect-site-audit-report&tab='.$key).'">'.esc_html($label).'</a>';
    }

    echo '</div>';
        if ($current_tab !== 'settings') {
            $refresh_url = wp_nonce_url(
                admin_url( 'admin-post.php?action=corespect_refresh&tab=' . $current_tab ),
                'corespect_refresh'
            );

            echo '<div class="cs-refresh-row"><a class="button" href="' . esc_url( $refresh_url ) . '">Refresh report</a></div>';
        }

    echo '</div>';

    echo '<div class="wrap corespect-wrap">';

    echo '<div class="corespect-content">';

    switch($current_tab) {
        case 'db': self::render_db_tab(); break;
        case 'performance': self::render_performance_tab(); break;
        case 'security': self::render_security_tab(); break;
        case 'files': self::render_files_tab(); break;
        case 'plugins': self::render_plugins_tab(); break;
        case 'export': self::render_export_tab(); break;
        case 'settings': CoreSpect_Settings::render_tab(); break;
    }

    echo '</div></div>';
}

/* -----------------------Database tab ---------------------------*/

    private static function render_db_tab() {

    $report = self::get_report();
    $db = $report['database'];

    $tables = $db['tables'];
    $total_rows = $db['total_rows'];

    $size_mb = $db['total_size_mb'];
    $overhead_mb = $db['total_overhead_mb'];

    $revisions = $db['revisions'];
    $transients = $db['transients'];
    $spam = $db['spam_comments'];

    $req_size_mb = CoreSpect_Health::db_size_mb($size_mb);
    $req_overhead_size_mb = CoreSpect_Health::db_overhead_size_mb($overhead_mb);
    $req_revisions = CoreSpect_Health::db_revisions($revisions);
    $req_transients = CoreSpect_Health::db_transients($transients);
    $req_spam = CoreSpect_Health::db_spam($spam);

    /* ======================
       STAT CARDS
    ====================== */

    echo '<div class="cs-card-grid">';
    self::card(
            'Total DB Size',
            $size_mb. ' MB',
            $req_size_mb['state'],
            [
                'good' => '< 100 MB',
                'warn' => '100 – 300 MB',
                'bad'  => '> 300 MB'
            ]
        );

    self::card(
            'Overhead',
            $overhead_mb. ' MB',
            $req_overhead_size_mb['state'],
            [
                'good' => '< 5 MB',
                'warn' => '< 20 MB',
                'bad'  => '> 20 MB'
            ]
        );

    self::card(
            'Revisions',
            $revisions,
            $req_revisions['state'],
            [
                'good' => '< 500',
                'warn' => '500 – 2000',
                'bad'  => '> 2000'
            ]
        );

    self::card(
            'Transients',
            $transients,
            $req_transients['state'],
            [
                'good' => '< 50',
                'warn' => '50 – 300',
                'bad'  => '> 300'
            ]
        );

    self::card(
            'Spam Comments',
            $spam,
            $req_spam['state'],
            [
                'good' => '< 5',
                'warn' => '5 – 30',
                'bad'  => '> 30'
            ]
        );

    echo '</div>';


    /* ======================
       TABLE
    ====================== */

    echo '<div class="table-container">
    <table class="widefat striped cs-table">
    <thead>
        <tr>
            <th>Table</th>
            <th>Rows</th>
            <th>Size (MB)</th>
            <th>Overhead (MB)</th>
        </tr>
    </thead><tbody>';

    foreach($tables as $table){
        echo '<tr>
            <td>'.esc_html($table['name']).'</td>
            <td>'.esc_html($table['rows']).'</td>
            <td>'.esc_html(round($table['size']/1024/1024,2)).'</td>
            <td>'.esc_html(round($table['overhead']/1024/1024,2)).'</td>
        </tr>';
    }

    echo '<tr class="cs-total-row">
        <td>Total</td>
        <td>'.esc_html($total_rows).'</td>
        <td>'.esc_html($size_mb).'</td>
        <td>'.esc_html($overhead_mb).'</td>
    </tr>';

    echo '</tbody></table>';


    /* ======================
       ORPHANS
    ====================== */

    $leftover = [];

    foreach (CoreSpect_DB::get_plugin_tables() as $entry) {
        if ($entry['status'] === 'leftover') {
            $leftover[$entry['plugin']][] = $entry['table'];
        }
    }

    if (empty($leftover)) {
        echo '<div class="orphan-tables">✔ No tables from missing plugins detected</div>';
    } else {
        echo '<div class="orphan-tables"><strong>Tables possibly left behind by plugins that are no longer installed:</strong><ul>';
        foreach ($leftover as $plugin => $tables) {
            echo '<li><strong>'.esc_html($plugin).'</strong> ('.esc_html(count($tables)).(count($tables) === 1 ? ' table' : ' tables').'): '.esc_html(implode(', ', $tables)).'</li>';
        }
        echo '</ul><p>This is a best-effort match based on table names. Take a backup and check with your developer before removing anything.</p></div>';
    }

    echo '</div>';
}

/* -----------------------Performance tab ---------------------------*/

    public static function render_performance_tab() {
    
    $report = self::get_report();
    $data = $report['performance'];
    
    $cache_state = $data['cache_state'] ?? 'bad';
    $php_health = CoreSpect_Health::php_version($data['php_version']);
    $memory_health = CoreSpect_Health::memory_limit_mb($data['memory_limit_mb'] ?? null);

        echo '<div class="cs-card-grid">';

        self::card(
            'Cache',
            $data['cache_plugin'],
            $cache_state,
            [
                'good' => '1 page cache plugin',
                'warn' => 'Multiple page caches, or an unrecognised one',
                'bad'  => 'No page cache detected',
            ]
            );
        self::card('Load Time', 'Test Needed', 'warn');
        self::card(
                'PHP Version',
                $data['php_version'],
                $php_health['state'],
                [
                    'good' => '>= 8.1',
                    'warn' => '8.0',
                    'bad'  => '< 8.0'
                ]
            );

        self::card(
                'PHP Memory Limit',
                $data['memory_limit'],
                $memory_health['state'],
                [
                    'good' => '>= 256MB',
                    'warn' => '128MB – 255MB',
                    'bad'  => '< 128MB'
                ]
            );

        echo '</div>';


?> <div class="bar-chart-section">
    <div class="bar-chart">
        <h3>Largest Plugin &amp; Theme Files</h3>

        <div class="chart-container">
            <?php
                    $largest = $data['largest_resources'];
                    // $largest = [
                    // ['name' => 'jquery.js', 'size' => 1048576],       // 1 MB
                    // //['name' => 'style.css', 'size' => 524288],        // 0.5 MB
                    // ['name' => 'main.js', 'size' => 2097152],         // 2 MB
                    // ['name' => 'plugin.js', 'size' => 3145728],       // 3 MB
                    // ['name' => 'extra.css', 'size' => 1572864],       // 1.5 MB
                    // ['name' => 'extra-large.css', 'size' => 9097152],   
                    //     ];
                    $max_size = max(array_column($largest, 'size') ?: [1]); // largest file size (guard: no files found)
                    $y_steps = 6; // number of horizontal lines on Y-axis
                    $step_value = ceil($max_size / $y_steps); // size per step
                    ?>
            <!-- Y-axis -->
            <div class="chart-y-axis">
                <?php for($i = $y_steps; $i >= 0; $i--):
                        $value_bytes = $i * $step_value;
                        // Convert to MB with 1 decimal
                        $value_mb = $value_bytes / (1024*1024);
                        $label = ($value_mb >= 1) ? round($value_mb, 1).' MB' : round($value_bytes / 1024, 0).' KB';
                    ?>
                <div class="y-label"><?php echo esc_html($label); ?></div>
                <?php endfor; ?>
            </div>

            <!-- Bars -->
            <div class="chart-bars">
                <?php foreach($largest as $item):
                        $height_percent = ($item['size'] / ($y_steps * $step_value)) * 100;
                    ?>
                <div class="bar" style="height: <?php echo esc_attr($height_percent); ?>%">
                    <div class="bar-value"><?php echo esc_html(size_format($item['size'])); ?></div>
                </div>
                <?php endforeach; ?>

                <div class="bar-label-group">
                    <?php foreach($largest as $label): ?>
                    <span class="bar-label" title="<?php echo esc_attr($label['path'] ?? ''); ?>"><?php echo esc_html($label['name']); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    </div>

    <!-- <div class="bar-side-section">
        <?php 
            self::card(
                'PHP Version',
                $data['php_version'],
                $php_health['state'],
                [
                    'good' => '>= 8.1',
                    'warn' => '8.0',
                    'bad'  => '< 8.0'
                ]
            );

            self::card(
                'PHP Memory Limit',
                $data['memory_limit'],
                $memory_health['state'],
                [
                    'good' => '>= 256MB',
                    'warn' => '128MB – 255MB',
                    'bad'  => '< 128MB'
                ]
            );
            ?>
    </div> -->

</div>

<?php

    }


/* -----------------------Security tab ---------------------------*/
    public static function render_security_tab() {

    $data = self::get_security_data();
    $security = CoreSpect_Health::security_plugins_status($data['security_plugins']);
    $firewall_status = CoreSpect_Health::firewall_status($data['firewall']);
    $outdated_plugins_status = CoreSpect_Health::outdated_plugins_status($data['updates']['plugins']);
    $outdated_themes_status = CoreSpect_Health::outdated_themes_status($data['updates']['themes']);
    $login_safety_status = CoreSpect_Health::login_safety_status($data['login']);

    echo '<div class="cs-card-grid">';

    self::card(
        'Security Plugins',
        $security['value'],
        $security['state'],
        $security['tooltip']
    );
    self::card(
        'Firewall', 
        $firewall_status['value'],
        $firewall_status['state'],
        $firewall_status['tooltip']
    );
    self::card(
        'Outdated Plugins', 
        $outdated_plugins_status['value'],
        $outdated_plugins_status['state'],
        $outdated_plugins_status['tooltip']
        );
    self::card(
        'Outdated Themes', 
        $outdated_themes_status['value'],
        $outdated_themes_status['state'],
        $outdated_themes_status['tooltip']
        );
    self::card('Login Safety', $login_safety_status['value'], $login_safety_status['state'], $login_safety_status['tooltip']);

    echo '</div>';

    echo '<div class="cs-security-large-section">
    <div class="cs-suspicious-list"><h3>Files Worth Reviewing</h3>';

    if (!empty($data['review_files'])) {
        echo '<p>PHP files do not normally belong in the uploads folder. If you do not recognise these, ask your developer or host to check them.</p>';
        echo '<ul>';
        foreach ($data['review_files'] as $file) {
            echo '<li>'.esc_html($file['path']).' ('.esc_html(size_format($file['size'])).')</li>';
        }
        echo '</ul>';
    } else {
        echo 'No PHP files found in the uploads folder';
    }

    echo '</div>
    
    <div class="cs-security-side-section">';
        self::card(
            'Debug',
            $data['debug_mode']['value'],
            $data['debug_mode']['state'],
            [
                'good' => 'Disabled',
                'bad'  => 'Enabled'
            ]
        );

        // self::card(
        //     'Debug Log',
        //     $data['debug_log']['value'],
        //     $data['debug_log']['state'],
        //     [
        //         'good' => 'Disabled',
        //         'warn' => 'Enabled'
        //     ]
        // );

        self::card(
            'File Editor',
            $data['file_edit']['value'],
            $data['file_edit']['state'],
            [
                'good' => 'Disabled',
                'bad'  => 'Enabled'
            ]
        );
                
    echo '</div>

    </div>';


    }

    private static function get_security_data() {

    return [
        'security_plugins' => self::detect_security_plugins(),
        'updates' => self::check_updates(),
        'firewall' => self::detect_firewall(),
        'login' => self::check_login_security(),
        'review_files' => self::scan_uploads_php_files(),
        'debug_mode' => self::check_debug_mode(),
        //'debug_log'  => self::check_debug_log(),
        'file_edit'  => self::check_file_edit()
    ];
    }

    private static function detect_security_plugins() {

    $known = [
        'wordfence/wordfence.php' => 'Wordfence',
        'defender-security/wp-defender.php' => 'Defender',
        'ithemes-security-pro/ithemes-security-pro.php' => 'iThemes Security',
        'sucuri-scanner/sucuri.php' => 'Sucuri'
    ];

    $results = [];

    foreach ($known as $file => $name) {
        if (is_plugin_active($file)) {
            $results[] = $name . ' (Active)';
        }
    }

    if (!$results) {
        $results[] = 'No security plugin detected';
    }

    return $results;
    }

    private static function detect_firewall() {

        if (file_exists(ABSPATH . '.htaccess')) {
            $ht = file_get_contents(ABSPATH . '.htaccess');

            if (strpos($ht, 'Wordfence') !== false) return 'Wordfence Firewall Active';

            return 'Basic .htaccess rules detected';
        }

        return 'No firewall rules detected';
    }

    private static function check_updates() {

    require_once ABSPATH . 'wp-admin/includes/update.php';

    if (!function_exists('get_plugins')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    wp_update_plugins();
    wp_update_themes();

    $plugins = get_site_transient('update_plugins');
    $themes  = get_site_transient('update_themes');

    $all_plugins  = get_plugins();
    $plugin_names = [];

    foreach (array_keys((array) ($plugins->response ?? [])) as $file) {
        $plugin_names[] = $all_plugins[$file]['Name'] ?? $file;
    }

    $theme_names = [];

    foreach (array_keys((array) ($themes->response ?? [])) as $stylesheet) {
        $theme = wp_get_theme($stylesheet);
        $theme_names[] = $theme->exists() ? $theme->get('Name') : $stylesheet;
    }

    return [
        'plugins'      => count($plugins->response ?? []),
        'themes'       => count($themes->response ?? []),
        'plugin_names' => $plugin_names,
        'theme_names'  => $theme_names
    ];
    }

    private static function check_login_security() {

    $issues = [];

    if (get_option('users_can_register')) {
        $issues[] = 'Anyone can register';
    }

    if (!file_exists(ABSPATH . '.htaccess')) {
        $issues[] = 'No server protection (.htaccess missing)';
    }

    if (!$issues) $issues[] = 'Basic protection OK';

    return $issues;
    }

    /**
     * PHP files inside the uploads folder. Uploads should only hold media and
     * documents, so executable PHP files there are worth a second look.
     * Plugin and theme code is deliberately NOT scanned: legitimate code uses
     * functions like eval() and base64_decode(), so a text match produces
     * mostly false alarms.
     */
    private static function scan_uploads_php_files() {

        $uploads = wp_upload_dir();
        $base    = $uploads['basedir'] ?? '';

        if (empty($base) || !is_dir($base)) {
            return [];
        }

        $php_extensions = ['php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar'];
        $base_path      = wp_normalize_path($base);
        $found          = [];

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {

                if (!$file->isFile()) continue;

                if (!in_array(strtolower($file->getExtension()), $php_extensions, true)) continue;

                // Ignore the tiny "Silence is golden" index.php files that
                // WordPress and many plugins place in folders.
                if (strtolower($file->getFilename()) === 'index.php' && $file->getSize() < 100) continue;

                $found[] = [
                    'path'   => 'uploads/' . ltrim(substr(wp_normalize_path($file->getPathname()), strlen($base_path)), '/'),
                    'size'   => $file->getSize(),
                    'reason' => 'PHP file inside the uploads folder'
                ];

                if (count($found) >= 20) break;
            }

        } catch (Exception $e) {
            // unreadable folder: report what could be read
        }

        return $found;
    }

    private static function check_debug_mode() {

    if (defined('WP_DEBUG') && WP_DEBUG) {
        return [
            'value' => 'Enabled',
            'state' => 'bad'
        ];
    }

    return [
        'value' => 'Disabled',
        'state' => 'good'
    ];
    }

    private static function check_file_edit() {

    if (defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT) {
        return [
            'value' => 'Disabled',
            'state' => 'good'
        ];
    }

    return [
        'value' => 'Enabled',
        'state' => 'warn'
    ];
    }



/* -----------------------Files tab ---------------------------*/
    public static function render_files_tab() {

    $data = self::get_filesystem_data();
    $site_size_mb_status = CoreSpect_Health::site_size_mb($data['total']);
    $uploads_size_status = CoreSpect_Health::uploads_size($data['uploads']);
    $temp_size_status = CoreSpect_Health::temp_size($data['temp']);
    $log_size_status = CoreSpect_Health::log_size($data['logs']['total']);

    echo '<div class="cs-card-grid">';

    //self::card('Total Site Size', size_format($data['total']));
    self::card(
        'Total Site Size',
        $site_size_mb_status['value'] . ' MB',
        $site_size_mb_status['state'],
        $site_size_mb_status['tooltip']
    );
    //self::card('Uploads', size_format($data['uploads']));
    self::card(
        'Uploads',
        $uploads_size_status['value'] . ' MB',
        $uploads_size_status['state'],
        $uploads_size_status['tooltip']
    );
    //self::card('Temp/Cache', size_format($data['temp']));
    self::card(
        'Temp/Cache',
        $temp_size_status['value'] . ' MB',
        $temp_size_status['state'],
        $temp_size_status['tooltip']
    );
    //self::card('Log Files', count($data['logs']));
    self::card(
        'Log Files',
        $log_size_status['value'] . ' MB',
        $log_size_status['state'],
        $log_size_status['tooltip']
    );

    echo '</div>';

    echo '<div class="cs-files-large-section">
    <div class="cs-log-list"><h3>Log Files</h3>';

    if ($data['logs']['files']) {
        echo '<ul>';
        foreach ($data['logs']['files'] as $log) {
            echo '<li>'.esc_html($log['name']).' ('. esc_html(size_format($log['size'])).')</li>';
        }
        echo '</ul>';
    } else {
        echo 'No log files found';
    }

    echo '<h3>Large Files (>10MB)</h3>';

    if ($data['large']) {
        echo '<ul>';
        foreach ($data['large'] as $file) {
            echo '<li>'.esc_html($file['name']).' ('. esc_html(size_format($file['size'])).')</li>';
        }
        echo '</ul>';
    } else {
        echo 'No unusually large files found';
    }

    echo '</div>
    
    <div class="cs-files-side-section">';

    self::gauge($data['total']);

    echo '</div>';

    echo '</div>';

    }

    private static function get_filesystem_data() {

    return [
        'total'   => self::folder_size(ABSPATH),
        'uploads' => self::folder_size(wp_upload_dir()['basedir']),
        'logs'    => self::scan_logs(),
        'temp'    => self::scan_temp(),
        'large'   => self::scan_large_files(ABSPATH)
    ];
}

private static function folder_size($path) {

    $size = 0;

    if (!is_dir($path)) return 0;

    foreach (new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
    ) as $file) {
        $size += $file->getSize();
    }

    return $size;
}

private static function gauge($bytes) {

    // Convert to MB for logic
    $value_mb = $bytes / 1024 / 1024;

    // Max 3GB (in MB)
    $max_mb = 3072;

    $percentage = min(100, ($value_mb / $max_mb) * 100);

    // Determine color
    if ($value_mb < 300) {
        $color = '#ceeac2'; // green
    } elseif ($value_mb < 1000) {
        $color = '#ffd0b3'; // orange
    } else {
        $color = '#ffc6c6'; // red
    }

    // Format display nicely
    $display = size_format($bytes, 1);

    ob_start();
    ?>

<svg viewBox="0 0 200 120">

    <!-- Background arc -->
    <path d="M20 100 A80 80 0 0 1 180 100" stroke="#eee" stroke-width="14" fill="none" />

    <!-- Value arc -->
    <path d="M20 100 A80 80 0 0 1 180 100" stroke="<?php echo esc_attr($color); ?>" stroke-width="14" fill="none"
        stroke-dasharray="<?php echo esc_attr($percentage * 2.83); ?> 999" stroke-linecap="round" />

    <!-- Center Value -->
    <text x="100" y="85" text-anchor="middle" class="cs-gauge-value">
        <?php echo esc_html($display); ?>
    </text>
    <text x="100" y="100" text-anchor="middle" class="cs-gauge-text">
        Total Site Size
    </text>

</svg>

<?php
    echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup; dynamic values above are escaped individually.
}



private static function scan_logs() {

    $log_files = [];
    $total_size = 0;

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(ABSPATH, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {

        if (!$file->isFile()) continue;

        $filename  = $file->getFilename();
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // Only real .log files
        if ($extension === 'log') {

            $size = $file->getSize();

            $log_files[] = [
                'name' => $filename,
                'path' => ltrim(str_replace(wp_normalize_path(ABSPATH), '', wp_normalize_path($file->getPathname())), '/'),
                'size' => $size
            ];

            $total_size += $size;
        }
    }

    return [
        'files' => $log_files,
        'total' => $total_size
    ];
}


private static function scan_temp() {

    $folders = [
        ABSPATH . 'cache',
        ABSPATH . 'tmp',
        ABSPATH . 'temp',
        WP_CONTENT_DIR . '/cache',
        WP_CONTENT_DIR . '/litespeed'
    ];

    $total = 0;

    foreach ($folders as $path) {
        if (is_dir($path)) {
            $total += self::folder_size($path);
        }
    }

    return $total;
}

private static function scan_large_files($path, $limit = 10485760) {

    $large = [];
    $root  = wp_normalize_path($path);

    foreach (new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
    ) as $file) {

        if ($file->getSize() > $limit) {
            $large[] = [
                'name' => $file->getFilename(),
                'path' => ltrim(str_replace($root, '', wp_normalize_path($file->getPathname())), '/'),
                'size' => $file->getSize()
            ];

            // keep memory flat on sites with very many large files
            if (count($large) > 100) {
                usort($large, fn($a, $b) => $b['size'] <=> $a['size']);
                $large = array_slice($large, 0, 10);
            }
        }
    }

    usort($large, fn($a, $b) => $b['size'] <=> $a['size']);

    return array_slice($large, 0, 10);
}

/* -----------------------Plugins tab ---------------------------*/
    public static function render_plugins_tab() {

    $data = self::get_plugins_data();

    $active_count = count(array_filter($data['plugins'], fn($p) => $p['active']));
    $total_plugins = count($data['plugins']);
    $plugin_count_status = CoreSpect_Health::plugin_count($active_count);
    $inactive_plugin_count_status = CoreSpect_Health::inactive_plugins($total_plugins - $active_count);
    $wp_version_status = CoreSpect_Health::wp_version($data['compat']['wp']);

    echo '<div class="cs-card-grid">';

    self::card(
        'Active Plugins',
        $plugin_count_status['value'],
        $plugin_count_status['state'],
        $plugin_count_status['tooltip']
    );
    self::card(
        'Inactive Plugins',
        $inactive_plugin_count_status['value'],
        $inactive_plugin_count_status['state'],
        $inactive_plugin_count_status['tooltip']
    );
    self::card(
        'WordPress Version',
        $wp_version_status['value'],
        $wp_version_status['state'],
        $wp_version_status['tooltip']
    );

    echo '</div>';

    echo '<div class="cs-table-container">';
    echo '<div class="cs-first-table-card"><h3>Plugins</h3>';

    echo '<table class="cs-plugin-table">
    <tr><th>Name</th><th>Version</th><th>Status</th></tr>';

    foreach ($data['plugins'] as $p) {

        $status = $p['active']
            ? '<span class="cs-plugin-active">Active</span>'
            : '<span class="cs-plugin-inactive">Inactive</span>';

        echo "<tr>
                <td>" . esc_html($p['name']) . "</td>
                <td>" . esc_html($p['version']) . "</td>
                <td>{$status}</td>
            </tr>";
    }

    echo '</table></div>';

    echo '<div class="cs-second-table-card"><h3>Themes</h3>';

    echo '<table class="cs-theme-table">
    <tr><th>Name</th><th>Version</th><th>Status</th></tr>';

    foreach ($data['themes'] as $t) {

        $status = $t['active']
            ? '<span class="cs-theme-active">Active</span>'
            : '<span class="cs-theme-inactive">Inactive</span>';

        echo "<tr>
                <td>" . esc_html($t['name']) . "</td>
                <td>" . esc_html($t['version']) . "</td>
                <td>{$status}</td>
            </tr>";
    }

    echo '</table></div>';
    echo '</div>';

    }

    private static function get_plugins_data() {

    return [
        'plugins' => self::get_plugins_list(),
        'themes'  => self::get_themes_list(),
        'compat'  => self::compatibility_check()
    ];
}

private static function get_plugins_list() {

    require_once ABSPATH . 'wp-admin/includes/plugin.php';

    $all_plugins = get_plugins();
    $active = get_option('active_plugins');

    $list = [];

    foreach ($all_plugins as $path => $plugin) {

        $list[] = [
            'name' => $plugin['Name'],
            'version' => $plugin['Version'],
            'active' => in_array($path, $active)
        ];
    }

    return $list;
}

private static function get_themes_list() {

    $themes = wp_get_themes();
    $active = wp_get_theme()->get_stylesheet();

    $list = [];

    foreach ($themes as $slug => $theme) {

        $list[] = [
            'name' => $theme->get('Name'),
            'version' => $theme->get('Version'),
            'active' => ($slug === $active)
        ];
    }

    return $list;
}

private static function compatibility_check() {

    return [
        'php' => PHP_VERSION,
        'wp'  => get_bloginfo('version'),
        'php_ok' => version_compare(PHP_VERSION, '8.0', '>='),
        'wp_ok'  => version_compare(get_bloginfo('version'), '6.0', '>=')
    ];
}

/* -----------------------Export tab ---------------------------*/
    public static function render_export_tab() {

    echo '<div class="cs-export-container">';
        echo '<div class="cs-export-large-sec">';
            echo '<h2>Export Report</h2>';

            echo '<p class="description">To show your name or agency at the top of exported reports, add it under <a href="' . esc_url(admin_url('tools.php?page=corespect-site-audit-report&tab=settings')) . '">Settings</a>.</p>';

            echo '<div class="cs-export-options">
            <label>
                <input type="radio" name="cs_export_type" value="html" checked>
                HTML Report
            </label>

            <label>
                <input type="radio" name="cs_export_type" value="txt">
                TXT Report
            </label>

            <label>
                <input type="radio" name="cs_export_type" value="json">
                JSON Report
            </label>

            <label>
                <input type="radio" name="cs_export_type" value="csv">
                CSV Report
            </label>

            <label>
                <input type="radio" name="cs_export_type" value="pdf">
                PDF (Print)
            </label>

        </div>';

        echo '<input type="hidden" id="cs-export-nonce" value="' . esc_attr(wp_create_nonce('cs_export')) . '">';

        /* ACTION BUTTONS */
        echo '<div class="cs-export-actions">
            <button id="cs-download" class="button button-primary">Download</button>
            <a id="cs-preview" class="button" target="_blank">Preview</a>
        </div>';

            
        echo '</div>';
        echo '<div class="cs-export-small-sec">';
            echo '<h2>Helpful Links</h2>';

            $corespect_slug = 'corespect-site-audit-report';

            $corespect_links = [
                [
                    'label' => 'Request Feature',
                    'icon'  => 'icon-alert',
                    'url'   => 'https://wordpress.org/support/plugin/' . $corespect_slug . '/',
                ],
                [
                    'label' => 'Get Support',
                    'icon'  => 'icon-question',
                    'url'   => 'https://wordpress.org/support/plugin/' . $corespect_slug . '/',
                ],
                [
                    'label' => 'Rate our Plugin',
                    'icon'  => 'icon-tick',
                    'url'   => 'https://wordpress.org/support/plugin/' . $corespect_slug . '/reviews/#new-post',
                ],
            ];

            foreach ( $corespect_links as $link ) {
                echo '<a href="' . esc_url( $link['url'] ) . '" target="_blank" rel="noopener noreferrer">';
                echo '<div class="cs-links-sec">';
                echo '<p><span class="' . esc_attr( $link['icon'] ) . '"></span>' . esc_html( $link['label'] ) . '</p>';
                echo '</div>';
                echo '</a>';
            }

        echo '</div>';
    echo '</div>';
}

private static function collect_full_report() {

    // Reuses the exact same data sources as the dashboard tabs
    // (get_report(), get_security_data(), get_filesystem_data(), get_plugins_data())
    // so the exported report always matches what's on screen.
    $report = self::get_report();

    return [
        'database'    => $report['database'],
        'performance' => $report['performance'],
        'security'    => self::get_security_data(),
        'files'       => self::get_filesystem_data(),
        'plugins'     => self::get_plugins_data()
    ];
}



public static function handle_export() {

    if (!current_user_can('manage_options')) {
        wp_die('No permission');
    }

    check_admin_referer('cs_export');

    $type = isset($_GET['type']) ? sanitize_key(wp_unslash($_GET['type'])) : 'html';

    $report = self::collect_full_report();

    // CRITICAL
    while (ob_get_level()) ob_end_clean();

    switch ($type) {

    case 'json':
        self::export_json($report);
        break;

    case 'csv':
        self::export_csv($report);
        break;

    case 'txt':
        self::export_txt($report);
        break;

    case 'pdf':
        self::export_pdf($report);
        break;
    
    case 'preview':
        self::preview_html($report);
        break;


    case 'html':
    default:
        self::export_html($report);
        break;
}

exit;

}



private static function export_json($data) {

header('Content-Type: application/json');
header('Content-Disposition: attachment; filename=corespect-report.json');

echo json_encode($data, JSON_PRETTY_PRINT);
}

private static function export_csv($data) {

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename=corespect-report.csv');

$out = fopen('php://output', 'w'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- streaming a CSV download to php://output, not a real file; WP_Filesystem cannot target this stream.

foreach ($data as $section => $values) {

fputcsv($out, [$section]);

foreach ($values as $k => $v) {

    // simple values
    if (!is_array($v)) {
        fputcsv($out, [$k, $v]);
    }

    // nested arrays → flatten into rows
    else {

        fputcsv($out, [$k]);

        foreach ($v as $item) {

            if (is_array($item)) {
                fputcsv($out, array_values($item));
            } else {
                fputcsv($out, [$item]);
            }
        }
    }
}


fputcsv($out, []);
}

fclose($out); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- closing the php://output stream opened above, not a real file handle.
}

private static function export_txt($data) {
    // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- this is a plain-text (.txt) file download, not HTML; esc_html() would corrupt the output (e.g. turning "&" into "&amp;").

    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename=corespect-report.txt');

    foreach ($data as $section => $values) {

        echo strtoupper($section) . "\n";
        echo str_repeat("=", 30) . "\n";

        foreach ($values as $k => $v) {

            if (!is_array($v)) {
                echo "$k: $v\n";
            } else {
                echo "$k:\n";
                foreach ($v as $item) {
                    if (is_array($item)) {
                        echo " - " . implode(", ", $item) . "\n";
                    }
                }
            }
        }

        echo "\n\n";
    }
    // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
}

private static function preview_html($data) {

    // no content-disposition = browser renders
    header('Content-Type: text/html');

    // reuse same UI
    self::render_html_template($data);

    exit;
}

private static function export_pdf($data) {

    // No server-side PDF library is bundled with this plugin, so "PDF" is
    // implemented as the export tab labels it: "PDF (Print)" — render the
    // same HTML report and open the browser's print dialog automatically,
    // which lets the user save it as a PDF via "Print to PDF".
    header('Content-Type: text/html');

    self::render_html_template($data);

    wp_register_script(
        'corespect-print',
        CORESPECT_URL . 'admin/js/print.js',
        [],
        CORESPECT_VERSION,
        true
    );
    wp_enqueue_script('corespect-print');

    // As with the stylesheet above, this document never calls wp_footer(),
    // so the enqueued script is printed manually at this point.
    wp_print_scripts('corespect-print');

    exit;
}

private static function render_html_template($data) {
    CoreSpect_Report_View::render($data);
}

private static function export_html($data) {

    header('Content-Type: text/html');
    header('Content-Disposition: attachment; filename=corespect-report.html');

    self::render_html_template($data);
    exit;
}



}

/* style enqueue */
add_action('admin_enqueue_scripts', function($hook){
if($hook !== 'tools_page_corespect-site-audit-report') return;

wp_enqueue_style(
'corespect-admin',
CORESPECT_URL . 'admin/css/admin-style.css',
[],
CORESPECT_VERSION
);

/* script enqueue: each script only on the tab that uses it */
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab check, no state change.
$corespect_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'db';

if ($corespect_tab === 'export') {
    wp_enqueue_script(
        'cs-export-js',
        CORESPECT_URL . 'admin/js/export.js',
        [],
        CORESPECT_VERSION,
        true
    );
}

if ($corespect_tab === 'settings') {
    wp_enqueue_media();

    wp_enqueue_script(
        'corespect-settings-js',
        CORESPECT_URL . 'admin/js/settings.js',
        ['jquery'],
        CORESPECT_VERSION,
        true
    );
}

});