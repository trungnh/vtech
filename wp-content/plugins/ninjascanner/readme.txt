=== NinjaScanner - Virus & Malware scan ===
Contributors: nintechnet, bruandet
Tags: antimalware, antivirus, security, protection, malware scanner
Requires at least: 3.3.0
Tested up to: 5.4
Stable tag: 2.0.6
License: GPLv3 or later
Requires PHP: 5.5
License URI: http://www.gnu.org/licenses/gpl-3.0.html

== Description ==

= A lightweight, fast and powerful antivirus scanner for WordPress. =

NinjaScanner is a lightweight, fast and powerful antivirus scanner for WordPress which includes many features to help you scan your blog for malware and virus.

= Features =

* File integrity checker.
* File comparison viewer.
* Exclusion filters.
* File snapshot.
* Database snapshot.
* Anti-malware/Antivirus.
* [Sandbox for quarantined files](http://nin.link/nssandbox/ "NinjaScanner sandbox").
* Ignored files list.
* Google's Safe Browsing Lookup API.
* Incremental scans.
* Background scans.
* Scheduled scans (Premium).
* WP-CLI integration (Premium).
* Debugging log.
* Email report.
* Integration with [NinjaFirewall (WP and WP+ Edition)](https://wordpress.org/plugins/ninjafirewall/ "Download NinjaFirewall").
* Multi-site support.
* Contextual help.
* And many more...

= File Integrity Checker =

The File Integrity Checker will compare your WordPress core files as well as your plugin and theme files to their original package. Its File Comparison Viewer will show you the differences between any modified file and the original. You can also [add your Premium themes and plugins](https://blog.nintechnet.com/ninjascanner-powerful-antivirus-scanner-for-wordpress/#integrity "") to the File Integrity Checker. Infected or corrupted files can be easily restored with one click.

= File Snapshot =

The File Snapshot will show you which files were changed, added or deleted since the previous scan.

= Database Snapshot =

NinjaScanner will compare all published posts and pages in the database with the previous scan and will report if any of them were changed, added or deleted.

= Anti-Malware Signatures =

You can scan your blog for potential malware and virus using the built-in signatures. The scanning engine is compatible with [Linux Malware Detect LMD](https://github.com/rfxn/linux-malware-detect "") (whose anti-malware signatures are included) and with some [ClamAV](https://www.clamav.net/ "") signatures as well. You can even [write your own anti-malware signatures](https://blog.nintechnet.com/ninjascanner-powerful-antivirus-scanner-for-wordpress/#signatures "").

= Incremental Scan =

If a scan is interrupted before completion (e.g., crash, error etc), it will restart automatically where it left off.

= NinjaFirewall Integration =

If you are running our [NinjaFirewall (WP or WP+ Edition)](https://wordpress.org/plugins/ninjafirewall/ "Download NinjaFirewall") web application firewall plugin, you can use this option to integrate NinjaScanner into its menu.

= Fast and Lightweight Scanner =

NinjaScanner has strictly no impact on your database. It only uses it to store its configuration (less than 1Kb). It saves the scan data, report, logs etc on disk only, makes use of caching to save bandwidth and server resources. It also includes a Garbage Collector that will clean up its cache on a regular basis.

= Background Scans =

Another great NinjaScanner feature is that it runs in the background: start a scan, let it run and keep working on your blog as usual. You can even log out of the WordPress dashboard while a scanning process is running! You don't have to wait patiently until the scan has finished. Additionally, a scan report can be sent to one or more email addresses.

= Sandbox for quarantined files =

When moving a file to the quarantine folder, NinjaScanner can use a testing environment (a.k.a. sandbox) to make sure that this action does not crash your blog with a fatal error. If it does, it will warn you and will not quarantine the file. It is possible (but not recommended) to disable the sandbox.

= Advanced Settings =

NinjaScanner offers many advanced settings to finely tune it, such as exclusion filters, selection of the algorithm to use, a debugging log etc.

= Privacy Policy =

Your website can run NinjaScanner and be 100% compliant with the **General Data Protection Regulation (GDPR)**:

We, the authors, do not collect, share or sell personal information. We don't track or profile you. Our software does not collect any private data from you or your visitors.

= Premium Features =

Check out our [NinjaScanner Premium Edition](https://nintechnet.com/ninjascanner/ "NinjaScanner Premium Edition")

* **Scheduled Scans**: Don't leave your blog at risk. With the scheduled scan option, NinjaScanner will run automatically hourly, twice daily or daily.
* **WP-CLI Integration**: Do you own several blogs and prefer to manage them from the command line? NinjaScanner can nicely integrate with WP-CLI, using the `ninjascanner` command. You can use it to start or stop a scanning process, view its status, its report or log from your favourite terminal, without having to log in to the WordPress Admin Dashboard.
* **Dedicated Help Desk with Priority Support**

== Installation ==

1. Upload the `ninjascanner` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' page in WordPress.
3. Plugin settings are located in the 'Tools > NinjaScanner' sub-menu.

== Screenshots ==

1. Summary page.
2. Basic settings.
3. Advanced settings.
4. Nerds settings.
5. WP-CLI integration.
6. Report sample.
7. Viewing differences between the modified and the original files.
8. Debugging log.
9. Integration with NinjaFirewall.

== Changelog ==

= 2.0.6 =

* Tweaked the scanner to lower false positives and to better differentiate between "critical" and "important" severity levels.

= 2.0.5 =

* It is possible to disable the syntax highlighter used when viewing a file (see "Setting > Advanced Users Settings > Scan report").
* Better accessibility: when viewing or comparing files, NinjaScanner will open the content in a new browser tab rather than a small popup window. Fonts size has been increased too.
* Fixed a bug in the sandbox: when the site was password-protected (HTTP basic authentication), the username and password were not used by the sandbox, which threw a 401 Unauthorized error.
* Better handling of AJAX responses and errors.
* Small fixes and adjustments.

= 2.0.4 =

* Fixed a potential issue where, when viewing a suspicious file, the malware code was not highlighted.
* When comparing two files, NinjaScanner will warn the user if they have different line endings (e.g., LF vs CRLF).
* Updated Prism syntax highlighter JS file to the latest version.
* Fixed some CSS and icons issues.
* Small fixes and adjustments.

= 2.0.3 =

* Improved the quarantine sandbox to better detect errors after moving a file to the quarantine folder.
* You can now change the path to the `wp-content/ninjascanner/local/` folder (used for premium themes and plugins installation packages and user signatures) with the `NSCAN_LOCAL` constant in your wp-config.php file. For instance: `define( 'NSCAN_LOCAL', '/foo/bar/local' );`. See https://blog.nintechnet.com/ninjascanner-powerful-antivirus-scanner-for-wordpress/#integrity
* Fixed some CSS issues in the "Quarantine" and "Ignored" tabs.
* You can select to keep NinjaScanner's options and cache folder after uninstalling it. This feature can be useful if you wanted to reinstall it without losing the current settings and cached files. See "Settings > Advanced Users Settings > Nerds Settings > Uninstall options".
* Small fixes and adjustments.

= 2.0.2 =

* Fixed a bug where NinjaScanner original menu was not removed from the dashboard left frame after integrating it with NinjaFirewall v4.0+.

= 2.0.1 =

* Added an option to delete the scan report and its corresponding snapshot. You can use it to clear the whole cache and its data if the snapshot was corrupted instead of having to delete the files manually over FTP. See "Settings > Advanced Users Settings > Nerds Settings > Clear snapshot and scan report".
* Added better HTTP headers than WordPress default ones to all AJAX requests.

= 2.0 =

* Added a new user interface for the scanner report: nicer UI with a separate section for each items, row action links etc.
* Added some options to configure the scanner report UI. See "Settings > Advanced Users Settings > Scan report".
* New UI is now fully compatible with portable devices.
* Added an ignored files list: all files moved to that list will be ignored by the scanner, unless they are modified or removed from the list.
* Improved the file viewer.
* Added more AJAX actions. All Javascript code was rewritten.
* Better handling of errors.
* Added more verbosity below the progress bar when a scan is running.
* Added HTTP referrer to satisfy Google Safe Browsing application restriction.

= 1.5.1 =

* Compatibility with WordPress 5.2.
* Updated checksum hashes.

= 1.5 =

* Added an option to check the site against Google's Safe Browsing Lookup API. See "Settings > Advanced Users Settings > Google Safe Browsing".
* Added an option for HTTP basic authentication: if the site is password-protected, you can add the username and password to the "Settings > Advanced Users Settings > Nerds Settings > HTTP basic authentication" option.
* When attempting to view a file, NinjaScanner will return an error if it is a binary file.
* Small fixes and adjustements.

= 1.4.1 =

* Added an exclusion list to avoid false positives when checking user roles and capabilities if the blog is running plugins that add new roles in the database (e.g., WooCommerce).

= 1.4 =

* NinjaScanner will now also check if some important WordPress options in the database have been tampered with (e.g., user roles and capabilities).

= 1.3.4 =

* Fixed a potential "Undefined variable: version" PHP notice when writing to the scanner log.
* Fixed a potential "Failed to open stream" PHP warning when a temporary file was deleted right after the scanner built the list of files.
* Added the values of "memory_limit" and "max_execution_time" to the scanner log for debugging purposes.
* Increased the height of the textarea in the "Log" and "Quarantine" pages.
* Small fixes and adjustements.

= 1.3.3 =

* When viewing a file marked as suspicious by the antimalware scanner, the suspicious code will be highlighted in yellow.
* When comparing two files, the full path and filename will be displayed at the top.
* The scanner's antimalware signatures are now digitally signed to make sure they weren't tampered with.
* The scanning process forking method will be set to AJAX instead of WP_CRON by default.
* Small fixes and adjustements.

= 1.3.2 =

* Improved the anti-malware engine processing speed.

= 1.3.1 =

* Added a new option to fork the scanning process using WordPress built-in AJAX feature instead of the default WP-CRON. Use this alternate option if the scan does not start and throws an error. See "Settings > Advanced Users Settings > Scanning process > Fork process".
* Various fixes and adjustements.

= 1.3 =

* Added a new option to detect and report all published pages and posts that were changed, added or deleted in the database since last scan. See "Settings > Advanced Users Settings > Database snapshot".
* Various fixes and adjustements.

= 1.2.4 =

* Fixed an issue where the scanner might not able to verify a plugin integrity even it is was available in the wordpress.org repo because it was not properly "tagged" by its author. If the problem occurs, NinjaScanner will download the plugin from its "trunk" folder as a last resort.

= 1.2.3 =

* Added a new option: "Advanced Users Settings > Incremental scan > Attempt to force-restart the scan using an alternate method". Because some hosts may kill PHP scripts if they take too long to run, this option will attempt to force-restart the scan using an alternate method. Enable it only if the scan hangs or does not seem to terminate.
* The scan report will no longer suggest to install NinjaFirewall if the server is running Microsoft Windows Server OS.
* Fixed a potential "Zend OPcache API" warning message when moving a file to the quarantine folder.
* Minor fixes and adjustments.

= 1.2.2 =

* Added an option to apply the files & folders exclusion list to the file integrity checker. This option can be useful if you have themes or plugins that create temporary or cached files inside their own installation folder, and want them to be excluded from the file integrity checker (see "NinjaScanner > Settings > Basic Settings > Ignore files/folders > Apply the exclusion list to the file integrity checker").
* Replaced the animated GIF with a progress bar when a scan is running.

= 1.2.1 =

* Fixed a fatal error with non UTF-8 chars when calling the json_decode() function.
* Makes sure the destination folder is writable before restoring a file.
* Added a "GDPR Compliance" link in the "About" page.

= 1.2 =

* Added a sandbox to the quarantine option: When moving a file to the quarantine folder, NinjaScanner can use a testing environment (a.k.a. sandbox) to make sure that this action does not crash your blog with a fatal error. If it does, it will warn you and will not quarantine the file. The sandbox option can be disabled from the "Nerds Settings" menu. See also our blog: http://nin.link/nssandbox/
* Added support for chrooted `ABSPATH` ("/").
* When moving a file to the quarantine folder, an error message will be returned if the source file is not writable and cannot be deleted.

= 1.1 =

* You can now restore modified files (WordPress core, plugin and theme) or quarantine other files with one click while viewing the scan report: select the file in the listbox, and click the corresponding button below.
* Added a new "Quarantine" tab. It displays the list of quarantined files, if any, and can be used to managed them.
* Added a diagnostics button to help detect potential errors ("NinjaScanner > Settings > Advanced Users Settings > Nerds Settings > Debugging > Run diagnostics").
* Better error handling (memory allocation errors etc).
* Added a new "System" section to the scan report. It will be used to perform various system tests.
* Minor fixes and adjustments.

= 1.0.5 =

* The File Comparison Viewer will always attempt to retrieve the original core, plugin or theme file from the local cache first and, if not found, it will download it from wordpress.org rather than returning an error message.
* Fixed a bug where some errors occurring while checking the core files integrity (e.g., connection errors, time-out) were not mentioned in the email report.

= 1.0.4 =

* Fixed a bug where the scan report was sent by email regardless of the user settings.
* Fixed an issue with non-en_US locale WordPress installations: the "File Integrity Checker" could wrongly report that bundled translation files (.mo and .po) were modified because it was using outdated cached copies of the files.
* By default, the Garbage Collector will run hourly instead of daily. You can also run it manually to flush the cache immediately (see "NinjaScanner > Settings > Advanced Users Settings > Nerds Settings > Run the garbage collector").

= 1.0.3 =

* Added the option to send the email report depending on the scan results (e.g., only if a critical or important problem was detected). See the "NinjaScanner > Settings > Send the scan report" option.
* Improved the detection of backdoors in the root (`ABSPATH`) of the blog installation.
* Fixed a bug that could wrongly flag a cached file as suspicious when a caching plugin was installed.
* Minor fixes and adjustments.

= 1.0.2 =

* The scanning process can be started even when `DISABLE_WP_CRON` is set (note that a cron job is still needed to run scheduled scans and the garbage collector).
* Fixed a bug in the file comparison viewer that would skip some empty lines.

= 1.0.1 =

* Fixed an issue with non-en_US locale WordPress installations: the "File Integrity Checker" could wrongly report that some files (wp-config-sample.php, version.php and readme.html) were modified.
* Increased remote connections timeout from 10 to 60 seconds.
* Added a warning if the report was created with a different version of NinjaScanner.

= 1.0 =

* Initial released.

