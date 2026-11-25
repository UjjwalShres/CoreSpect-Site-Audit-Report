<?php
if (!defined('ABSPATH')) exit;

/**
 * Builds and renders the exported report (HTML / print-to-PDF).
 *
 * The dashboard and the report share the same health ratings
 * (CoreSpect_Health). This class adds plain-language descriptions, a summary
 * of what needs attention, and a print-friendly layout.
 */
class CoreSpect_Report_View {

    /* =================================================
       STATUS HELPERS
    ================================================= */

    private static function state_label($state) {
        $labels = [
            'good' => 'Good',
            'warn' => 'Warning',
            'bad'  => 'Action needed'
        ];

        return $labels[$state] ?? 'Warning';
    }

    private static function state_symbol($state) {
        $symbols = [
            'good' => '✓',
            'warn' => '!',
            'bad'  => '✕'
        ];

        return $symbols[$state] ?? '!';
    }

    private static function num($value) {
        return number_format_i18n((float) $value);
    }

    /**
     * One rated check, as shown on a card.
     *
     * $what   = what this measures, in plain language
     * $action = what to do (only shown when the state is not "good")
     * $detail = optional list of specifics (plugin names, file paths ...)
     */
    private static function item($title, $value, $state, $what, $action = '', $detail = []) {
        return [
            'title'  => $title,
            'value'  => $value,
            'state'  => $state,
            'what'   => $what,
            'action' => $action,
            'detail' => $detail
        ];
    }

    /* =================================================
       BUILD THE REPORT MODEL
    ================================================= */

    public static function build($data) {

        $sections = [
            self::section_database($data['database']),
            self::section_performance($data['performance']),
            self::section_security($data['security']),
            self::section_files($data['files']),
            self::section_plugins($data['plugins'])
        ];

        $counts    = ['bad' => 0, 'warn' => 0, 'good' => 0];
        $urgent    = [];
        $warnings  = [];

        foreach ($sections as $section) {
            foreach ($section['items'] as $item) {

                $counts[$item['state']]++;

                if ($item['state'] === 'bad') {
                    $urgent[] = $item + ['section' => $section['title']];
                } elseif ($item['state'] === 'warn') {
                    $warnings[] = $item + ['section' => $section['title']];
                }
            }
        }

        return [
            'sections'  => $sections,
            'counts'    => $counts,
            'attention' => array_merge($urgent, $warnings), // most serious first
            'appendix'  => self::appendix($data)
        ];
    }

    /* -------------------------------------------------
       DATABASE
    ------------------------------------------------- */

    private static function section_database($db) {

        $table_count = count($db['tables']);

        $tables_h   = CoreSpect_Health::db_tables($table_count);
        $size_h     = CoreSpect_Health::db_size_mb($db['total_size_mb']);
        $overhead_h = CoreSpect_Health::db_overhead_size_mb($db['total_overhead_mb']);
        $revs_h     = CoreSpect_Health::db_revisions((int) $db['revisions']);
        $trans_h    = CoreSpect_Health::db_transients((int) $db['transients']);
        $spam_h     = CoreSpect_Health::db_spam((int) $db['spam_comments']);

        // Tables that look like they belong to plugins that are no longer installed.
        $leftover       = [];
        $leftover_count = 0;

        foreach (($db['plugin_tables'] ?? []) as $entry) {
            if ($entry['status'] === 'leftover') {
                $leftover[$entry['plugin']][] = $entry['table'];
                $leftover_count++;
            }
        }

        $leftover_detail = [];
        foreach ($leftover as $plugin => $tables) {
            $leftover_detail[] = $plugin . ' (' . count($tables) . (count($tables) === 1 ? ' table' : ' tables') . ')';
        }

        $items = [
            self::item(
                'Database tables',
                self::num($table_count),
                $tables_h['state'],
                'The number of tables in your database. Each plugin can add its own, so this grows as plugins are installed.',
                'Check that every plugin that adds tables is still needed.'
            ),
            self::item(
                'Database size',
                $db['total_size_mb'] . ' MB',
                $size_h['state'],
                'Total space used by your database. Larger databases make backups and migrations slower.',
                'Clear out unused data (old revisions, expired transients, leftover plugin tables) and optimize the tables.'
            ),
            self::item(
                'Table overhead',
                $db['total_overhead_mb'] . ' MB',
                $overhead_h['state'],
                'Unused space left inside tables after rows are deleted or changed. Optimizing the tables recovers it.',
                'Optimize the database tables (take a backup first).'
            ),
            self::item(
                'Post revisions',
                self::num($db['revisions']),
                $revs_h['state'],
                'Saved copies of posts and pages, created each time one is edited.',
                'Limit how many revisions WordPress keeps and remove old ones.'
            ),
            self::item(
                'Transients',
                self::num($db['transients']),
                $trans_h['state'],
                'Temporary data that plugins store in the database to save work. The count includes each item\'s expiry record.',
                'Clear expired transients. If the number keeps growing, a plugin may not be cleaning up after itself.'
            ),
            self::item(
                'Spam comments',
                self::num($db['spam_comments']),
                $spam_h['state'],
                'Comments marked as spam that are still stored in the database.',
                'Empty the spam folder under Comments.'
            ),
            self::item(
                'Possibly leftover tables',
                $leftover_count > 0 ? self::num($leftover_count) . ($leftover_count === 1 ? ' table' : ' tables') : 'None found',
                $leftover_count > 0 ? 'warn' : 'good',
                'Tables whose names match a plugin that is no longer installed. This is a best-effort match based on table names, so treat it as a lead, not a verdict.',
                'Take a backup and check with your developer before removing any table.',
                $leftover_detail
            )
        ];

        // Largest tables
        $tables = $db['tables'];
        usort($tables, function ($a, $b) {
            return $b['size'] <=> $a['size'];
        });

        $largest_rows = [];
        foreach (array_slice($tables, 0, 10) as $t) {
            $largest_rows[] = [$t['name'], self::num($t['rows']), size_format($t['size'], 1)];
        }

        return [
            'id'     => 'database',
            'title'  => 'Database',
            'intro'  => 'How your site\'s content and settings are stored. A tidy database keeps backups small and the site responsive.',
            'items'  => $items,
            'tables' => [[
                'title'   => 'Largest database tables',
                'note'    => 'The ten largest tables. The full list is in the appendix.',
                'head'    => ['Table', 'Rows', 'Size'],
                'numeric' => [1, 2],
                'rows'    => $largest_rows
            ]]
        ];
    }

    /* -------------------------------------------------
       PERFORMANCE
    ------------------------------------------------- */

    private static function section_performance($perf) {

        $php_h    = CoreSpect_Health::php_version($perf['php_version']);
        $memory_h = CoreSpect_Health::memory_limit_mb($perf['memory_limit_mb'] ?? null);

        $cache_state  = $perf['cache_state'] ?? 'bad';
        $cache_reason = $perf['cache_reason'] ?? '';

        if ($cache_reason === 'multiple') {
            $cache_action = 'Use only one page-cache plugin. Running several at once can cause conflicts.';
        } elseif ($cache_reason === 'unrecognised') {
            $cache_action = 'A page cache seems to be active, but it is not one this report recognises. Confirm that it is working as intended.';
        } else {
            $cache_action = 'Consider a page-caching plugin, or ask your host. Some hosts cache pages at server level, which this check cannot see.';
        }

        $items = [
            self::item(
                'Page cache',
                $perf['cache_plugin'],
                $cache_state,
                'Page caching stores ready-made copies of your pages so visitors receive them faster.',
                $cache_action
            ),
            self::item(
                'PHP version',
                $perf['php_version'],
                $php_h['state'],
                'The version of PHP your server uses to run WordPress. Newer versions are faster and keep receiving security fixes.',
                'Ask your host about moving to a newer, supported PHP version (test the site first).'
            ),
            self::item(
                'PHP memory limit',
                $perf['memory_limit'],
                $memory_h['state'],
                'The most memory a single request is allowed to use. Low limits can cause errors with larger plugins or imports.',
                'Ask your host to raise the PHP memory limit to at least 256 MB.'
            )
        ];

        $file_rows = [];
        foreach ($perf['largest_resources'] as $f) {
            $file_rows[] = [$f['path'] ?? $f['name'], size_format($f['size'], 1)];
        }

        return [
            'id'     => 'performance',
            'title'  => 'Performance',
            'intro'  => 'How the server is set up to run your site.',
            'items'  => $items,
            'tables' => [[
                'title'   => 'Largest plugin and theme files',
                'note'    => 'The largest JavaScript and CSS files installed by plugins and themes. This lists files on disk, not what each page loads.',
                'head'    => ['File', 'Size'],
                'numeric' => [1],
                'rows'    => $file_rows
            ]]
        ];
    }

    /* -------------------------------------------------
       SECURITY
    ------------------------------------------------- */

    private static function section_security($sec) {

        $plugins_h  = CoreSpect_Health::security_plugins_status($sec['security_plugins']);
        $firewall_h = CoreSpect_Health::firewall_status($sec['firewall']);
        $out_pl_h   = CoreSpect_Health::outdated_plugins_status($sec['updates']['plugins']);
        $out_th_h   = CoreSpect_Health::outdated_themes_status($sec['updates']['themes']);
        $login_h    = CoreSpect_Health::login_safety_status($sec['login']);

        $review_files = $sec['review_files'] ?? [];

        $review_detail = [];
        foreach (array_slice($review_files, 0, 8) as $file) {
            $review_detail[] = $file['path'];
        }
        if (count($review_files) > 8) {
            $review_detail[] = 'and ' . (count($review_files) - 8) . ' more';
        }

        $sec_plugin_action = $plugins_h['state'] === 'bad'
            ? 'Running more than one security plugin can cause conflicts. Keep only one.'
            : 'If no security plugin is installed, consider adding one, or confirm that your host provides equivalent protection.';

        $items = [
            self::item(
                'Security plugin',
                $plugins_h['value'],
                $plugins_h['state'],
                'Security plugins add firewalls, malware scanning and login protection. This check only recognises a short list of well-known ones.',
                $sec_plugin_action
            ),
            self::item(
                'Firewall',
                $firewall_h['value'],
                $firewall_h['state'],
                'A firewall blocks malicious requests before they reach WordPress. This check looks for rules added by security plugins; a firewall at your host or a service such as Cloudflare cannot be detected.',
                'Confirm that your host or a security service provides a firewall.'
            ),
            self::item(
                'Outdated plugins',
                self::num($sec['updates']['plugins']),
                $out_pl_h['state'],
                'Plugins with a newer version available. Updates often include security fixes.',
                'Back up the site, then update these plugins.',
                $sec['updates']['plugin_names'] ?? []
            ),
            self::item(
                'Outdated themes',
                self::num($sec['updates']['themes']),
                $out_th_h['state'],
                'Themes with a newer version available. Updates often include security fixes.',
                'Back up the site, then update these themes.',
                $sec['updates']['theme_names'] ?? []
            ),
            self::item(
                'Login safety',
                $login_h['state'] === 'good' ? 'Basic checks passed' : $login_h['value'],
                $login_h['state'],
                'Checks two basics: whether anyone can register an account, and whether an .htaccess file is present. It does not test password strength, limits on login attempts or two-factor login.',
                'Switch off open registration if you do not need it (Settings, then General), and check your server configuration.'
            ),
            self::item(
                'Debug mode',
                $sec['debug_mode']['value'],
                $sec['debug_mode']['state'],
                'Debug mode shows PHP errors and warnings. On a live site this can expose technical details to visitors.',
                'Set WP_DEBUG to false in wp-config.php.'
            ),
            self::item(
                'File editor',
                $sec['file_edit']['value'],
                $sec['file_edit']['state'],
                'WordPress lets administrators edit plugin and theme code from the dashboard. If an administrator account is compromised, this makes it easier to inject malicious code.',
                'Add define( \'DISALLOW_FILE_EDIT\', true ); to wp-config.php.'
            ),
            self::item(
                'PHP files in uploads',
                $review_files ? self::num(count($review_files)) . (count($review_files) === 1 ? ' file' : ' files') : 'None found',
                $review_files ? 'warn' : 'good',
                'The uploads folder should only hold media and documents, so PHP files there are unusual. Some plugins do place files there on purpose.',
                'If you do not recognise these files, ask your developer or host to check them.',
                $review_detail
            )
        ];

        return [
            'id'     => 'security',
            'title'  => 'Security',
            'intro'  => 'Basic checks of common weak points. This is not a full security audit.',
            'items'  => $items,
            'tables' => []
        ];
    }

    /* -------------------------------------------------
       FILES
    ------------------------------------------------- */

    private static function section_files($files) {

        $total_h   = CoreSpect_Health::site_size_mb($files['total']);
        $uploads_h = CoreSpect_Health::uploads_size($files['uploads']);
        $temp_h    = CoreSpect_Health::temp_size($files['temp']);
        $logs_h    = CoreSpect_Health::log_size($files['logs']['total']);

        $items = [
            self::item(
                'Total site size',
                size_format($files['total'], 1),
                $total_h['state'],
                'Disk space used by all WordPress files and media.',
                'Check uploads, backups and logs for files that can be removed or moved off the server.'
            ),
            self::item(
                'Uploads',
                size_format($files['uploads'], 1),
                $uploads_h['state'],
                'Space used by your media library: images, videos and documents.',
                'Compress large images or move big media files to external storage.'
            ),
            self::item(
                'Temp and cache folders',
                size_format($files['temp'], 1),
                $temp_h['state'],
                'Temporary and cache folders. These should stay fairly small.',
                'Clear the cache. An unusually large cache can mean a caching plugin is misconfigured.'
            ),
            self::item(
                'Log files',
                size_format($files['logs']['total'], 1),
                $logs_h['state'],
                'Error and debug logs written by WordPress, plugins or the server. Large logs often mean something keeps failing.',
                'Find out what is writing to the logs, then delete or rotate the old ones.'
            )
        ];

        $tables = [];

        $log_rows = [];
        foreach ($files['logs']['files'] as $log) {
            $log_rows[] = [$log['path'] ?? $log['name'], size_format($log['size'], 1)];
        }
        if ($log_rows) {
            $tables[] = [
                'title'   => 'Log files',
                'head'    => ['File', 'Size'],
                'numeric' => [1],
                'rows'    => $log_rows
            ];
        }

        $large_rows = [];
        foreach ($files['large'] as $large) {
            $large_rows[] = [$large['path'] ?? $large['name'], size_format($large['size'], 1)];
        }
        if ($large_rows) {
            $tables[] = [
                'title'   => 'Largest files over 10 MB',
                'head'    => ['File', 'Size'],
                'numeric' => [1],
                'rows'    => $large_rows
            ];
        }

        return [
            'id'     => 'files',
            'title'  => 'Files',
            'intro'  => 'How much disk space the site uses and what is taking it up.',
            'items'  => $items,
            'tables' => $tables
        ];
    }

    /* -------------------------------------------------
       PLUGINS AND THEMES
    ------------------------------------------------- */

    private static function section_plugins($plugins) {

        $active   = count(array_filter($plugins['plugins'], function ($p) {
            return $p['active'];
        }));
        $inactive = count($plugins['plugins']) - $active;

        $active_h   = CoreSpect_Health::plugin_count($active);
        $inactive_h = CoreSpect_Health::inactive_plugins($inactive);
        $wp_h       = CoreSpect_Health::wp_version($plugins['compat']['wp']);

        $items = [
            self::item(
                'Active plugins',
                self::num($active),
                $active_h['state'],
                'Every active plugin adds code that runs on your site and has to be kept up to date.',
                'Remove plugins you no longer need.'
            ),
            self::item(
                'Inactive plugins',
                self::num($inactive),
                $inactive_h['state'],
                'Plugins that are installed but switched off. They still sit on the server and can carry security issues if left outdated.',
                'Delete inactive plugins you do not plan to use.'
            ),
            self::item(
                'WordPress version',
                $plugins['compat']['wp'],
                $wp_h['state'],
                'The version of WordPress itself. Updates fix bugs and security problems.',
                'Update WordPress (take a backup first).'
            )
        ];

        return [
            'id'     => 'plugins',
            'title'  => 'Plugins and themes',
            'intro'  => 'What is installed on the site. Unused or outdated plugins are a common source of problems. The full list is in the appendix.',
            'items'  => $items,
            'tables' => []
        ];
    }

    /* -------------------------------------------------
       APPENDIX
    ------------------------------------------------- */

    private static function appendix($data) {

        $tables = $data['database']['tables'];
        usort($tables, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        $table_rows = [];
        foreach ($tables as $t) {
            $table_rows[] = [$t['name'], self::num($t['rows']), size_format($t['size'], 1)];
        }
        $table_rows[] = ['Total', self::num($data['database']['total_rows']), $data['database']['total_size_mb'] . ' MB'];

        $plugin_rows = [];
        foreach ($data['plugins']['plugins'] as $p) {
            $plugin_rows[] = [$p['name'], $p['version'], $p['active'] ? 'Active' : 'Inactive'];
        }

        $theme_rows = [];
        foreach ($data['plugins']['themes'] as $t) {
            $theme_rows[] = [$t['name'], $t['version'], $t['active'] ? 'Active' : 'Inactive'];
        }

        return [
            [
                'title'   => 'All database tables',
                'head'    => ['Table', 'Rows', 'Size'],
                'numeric' => [1, 2],
                'rows'    => $table_rows,
                'total'   => true
            ],
            [
                'title'   => 'Plugins',
                'head'    => ['Name', 'Version', 'Status'],
                'numeric' => [],
                'rows'    => $plugin_rows
            ],
            [
                'title'   => 'Themes',
                'head'    => ['Name', 'Version', 'Status'],
                'numeric' => [],
                'rows'    => $theme_rows
            ]
        ];
    }

    /* =================================================
       RENDER
    ================================================= */

    private static function chip($state) {
        echo '<span class="cs-chip cs-chip-' . esc_attr($state) . '"><span class="cs-chip-icon" aria-hidden="true">'
            . esc_html(self::state_symbol($state)) . '</span> '
            . esc_html(self::state_label($state)) . '</span>';
    }

    private static function render_item($item) {
        ?>
        <div class="cs-item cs-item-<?php echo esc_attr($item['state']); ?>">
            <div class="cs-item-head">
                <h4 class="cs-item-title"><?php echo esc_html($item['title']); ?></h4>
                <?php self::chip($item['state']); ?>
            </div>
            <div class="cs-item-value"><?php echo esc_html($item['value']); ?></div>
            <p class="cs-item-what"><?php echo esc_html($item['what']); ?></p>
            <?php if (!empty($item['detail'])): ?>
            <ul class="cs-item-detail">
                <?php foreach ($item['detail'] as $line): ?>
                <li><?php echo esc_html($line); ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <?php if ($item['state'] !== 'good' && $item['action'] !== ''): ?>
            <p class="cs-item-action"><strong>What to do:</strong> <?php echo esc_html($item['action']); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function render_table($table) {
        $numeric = $table['numeric'] ?? [];
        $total   = !empty($table['total']);
        $last    = count($table['rows']) - 1;
        ?>
        <div class="cs-table-block">
            <h3 class="cs-table-title"><?php echo esc_html($table['title']); ?></h3>
            <?php if (!empty($table['note'])): ?>
            <p class="cs-table-note"><?php echo esc_html($table['note']); ?></p>
            <?php endif; ?>
            <table class="cs-table">
                <thead>
                    <tr>
                        <?php foreach ($table['head'] as $i => $heading): ?>
                        <th<?php echo in_array($i, $numeric, true) ? ' class="cs-num"' : ''; ?>><?php echo esc_html($heading); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($table['rows'] as $r => $row): ?>
                    <tr<?php echo ($total && $r === $last) ? ' class="cs-total"' : ''; ?>>
                        <?php foreach ($row as $i => $cell): ?>
                        <td<?php echo in_array($i, $numeric, true) ? ' class="cs-num"' : ''; ?>><?php echo esc_html($cell); ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function render($data) {

        $model = self::build($data);

        $site_name = get_bloginfo('name');
        $site_url  = home_url();
        $generated = wp_date('j F Y, H:i') . ' (' . wp_timezone_string() . ')';

        // Optional "Prepared by" block, set on the plugin's Settings tab. The
        // 'corespect_report_prepared_by' filter can still override it:
        // ['name' => '...', 'logo_url' => '...'].
        $prepared = apply_filters('corespect_report_prepared_by', CoreSpect_Settings::get_prepared_by());
        $prepared_name = is_array($prepared) ? ($prepared['name'] ?? '') : '';
        $prepared_logo = is_array($prepared) ? ($prepared['logo_url'] ?? '') : '';

        $counts = $model['counts'];
        ?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr(get_bloginfo('language')); ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html('Site Audit Report - ' . $site_name); ?></title>

    <?php
        wp_register_style(
            'corespect-export-style',
            CORESPECT_URL . 'admin/css/export-style.css',
            [],
            CORESPECT_VERSION
        );
        wp_enqueue_style('corespect-export-style');

        // This document is generated directly by an admin-post.php handler
        // and never calls wp_head(), so the enqueued stylesheet is printed
        // manually at this exact point instead.
        wp_print_styles('corespect-export-style');
    ?>
</head>

<body>
    <div class="cs-no-print cs-hint">Saving as a PDF? In the print dialog, switch off "Headers and footers" for a cleaner page.</div>

    <div class="cs-page">

        <!-- ================= HEADER ================= -->
        <header class="cs-report-header">
            <div class="cs-brand">
                <img class="cs-logo" src="<?php echo esc_url(CORESPECT_ADMIN_URL . 'assets/corespect-logo-white.png'); ?>" alt="">
                <div>
                    <div class="cs-report-kicker">Site Audit Report</div>
                    <h1 class="cs-report-title"><?php echo esc_html($site_name); ?></h1>
                    <div class="cs-report-url"><?php echo esc_html($site_url); ?></div>
                </div>
            </div>

            <?php if ($prepared_name !== '' || $prepared_logo !== ''): ?>
            <div class="cs-prepared">
                <span class="cs-prepared-label">Prepared by</span>
                <?php if ($prepared_logo !== ''): ?>
                <img class="cs-prepared-logo" src="<?php echo esc_url($prepared_logo); ?>" alt="<?php echo esc_attr($prepared_name); ?>">
                <?php endif; ?>
                <?php if ($prepared_name !== ''): ?>
                <span class="cs-prepared-name"><?php echo esc_html($prepared_name); ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </header>

        <dl class="cs-meta">
            <div><dt>Generated</dt><dd><?php echo esc_html($generated); ?></dd></div>
            <div><dt>WordPress</dt><dd><?php echo esc_html(get_bloginfo('version')); ?></dd></div>
            <div><dt>PHP</dt><dd><?php echo esc_html(PHP_VERSION); ?></dd></div>
        </dl>

        <!-- ================= SUMMARY ================= -->
        <section class="cs-summary">
            <h2 class="cs-h2">Summary</h2>

            <div class="cs-tiles">
                <div class="cs-tile cs-tile-bad">
                    <div class="cs-tile-number"><?php echo esc_html($counts['bad']); ?></div>
                    <div class="cs-tile-label">Action needed</div>
                </div>
                <div class="cs-tile cs-tile-warn">
                    <div class="cs-tile-number"><?php echo esc_html($counts['warn']); ?></div>
                    <div class="cs-tile-label">Warnings</div>
                </div>
                <div class="cs-tile cs-tile-good">
                    <div class="cs-tile-number"><?php echo esc_html($counts['good']); ?></div>
                    <div class="cs-tile-label">Good</div>
                </div>
            </div>

            <?php if ($model['attention']): ?>
            <h3 class="cs-h3">Needs attention</h3>
            <ol class="cs-attention">
                <?php foreach ($model['attention'] as $item): ?>
                <li class="cs-attention-<?php echo esc_attr($item['state']); ?>">
                    <div class="cs-attention-head">
                        <strong><?php echo esc_html($item['title']); ?></strong>
                        <span class="cs-attention-value"><?php echo esc_html($item['value']); ?></span>
                        <?php self::chip($item['state']); ?>
                    </div>
                    <div class="cs-attention-section"><?php echo esc_html($item['section']); ?></div>
                    <?php if ($item['action'] !== ''): ?>
                    <div class="cs-attention-action"><?php echo esc_html($item['action']); ?></div>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ol>
            <?php else: ?>
            <p class="cs-all-good">✓ These checks found nothing that needs attention.</p>
            <?php endif; ?>
        </section>

        <!-- ================= SECTIONS ================= -->
        <?php foreach ($model['sections'] as $section): ?>
        <section class="cs-section" id="<?php echo esc_attr($section['id']); ?>">
            <h2 class="cs-h2"><?php echo esc_html($section['title']); ?></h2>
            <p class="cs-intro"><?php echo esc_html($section['intro']); ?></p>

            <div class="cs-items">
                <?php foreach ($section['items'] as $item): ?>
                    <?php self::render_item($item); ?>
                <?php endforeach; ?>
            </div>

            <?php foreach ($section['tables'] as $table): ?>
                <?php self::render_table($table); ?>
            <?php endforeach; ?>
        </section>
        <?php endforeach; ?>

        <!-- ================= APPENDIX ================= -->
        <section class="cs-section cs-appendix">
            <h2 class="cs-h2">Appendix</h2>
            <p class="cs-intro">The complete lists behind the sections above.</p>

            <?php foreach ($model['appendix'] as $table): ?>
                <?php self::render_table($table); ?>
            <?php endforeach; ?>
        </section>

        <!-- ================= FOOTER ================= -->
        <footer class="cs-footer">
            <p>This report was generated automatically by CoreSpect Site Audit &amp; Report <?php echo esc_html(CORESPECT_VERSION); ?> on <?php echo esc_html($generated); ?>.
            It summarises a set of basic automated checks and is not a complete security audit or performance test.
            Some protections, such as a firewall or caching provided by your host, cannot be detected from inside WordPress.</p>
        </footer>

    </div>
</body>

</html>
        <?php
    }
}
