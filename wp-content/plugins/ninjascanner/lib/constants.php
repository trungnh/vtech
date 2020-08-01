<?php
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
 +=====================================================================+ // sa+i18n
*/
if (! defined( 'ABSPATH' ) ) { die( 'Forbidden' ); }

// =====================================================================

const NS_TOTAL_STEPS = 10;

// =====================================================================
// URLs

// Signatures/checksums download URL:
define( 'NSCAN_SIGNATURES_URL', 'https://ninjascanner.nintechnet.com/index.php' );

// WP plugins/themes URL:
define( 'NSCAN_PLUGINS_URL', 'https://downloads.wordpress.org/plugin/' );
define( 'NSCAN_THEMES_URL', 'https://downloads.wordpress.org/theme/' );

// WP SVN:
define( 'NSCAN_SVN_CORE', 'https://core.svn.wordpress.org/tags/%s' );
define( 'NSCAN_SVN_PLUGINS', 'https://plugins.svn.wordpress.org/%s/tags/%s' );
define( 'NSCAN_SVN_THEMES', 'https://themes.svn.wordpress.org/%s/%s' );

define( 'NSCAN_GSB', 'https://safebrowsing.googleapis.com/v4/threatMatches:find' );
// =====================================================================
// Links pointing to misc. documentation mentioned in the settings page:

define( 'NSCAN_LINK_ADD_SIGS',
	'https://blog.nintechnet.com/ninjascanner-powerful-antivirus-scanner-for-wordpress/#signatures' );
define( 'NSCAN_LINK_INTEGRITY_CHECK',
	'https://blog.nintechnet.com/ninjascanner-powerful-antivirus-scanner-for-wordpress/#integrity' );

// =====================================================================
// Paths:

define( 'NSCAN_ROOTDIR', WP_CONTENT_DIR .'/ninjascanner' );

// Users can upload their own ZIP files (premium themes
// or plugins) into this folder:
if (! defined( 'NSCAN_LOCAL' ) ) {
	define( 'NSCAN_LOCAL', NSCAN_ROOTDIR .'/local' );
}

// Find (or create) the cache folder:
$glob = array();
$glob = glob( NSCAN_ROOTDIR .'/nscan*' );
if ( is_array( $glob ) ) {
	foreach( $glob as $file ) {
		// Must be a folder:
		if (! is_dir( "{$file}/cache" ) ) { continue; }
		// We found it:
		define( 'NSCAN_SCANDIR', $file );
		break;
	}
}
if (! defined( 'NSCAN_SCANDIR' ) ) {
	// Create it
	require_once __DIR__ .'/install.php';
	$uniqid = uniqid( 'nscan', true);
	nscan_cache_folder( $uniqid );
	define( 'NSCAN_SCANDIR', NSCAN_ROOTDIR  . "/{$uniqid}" );
}

define( 'NSCAN_CACHEDIR', NSCAN_SCANDIR  . '/cache' );
define( 'NSCAN_QUARANTINE', NSCAN_SCANDIR  . '/quarantine' );

// =====================================================================
// Files:

define( 'NSCAN_LOCKFILE', NSCAN_SCANDIR .'/nscan.lock' );
define( 'NSCAN_CANCEL', NSCAN_SCANDIR .'/cancel.lock' );
define( 'NSCAN_MAX_RETRIES_LOG', NSCAN_SCANDIR .'/retry.log' );
define( 'NSCAN_MALWARE_LOG', NSCAN_CACHEDIR .'/malware.log' );
define( 'NSCAN_SIGNATURES', NSCAN_CACHEDIR .'/signatures.txt' );

define( 'NSCAN_DEBUGLOG', NSCAN_SCANDIR .'/debug.log' );
define( 'NSCAN_LASTSCANLOG', NSCAN_SCANDIR .'/last.log' );
define( 'NSCAN_HASHFILE', NSCAN_SCANDIR . '/nscan.' . NSCAN_VERSION );
define( 'NSCAN_WPCORE_HASHFILE', NSCAN_CACHEDIR . '/wpcore.%s' );
define( 'NSCAN_SNAPSHOT', NSCAN_CACHEDIR .'/snapshot.log' );
define( 'NSCAN_OLD_SNAPSHOT', NSCAN_CACHEDIR .'/snapshot.old' );
define( 'NSCAN_IGNORED_LOG', NSCAN_CACHEDIR .'/ignored.log' );

// =====================================================================
// Misc options that can be user-defined in the wp-config.php:

// Max retries attempts in case of failure:
if (! defined( 'NSCAN_MAX_RETRIES' ) ) {
	define( 'NSCAN_MAX_RETRIES', 10 );
}
// AJAX interval (milliseconds):
if (! defined( 'NSCAN_MILLISECONDS' ) ) {
	define( 'NSCAN_MILLISECONDS', 1500 ); // 1.5s
}
// Time to wait before cancelling a scan:
if (! defined( 'NSCAN_ERROR_CANCEL' ) ) {
	define( 'NSCAN_ERROR_CANCEL', 20 ); // 20s
}
// cURL timeout (seconds):
if (! defined( 'NSCAN_CURL_TIMEOUT' ) ) {
	define( 'NSCAN_CURL_TIMEOUT', 120 ); // 120s
}
// Sleep time (microsecond) after spawning cron:
if (! defined( 'NSCAN_SPAWNCRON_USLEEP' ) ) {
	define( 'NSCAN_SPAWNCRON_USLEEP', 2000000 ); // 2s
}
// =====================================================================
// EOF
