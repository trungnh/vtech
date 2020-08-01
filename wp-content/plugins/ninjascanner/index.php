<?php
/*
Plugin Name: NinjaScanner
Plugin URI: https://nintechnet.com/ninjascanner/
Description: A lightweight, fast and powerful antivirus scanner for WordPress.
Author: The Ninja Technologies Network
Author URI: https://nintechnet.com/
Version: 2.0.6
License: GPLv3 or later
Network: true
Text Domain: ninjascanner
Domain Path: /languages
*/

define( 'NSCAN_VERSION', '2.0.6' );

/*
 +=====================================================================+
 |     _   _ _        _       ____                                     |
 |    | \ | (_)_ __  (_) __ _/ ___|  ___ __ _ _ __  _ __   ___ _ __    |
 |    |  \| | | '_ \ | |/ _` \___ \ / __/ _` | '_ \| '_ \ / _ \ '__|   |
 |    | |\  | | | | || | (_| |___) | (_| (_| | | | | | | |  __/ |      |
 |    |_| \_|_|_| |_|/ |\__,_|____/ \___\__,_|_| |_|_| |_|\___|_|      |
 |                 |__/                                                |
 |                                                                     |
 | (c) NinTechNet ~ https://nintechnet.com/                            |
 +=====================================================================+
*/

if (! defined( 'ABSPATH' ) ) { die( 'Forbidden' ); }

// =====================================================================
// Contants:
require_once __DIR__ . '/lib/constants.php';

// =====================================================================

$i18n = __( "A lightweight, fast and powerful antivirus scanner for WordPress.", "ninjascanner" );
// Both constants are used by NinjaFirewall:
define( 'NSCAN_NAME', 'NinjaScanner' );
define( 'NSCAN_SLUG', 'ninjascanner' );

// =====================================================================
// Load (force) our translation files.

$ns_locale = array( 'fr_FR' );
$this_locale = get_locale();
if ( in_array( $this_locale, $ns_locale ) ) {
	if ( file_exists( __DIR__ . "/languages/ninjascanner-{$this_locale}.mo" ) ) {
		unload_textdomain( 'ninjascanner' );
		load_textdomain( 'ninjascanner', __DIR__ . "/languages/ninjascanner-{$this_locale}.mo" );
	}
}

// =====================================================================
// Activation: make sure the blog meets the requirements.

function nscan_activate() {

	global $wp_version;
	if ( version_compare( $wp_version, '3.3', '<' ) ) {
		exit( sprintf(
		__( 'NinjaScanner requires WordPress 3.3 or greater but your current version is %s.',
		'ninjascanner' ),
		htmlspecialchars( $wp_version ) ) );
	}

	if ( version_compare( PHP_VERSION, '5.3', '<' ) ) {
		exit( sprintf(
		__( 'NinjaScanner requires PHP 5.3 or greater but your current version is %s.',
		'ninjascanner' ),
		htmlspecialchars( PHP_VERSION ) ) );
	}

	if (! class_exists('ZipArchive') ) {
		exit( __( 'Your PHP configuration is missing the "ZipArchive" class. '.
			'It is not possible to run NinjaScanner.', 'ninjascanner' ) );
	}

	$nscan_options = get_option( 'nscan_options' );
	require_once 'lib/install.php';

	// This is not the first time we run:
	if ( isset( $nscan_options['scan_scheduled'] ) ) {
		// Restore the cron jobs:
		nscan_default_gc( $nscan_options['scan_garbage_collector'] );
		nscan_default_sc( $nscan_options['scan_scheduled'] );
		return;
	}

	// First run: get and save default settings:
	$nscan_options = array();
	$nscan_options = nscan_default_settings();
	update_option( 'nscan_options', $nscan_options );

	// Setup the garbage collector (via WP-cron):
	nscan_default_gc( $nscan_options['scan_garbage_collector'] );

}

register_activation_hook( __FILE__, 'nscan_activate' );

// =====================================================================
// Deactivation: stop any cron.

function nscan_deactivate() {

	$nscan_options = get_option( 'nscan_options' );
	require_once 'lib/install.php';
	nscan_default_gc(0);
	nscan_default_sc(0);

}

register_deactivation_hook( __FILE__, 'nscan_deactivate' );

// =====================================================================
// Run the garbage collector to clean-up the cached folder.

require_once __DIR__ .'/lib/gc.php';
add_action( 'nscan_garbage_collector', 'nscan_gc' );

// =====================================================================
// View the file or compare it to the original one. Applies to WordPress
// core files or to themes/plugins available in the wordpress.org repo.
// Additionally, adjust options if we just update the plugin to a newer
// version.

function nscan_init() {

	// Admin/Superadmin only:
	if (! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	// Load the selected file(s) in the pop-up window:
	if ( isset( $_GET['page'] ) && $_GET['page'] == 'NinjaScanner' && isset( $_GET['nscanop'] )
			&& in_array( $_GET['nscanop'], array ( 'view', 'compare' ) ) ) {

		// Verify security nonce:
		if ( empty( $_GET['nscanop_nonce'] ) || ! wp_verify_nonce( $_GET['nscanop_nonce'], 'nscan_file_op' ) ) {
			wp_nonce_ays('nscan_file_op');
		}

		if ( empty( $_GET['file'] ) ) {
			wp_die( sprintf(
				__('Missing or incorrect parameter: %s', 'ninjascanner' ),
				'file'
			) );
		}
		$file = base64_decode( $_GET['file'] );

		// File must exist:
		if (! file_exists( $file ) ) {
			wp_die( sprintf(
				__('File does not exist: %s', 'ninjascanner' ),
				htmlentities( $file )
			) );
		}
		// File must be readable:
		if (! is_readable( $file ) ) {
			wp_die( sprintf(
				__('File cannot be read: %s', 'ninjascanner' ),
				htmlentities( $file )
			) );
		}

		// View file:
		if ( $_GET['nscanop'] == 'view' ) {
			require __DIR__ .'/lib/file_view.php';

		// Compare the file to the original one:
		} else {
			require __DIR__ .'/lib/file_compare.php';
		}

		exit;
	}

	// Updates may require to adjust the current configuration:
	require __DIR__ .'/lib/core_updates.php';

}

add_action( 'admin_init', 'nscan_init' );

// =====================================================================
// Ajax processing: quarantine the file.

add_action( 'wp_ajax_nscan_quarantine', 'nscan_ajax_quarantine' );

function nscan_ajax_quarantine() {

	// Allow only the Admin/Superadmin with a valid nonce:
	if (! current_user_can('activate_plugins') ||
		! check_ajax_referer( 'nscan_file_op', 'nscanop_nonce', false ) ) {
		_e('Error: Security nonces do not match. Reload the page and try again.', 'ninjascanner');
		wp_die();
	}

	$file = base64_decode( $_POST['file'] );

	if (! file_exists( $file ) ) {
		echo '404';
		wp_die();
	}

	require __DIR__ .'/lib/file_quarantine.php';

	// Make sure it was successfully quarantined:
	if (! file_exists( $file ) ) {
		echo "success";
	} else {
		_e('Error: Cannot quarantine the file.', 'ninjascanner');
	}

	wp_die();
}

// =====================================================================
// Ajax processing: ignore the file.

add_action( 'wp_ajax_nscan_ignore', 'nscan_ajax_ignore' );

function nscan_ajax_ignore() {

	// Allow only the Admin/Superadmin with a valid nonce:
	if (! current_user_can('activate_plugins') ||
		! check_ajax_referer( 'nscan_file_op', 'nscanop_nonce', false ) ) {
		_e('Error: Security nonces do not match. Reload the page and try again.', 'ninjascanner');
		wp_die();
	}

	$file = base64_decode( $_POST['file'] );

	if (! file_exists( $file ) ) {
		echo '404';
		wp_die();
	}

	require __DIR__ .'/lib/file_ignore.php';

	echo "success";
	wp_die();

}

// =====================================================================
// Ajax processing: restore the original file (core, plugin or theme).

add_action( 'wp_ajax_nscan_restore', 'nscan_ajax_restore' );

function nscan_ajax_restore() {

	// Allow only the Admin/Superadmin with a valid nonce:
	if (! current_user_can('activate_plugins') ||
		! check_ajax_referer( 'nscan_file_op', 'nscanop_nonce', false ) ) {
		_e('Error: Security nonces do not match. Reload the page and try again.', 'ninjascanner');
		wp_die();
	}

	$file = base64_decode( $_POST['file'] );

	if (! file_exists( $file ) ) {
		_e('Error: File does not exist.', 'ninjascanner');
		wp_die();
	}

	require __DIR__ .'/lib/file_restore.php';

	echo "success";
	wp_die();

}

// =====================================================================
// Ajax processing: check user's Google Safe Browsing API key validity.

add_action( 'wp_ajax_nscan_checkapikey', 'nscan_checkapikey' );

function nscan_checkapikey() {

	// Allow only the Admin/Superadmin with a valid nonce:
	if (! current_user_can('activate_plugins') ||
		! check_ajax_referer( 'nscan_gsbapikey', 'nscanop_nonce', false ) ) {
		_e('Error: Security nonces do not match. Reload the page and try again.', 'ninjascanner');
		wp_die();
	}

	if ( empty( $_POST['api_key'] ) ) {
		_e('Please enter your API key.', 'ninjascanner');
		wp_die();
	}
	global $wp_version;

	// Used for Google referrer restriction:
	$referrer = get_site_url();

	$body = array(
		'body' => '{
			"threatInfo": {
				"threatTypes":      ["MALWARE", "SOCIAL_ENGINEERING"],
				"platformTypes":    ["ANY_PLATFORM"],
				"threatEntryTypes": ["URL"],
				"threatEntries": [
					{"url": "htt'.'p://malware.tes'.'ting.google.test/testing/malware/"},
				]
			}
		}',
		'headers' => array(
			'content-type' => 'application/json',
			'Referer' => $referrer,
		),
		'data_format' => 'body',
		'user-agent' => 'Mozilla/5.0 (compatible; NinjaScanner/'.
							NSCAN_VERSION ."; WordPress/{$wp_version})",
		'timeout' => NSCAN_CURL_TIMEOUT,
		'httpversion' => '1.1' ,
		'sslverify' => true
	);
	$res = wp_remote_post( NSCAN_GSB . "?key={$_POST['api_key']}", $body);

	if (! is_wp_error($res) ) {
		$data = json_decode( $res['body'], true );
		// Invalid key:
		if (! empty( $data['error']['message'] ) ) {
			printf( __('Error: %s', 'ninjascanner'), $data['error']['message'] );
			wp_die();
		}
		// OK:
		if (! empty( $data['matches'][0]['threat']['url'] ) ) {
			echo "success";
			wp_die();
		}
	}

	// Something went wrong:
	_e('Unknown error.', 'ninjascanner');
	wp_die();

}

// =====================================================================
// Display settings link in the "Plugins" page.

function nscan_settings_link( $links ) {

	if ( is_multisite() ) {	$net = 'network/'; } else { $net = '';	}
	$get_admin_url = get_admin_url(null, "{$net}admin.php?page=NinjaScanner");

	// If a scanning process is running, we remove
	// the "Deactivate" link and add a warning instead:
	$res = nscan_is_scan_running();
	if ( $res[0] == 1 ) {
		$links[] = "<a href='{$get_admin_url}'>". __('A scan is running...', 'ninjascanner') .'</a>';
		unset( $links['edit'] );
		unset( $links['deactivate'] );
		return $links;
	}

	$links[] = "<a href='{$get_admin_url}'>". __('Settings', 'ninjascanner') .'</a>';
	$links[] = '<a href="https://wordpress.org/support/view/plugin-reviews/ninjascanner?'.
		'rate=5#postform" target="_blank">'. __('Rate it!', 'ninjascanner'). '</a>';
	unset( $links['edit'] );
	return $links;
}

if ( is_multisite() ) {
	add_filter(
		'network_admin_plugin_action_links_' . plugin_basename(__FILE__),
		'nscan_settings_link'
	);
} else {
	add_filter(
		'plugin_action_links_' . plugin_basename(__FILE__),
		'nscan_settings_link'
	);
}

// =====================================================================
// WP CLI commands.

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once __DIR__ . '/lib/cli.php';
}

// =====================================================================
// Create NinjaScanner menu. It can be, however, integrated into
// NinjaFirewall own menu too.

function nscan_admin_menu() {

	if (! is_main_site() ) { return;}

	// If NinjaFirewall (>3.5.4) is installed and enabled and the user
	// activated the "NinjaFirewall menu integration" option, we don't
	// display any menu item:
	$nscan_options = get_option( 'nscan_options' );
	if ( ( is_plugin_active( 'ninjafirewall/ninjafirewall.php' ) || is_plugin_active( 'nfwplus/nfwplus.php' ) )
		&& ! empty( $nscan_options['scan_nfwpintegration'] ) &&
		version_compare( NFW_ENGINE_VERSION, '3.5.4', '>' ) ) {

		define( 'NSCAN_NFWP', true );
		return;
	}

	$menuhook = add_menu_page(
		'NinjaScanner',
		'NinjaScanner',
		// In a multisite environment, only the superadmin can run NinjaScanner:
		'activate_plugins',
		'NinjaScanner',
		'nscan_main_menu'
	);
	// Load contextual help:
	require_once plugin_dir_path( __FILE__ ) . 'lib/help.php';
	add_action( 'load-' . $menuhook, 'nscan_help' );

}
// Must load after NinjaFirewall (10):
if (! is_multisite() )  {
	add_action( 'admin_menu', 'nscan_admin_menu', 11 );
} else {
	add_action( 'network_admin_menu', 'nscan_admin_menu', 11 );
}

// =====================================================================

function nscan_insert_jscss() {

	// Load the external JS script and CSS:
	// -Single site: to the admin only.
	// -Multi-site: to the superadmin and from the main network admin screen only.
	if (! current_user_can('activate_plugins') || ! is_main_site() ) { return; }

	wp_enqueue_script(
		'nscan_javascript',
		plugin_dir_url( __FILE__ ) . 'static/ninjascanner.js',
		array( 'jquery' ),
		NSCAN_VERSION
	);

	// Javascript i18n:
	$nscan_js_array = array(
		'cancel_scan' =>
			__('Cancel the scanning process?', 'ninjascanner'),
		'step' =>
			__('Step', 'ninjascanner' ),
		'wait' =>
			__('Please wait...', 'ninjascanner'),
		'unknown_error' =>
			__('An unknown error occurred:', 'ninjascanner'),
		'http_error' =>
			__('The HTTP server returned the following error:', 'ninjascanner'),
		'http_auth' =>
			__('If your website or <code>/wp-admin/</code> folder is password-protected using HTTP basic authentication, you can enter your username and password in the "Settings > Advanced Settings > Nerds Settings" section.', 'ninjascanner'),
		'no_problem' =>
			__('No problem detected. To refresh the list, run a new scan.', 'ninjascanner'),
		'slow_down_scan_disable' =>
			__('Disabling this option could slow down the scanning process on low resource servers. Continue?', 'ninjascanner'),
		'slow_down_scan_enable' =>
			__('Enabling this option could slow down the scanning process on low resource servers. Continue?', 'ninjascanner'),
		'force_restart_enable' =>
			__('Enable this option only if the scan hangs or does not terminate. Continue?', 'ninjascanner'),
		'restore_settings' =>
			__('All fields will be restored to their default values. Continue?', 'ninjascanner'),
		'cancel_scan' =>
			__('Cancel the scanning process?', 'ninjascanner'),
		'empty_log' =>
			__('No records were found that match the specified search criteria.', 'ninjascanner'),
		'clear_cache_now' =>
			__('Run the garbage collector now to clear all cached files?', 'ninjascanner'),
		'unknown_action' =>
			__('Unknown action.', 'ninjascanner'),
		'select_elements' =>
			__('No file selected.', 'ninjascanner'),
		'permanently_delete' =>
			__('Permanently delete the selected files?', 'ninjascanner'),
		'restore_file' =>
			__('Restore the selected files to their original folder?', 'ninjascanner'),
		'empty_apikey' =>
			__('Please enter your API key.', 'ninjascanner'),
		'success_apikey' =>
			__('Your API key is valid.', 'ninjascanner'),
	);
	wp_localize_script( 'nscan_javascript', 'nscani18n', $nscan_js_array );

	// CSS:
	wp_enqueue_style(
		'nscan_style',
		plugin_dir_url( __FILE__ ) . 'static/ninjascanner.css',
		null,
		NSCAN_VERSION
	);

}

add_action( 'admin_footer', 'nscan_insert_jscss' );

// =====================================================================
// Show the selected tab and page.

function nscan_main_menu() {

	$tab = array ( 'summary', 'settings', 'quarantine',
						'log', 'premium', 'about', 'ignored' );
	// Make sure $_GET['nscantab']'s value is okay,
	// otherwise set it to its default 'summary' value:
	if (! isset( $_GET['nscantab'] ) || ! in_array( $_GET['nscantab'], $tab ) ) {
		$_GET['nscantab'] = 'summary';
	}
	$nscan_menu = "nscan_menu_{$_GET['nscantab']}";
	$nscan_menu();

}

// =====================================================================
// Display (in)active tabs.

function nscan_display_tabs( $which ) {

	$t1 = ''; $t2 = ''; $t3 = ''; $t4 = ''; $t5 = ''; $t6 = ''; $t7 = '';

	if ( $which == 1 ) {
		$t1 = ' nav-tab-active';
	} elseif ( $which == 2 ) {
		$t2 = ' nav-tab-active';
	} elseif ( $which == 3 ) {
		$t3 = ' nav-tab-active';
	} elseif ( $which == 4 ) {
		$t4 = ' nav-tab-active';
	} elseif ( $which == 5 ) {
		$t5 = ' nav-tab-active';
	} elseif ( $which == 6 ) {
		$t6 = ' nav-tab-active';
	} elseif ( $which == 7 ) {
		$t7 = ' nav-tab-active';
	}

	?>
	<h1>NinjaScanner</h1>

	<h2 class="nav-tab-wrapper wp-clearfix">
		<a href="?page=NinjaScanner&nscantab=summary" class="nav-tab<?php
			echo $t1 ?>"><?php _e( 'Summary', 'ninjascanner' ) ?></a>
		<a href="?page=NinjaScanner&nscantab=settings" class="nav-tab<?php
			echo $t2 ?>"><?php _e( 'Settings', 'ninjascanner' ) ?></a>
		<a href="?page=NinjaScanner&nscantab=quarantine" class="nav-tab<?php
			echo $t6 ?>"><?php _e( 'Quarantine', 'ninjascanner' ) ?></a>
		<a href="?page=NinjaScanner&nscantab=ignored" class="nav-tab<?php
			echo $t7 ?>"><?php _e( 'Ignored', 'ninjascanner' ) ?></a>
		<?php

		$nscan_options = get_option( 'nscan_options' );
		// Show debugging log?
		if (! empty( $nscan_options['scan_debug_log'] ) ) {
		?>
			<a href="?page=NinjaScanner&nscantab=log" class="nav-tab<?php
			echo $t3 ?>"><?php _e( 'Log', 'ninjascanner' ) ?></a>
		<?php
		}
		?>
		<a href="?page=NinjaScanner&nscantab=premium" class="nav-tab<?php
			echo $t4 ?>"><?php _e( 'Premium', 'ninjascanner' ) ?></a>

		<a href="?page=NinjaScanner&nscantab=about" class="nav-tab<?php
			echo $t5 ?>"><?php _e( 'About', 'ninjascanner' ) ?></a>

		<div style="text-align:center;font-weight:normal;"><span class="description" style="color:#808080;vertical-align:text-bottom;"><?php _e('Click on the above "Help" tab for help.', 'ninjascanner') ?></span></div>
	</h2>

	<?php
}

// =====================================================================
// Ajax processing: returns the scanning process status.

add_action( 'wp_ajax_nscan_check_status', 'nscan_check_status_ajax' );

function nscan_check_status_ajax() {

	// Allow only the Admin/Superadmin with a valid nonce:
	if (! current_user_can('activate_plugins') ||
		! check_ajax_referer( 'nscan_on_demand_nonce', 'nscan_nonce', false ) ) {

		_e('Error: Security nonces do not match. Reload the page and try again.', 'ninjascanner');
		wp_die();
	}

	$res = explode( '::', nscan_is_scan_running(), 2 );
	if (! isset( $res[1] ) ) {
		$res[1] = '::';
	}

	if ( $res[0] == 0 ) {
		echo 'stopped::';

	} elseif( $res[0] == 1 ) {
		echo "running::{$res[1]}";

	} else {
		echo "error::{$res[1]}";
	}

	wp_die();
}


// =====================================================================
// Load AJAX code depending on the requested page and the scan status.

if ( empty( $_GET['page'] ) || $_GET['page'] != 'NinjaScanner' ) {
	add_action( 'admin_footer', 'nscan_status_ajax_all' );
}

function nscan_status_ajax_all() {

	if (! current_user_can('activate_plugins') || ! is_main_site() ) { return; }

	require_once 'lib/ajax_all.php';
}

// =====================================================================
// Summary/report page.

function nscan_menu_summary() {

	echo '<div class="wrap">';
	require_once __DIR__ . '/lib/tab_summary.php';
	echo '</div>';
}

// =====================================================================
// Settings page.

function nscan_menu_settings() {

	echo '<div class="wrap">';
	require_once __DIR__ . '/lib/tab_settings.php';
	echo '</div>';
}

// =====================================================================
// Quarantined files.

function nscan_menu_quarantine() {

	echo '<div class="wrap">';
	require_once __DIR__ . '/lib/tab_quarantine.php';
	echo '</div>';
}

// =====================================================================
// Scanner's debugging log page.

function nscan_menu_log() {

	echo '<div class="wrap">';
	require_once __DIR__ . '/lib/tab_log.php';
	echo '</div>';
}

// =====================================================================
// Ignored list.

function nscan_menu_ignored() {

	echo '<div class="wrap">';
	require_once __DIR__ . '/lib/tab_ignored.php';
	echo '</div>';
}

// =====================================================================

function nscan_menu_premium() {

	echo '<div class="wrap">';
	require_once __DIR__ . '/lib/tab_premium.php';
	echo '</div>';
}

// =====================================================================
// Copyright/about page.

function nscan_menu_about() {

	echo '<div class="wrap">';
	require_once __DIR__ . '/lib/tab_about.php';
	echo '</div>';

}

// =====================================================================
// Get the blog timezone.

function nscan_get_blogtimezone() {

	$tzstring = get_option( 'timezone_string' );
	if (! $tzstring ) {
		$tzstring = ini_get( 'date.timezone' );
		if (! $tzstring ) {
			$tzstring = 'UTC';
		}
	}
	date_default_timezone_set( $tzstring );
}

// =====================================================================
// Check if a scanning process is running (0 == stopped, 1 == running,
// 2 == error).

function nscan_is_scan_running() {

	if (! file_exists( NSCAN_LOCKFILE ) ) {
		return '0::::';
	}

	// Look for a potential error:
	if ( file_exists( NSCAN_SCANDIR .'/err.lock' ) ) {
		// Make sure it was not created more than
		// NSCAN_ERROR_CANCEL seconds ago (default 20s):
		$ctime = filectime( NSCAN_SCANDIR .'/err.lock' );
		if ( time() - $ctime > NSCAN_ERROR_CANCEL ) {
			// Check if we have an error message:
			if ( filesize( NSCAN_SCANDIR .'/err.lock' ) > 0 ) {
				rename( NSCAN_SCANDIR .'/err.lock', NSCAN_CACHEDIR . '/error.txt' );
				$err_msg = file_get_contents( NSCAN_CACHEDIR . '/error.txt' );
			} else {
				$err_msg = __('Fatal error, aborting. More information may be available in the scanner Log.', 'ninjascanner');
				file_put_contents( NSCAN_CACHEDIR . '/error.txt', $err_msg );
			}
			nscan_cleanup( 'cancel' );

			// Warn the user by email:
			$nscan_options = get_option( 'nscan_options' );
			if (! empty( $nscan_options['admin_email'] ) ) {
				if ( is_multisite() ) {
					$blog = network_home_url('/');
				} else {
					$blog = home_url('/');
				}
				$subject = sprintf(
					__('[NinjaScanner] SCAN ERROR (%s)', 'ninjascanner'),
					$blog
				);
				$message = __('Hi,', 'ninjascanner') ."\n\n";
				$message .= __('This is the NinjaScanner scan report.', 'ninjascanner') .' ';
				$message .= sprintf(
					__('A fatal error occurred while attempting to generate the report: "%s"', 'ninjascanner'),
					$err_msg
				);
				$message .= "\n\n". __('More details may be available in the scanner log.', 'ninjascanner' ) ."\n";
				$signature = "\nNinjaScanner - https://nintechnet.com/\n" .
					__('Help Desk (Premium customers only):', 'ninjascanner') . " https://secure.nintechnet.com/login/\n";
				wp_mail( $nscan_options['admin_email'], $subject, $message . $signature );
			}
			// Quit:
			return "2::0::{$err_msg}";
		}
	}

	// It's still up and running:
	return '1::'. trim( file_get_contents( NSCAN_LOCKFILE ) );
}

// =====================================================================
// Run scheduled scan (WP-Cron).

function nscan_sched_cron() {

	// Make sure no scan is running:
	$res = nscan_is_scan_running();
	if ( $res[0] == 1 ) {
		// Append line, we don't want to clear the log:
		$message = __('A scanning process is running. Please wait or stop it.',	'ninjascanner' );
		file_put_contents( NSCAN_DEBUGLOG, time() ."~~4~~$message\n", FILE_APPEND );
		exit;
	}

	if ( nscan_is_valid() < 1 ) {
		require( __DIR__ . '/lib/install.php' );
		nscan_default_sc(0);
		$nscan_options['scan_scheduled'] = 0;
		update_option( 'nscan_options', $nscan_options );
		exit;
	}

	// Createlog file:
	$message = __('Scheduling a scanning process', 'ninjascanner');
	file_put_contents( NSCAN_DEBUGLOG, time() ."~~1~~$message\n" );

	// Start scan:
	nscan_malware_scan();
}

add_action( 'nscan_scheduled_scan', 'nscan_sched_cron' );

// =====================================================================
// Request a scanning process (AJAX call from the Summary page).

add_action( 'wp_ajax_nscan_on_demand', 'nscan_on_demand' );

function nscan_on_demand() {

	// Allow only the Admin/Superadmin with a valid nonce:
	if (! current_user_can('activate_plugins') ||
		! check_ajax_referer( 'nscan_on_demand_nonce', 'nscan_nonce', false ) ) {

		_e('Error: Security nonces do not match. Reload the page and try again.', 'ninjascanner');
		wp_die();
	}

	$res = nscan_fork_process();
	echo $res;
	wp_die();
}

// =====================================================================
// Cancel a scanning process (AJAX call from the Summary page).

add_action( 'wp_ajax_nscan_cancel', 'nscan_cancel_ajax' );

function nscan_cancel_ajax() {

	// Allow only the Admin/Superadmin with a valid nonce:
	if (! current_user_can('activate_plugins') ||
		! check_ajax_referer( 'nscan_on_demand_nonce', 'nscan_nonce', false ) ) {

		_e('Error: Security nonces do not match. Reload the page and try again.', 'ninjascanner');
		wp_die();
	}

	nscan_cancel_scan();
	echo 200;
	wp_die();
}

// =====================================================================
// Schedule a scanning process (immediately).

function nscan_fork_process( $step = 1, $retry = 0 ) {

	$nscan_options = get_option( 'nscan_options' );

	// We make a quick check to ensure the site doesn't throw an HTTP error
	// because the fork uses non-blocking socket and won't be able to detect it:
	if ( $step == 1 ) {
		$url = home_url( '/' );
		$headers = array();
		if (! empty( $nscan_options['username'] ) && ! empty( $nscan_options['password'] ) ) {
			$headers['Authorization'] = 'Basic '. base64_encode( $nscan_options['username'] .':'. $nscan_options['password'] );
		}
		$res = wp_remote_get( $url, compact( 'headers' ) );
		if (! is_wp_error( $res ) ) {
			if ( $res['response']['code'] >= 400 ) {
				$msg = sprintf( __('Error: The website front-end returned: HTTP %s %s.', 'ninjascanner'),
					(int) $res['response']['code'],
					htmlspecialchars( $res['response']['message'] )
				);
				nscan_log_error( $msg, false );
				nscan_cancel_scan();
				return $msg;
			}
		}
	}

	// Fork method: WP-CRON or admin-ajax.php?
	if (! empty( $nscan_options['scan_fork_method'] ) && $nscan_options['scan_fork_method'] == 2 ) {
		// admin-ajax.php:
		$fork = 1;

		// Note: URL is the same for multisite and single installation:
		$url = admin_url( 'admin-ajax.php?action=ninjascanner_fork' );

		// Create AJAX key with a 60-second validity:
		$nscan_key = nscan_generate_key();
		set_transient( 'nscan_ajax_start', $nscan_key, 60 );

		$request = array(
			'timeout'   => 0.01,
			'blocking'  => false,
			'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
			'body' => array(
				'nscan_key' => $nscan_key,
				'step'		=> $step,
				'retry'		=> $retry
			),
		);

		if (! empty( $nscan_options['username'] ) && ! empty( $nscan_options['password'] ) ) {
			$request['headers'] = array(
				'Authorization' => 'Basic '. base64_encode( $nscan_options['username'] .':'. $nscan_options['password'] )
			);
		}

		$request['headers']['Accept-Language'] = 'en-US,en;q=0.5';
		$request['headers']['User-Agent'] = 'Mozilla/5.0 (X11; Linux x86_64; rv:60.0)';

		$res = wp_remote_post( $url, $request );

		if ( is_wp_error( $res ) ) {
			nscan_log_error( sprintf(
				__('Fatal error: scheduled task %s failed (%s). Aborting', 'ninjascanner'),
				$fork,
				$res->get_error_message()
			));
			nscan_cancel_scan();
			return 10;
		}

	} else {
		// WP-CRON:
		if ( defined('DISABLE_WP_CRON') && DISABLE_WP_CRON ) {

			$fork = 2;
			wp_schedule_single_event( time() - 1, 'nscanmalwarescan', array( $step, $retry ) );
			$doing_wp_cron = sprintf( '%.22F', microtime( true ) );
			set_transient( 'doing_cron', $doing_wp_cron );

			$cron_request = apply_filters( 'cron_request', array(
				'url'  => add_query_arg( 'doing_wp_cron', $doing_wp_cron, site_url( 'wp-cron.php' )	),
				'key'  => $doing_wp_cron,
				'args' => array(
					'timeout'   => 0.01,
					'blocking'  => false,
					'sslverify' => apply_filters( 'https_local_ssl_verify', false )
				)
			), $doing_wp_cron );

			// POST the request:
			$res = wp_remote_post( $cron_request['url'], $cron_request['args'] );

			if ( is_wp_error( $res ) ) {
				nscan_log_error( sprintf(
					__('Fatal error: scheduled task %s failed (%s). Aborting', 'ninjascanner'),
					$fork,
					$res->get_error_message()
				));
				nscan_cancel_scan();
				return 11;
			}

		} else {
			$fork = 3;

			delete_transient( 'doing_cron' );
			wp_schedule_single_event( time() - 1, 'nscanmalwarescan', array( $step, $retry )	);
			// POST the request:
			spawn_cron();
		}

	}

	if ( defined('NSCAN_SPAWNCRON_USLEEP') ) {
		usleep( NSCAN_SPAWNCRON_USLEEP );
	}
	// Because we use a non-blocking socket, we cannot get the HTTP response code
	// or even know if there was an error. Therefore, we check if the scanner
	// transient was set and return an error if it wasn't:
	if ( get_transient( 'nscan_start' ) === false ) {
		$msg = sprintf(
			__('Fatal error: scheduled task failed (#%s). More details may be available in your server HTTP log. Aborting', 'ninjascanner'),
			$fork
		);
		nscan_log_error( $msg , false );
		nscan_cancel_scan();
		return 12;
	}

	delete_transient( 'nscan_start' );

	// Success:
	return 200;

}

// =====================================================================

function nscan_generate_key() {

	$key = '';
	for ( $i = 0; $i < 40; ++$i ) {
		$key .= chr( mt_rand( 33, 126 ) );
	}
	return hash( 'sha256', $key );

}

// =====================================================================
// Alternate fork method via admin-ajax.

function nscan_ajaxfork() {

	$nscan_options = get_option( 'nscan_options' );

	// Make sure we have a valid temp key to access this public AJAX call:
	if ( empty( $_POST['nscan_key'] ) ||
		$nscan_options['scan_fork_method'] != 2 ||
		( $nscan_key = get_transient( 'nscan_ajax_start' ) ) === false ||
		$nscan_key !== $_POST['nscan_key']
	) {

		delete_transient( 'nscan_ajax_start' );
		wp_die(0);
	}

	if ( isset( $_POST['step'] ) ) {
		$step = (int) $_POST['step'];
	} else {
		$step = 1;
	}
	if ( isset( $_POST['retry'] ) ) {
		$retry = (int) $_POST['retry'];
	} else {
		$retry = 0;
	}

	delete_transient( 'nscan_ajax_start' );
	nscan_malware_scan( $step, $retry );
	wp_die(0);
}

add_action('wp_ajax_nopriv_ninjascanner_fork', 'nscan_ajaxfork');

// =====================================================================
// Called from WP Cron to start (or restart) a scanning process.

add_action('nscanmalwarescan', 'nscan_malware_scan', 10, 2);

function nscan_malware_scan( $step = 1 , $retry = 0 ) {

	// First run? Clean-up any potential temp files left:
	if ( $step == 1 && $retry == 0 ) {
		nscan_cleanup( 'start' );

	}

	file_put_contents( NSCAN_LOCKFILE, "$step::". __('Initializing', 'ninjascanner' ) );

	// Remove the error lock file:
	if ( file_exists( NSCAN_SCANDIR .'/err.lock' ) ) {
		unlink( NSCAN_SCANDIR .'/err.lock' );
	}

	// Used by AJAX and the cron:
	set_transient( 'nscan_start', 1, 0 );

	// Make sure to report errors:
	@error_reporting( E_ALL );
	// But don't print them to the screen:
	ini_set('display_errors', 0);

	$nscan_options = get_option( 'nscan_options' );

	if (! empty( $nscan_options['scan_incremental_forced'] ) ) {
		// NSCAN_MAX_EXEC_TIME can be defined in the wp-config.php:
		if (! defined( 'NSCAN_MAX_EXEC_TIME' ) ) {
			$met = (int) ini_get('max_execution_time');
			if ( $met > 5 ) {
				define( 'NSCAN_MAX_EXEC_TIME', time() + $met - 5 );
			} else {
				define( 'NSCAN_MAX_EXEC_TIME', time() + 50 );
			}
		}
		// Unlikely to help, but who knows:
		ini_set('max_execution_time', 0);
		// Start the scanning process:
		require_once __DIR__ . '/lib/scan.php';
		nscan_shutdown();

	} else {
		ini_set('max_execution_time', 0);
		register_shutdown_function('nscan_shutdown');
		// Start the scanning process:
		require_once __DIR__ . '/lib/scan.php';
	}

	exit;
}

// =====================================================================

function nscan_shutdown() {

	// Check if an error occurred:
	$e = error_get_last();
	if ( isset( $e['type'] ) && $e['type'] === E_ERROR ) {
		nscan_log_error( sprintf(
			__('Error: E_ERROR (%s - %s)', 'ninjascanner'),
			$e['message'],
			$e['line']
		));
		file_put_contents( NSCAN_SCANDIR .'/err.lock', $e['message'] );
	}

	global $snapshot;

	// Cancelling a scan:
	if ( file_exists( NSCAN_CANCEL ) ) {
		unlink( NSCAN_CANCEL );

		// Is it an error or did the user click cancel?
		if (! empty( $snapshot['error'] ) ) {
			// Fatal error. Shall we warn the user by email?
			$nscan_options = get_option( 'nscan_options' );
			if (! empty( $nscan_options['admin_email'] ) ) {
				nscan_send_email_report( $snapshot, $nscan_options );
			}
			// Save error message to a temporary file as well:
			file_put_contents( NSCAN_CACHEDIR . '/error.txt', $snapshot['error'] );
		}

		// Quit:
		nscan_cleanup( 'cancel' );
		exit;
	}

	// Save the state of the current snapshot to disk:
	if (! empty( $snapshot ) ) {
		file_put_contents( NSCAN_SNAPSHOT, serialize( $snapshot ) );
	}

	// No error, clean exit:
	if ( $e['type'] !== E_ERROR && ! defined('NSCAN_RESTART') ) {

		// Shall we send the report by email?
		$nscan_options = get_option( 'nscan_options' );
		if (! empty( $nscan_options['admin_email'] ) ) {
			nscan_send_email_report( $snapshot, $nscan_options );
		}

		// Remove temporary error file:
		if ( file_exists( NSCAN_CACHEDIR . '/error.txt' ) ) {
			unlink( NSCAN_CACHEDIR . '/error.txt' );
		}

		// Save some benchmarks:
		$time = round( microtime(true) - $snapshot['sys']['time_start'], 2);
		$used = memory_get_peak_usage( false );
		nscan_log_debug( sprintf(
			__('Total time: %s seconds. Max memory allocated by system: %s bytes. Max memory used by WordPress: %s bytes (NinjaScanner: %s bytes).', 'ninjascanner'),
			$time,
			number_format_i18n( memory_get_peak_usage( true ) ),
			number_format_i18n( $used ),
			number_format_i18n( $used - $snapshot['sys']['max_mem_used'] )
		) );

		nscan_log_info( __('Exiting scanning process', 'ninjascanner') );
		nscan_cleanup( 'exit' );
		exit;
	}

	// Check if a scan was running (timeout)?
	if ( file_exists( NSCAN_LOCKFILE ) ) {

		// Make sure we didn't reach NSCAN_MAX_RETRIES_LOG:
		$retry = 0;
		if ( file_exists( NSCAN_MAX_RETRIES_LOG ) ) {
			$retry = filesize( NSCAN_MAX_RETRIES_LOG );
			if ( $retry >= NSCAN_MAX_RETRIES ) {
				// Give up:
				nscan_log_error( __('Fatal error: Number of retries reached NSCAN_MAX_RETRIES. Consider increasing its value or adjusting NinjaScanner configuration. Aborting', 'ninjascanner') );
				nscan_cleanup( 'cancel' );
				exit;
			}
		}

		// Fork another scan:
		$nscan_options = get_option( 'nscan_options' );
		file_put_contents( NSCAN_MAX_RETRIES_LOG, ' ', FILE_APPEND );
		++$retry;

		// Shall we restart where it left off or start over?
		if (! empty( $nscan_options['scan_incremental'] ) ) {
			$tmp = explode( '::', trim( file_get_contents( NSCAN_LOCKFILE ) ) );
			$step = (int) $tmp[0];

			nscan_log_debug( sprintf(
				__('Incremental scan is enabled, restarting from step %s', 'ninjascanner' ),
				$step
			));
		} else {
			$step = 1;
			nscan_log_debug( __('Incremental scan is DISABLED, starting over from step 1', 'ninjascanner' ) );
		}

		// Save anti-malware state if there was an error
		// while it was running ($step == 9):
		global $current_snapshot;
		if ( $step == 9 && ! empty( $current_snapshot ) ) {
			file_put_contents( NSCAN_MALWARE_LOG, serialize( $current_snapshot ) );
			nscan_log_debug( sprintf(
				__('Saving current anti-malware buffer to temporary file', 'ninjascanner' ),
				$step
			));
		}

		// Schedule it:
		nscan_fork_process( $step, $retry );

		exit;
	}
}

// =====================================================================
// Cancel a running scan.

function nscan_cancel_scan() {

	nscan_log_info( __('Cancelling scanning process', 'ninjascanner' ), false );

	touch( NSCAN_CANCEL );

	if ( file_exists( NSCAN_LOCKFILE ) ) {
		if ( unlink( NSCAN_LOCKFILE ) === false ) {
			return 2;
		} else {
			return;
		}
	}
	return 1;

}

// =====================================================================
// Send scan report by email.

function nscan_send_email_report( $snapshot, $nscan_options ) {

	// Populate plain text report:
	require_once __DIR__ . '/lib/report_text.php';
	$report = text_report( $snapshot );

	$signature = "\nNinjaScanner - https://nintechnet.com/\n" .
					__('Help Desk (Premium customers only):', 'ninjascanner') . " https://secure.nintechnet.com/login/\n";

	if (! empty( $report['error'] ) ) {
		// Scan failed, inform the user:
		$subject = sprintf(
			__('[NinjaScanner] SCAN ERROR (%s)', 'ninjascanner'),
			$report['blog']
		);
		$message = __('Hi,', 'ninjascanner') ."\n\n";
		$message .= __('This is the NinjaScanner scan report.', 'ninjascanner') .' ';
		$message .= sprintf(
			__('A fatal error occurred while attempting to generate the report: "%s"', 'ninjascanner'),
			$report['error']
		);
		$message .= "\n\n". __('More details may be available in the scanner log.', 'ninjascanner' ) ."\n";
		wp_mail( $nscan_options['admin_email'], $subject, $message . $signature );

	} else {

		if ( empty( $nscan_options['admin_email_report'] ) ||
			( $nscan_options['admin_email_report'] == 1 && (! empty( $report['critical'] ) || ! empty( $report['important'] ) ) ) ||
			( $nscan_options['admin_email_report'] == 2 && ! empty( $report['critical'] ) )
		) {
			nscan_log_debug( __('Sending email report', 'ninjascanner') );

			$attachment = '';
			$subject = sprintf(
				__('[NinjaScanner] Scan report for %s', 'ninjascanner'),
				$report['blog']
			);
			$message = __('Hi,', 'ninjascanner') ."\n\n";
			$message .= __('This is the NinjaScanner scan report.', 'ninjascanner') .' ';

			// If the report is too big (>10,000 bytes), we save it to a file
			// and attached it, otherwise we send it inline:
			if ( strlen( $report['body'] ) > 10000 ) {
				$message .= __('Due to the large number of lines, it was attached '.
						'to this email for your convenience.', 'ninjascanner' ) ."\n";
				$attachment = NSCAN_CACHEDIR. '/ninjascanner_report.txt';
				file_put_contents( $attachment, $report['body'] );
			}
			$message .= __('A more detailed report can be viewed from your WordPress '.
				'Dashboard by clicking on "NinjaScanner > Summary > View Scan Report".', 'ninjascanner' ) .
				"\n";
			if ( $attachment ) {
				wp_mail( $nscan_options['admin_email'], $subject, $message . $signature, '', $attachment );
				// Clear attachment file:
				unlink( $attachment );
			} else {
				// Inline:
				wp_mail( $nscan_options['admin_email'], $subject, $message . $report['body'] . $signature, '' );
			}
		}
	}

}

// =====================================================================
// Check if a file content is json_encoded (old format) or serialized.

function nscan_is_json_encoded( $file ) {

	$chars = file_get_contents( $file, false, null, 0, 2 );
	if ( $chars == 'a:' ) {
		return false;
	}
	return true;

}

// =====================================================================

function nscan_is_valid() {

	$nscan_options = get_option( 'nscan_options' );
	nscan_get_blogtimezone();
	if (! empty( $nscan_options['exp'] ) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $nscan_options['exp'] ) ) {
		if ( $nscan_options['exp'] < date( 'Y-m-d', strtotime( '-1 day' ) ) ) {
			return -1;
		} elseif ( $nscan_options['exp'] < date( 'Y-m-d', strtotime( '+30 day' ) ) ) {
			return 30;
		}
		return 1;
	}
	return 0;
}

// =====================================================================

function nscan_check_license( $nscan_options, $key = '' ) {

	if ( is_multisite() ) {
		$site_url = rtrim( strtolower( network_site_url('','http') ), '/' );
	} else {
		$site_url = rtrim( strtolower(site_url('','http') ), '/' );
	}

	global $wp_version;
	$opt_update = 0;
	$res = array();

	if ( empty( $key ) && ! empty( $nscan_options['key'] ) ) {
		$key = $nscan_options['key'];
	}

	if ( empty( $key ) ) {
		$res['nscan_err'] = __('Error: You do not have a Premium license.', 'ninjascanner');
		return $res;
	}

	$request_string = array(
		'body' => array(
			'action' => 'check_license',
			'key'	=>	$key,
			'cache_id' => sha1( home_url() ),
			'host' => @strtolower( $_SERVER['HTTP_HOST'] )
		),
		'user-agent' => 'Mozilla/5.0 (compatible; NinjaScanner/'. NSCAN_VERSION ."; WordPress/{$wp_version})",
		'timeout' => NSCAN_CURL_TIMEOUT,
		'httpversion' => '1.1' ,
		'sslverify' => true
	);
	// POST the request:
	$res = wp_remote_post( NSCAN_SIGNATURES_URL, $request_string );

	if (! is_wp_error($res) ) {

	if ( $res['response']['code'] == 200 ) {

			// Fetch the array:
			$data = json_decode( $res['body'], true );
			// Verify its content:
			if ( empty( $data['checked'] ) ) {
				$res['nscan_err'] = __('An unknown error occurred while connecting to NinjaScanner API server. Please try again in a few minutes.', 'ninjascanner');
				return $res;
			}
			if (! empty( $data['exp'] ) ) {
				$nscan_options['exp'] = $data['exp'];
				$res['nscan_exp'] = $data['exp'];
				update_option( 'nscan_options', $nscan_options );
			}

			if (! empty( $data['err'] ) ) {
				$res['nscan_err'] = sprintf(
					__('Error: Your license is not valid (#%s).', 'ninjascanner'),
					(int)$data['err']
				);
				return $res;
			}

			$res['nscan_msg'] = __('You have a valid license', 'ninjascanner');
			return $res;

		} else {
			// HTTP error:
			$res['nscan_err'] = sprintf(
				__('HTTP Error (%s): Cannot connect to the API server. Try again later', 'ninjascanner'),
				(int)$res['response']['code']
			);
			return $res;
		}
	} else {
		// Unknown error:
		$res['nscan_err']  = sprintf(
			__('Error (%s): Cannot connect to the API server. Try again later', 'ninjascanner'),
			htmlspecialchars( $res->get_error_message() )
		);
		return $res;
	}
}

// =====================================================================

function nscan_save_license( $nscan_options ) {

	$res = array();
	$key = trim( $_POST['key'] );
	$res = nscan_check_license( $nscan_options, $key );
	if ( empty( $res['nscan_err'] ) ) {
		$nscan_options['key'] = $key;
		$nscan_options['exp'] = $res['nscan_exp'];
		update_option( 'nscan_options', $nscan_options );
		$res['nscan_msg'] = __('Your license has been accepted and saved.', 'ninjascanner');
	}
	return $res;

}

// =====================================================================
// When exiting or starting a scan, remove or rename some temporary
// files.

function nscan_cleanup( $what = '' ) {

	// Delete temporary files:
	if ( file_exists( NSCAN_LOCKFILE ) ) {
		unlink( NSCAN_LOCKFILE );
	}
	if ( file_exists( NSCAN_MAX_RETRIES_LOG ) ) {
		unlink( NSCAN_MAX_RETRIES_LOG );
	}
	if ( file_exists( NSCAN_MALWARE_LOG ) ) {
		unlink( NSCAN_MALWARE_LOG );
	}
	if ( file_exists( NSCAN_SCANDIR .'/err.lock' ) ) {
		unlink( NSCAN_SCANDIR .'/err.lock' );
	}
	// Starting a scan:
	if ( $what == 'start' ) {
		// If there is a valid snapshot, back it up before
		// starting a new scan:
		if ( file_exists( NSCAN_SNAPSHOT ) ) {
			rename( NSCAN_SNAPSHOT, NSCAN_OLD_SNAPSHOT );
		}

		if ( file_exists( NSCAN_DEBUGLOG ) ) {
			unlink( NSCAN_DEBUGLOG );
		}

		return;
	}

	// Cancelling a scan:
	if ( $what == 'cancel' ) {
		// If there was an older snapshot file, we restore it.
		// (Note: mtime must be used to retrieve the file last modified
		// date, because ctime will be changed when renaming it).
		if ( file_exists( NSCAN_OLD_SNAPSHOT ) ) {
			if ( file_exists( NSCAN_SNAPSHOT ) ) {
				unlink( NSCAN_SNAPSHOT );
			}
			rename( NSCAN_OLD_SNAPSHOT, NSCAN_SNAPSHOT );
		}
		return;
	}
}

// =====================================================================
// Write message to the log. Log level can be a combination of INFO (1),
// WARN (2), ERROR (4) and DEBUG (8) and can be adjusted while viewing
// the log. Check also if the scanning process was cancelled (missing
// lock file) and exit.

function nscan_log( $string, $level = 1, $exit = true ) {

	$res = nscan_is_scan_running();
	if ( $exit == true && $res[0] != 1 ) {
		$string = __('Could not get lock, exiting','ninjascanner');
		file_put_contents( NSCAN_DEBUGLOG, time() . "~~8~~$string\n", FILE_APPEND );
		exit;
	}
	file_put_contents( NSCAN_DEBUGLOG, time() . "~~$level~~$string\n", FILE_APPEND );
}

function nscan_log_info(  $string, $exit = true ) { nscan_log( $string, 1, $exit ); }
function nscan_log_warn(  $string, $exit = true ) { nscan_log( $string, 2, $exit ); }
function nscan_log_error( $string, $exit = true ) { nscan_log( $string, 4, $exit ); }
function nscan_log_debug( $string, $exit = true ) { nscan_log( $string, 8, $exit ); }

// =====================================================================
// EOF
