=== CoreSpect Site Audit & Report ===
Contributors: ujjwalshres
Tags: audit, database, performance, security, reports
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A one-click site health report for your WordPress database, performance, security, and files exportable as HTML, PDF, JSON, CSV, or TXT.

== Description ==

CoreSpect Site Audit & Report gives you a single dashboard to check the health of your WordPress site, and lets you export the findings to share with a client or keep for your own records.

**Database**

- Table count, total size, and per-table row counts and sizes
- Detection of tables potentially left behind by uninstalled plugins
- Overhead, revisions, transients, and spam comments

**Performance**

- PHP version, enforced PHP memory limit, and cache plugin detection
- Largest JavaScript, CSS, and other heavy files across plugins and themes

**Security**

- Detected security plugins and firewall checks
- Outdated plugin and theme counts with names
- Debug mode and file editor status
- Basic login-safety checks
- A check for PHP files inside the uploads folder

**Files**

- Total site size, uploads folder size, temp/cache size
- Log files and unusually large files (over 10MB)

**Plugins & Themes**

- Active vs. inactive plugin counts
- Full plugin and theme inventory with versions

**Export**

Reports can be exported as a print-ready HTML or PDF document (via the browser's print dialog) with a summary of what needs attention, plain-language explanations for each check, and a full appendix, or as JSON, CSV, or plain text handy for sharing a snapshot with a client or keeping a record over time.

= Privacy =

CoreSpect Site Audit & Report does not collect, transmit, or store any data outside of your own WordPress installation. The only thing it saves is the optional "Prepared by" name and logo you enter on the Settings tab, which is removed when you delete the plugin. All checks run locally against your site's own database and file system.

== Installation ==

1. Upload the `corespect-site-audit-report` folder to the `/wp-content/plugins/` directory, or install the plugin directly through the WordPress admin under Plugins > Add New.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to **Tools > CoreSpect** in the admin sidebar to view your report.

== Frequently Asked Questions ==

= Does this plugin modify my site or fix issues automatically? =

No. CoreSpect Site Audit & Report is read-only. It inspects your database, files, and configuration and reports on them. It does not change any settings or delete any data.

= How do I add my name or agency to the report? =

Go to **Tools > CoreSpect > Settings**, enter a name and optionally choose a logo from your media library, then save. They appear as a "Prepared by" block at the top of every exported report. Leave both empty to hide it.

= Where do exported reports go? =

Exports are generated on demand and downloaded directly to your computer. Nothing is stored on the server beyond WordPress's normal temporary output buffering.

= Does the PDF export require any extra libraries? =

No. PDF export opens the report in your browser and triggers the print dialog, so you can save it as a PDF using your browser's built-in "Print to PDF" option.

== Screenshots ==

1. Database overview tab
2. Performance environment tab
3. Security status tab
4. Files overview tab
5. Plugins & themes tab
6. Export options
7. Settings tab
8. Demo Report

== Changelog ==

= 1.2.0 =
- Fixed: PHP memory limit now shows the value PHP enforces, and its status rating is no longer inverted.
- Fixed: Outdated plugin and theme tooltips now match their thresholds.
- Fixed: Dashboard tabs now wrap on narrower screens instead of running off the edge.
- Fixed: A JavaScript error on dashboard tabs other than Export.
- Improved: "Files Worth Reviewing" now checks for PHP files in the uploads folder instead of scanning all plugin and theme code, which produced false alarms.
- Improved: Database tab now identifies tables possibly left behind by plugins that are no longer installed.
- Improved: Outdated plugins and themes are listed by name; file lists show full paths.
- Improved: Firewall check no longer treats a plain .htaccess file as a firewall; missing page cache is now a warning.
- Improved: Plugin data is removed on uninstall.
- Changed: Performance tab now lists the largest plugin and theme files. The Requests and Page Size cards were removed because they measured admin pages, not visitor-facing pages.
- New: Redesigned report with a summary of what needs attention, plain-language explanations, and an appendix.
- New: "Prepared by" support via the `corespect_report_prepared_by` filter.
- New: Settings tab to add your name or agency and a logo, shown as "Prepared by" in exported reports.

= 1.1.0 =
- New: "Refresh report" button to rebuild the report on demand.
- New: The report now refreshes automatically when a plugin or theme is installed, activated, deactivated, updated or deleted.
- New: "Settings" link on the Plugins page that opens the report.
- Improved: Cache detection now recognises 16 caching plugins, detects network-activated plugins, and flags multiple page-cache plugins or an unrecognised page cache. Developers can add plugins with the `corespect_cache_plugins` filter.
- Fixed: WordPress admin notices now appear above the plugin header on every tab, including Export.
- Fixed: The Helpful Links (Request Feature, Get Support, Rate our Plugin) now point to the plugin's WordPress.org pages.

= 1.0.0 =
- Initial release.

== Upgrade Notice ==

= 1.2.0 =
Redesigned report layout with attention summaries and plain-language explanations, a new Settings tab for "Prepared by" branding, cleaner security/performance checks, and uninstall cleanup.

= 1.0.0 =
Initial release.