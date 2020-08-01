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
 +=====================================================================+ sa+i18n
*/

if (! defined( 'ABSPATH' ) ) { die( 'Forbidden' ); }

$nscan_options = get_option( 'nscan_options' );

global $snapshot;
$snapshot = array();
global $current_snapshot;
$current_snapshot = array();

// Ignored files list:
global $ignored_files;
$ignored_files = array();
if ( file_exists( NSCAN_IGNORED_LOG ) ) {
	$ignored_files = unserialize( file_get_contents( NSCAN_IGNORED_LOG ) );
}

// Save WP version:
global $wp_version;
$snapshot['version'] = $wp_version;
// Save locale:
global $wp_local_package;
if (! empty( $wp_local_package ) ) {
	$snapshot['locale'] = $wp_local_package;
}
// Save NinjaScanner's version:
$snapshot['nscan_version'] = NSCAN_VERSION;

$snapshot['sys']['time_start'] = microtime(true);
$snapshot['sys']['max_mem_used'] = memory_get_peak_usage( false );

if ( $retry ) {
	nscan_log_info( __('Restarting scanning process', 'ninjascanner') );
} else {
	nscan_log_info( __('Starting scanning process', 'ninjascanner') );
	// Write some system info to the log:
	if ( ( $tmp = ini_get('memory_limit') ) !== false ) {
		nscan_log_debug( sprintf( __('Server memory_limit: %s', 'ninjascanner'), $tmp ) );
	}
	if ( ( $tmp = ini_get('max_execution_time') ) !== false ) {
		nscan_log_debug( sprintf( __('Server max_execution_time: %s', 'ninjascanner'), $tmp ) );
	}
}

// =====================================================================
// Step 1: Verify NinjaScanner's files integrity before starting a scan.
// We give up if one or more files have been changed. User can still
// bypass this by disabling the integrity checker from the "Settings" page.
if ( $step < 2 ) {

	$step_msg = __('Checking NinjaScanner files integrity', 'ninjascanner');
	file_put_contents( NSCAN_LOCKFILE, "$step::$step_msg" );


	nscan_log_info( sprintf(
		__('Processing step %s (%s/%s)', 'ninjascanner'), $step, $retry, NSCAN_MAX_RETRIES
	));
	if ( $nscan_options['scan_ninjaintegrity'] ) {
		nscan_log_info( $step_msg );

		if ( ( $res = nscan_check_ninjascanner_integrity() ) === true ) {
			nscan_log_info( __('Files integrity is O.K', 'ninjascanner') );
		} else {
			if ( $res === false ) {
				// Failed! We warn and quit:
				touch( NSCAN_CANCEL );
				exit;
			} else {
				// The server may be down. Clear the 'scan_ninjaintegrity' flag,
				// we'll attempt to check the plugin again while checking all
				// plugin files integrity:
				$nscan_options['scan_ninjaintegrity'] = 0;
				nscan_log_debug( __("Clearing 'scan_ninjaintegrity', we'll check the plugin again later", 'ninjascanner') );
			}
		}
	} else {
		nscan_log_info( __('Skipping NinjaScanner files integrity check', 'ninjascanner') );
	}
	++$step;
	nscan_log_debug( sprintf(
		__('Process used %s bytes out of %s bytes of allocated memory.', 'ninjascanner' ),
		number_format_i18n( memory_get_peak_usage( false ) ),
		number_format_i18n( memory_get_peak_usage( true ) )
	) );
	nscan_check_max_exec_time( $nscan_options );
} else {
	nscan_log_info( sprintf( __('Ignoring step %s (already done)', 'ninjascanner'), 1 ) );
}

// =====================================================================
// Step 2: Build the list of files:

// Make sur we have a snapshot of the blog files before skipping step 2:
if ( $step > 2 ) {

	$step_msg = __('Building files list', 'ninjascanner');
	file_put_contents( NSCAN_LOCKFILE, "$step::$step_msg" );


	if ( empty( $snapshot['abspath'] ) ) {
		if ( file_exists( NSCAN_SNAPSHOT ) ) {
			if ( nscan_is_json_encoded( NSCAN_SNAPSHOT ) === true ) {
				$snapshot = json_decode( file_get_contents( NSCAN_SNAPSHOT ), true );
			} else {
				$snapshot = unserialize( file_get_contents( NSCAN_SNAPSHOT ) );
			}
			if ( empty( $snapshot['abspath'] ) ) {
				// Don't skip step 2:
				$step = 2;
			}
		} else {
			// Don't skip step 2:
			$step = 2;
		}
	}
}

if ( $step == 2 ) {
	nscan_log_info(sprintf(
		__('Processing step %s (%s/%s)', 'ninjascanner'), $step, $retry, NSCAN_MAX_RETRIES
	));

	$abspath = rtrim( ABSPATH, '/' );
	// Chrooted WP?
	if ( $abspath == '' ) {
		$abspath = '/';
	}
	nscan_build_files_list(
		$abspath,
		$nscan_options['scan_nosymlink'],
		$nscan_options['scan_warnsymlink'],
		$nscan_options['scan_warnhiddenphp'],
		$nscan_options['scan_warnunreadable'],
		$nscan_options['scan_warnbinary'],
		WP_PLUGIN_DIR,
		WP_CONTENT_DIR .'/themes'
	);

	// Save ignored list as it may have changed:
	file_put_contents( NSCAN_IGNORED_LOG, serialize( $ignored_files ) );

	nscan_log_info( $step_msg );

	if ( empty( $snapshot['abspath'] ) ) {
		$msg = __('Fatal error: No file found. Check your NinjaScanner configuration. Aborting.', 'ninjascanner');
		$snapshot['error'] = $msg;
		nscan_log_error( $msg );
		touch( NSCAN_CANCEL );
		exit;
	}

	nscan_log_info( sprintf(
		__('Total files found: %s', 'ninjascanner'), count( $snapshot['abspath'] )
	));

	if (! empty( $snapshot['core_symlink'] ) ) {
		nscan_log_warn( sprintf(
			__('Symlinks found: %s', 'ninjascanner'), count( $snapshot['core_symlink'] )
		));
	}
	if ( empty( $nscan_options['scan_warnsymlink'] ) ) {
		$snapshot['skip']['core_symlink'] = 1;
	}

	if (! empty( $snapshot['core_unreadable'] ) ) {
		nscan_log_warn( sprintf(
			__('Unreadable files found: %s', 'ninjascanner'), count( $snapshot['core_unreadable'] )
		));
	}
	if ( empty( $nscan_options['scan_warnunreadable'] ) ){
		$snapshot['skip']['core_unreadable'] = 1;
	}

	if (! empty( $snapshot['core_hidden'] ) ) {
		nscan_log_warn( sprintf(
			__('Hidden scripts found: %s', 'ninjascanner'), count( $snapshot['core_hidden'] )
		));
	}
	if ( empty( $nscan_options['scan_warnhiddenphp'] ) ) {
		$snapshot['skip']['core_hidden'] = 1;
	}

	if (! empty( $snapshot['core_binary'] ) ) {
		nscan_log_warn( sprintf(
			__('Executable files found: %s', 'ninjascanner'), count( $snapshot['core_binary'] )
		));
	}
	if ( empty( $nscan_options['scan_warnbinary'] ) ) {
		$snapshot['skip']['core_binary'] = 1;
	}

	// Create the database posts and pages checksum:
	global $wpdb;
	$snapshot['posts'] = array(); $snapshot['pages'] = array();
	nscan_log_info( __('Building database posts and pages checksum', 'ninjascanner') );
	// Posts:
	$tmp_array = $wpdb->get_results(
		"SELECT ID, post_title, sha1(concat(post_content, post_title, post_excerpt, post_name))
		as hash
		FROM {$wpdb->posts}
		WHERE `post_type` = 'post' and `post_status` = 'publish'"
	);
	foreach( $tmp_array as $item ) {
		$snapshot['posts'][$item->ID]['permalink'] = get_permalink( $item->ID );
		$snapshot['posts'][$item->ID]['hash'] = $item->hash;
	}
	unset($tmp_array);
	// Pages:
	$tmp_array = $wpdb->get_results(
		"SELECT ID, post_title, sha1(concat(post_content, post_title, post_excerpt, post_name))
		as hash
		FROM {$wpdb->posts}
		WHERE `post_type` = 'page' and `post_status` = 'publish'"
	);
	foreach( $tmp_array as $item ) {
		$snapshot['pages'][$item->ID]['permalink'] = get_permalink( $item->ID );
		$snapshot['pages'][$item->ID]['hash'] = $item->hash;
	}
	unset($tmp_array);
	nscan_log_info( sprintf(
		__('Found %s posts and %s pages in the database', 'ninjascanner'),
		count( $snapshot['posts'] ),
		count( $snapshot['pages'] )
	) );

	++$step;
	nscan_log_debug( sprintf(
		__('Process used %s bytes out of %s bytes of allocated memory.', 'ninjascanner' ),
		number_format_i18n( memory_get_peak_usage( false ) ),
		number_format_i18n( memory_get_peak_usage( true ) )
	) );
	nscan_check_max_exec_time( $nscan_options );
} else {
	nscan_log_info( sprintf( __('Ignoring step %s (already done)', 'ninjascanner'), 2 ) );
}

// =====================================================================
// Step 3: Compare WordPress core files to their original package.

if ( $step == 3 ) {

	$step_msg = __('Checking WordPress core files integrity', 'ninjascanner');
	file_put_contents( NSCAN_LOCKFILE, "$step::$step_msg" );


	nscan_log_info( sprintf(
		__('Processing step %s (%s/%s)', 'ninjascanner'), $step, $retry, NSCAN_MAX_RETRIES
	));
	if ( $nscan_options['scan_wpcoreintegrity'] ) {
		nscan_log_info( $step_msg );

		if ( nscan_check_wordpress_integrity( $step, $step_msg ) === true ) {
			nscan_log_info( __('Files integrity is O.K', 'ninjascanner') );
		}
		if (! empty( $snapshot['core_unknown'] ) ) {
			nscan_log_warn( sprintf(
				__('Additional/suspicious files: %s', 'ninjascanner'), count( $snapshot['core_unknown'] )
			));
		}

	} else {
		nscan_log_info( __('Skipping WordPress core files integrity check', 'ninjascanner') );
		$snapshot['skip']['scan_wpcoreintegrity'] = 1;
	}
	++$step;
	nscan_log_debug( sprintf(
		__('Process used %s bytes out of %s bytes of allocated memory.', 'ninjascanner' ),
		number_format_i18n( memory_get_peak_usage( false ) ),
		number_format_i18n( memory_get_peak_usage( true ) )
	) );
	nscan_check_max_exec_time( $nscan_options );
} else {
	nscan_log_info( sprintf( __('Ignoring step %s (already done)', 'ninjascanner'), 3 ) );
}

// =====================================================================
// Step 4: Compare plugin files to their original package

if ( $step == 4 ) {

	$step_msg = __('Checking plugin files integrity', 'ninjascanner');
	file_put_contents( NSCAN_LOCKFILE, "$step::$step_msg" );

	nscan_log_info( sprintf(
		__('Processing step %s (%s/%s)', 'ninjascanner'), $step, $retry, NSCAN_MAX_RETRIES
	));
	if ( $nscan_options['scan_pluginsintegrity'] ) {
		nscan_log_info( $step_msg );

		if ( nscan_check_plugins_integrity( $step, $step_msg ) === true ) {
			nscan_log_info( __('Plugin files integrity is O.K', 'ninjascanner') );
		}
	} else {
		nscan_log_info( __('Skipping plugin files integrity check', 'ninjascanner') );
		$snapshot['skip']['scan_pluginsintegrity'] = 1;
	}
	++$step;
	nscan_log_debug( sprintf(
		__('Process used %s bytes out of %s bytes of allocated memory.', 'ninjascanner' ),
		number_format_i18n( memory_get_peak_usage( false ) ),
		number_format_i18n( memory_get_peak_usage( true ) )
	) );
} else {
	nscan_log_info( sprintf( __('Ignoring step %s (already done)', 'ninjascanner'), 4 ) );
}

// =====================================================================
// Step 5: Compare theme files to their original package

if ( $step == 5 ) {

	$step_msg = __('Checking theme files integrity', 'ninjascanner');
	file_put_contents( NSCAN_LOCKFILE, "$step::$step_msg" );

	nscan_log_info( sprintf(
		__('Processing step %s (%s/%s)', 'ninjascanner'), $step, $retry, NSCAN_MAX_RETRIES
	));
	if ( $nscan_options['scan_themeseintegrity'] ) {
		nscan_log_info( $step_msg );

		if ( nscan_check_themes_integrity( $step, $step_msg ) === true ) {
			nscan_log_info( __('Theme files integrity is O.K', 'ninjascanner') );
		}
	} else {
		nscan_log_info( __('Skipping theme files integrity check', 'ninjascanner') );
		$snapshot['skip']['scan_themeseintegrity'] = 1;
	}
	++$step;
	nscan_log_debug( sprintf(
		__('Process used %s bytes out of %s bytes of allocated memory.', 'ninjascanner' ),
		number_format_i18n( memory_get_peak_usage( false ) ),
		number_format_i18n( memory_get_peak_usage( true ) )
	) );
} else {
	nscan_log_info( sprintf( __('Ignoring step %s (already done)', 'ninjascanner'), 5 ) );
}

// =====================================================================
// Step 6: Compare the previous (if any) and current file snapshot and
// report changes (modified, added or deleted files).

if ( $step == 6 ) {

	$step_msg = __('Comparing previous and current file snapshots', 'ninjascanner');
	file_put_contents( NSCAN_LOCKFILE, "$step::$step_msg" );

	nscan_log_info(
		sprintf( __('Processing step %s (%s/%s)', 'ninjascanner'), $step, $retry, NSCAN_MAX_RETRIES )
	);
	if (! empty( $nscan_options['scan_warnfilechanged'] ) ) {

		nscan_log_info( $step_msg );

		// Make sure we have an older snapshot file:
		if (! file_exists( NSCAN_OLD_SNAPSHOT ) ) {
			nscan_log_info( __('Skipping snapshots comparison, no older files shapshot found', 'ninjascanner') );

		} else {
			$old_snapshot = array();
			if ( nscan_is_json_encoded( NSCAN_OLD_SNAPSHOT ) === true ) {
				$old_snapshot = json_decode( file_get_contents( NSCAN_OLD_SNAPSHOT ), true );
			} else {
				$old_snapshot = unserialize( file_get_contents( NSCAN_OLD_SNAPSHOT ) );
			}

			if ( empty( $old_snapshot['abspath'] ) ) {
				nscan_log_warn( __('Old snapshot file seems corrupted. Deleting it and skipping this step', 'ninjascanner') );

			} else {

				if ( nscan_compare_snapshots( $old_snapshot['abspath'] ) === true ) {
					nscan_log_info( __('Previous and current snapshots match', 'ninjascanner') );
				}
			}
		}

	} else {
		nscan_log_info( __('Skipping snapshots comparison', 'ninjascanner') );
		$snapshot['skip']['scan_warnfilechanged'] = 1;
	}
	++$step;
	nscan_log_debug( sprintf(
		__('Process used %s bytes out of %s bytes of allocated memory.', 'ninjascanner' ),
		number_format_i18n( memory_get_peak_usage( false ) ),
		number_format_i18n( memory_get_peak_usage( true ) )
	) );
	nscan_check_max_exec_time( $nscan_options );
} else {
	nscan_log_info( sprintf( __('Ignoring step %s (already done)', 'ninjascanner'), 6 ) );
}

// =====================================================================
// Step 7: Compare the previous (if any) and current database 'posts' and
// 'pages' snapshot and report changes (modified, added or deleted items).
if ( $step == 7 ) {

	$step_msg = __('Comparing previous and current database snapshots', 'ninjascanner');
	file_put_contents( NSCAN_LOCKFILE, "$step::$step_msg" );

	nscan_log_info(
		sprintf( __('Processing step %s (%s/%s)', 'ninjascanner'), $step, $retry, NSCAN_MAX_RETRIES )
	);
	if (! empty( $nscan_options['scan_warndbchanged'] ) ) {

		nscan_log_info( $step_msg );

		// Make sure we have an older snapshot file:
		if (! file_exists( NSCAN_OLD_SNAPSHOT ) ) {
			nscan_log_info( __('Skipping snapshots comparison, no older database shapshot found', 'ninjascanner') );

		} else {

			// Did we load the previous snapshot already?
			if ( empty( $old_snapshot ) ) {
				// Load it:
				$old_snapshot = array();
				if ( nscan_is_json_encoded( NSCAN_OLD_SNAPSHOT ) === true ) {
					$old_snapshot = json_decode( file_get_contents( NSCAN_OLD_SNAPSHOT ), true );
				} else {
					$old_snapshot = unserialize( file_get_contents( NSCAN_OLD_SNAPSHOT ) );
				}
			}

			if ( empty( $old_snapshot['abspath'] ) ) {
				nscan_log_warn( __('Old snapshot file seems corrupted. Deleting it and skipping this step', 'ninjascanner') );

			} elseif (! isset( $old_snapshot['posts'] ) && ! isset( $old_snapshot['pages'] ) ) {
				nscan_log_info( __('Skipping snapshots comparison, no older database shapshot found', 'ninjascanner') );

			} else {
				if ( nscan_compare_db_snapshots( $old_snapshot['posts'], $old_snapshot['pages'] ) === true ) {
					nscan_log_info( __('Previous and current snapshots match', 'ninjascanner') );
				}
			}
		}

	} else {
		nscan_log_info( __('Skipping database snapshots comparison', 'ninjascanner') );
		$snapshot['skip']['scan_warndbchanged'] = 1;
	}
	++$step;
	nscan_log_debug( sprintf(
		__('Process used %s bytes out of %s bytes of allocated memory.', 'ninjascanner' ),
		number_format_i18n( memory_get_peak_usage( false ) ),
		number_format_i18n( memory_get_peak_usage( true ) )
	) );

} else {
	nscan_log_info( sprintf( __('Ignoring step %s (already done)', 'ninjascanner'), 7 ) );
}

unset( $old_snapshot );

// =====================================================================
// Step 8: Look up website URL in the Google Safe Browsing service.

if ( $step == 8 ) {

	$step_msg = __('Checking Google Safe Browsing', 'ninjascanner');
	file_put_contents( NSCAN_LOCKFILE, "$step::$step_msg" );

	nscan_log_info( sprintf(
		__('Processing step %s (%s/%s)', 'ninjascanner'), $step, $retry, NSCAN_MAX_RETRIES
	));

	if (! empty( $nscan_options['scan_gsb'] ) ) {
		nscan_log_info( $step_msg );
		nscan_check_gsb( $step );

	} else {
		nscan_log_info( __('Skipping Google Safe Browsing: no API key found', 'ninjascanner') );
		$snapshot['skip']['scan_gsb'] = 1;
	}
	++$step;
	nscan_log_debug( sprintf(
		__('Process used %s bytes out of %s bytes of allocated memory.', 'ninjascanner' ),
		number_format_i18n( memory_get_peak_usage( false ) ),
		number_format_i18n( memory_get_peak_usage( true ) )
	) );
}

// =====================================================================
// Step 9: Run the antimalware scanner using the built-in signatures
// and, if any, the user-defined signatures.

if ( $step == 9 ) { // Must match $step in index.php (saved state)

	$step_msg = __('Running anti-malware scanner', 'ninjascanner');
	file_put_contents( NSCAN_LOCKFILE, "$step::$step_msg" );

	nscan_log_info( sprintf(
		__('Processing step %s (%s/%s)', 'ninjascanner'), $step, $retry, NSCAN_MAX_RETRIES
	));

	$scan_signatures = array();
	$scan_signatures = json_decode( $nscan_options['scan_signatures'], true );

	if (! empty( $scan_signatures ) ) {
		nscan_log_info( $step_msg );

		// Check if we have the list of files to scan.
		// Was it saved to disk (previous attempt)?
		if ( $retry && file_exists( NSCAN_MALWARE_LOG ) ) {
			nscan_log_debug( __('Re-using NSCAN_MALWARE_LOG from previous attempt', 'ninjascanner') );
			if ( nscan_is_json_encoded( NSCAN_MALWARE_LOG ) === true ) {
				$current_snapshot = json_decode( file_get_contents( NSCAN_MALWARE_LOG ), true );
			} else {
				$current_snapshot = unserialize( file_get_contents( NSCAN_MALWARE_LOG ) );
			}
		}
		// Create a new one if needed:
		if ( empty( $current_snapshot ) ) {
			nscan_log_info( __('Cannot find the list of files to check, creating a new one', 'ninjascanner') );
			// Removed excluded files (based on user-exclusion lists)
			$current_snapshot = nscan_apply_exclusion( $snapshot['abspath'] );
		}

		// Check all signatures:
		$signatures_list = array();
		nscan_log_info( __('Retrieving signatures lists', 'ninjascanner') );
		foreach( $scan_signatures as $sig ) {
			$tmp_list = array();
			// Built-in LMD + NinjaScanner signatures list?
			if ( $sig == 'lmd' ) {
				// Download it:
				if ( nscan_download_signatures( $step ) === false ) {
					continue;
				}
				// Verify signatures and return the list:
				if ( ( $tmp_list = nscan_verify_signatures( NSCAN_SIGNATURES ) ) === false ) {
					continue;
				}

			} else {
				nscan_log_debug( sprintf(
					__('Checking user-defined signatures list (%s)', 'ninjascanner'), $sig
				));
				// Verify signatures and return the list:
				if ( ( $tmp_list = nscan_verify_signatures( $sig ) ) === false ) {
					continue;
				}
			}
			if ( $tmp_list ) {
				// Concatenate arrays:
				$signatures_list += $tmp_list;
			}
		}

		if ( $signatures_list ) {
			nscan_log_info( sprintf(
				__('Total loaded signatures: %s', 'ninjascanner'), count( $signatures_list )
			));

			if ( nscan_run_antimalware( $signatures_list, $step, $step_msg ) === true ) {
				nscan_log_info( __('No suspicious file detected', 'ninjascanner') );
			}
		}

	} else {
		nscan_log_info( __('Skipping anti-malware scanner', 'ninjascanner') );
		$snapshot['skip']['scan_antimalware'] = 1;
	}
	++$step;
	nscan_log_debug( sprintf(
		__('Process used %s bytes out of %s bytes of allocated memory.', 'ninjascanner' ),
		number_format_i18n( memory_get_peak_usage( false ) ),
		number_format_i18n( memory_get_peak_usage( true ) )
	) );
} else {
	nscan_log_info( sprintf( __('Ignoring step %s (already done)', 'ninjascanner'), 8 ) );
}

// =====================================================================
// Step 10: Various tests (Note: tests can be disabled in the wp-config.php
// by using their corresponding constant).

if ( $step == 10 ) {

	$step_msg = __('Finishing', 'ninjascanner');
	file_put_contents( NSCAN_LOCKFILE, "$step::$step_msg" );


	nscan_log_info( sprintf(
		__('Processing step %s (%s/%s)', 'ninjascanner'), $step, $retry, NSCAN_MAX_RETRIES
	));

	if (! defined('NS_SKIP_SSHKEY' ) ) {
		if ( @file_exists( $key = dirname( @$_SERVER['DOCUMENT_ROOT']) .'/.ssh/authorized_keys') ||
			@file_exists( $key = @$_SERVER['DOCUMENT_ROOT'] .'/.ssh/authorized_keys') ) {

			$snapshot['various']['ssh_key'][$key] = 1;
		}
		// For backward compatibility, authorized_keys2 can still be used although deprecated since 2001:
		if ( @file_exists( $key = dirname( @$_SERVER['DOCUMENT_ROOT']) .'/.ssh/authorized_keys2') ||
			@file_exists( $key = @$_SERVER['DOCUMENT_ROOT'] .'/.ssh/authorized_keys2') ) {

			$snapshot['various']['ssh_key'][$key] = 1;
		}
		if (! empty( $snapshot['various']['ssh_key'] ) ) {
			nscan_log_warn( sprintf(
				_n('Found %s SSH key in user home folder',
					'Found %s SSH keys in user home folder',
					count( $snapshot['various']['ssh_key'] ), 'ninjascanner'
				),
				count( $snapshot['various']['ssh_key'] )
			));
		}
	}

	if (! defined('NS_SKIP_WPREGISTRATION' ) ) {
		$default_role = get_option( 'default_role' );
		$users_can_register = get_option( 'users_can_register' );
		if ( $default_role == 'administrator' ) {
			if (! empty( $users_can_register ) ) {
				// Critical
				$snapshot['various']['membership'] = 2;
				nscan_log_warn( __('All New Registered users have administrator role', 'ninjascanner') );
			} else {
				// Important
				$snapshot['various']['membership'] = 1;
				nscan_log_warn( __('New User Default Role is set to "administrator"', 'ninjascanner') );
			}
		}
	}

	if (! defined('NS_SKIP_WPUSERROLES' ) ) {
		$admin_only_cap = array(
			'activate_plugins', 'create_users', 'delete_plugins', 'delete_themes',
			'delete_users', 'edit_files', 'edit_plugins', 'edit_theme_options',
			'edit_themes', 'edit_users', 'export', 'import', 'install_plugins',
			'install_themes', 'list_users', 'manage_options', 'promote_users',
			'remove_users', 'switch_themes', 'update_core', 'update_plugins',
			'update_themes', 'edit_dashboard', 'customize',	'delete_site',
		);
		$exclusion_list = array(
			'shop_manager' => array(
				'slug'	=> 'woocommerce/woocommerce.php',
				'caps'	=>	array( 'edit_users', 'export', 'import', 'list_users',
								'edit_theme_options' ),
			),
		);
		include_once ABSPATH .'wp-admin/includes/plugin.php';

		// Fetch user_roles:
		global $wpdb;
		$user_roles = get_option("{$wpdb->base_prefix}user_roles");

		foreach ( $user_roles as $user => $cap ) {
			if ( $user != 'administrator' ) {
				foreach( $cap['capabilities'] as $k => $v ) {
					if (! empty( $v ) && in_array( $k, $admin_only_cap ) ) {

						// Check the exclusion list:
						if (! empty( $exclusion_list[$user] ) ) {
							if ( is_plugin_active( $exclusion_list[$user]['slug'] ) ) {
								if ( in_array( $k, $exclusion_list[$user]['caps'] ) ) {
									// Don't warn about this one:
									continue;
								}
							}
						}

						$snapshot['various']['user_roles'][$user][] = $k;
					}
				}
				if (! empty( $snapshot['various']['user_roles'][$user] ) ) {
					nscan_log_warn( sprintf( __('Found user roles with administrator capabilities: %s', 'ninjascanner'), $user ) );
				}
			}
		}
	}
	++$step;
	nscan_log_debug( sprintf(
		__('Process used %s bytes out of %s bytes of allocated memory.', 'ninjascanner' ),
		number_format_i18n( memory_get_peak_usage( false ) ),
		number_format_i18n( memory_get_peak_usage( true ) )
	) );
}

// =====================================================================

// Clear array:
$current_snapshot = array();

// =====================================================================
// Check Google Safe Browsing.

function nscan_check_gsb( $step ) {

	global $snapshot, $wp_version;
	$url = '';
	$nscan_options = get_option( 'nscan_options' );

	// In a multisite environment, we must check all sites:
	if ( is_multisite() && function_exists( 'get_sites' ) && class_exists( 'WP_Site_Query' ) ) {
		$mysites = get_sites([
			'public'  => 1,
			// <500, Google Safe Browsing usage limit
			'number'  => 499,
			'orderby' => 'registered',
			'order'   => 'ASC',
		]);
		foreach( $mysites as $id => $v ) {
			$site = get_site_url( $mysites[$id]->blog_id );
			if ( $site ) {
				$url .= '{"url": "'. $site .'"},';
			}
		}
		$total = count($mysites);
		$mysites = '';

	// Single site:
	} else {
		$site = home_url('/');
		$url .= '{"url": "'. $site .'"},';
		$total = 1;
	}

	nscan_log_info( sprintf(
		__('Total URL to check: %s', 'ninjascanner'),
		$total
	) );

	// Used for Google referrer restriction:
	$referrer = get_site_url();

	$body = array(
		'body' => '{
			"threatInfo": {
				"threatTypes":      ["MALWARE", "SOCIAL_ENGINEERING"],
				"platformTypes":    ["ANY_PLATFORM"],
				"threatEntryTypes": ["URL"],
				"threatEntries": [
					'. $url .'
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
	$res = wp_remote_post( NSCAN_GSB . "?key={$nscan_options['scan_gsb']}", $body );

	if (! is_wp_error($res) ) {
		$data = json_decode( $res['body'], true );

		// Invalid key:
		if (! empty( $data['error']['message'] ) ) {
			$snapshot['step_error'][$step] = $data['error']['message'];
			nscan_log_error( $data['error']['message'] );
			return false;
		}

		$snapshot['scan_gsb'] = array();
		if (! empty( $data['matches'] ) ) {
			foreach( $data['matches'] as $key ) {
				foreach( $key as $k => $v ) {
					$snapshot['scan_gsb'][$key['threat']['url']] = 1;
				}
			}
		}

		if (! empty( $snapshot['scan_gsb'] ) ) {
			nscan_log_warn( sprintf(
				__('Total blacklisted URL: %s', 'ninjascanner' ),
				count( $snapshot['scan_gsb'] )
			));
			return false;
		}
		return true;
	}

	// Unknown error:
	$err = sprintf(
		__('%s. Cannot check Google Safe Browsing. Try again later', 'ninjascanner'),
		$res->get_error_message()
	);
	$snapshot['step_error'][$step] = $err;
	nscan_log_error( $err );

	return false;
}

// =====================================================================
// Scan files for malware

function nscan_run_antimalware( $signatures, $step, $step_msg ) {

	global $snapshot;
	global $current_snapshot;

	$nscan_options = get_option( 'nscan_options' );

	$msg = __('items scanned:', 'ninjascanner');
	$total_scanned = 1;
	$log_interval = 0;

	$total_to_scan = count( $current_snapshot );

	foreach( $current_snapshot as $file => $v ) {

		if ( $log_interval > 15 ) {
			file_put_contents( NSCAN_LOCKFILE, "$step::$step_msg ($msg $total_scanned/$total_to_scan)" );
			$log_interval = 1;
		} else {
			++$log_interval;
		}
		++$total_scanned;

		if ( isset( $v['v'] ) && $v['v'] == 1 ) {
			// Don't scan core files that were verified already:
			unset( $current_snapshot[$file] );
			continue;
		}

		if (! file_exists( $file ) ) {
			// The file may have just been deleted (e.g., temp file etc):
			nscan_log_warn( sprintf(
				__('File does not exist, ignoring it: %s', 'ninjascanner'), $file
			));
			unset( $current_snapshot[$file] );
			continue;
		}

		nscan_check_max_exec_time( $nscan_options );

		// Clear the file from our buffer so that we could
		// restart where the scan left off in case or error/crash:
		unset( $current_snapshot[$file] );

		if ( ( $content = file_get_contents( $file ) ) !== false ) {
			foreach ( $signatures as $name => $sig ) {

				// Don't scan verified plugin & theme files,
				// unless the signature requests it:
				if ( isset( $v['v'] ) && $v['v'] != $name[4] ) {
					continue;
				}

				// Regex signature:
				if ( $name[1] == 'R' ) {
					if ( preg_match( "`$sig`", $content ) ) {
						$snapshot['infected_files'][$file] = $name;
						nscan_log_warn( sprintf(
							__('Potentially unsafe files: %s', 'ninjascanner'), $file
						));
						break;
					}
				// Simple signature:
				} else {
					if ( strpos( $content, $sig ) !== false ) {
						$snapshot['infected_files'][$file] = $name;
						nscan_log_warn( sprintf(
							__('Potentially unsafe files: %s', 'ninjascanner'), $file
						));
						break;
					}
				}
				// Scan was likely cancelled, let's quit:
				if (! file_exists( NSCAN_LOCKFILE ) ) {
					touch( NSCAN_CANCEL );
					exit;
				}
			}
		} else {
			nscan_log_error( sprintf(
				__('Cannot open %s, skipping it', 'ninjascanner'), $file
			));
		}
	}

	if (! empty( $snapshot['infected_files'] ) ) {
		nscan_log_warn( sprintf(
			__('Total potentially unsafe files: %s', 'ninjascanner'),
			count( $snapshot['infected_files'] )
		));
		return false;
	}

	return true;
}

// =====================================================================
// Read and verify the signatures list (built-in + user-defined),
// and return them as an array.

function nscan_verify_signatures( $file ) {

	// File must exists:
	if (! file_exists( $file ) ) {
		nscan_log_error( sprintf(
			__('Cannot find %s, skipping it', 'ninjascanner'), $file
		));
		return false;
	}

	$fh = fopen( $file, 'r' );
	if (! $fh ) {
		nscan_log_error( sprintf(
			__('Cannot open/read %s, skipping it', 'ninjascanner'), $file
		));
		return false;
	}

	nscan_log_debug( sprintf(
		__('Verifying signatures', 'ninjascanner'), $file
	));

	$tmp_signatures = array();
	$signatures = array();
	while (! feof( $fh ) ) {
		$line = fgets( $fh );
		$tmp_signatures = explode ( ':', rtrim( $line ) );
		unset($line);
		// Make sure we have what we are looking for:
		if (! empty( $tmp_signatures[3] ) && preg_match( '/^{[HR]EX\d?}/', $tmp_signatures[0] ) ) {
			// Decode hex-encoded signatures:
			if ( $res = nscan_hex2str( $tmp_signatures[3], $tmp_signatures[0] ) ) {
				$signatures[$tmp_signatures[0]] = $res;
			}
		}
		unset($tmp_signatures);
	}
	fclose( $fh );

	if (! empty( $signatures ) ) {
		nscan_log_debug( sprintf(
			__('Verified signatures: %s', 'ninjascanner'), count( $signatures )
		));
		return $signatures;
	}

	// Error:
	nscan_log_warn( __('No valid signatures found in that file, skipping it.', 'ninjascanner') );
	return false;
}

// =====================================================================
// Decode and test the hex-encoded signatures, including user-defined
// signatures. Signatures with a syntax error are ignored.

function nscan_hex2str( $hex, $type ) {

    $str = '';
    for ( $i = 0; $i < strlen( $hex ); $i += 2 ) {
		 $str .= chr( hexdec( substr( $hex, $i, 2 ) ) );
	 }
	 if ( preg_match( '/^{REX/', $type ) ) {
		$str = str_replace( '`', '\x60', $str );
		// Check regex validity:
		if ( preg_match("`$str`", 'foobar') === FALSE ) {
			nscan_log_error( sprintf(
				__('REX signature syntax error, skipping it: %s', 'ninjascanner'), $type
			));
			return false;
		}
	 } elseif ( preg_match( '/^{HEX/', $type ) ) {
		// Check signature validity (hex numbers only):
		if ( preg_match( '`[^a-f0-9]`i', $hex ) ) {
			nscan_log_error( sprintf(
				__('HEX signature syntax error, skipping it: %s', 'ninjascanner'), $type
			));
			return false;
		}
	}
	// OK:
	return $str;
}

// =====================================================================
// Download built-in LMD/NinjaScanner signatures list, or used the
// cached version if not older than one hour:

function nscan_download_signatures( $step ) {

	global $snapshot;
	$nscan_options = get_option( 'nscan_options' );

	nscan_log_debug( __('Checking built-in signatures list', 'ninjascanner') );

	// Check it we have a cached version:
	if ( file_exists( NSCAN_SIGNATURES ) ) {
		if ( time() - filemtime( NSCAN_SIGNATURES ) < 3600 ) {
			// Use it:
			nscan_log_debug( __('Using local copy', 'ninjascanner') );
			return;
		} else {
			// Too old, delete it:
			unlink( NSCAN_SIGNATURES );
			nscan_log_debug( __('Local copy is too old, deleting it', 'ninjascanner') );
		}
	}

	// Download the latest available version:
	nscan_log_debug( __('Downloading the latest version', 'ninjascanner') );
	global $wp_version;

	// Prepare the POST request:
	$data = array();
	$request_string = array(
		'body' => array(
			'action'	=> 'signatures',
			's' => 1,
			'cache_id' => sha1( home_url() )
		),
		'user-agent' => 'Mozilla/5.0 (compatible; NinjaScanner/'.
							NSCAN_VERSION ."; WordPress/{$wp_version})",
		'timeout' => NSCAN_CURL_TIMEOUT,
		'httpversion' => '1.1' ,
		'sslverify' => true
	);
	if (! empty( $nscan_options['key'] ) ) {
		// Premium users only:
		$request_string['body']['key'] = $nscan_options['key'];
		$request_string['body']['host'] = @strtolower( $_SERVER['HTTP_HOST'] );
	}

	// POST the request:
	$res = wp_remote_post( NSCAN_SIGNATURES_URL, $request_string);

	if (! is_wp_error($res) ) {
		if ( $res['response']['code'] == 200 ) {
			// Fetch the array:
			$data = json_decode( $res['body'], true );

			if (! empty( $data['exp'] ) ) {
				$nscan_options['exp'] = $data['exp'];
				update_option( 'nscan_options', $nscan_options );
			}

			// Make sure we have some signatures (a sig starts with '{', e.g., '{HEX}xxxxx'):
			if ( empty( $data['sig'] ) || $data['sig'][0] != '{' ) {
				if (! isset( $data['err'] ) ) { $data['err'] = 0; }
				$err = sprintf(
					__('The signatures list is either corrupted or empty. Try again later (error %s)', 'ninjascanner'),
					(int) $data['err']
				);
				$snapshot['step_error'][$step] = $err;
				nscan_log_warn( $err );
				return false;
			}

			// Verify the digital signature:
			if ( function_exists( 'openssl_pkey_get_public') && function_exists( 'openssl_verify' ) ) {
				nscan_log_debug( __('Verifying digital signature with public key', 'ninjascanner') );
				$public_key = rtrim( file_get_contents( __DIR__ .'/sign.pub' ) );
				$pubkeyid = openssl_pkey_get_public( $public_key );
				$verify = openssl_verify( trim( $data['sig'] ), base64_decode( $data['s'] ), $pubkeyid, OPENSSL_ALGO_SHA256);
				if ( $verify != 1 ) {
					$err = __('The digital signature is not correct. Aborting update, rules may have been tampered with.', 'ninjascanner');
					$snapshot['step_error'][$step] = $err;
					nscan_log_warn( $err );
					return false;
				}
			}

			// Save the signatures to the cache folder:
			file_put_contents( NSCAN_SIGNATURES, $data['sig'] );
			return true;

		} else {
			// HTTP error:
			$err = sprintf(
				__('HTTP Error %s. Cannot download signatures list. Try again later', 'ninjascanner'),
				(int)$res['response']['code']
			);
			$snapshot['step_error'][$step] = $err;
			nscan_log_warn( $err );
			return false;
		}
	}

	// Unknown error:
	$err = sprintf(
		__('%s. Cannot download built-in signatures list. Try again later', 'ninjascanner'),
		$res->get_error_message()
	);
	$snapshot['step_error'][$step] = $err;
	nscan_log_error( $err );
	return false;
}

// =====================================================================
// Compare the current and previous database snapshots for modifications
// (added, deleted and modified posts and pages).

function nscan_compare_db_snapshots( $old_posts, $old_pages ) {

	global $snapshot;
	$count = 0;

	// Posts:
	foreach( $snapshot['posts'] as $id => $val ) {
		// Post didn't exist when the previous snapshot was taken:
		if (! isset( $old_posts[$id] ) ) {
			$snapshot['snapshot']['added_posts'][$id] = $val['permalink'];
			++$count;
			continue;
		}
		// Post was changed:
		if ( $old_posts[$id]['hash'] != $val['hash'] ) {
			$snapshot['snapshot']['mismatched_posts'][$id] = $val['permalink'];
			++$count;
		}
		// Remove it from the list:
		unset( $old_posts[$id] );
	}
	// Make sur its not empty:
	if ( is_array( $old_posts ) ) {
		foreach( $old_posts as $id => $val ) {
			// Post was removed:
			$snapshot['snapshot']['deleted_posts'][$id] = $val['permalink'];
			++$count;
		}
	}
	if (! empty( $snapshot['snapshot']['added_posts'] ) ) {
		nscan_log_warn( sprintf(
			__('Total additional posts: %s', 'ninjascanner' ),
			count( $snapshot['snapshot']['added_posts'] )
		));
	}
	if (! empty( $snapshot['snapshot']['mismatched_posts'] ) ) {
		nscan_log_warn( sprintf(
			__('Total modified posts: %s', 'ninjascanner' ),
			count( $snapshot['snapshot']['mismatched_posts'] )
		));
	}
	if (! empty( $snapshot['snapshot']['deleted_posts'] ) ) {
		nscan_log_warn( sprintf(
			__('Total deleted posts: %s', 'ninjascanner' ),
			count( $snapshot['snapshot']['deleted_posts'] )
		));
	}

	// Pages:
	foreach( $snapshot['pages'] as $id => $val ) {
		// Page didn't exist when the previous snapshot was taken:
		if (! isset( $old_pages[$id] ) ) {
			$snapshot['snapshot']['added_pages'][$id] = $val['permalink'];
			++$count;
			continue;
		}
		// Page was changed:
		if ( $old_pages[$id]['hash'] != $val['hash'] ) {
			$snapshot['snapshot']['mismatched_pages'][$id] = $val['permalink'];
			++$count;
		}
		// Remove it from the list:
		unset( $old_pages[$id] );
	}
	// Make sur its not empty:
	if ( is_array( $old_pages ) ) {
		foreach( $old_pages as $id => $val ) {
			// Page was removed:
			$snapshot['snapshot']['deleted_pages'][$id] = $val['permalink'];
			++$count;
		}
	}
	if (! empty( $snapshot['snapshot']['added_pages'] ) ) {
		nscan_log_warn( sprintf(
			__('Total additional pages: %s', 'ninjascanner' ),
			count( $snapshot['snapshot']['added_pages'] )
		));
	}
	if (! empty( $snapshot['snapshot']['mismatched_pages'] ) ) {
		nscan_log_warn( sprintf(
			__('Total modified pages: %s', 'ninjascanner' ),
			count( $snapshot['snapshot']['mismatched_pages'] )
		));
	}
	if (! empty( $snapshot['snapshot']['deleted_pages'] ) ) {
		nscan_log_warn( sprintf(
			__('Total deleted pages: %s', 'ninjascanner' ),
			count( $snapshot['snapshot']['deleted_pages'] )
		));
	}

	if ( $count ) {
		return false;
	}
	return true;
}

// =====================================================================
// Compare the current and previous snapshots for modifications (added,
// deleted and modified files).

function nscan_compare_snapshots( $old_snapshot ) {

	global $snapshot;
	global $current_snapshot;

	$previous_snapshot = array();

	// Removed excluded files (based on user-exclusion lists)
	$current_snapshot = nscan_apply_exclusion( $snapshot['abspath'] );
	$previous_snapshot = nscan_apply_exclusion( $old_snapshot, 0 );

	$count = 0;
	foreach( $current_snapshot as $file => $stat ) {
		// File didn't exist when the previous snapshot was taken:
		if (! isset( $previous_snapshot[$file] ) ) {
			$snapshot['snapshot']['added_files'][$file] = 1;
			++$count;
			continue;
		}
		// File was changed:
		if ( $previous_snapshot[$file][0] != $stat[0] ) {
			$snapshot['snapshot']['mismatched_files'][$file] = 1;
			++$count;
		}
		// Remove it from the list:
		unset( $previous_snapshot[$file] );
	}

	foreach( $previous_snapshot as $file => $stat ) {
		// File was removed:
		$snapshot['snapshot']['deleted_files'][$file] = 1;
		++$count;
	}

	if (! empty( $snapshot['snapshot']['added_files'] ) ) {
		nscan_log_warn( sprintf(
			__('Total additional files: %s', 'ninjascanner' ),
			count( $snapshot['snapshot']['added_files'] )
		));
	}
	if (! empty( $snapshot['snapshot']['mismatched_files'] ) ) {
		nscan_log_warn( sprintf(
			__('Total modified files: %s', 'ninjascanner' ),
			count( $snapshot['snapshot']['mismatched_files'] )
		));
	}
	if (! empty( $snapshot['snapshot']['deleted_files'] ) ) {
		nscan_log_warn( sprintf(
			__('Total deleted files: %s', 'ninjascanner' ),
			count( $snapshot['snapshot']['deleted_files'] )
		));
	}

	if ( $count ) {
		return false;
	}
	return true;
}

// =====================================================================
// Removed files/folders from the array depending of the user-defined
// exclusion lists (based on names and file size).

function nscan_apply_exclusion( $buffer, $log = 1 ) {

	$nscan_options = get_option( 'nscan_options' );
	$count = 0;

	if ( $log ) {
		nscan_log_debug( __('Checking user-defined exclusion lists', 'ninjascanner') );
	}

	// Build the extensions exclusion list (case insensitive):
	$excluded_extensions = '';
	if (! empty( $nscan_options['scan_extensions'] ) ) {
		$extensions = json_decode( $nscan_options['scan_extensions'], true );
		if ( is_array( $extensions ) ) {
			foreach( $extensions as $extension ) {
				$excluded_extensions .= preg_quote( $extension ) . '|';
			}
			$excluded_extensions = '\.(?:'. rtrim( $excluded_extensions , '|' ) . ')$';
			if ( $log ) {
				nscan_log_debug( __('Creating extensions exclusion list', 'ninjascanner') );
			}
		}
	}
	// Build the files/folders exclusion list (case SeNsItIvE):
	$excluded_folders = '';
	if (! empty( $nscan_options['scan_folders'] ) ) {
		$folders = json_decode( $nscan_options['scan_folders'], true );
		if ( is_array( $folders ) ) {
			foreach( $folders as $folder ) {
				$excluded_folders .= preg_quote( $folder ) . '|';
			}
			$excluded_folders = rtrim( $excluded_folders , '|' );
			if ( $log ) {
				nscan_log_debug( __('Creating files/folders exclusion list', 'ninjascanner') );
			}
		}
	}
	// Filesize limit:
	$file_size = 0;
	if (! empty( $nscan_options['scan_size'] ) ) {
		$file_size = $nscan_options['scan_size'] * 1024;
		if ( $log ) {
			nscan_log_debug( sprintf(
				__('Limiting search to file smaller than %s bytes', 'ninjascanner' ),
				number_format_i18n( $file_size )
			) );
		}
	}

	// Apply the two exclusion lists to the array:
	foreach( $buffer as $file => $values ) {
		if ( $excluded_extensions && preg_match( "`$excluded_extensions`i", $file ) ) {
			// Remove files from list:
			unset( $buffer[$file] );
			++$count;
			continue;
		}
		if ( $excluded_folders && preg_match( "`$excluded_folders`", $file ) ) {
			// Remove files from list:
			unset( $buffer[$file] );
			++$count;
			continue;
		}

		if ( $file_size && $values[1] > $file_size ) {
			// Too big, exclude it too:
			unset( $buffer[$file] );
			++$count;
			continue;
		}
	}

	if ( $count && $log ) {
		nscan_log_debug( sprintf(
			__('Files ignored based on user-defined exclusion lists: %s', 'ninjascanner' ),
			$count
		));
	}

	// Return the buffer:
	return $buffer;
}

// =====================================================================
// Retrieve the list of installed themes and compare their files
// to the original ones by downloading them from the wordpress.org
// repo or using their local cached version.

function nscan_check_themes_integrity( $step, $step_msg ) {

	$nscan_options = get_option( 'nscan_options' );
	global $snapshot, $ignored_files;
	$failed = 0;

	// Build the list of themes (slug & version):
	nscan_log_info( __('Building themes list', 'ninjascanner') );
	if ( ! function_exists( 'wp_get_themes' ) ) {
		require_once ABSPATH . 'wp-includes/theme.php';
	}
	$themes = wp_get_themes();
	$nscan_themes_list = array();
	foreach( $themes as $k => $v ) {
		$nscan_themes_list['themes'][$k] = $v->Version;
	}

	if ( empty( $nscan_themes_list['themes'] ) ) {
		// That should never happened!
		nscan_log_warn( __('No themes found', 'ninjascanner') );
		return false;
	}
	nscan_log_debug( sprintf(
		__('Total themes found: %s', 'ninjascanner'),
		count( $nscan_themes_list['themes'] )
	));

	// Build the files/folders exclusion list (case SeNsItIvE):
	$excluded_folders = '';
	if (! empty( $nscan_options['scan_folders'] ) && ! empty( $nscan_options['scan_folders_fic'] ) ) {
		$folders = json_decode( $nscan_options['scan_folders'], true );
		if ( is_array( $folders ) ) {
			foreach( $folders as $folder ) {
				$excluded_folders .= preg_quote( $folder ) . '|';
			}
			$excluded_folders = rtrim( $excluded_folders , '|' );
			nscan_log_debug( __('Creating files/folders exclusion list', 'ninjascanner') );
		}
	}

	// Let's check their integrity if possible
	// (i.e., they are available at wordpress.org)
	$unknown_count = 0;
	foreach( $nscan_themes_list['themes'] as $slug => $version ) {

		$msg = "$slug $version";
		nscan_log_debug( $msg );
		file_put_contents( NSCAN_LOCKFILE, "$step::$step_msg ($msg)" );

		nscan_check_max_exec_time( $nscan_options );

		// Users can upload their own ZIP in a folder named "local":
		if ( file_exists( NSCAN_LOCAL ."/{$slug}.{$version}.zip" ) ) {
			nscan_log_debug( __('Using user-uploaded local copy', 'ninjascanner') );
			$theme_zip = NSCAN_LOCAL ."/{$slug}.{$version}.zip";

		// Check if we have a cached copy of the ZIP file:
		} elseif ( file_exists( NSCAN_CACHEDIR ."/theme_{$slug}.{$version}.zip" ) ) {
			nscan_log_debug( __('Using local copy', 'ninjascanner') );
			$theme_zip = NSCAN_CACHEDIR ."/theme_{$slug}.{$version}.zip";

		} else {
			nscan_log_debug( __('Attempting to download it from wordpress.org', 'ninjascanner') );

			if ( nscan_wp_repo_download( $slug, $version, 'theme', false ) === false ) {
				// Remove the theme from the list if we didn't find it in the WP repo:
				unset( $snapshot['themes'][$slug] );
				$snapshot['themes_not_found'][$slug] = $version;
				continue;
			}
			$theme_zip = NSCAN_CACHEDIR ."/theme_{$slug}.{$version}.zip";
		}

		// Return the ZIP archive list of files:
		$zip_files_list = array();
		if ( ( $zip_files_list = nscan_get_zip_files_list( $theme_zip ) ) === false ) {
			// Error, try next one:
			continue;
		}

		// Shall we only use the CRC checksum to check the integrity of the files?
		if (! empty( $nscan_options['scan_zipcrc'] ) ) {

			nscan_log_debug( __('Using CRC-32B checksum', 'ninjascanner') );

			foreach( $zip_files_list as $file => $checksum ) {

				// Make sure the file exists and is not on our ignored files list
				if ( file_exists( WP_CONTENT_DIR ."/themes/$slug/$file" ) && empty( $ignored_files[WP_CONTENT_DIR ."/themes/$slug/$file"] ) ) {
					$crc32b = hexdec( hash_file( 'crc32b',  WP_CONTENT_DIR ."/themes/$slug/$file" ) );
					// Compare checksums:
					if ( $crc32b !== $checksum ) {
						$snapshot['themes'][$slug][$file] = 1;
						nscan_log_warn( sprintf(
							__('Checksum mismatch: %s', 'ninjascanner'),
							WP_CONTENT_DIR ."/themes/$slug/$file"
						));
						++$failed;
						// Record type, version and slug for the report:
						$snapshot['abspath'][WP_CONTENT_DIR ."/themes/$slug/$file"]['slug'] = $slug;
						$snapshot['abspath'][WP_CONTENT_DIR ."/themes/$slug/$file"]['version'] = $version;
						$snapshot['abspath'][WP_CONTENT_DIR ."/themes/$slug/$file"]['type'] = 'theme';
					} else {
						// Remove the file from our list if it matches:
						unset( $snapshot['themes'][$slug][$file] );
						$snapshot['abspath'][WP_CONTENT_DIR ."/themes/$slug/$file"]['v'] = 3;
					}
				}
			}

		// Shall we extract the files from the archive?
		} else {
			// Extract the ZIP
			if ( nscan_extract_archive( $theme_zip, NSCAN_CACHEDIR ."/$slug" ) === false ) {
				// Error, try next one:
				continue;
			}

			// Select algo:
			if ( empty( $nscan_options['scan_checksum'] ) || $nscan_options['scan_checksum'] == 1 ) {
				$algo = 'md5';
			} elseif ( $nscan_options['scan_checksum'] == 2 ) {
				$algo = 'sha1';
			} else {
				$algo = 'sha256';
			}

			nscan_log_debug( sprintf( __('Using %s algo', 'ninjascanner'), $algo ) );

			// Compare local files with archive files:
			foreach( $zip_files_list as $file => $checksum ) {

				// Make sure the file exists and is not on our ignored files list
				if ( file_exists( WP_CONTENT_DIR ."/themes/$slug/$file" ) && empty( $ignored_files[WP_CONTENT_DIR ."/themes/$slug/$file"] ) ) {
					$local_file = hash_file( $algo, WP_CONTENT_DIR ."/themes/$slug/$file" );
					$original_file = hash_file( $algo, NSCAN_CACHEDIR ."/$slug/$slug/$file" ); // NSCAN_CACHEDIR/slug/slug/*

					// Compare checksums:
					if ( $local_file !== $original_file ) {
						$snapshot['themes'][$slug][$file] = 1;
						nscan_log_warn( sprintf(
							__('Checksum mismatch: %s', 'ninjascanner'),
							WP_CONTENT_DIR ."/themes/$slug/$file"
						));
						++$failed;
						// Record type, version and slug for the report:
						$snapshot['abspath'][WP_CONTENT_DIR ."/themes/$slug/$file"]['slug'] = $slug;
						$snapshot['abspath'][WP_CONTENT_DIR ."/themes/$slug/$file"]['version'] = $version;
						$snapshot['abspath'][WP_CONTENT_DIR ."/themes/$slug/$file"]['type'] = 'theme';
					} else {
						// Remove the file from our list if it matches:
						unset( $snapshot['themes'][$slug][$file] );
						$snapshot['abspath'][WP_CONTENT_DIR ."/themes/$slug/$file"]['v'] = 3;
					}
				}
			}
			// Remove the extracted files/directories:
			nscan_remove_dir( NSCAN_CACHEDIR ."/$slug" );
		}

		// Look for additional files in the themes folder:
		foreach( $snapshot['themes'][$slug] as $k => $v ) {
			if ( $excluded_folders && preg_match( "`$excluded_folders`i", WP_CONTENT_DIR ."/themes/$slug/$k" ) ) {
				// Ignore it, it's in the exclusion list:
				unset( $snapshot['themes'][$slug][$k] );
				continue;
			}
			if ( $v == 0 ) { ++$unknown_count; }
		}

	}

	if ( $unknown_count ) {
		nscan_log_warn( sprintf(
			__('Additional/suspicious files: %s', 'ninjascanner'),
			$unknown_count
	));
	}

	if ( $failed ) {
		nscan_log_warn( sprintf(
			__('Total modified theme files: %s', 'ninjascanner'),
			$failed
		));
		return false;
	} else {

		return true;
	}
}

// =====================================================================
// Retrieve the list of installed plugins and compare their files
// to the original ones by downloading them from the wordpress.org
// repo or using their local cached version.

function nscan_check_plugins_integrity( $step, $step_msg ) {

	$nscan_options = get_option( 'nscan_options' );
	global $snapshot, $ignored_files;
	$failed = 0;

	// Build the list of plugins (slug & version):
	nscan_log_debug( __('Building plugins list', 'ninjascanner') );
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$plugins = get_plugins();
	$nscan_plugins_list = array();
	foreach( $plugins as $k => $v ) {
		if ( $slug = substr( $k, 0, strpos( $k, '/' ) ) ) {
			$nscan_plugins_list['plugins'][$slug] = $v['Version'];
		} else {
			// Ignore 'Hello Dolly', we checked it already with WP core files:
			if ( $v['Name'] != 'Hello Dolly' ) {

				// Don't know what it is. It could be a backdoor,
				// we'll warn the user about it later:
				$snapshot['plugins_unknown'][$k] = $v['Version'];

				nscan_log_warn( sprintf(
					__('Additional/suspicious plugin: %s %s (%s)', 'ninjascanner'),
					$v['Name'], $v['Version'], WP_PLUGIN_DIR . "/$k"
				));
			}
		}
	}

	// Check if there is any MU plugins too:
	$mu_plugins = get_mu_plugins();
	foreach( $mu_plugins as $k => $v ) {
		if ( $slug = substr( $k, 0, strpos( $k, '/' ) ) ) {
			// Plugin with a folder/slug:
			$snapshot['mu_plugins'][$slug] = $v['Version'];

		} else {
		// No folder, just a single PHP script:
			$snapshot['mu_plugins'][$k] = $v['Version'];

		}
		nscan_log_warn( sprintf(
			__('mu-plugin found: %s %s (%s)', 'ninjascanner'),
			$v['Name'], $v['Version'], WP_PLUGIN_DIR . "/$k"
		));
	}

	$dropins = array(
		'advanced-cache.php', 'db.php', 'db-error.php', 'install.php', 'maintenance.php',
		'object-cache.php', 'sunrise.php', 'blog-deleted.php', 'blog-inactive.php',
		'blog-suspended.php'
	);
	foreach( $dropins as $dropin ) {
		if (! empty( $snapshot['abspath'][WP_CONTENT_DIR ."/$dropin"] ) ) {
			$snapshot['plugins_dropins'][$dropin] = 1;
		}
	}

	if ( empty( $nscan_plugins_list['plugins'] ) ) {
		nscan_log_warn( __('No plugins found', 'ninjascanner') );
		return false;
	}
	nscan_log_debug( sprintf(
		__('Total plugins found: %s', 'ninjascanner'),
		count( $nscan_plugins_list['plugins'] )
	));

	// Build the files/folders exclusion list (case SeNsItIvE):
	$excluded_folders = 'readme\.txt|';
	if (! empty( $nscan_options['scan_folders'] ) && ! empty( $nscan_options['scan_folders_fic'] ) ) {
		$folders = json_decode( $nscan_options['scan_folders'], true );
		if ( is_array( $folders ) ) {
			foreach( $folders as $folder ) {
				$excluded_folders .= preg_quote( $folder ) . '|';
			}
			nscan_log_debug( __('Creating files/folders exclusion list', 'ninjascanner') );
		}
	}
	$excluded_folders = rtrim( $excluded_folders , '|' );

	// Let's check their integrity if possible
	// (i.e., they are available at wordpress.org)
	$unknown_count = 0;
	foreach( $nscan_plugins_list['plugins'] as $slug => $version ) {

		$msg = "$slug $version";
		nscan_log_debug( $msg );
		file_put_contents( NSCAN_LOCKFILE, "$step::$step_msg ($msg)" );

		nscan_check_max_exec_time( $nscan_options );

		// If we already checked NinjaScanner files integrity, we skip it:
		if ( $nscan_options['scan_ninjaintegrity'] && $slug == 'ninjascanner' ) {
			nscan_log_debug( __('Ignoring NinjaScanner, its integrity was checked already', 'ninjascanner') );
			$snapshot['plugins'][$slug] = array();
			continue;
		}

		// Users can upload their own ZIP in a folder named "local":
		if ( file_exists( NSCAN_LOCAL ."/{$slug}.{$version}.zip" ) ) {
			nscan_log_debug( __('Using user-uploaded local copy', 'ninjascanner') );
			$plugin_zip = NSCAN_LOCAL ."/{$slug}.{$version}.zip";

		// Check if we have a cached copy of the ZIP file:
		} elseif ( file_exists( NSCAN_CACHEDIR ."/plugin_{$slug}.{$version}.zip" ) ) {
			nscan_log_debug( __('Using local copy', 'ninjascanner') );
			$plugin_zip = NSCAN_CACHEDIR ."/plugin_{$slug}.{$version}.zip";

		} else {
			nscan_log_debug( __('Attempting to download it from wordpress.org', 'ninjascanner') );

			if ( nscan_wp_repo_download( $slug, $version, 'plugin', false ) === false ) {
				// Try to download it from the trunk folder instead:
				nscan_log_debug( __('Not found. Attempting to download it from the trunk folder instead', 'ninjascanner') );
				if ( nscan_wp_repo_download( $slug, $version, 'plugin', true ) === false ) {
					// Remove the plugin from the list if we didn't find it in the WP repo:
					unset( $snapshot['plugins'][$slug] );
					$snapshot['plugins_not_found'][$slug] = $version;
					continue;
				}
			}
			$plugin_zip = NSCAN_CACHEDIR ."/plugin_{$slug}.{$version}.zip";
		}

		// Return the ZIP archive list of files:
		$zip_files_list = array();
		if ( ( $zip_files_list = nscan_get_zip_files_list( $plugin_zip ) ) === false ) {
			// Error, try next one:
			continue;
		}

		// Shall we only use the CRC checksum to check the integrity of the files?
		if (! empty( $nscan_options['scan_zipcrc'] ) ) {

			nscan_log_debug( __('Using CRC-32B checksum', 'ninjascanner') );

			foreach( $zip_files_list as $file => $checksum ) {
				// Make sure the file exists and is not on our ignored files list
				if ( file_exists( WP_PLUGIN_DIR ."/$slug/$file" ) && empty( $ignored_files[WP_PLUGIN_DIR ."/$slug/$file"] ) ) {
					$crc32b = hexdec( hash_file( 'crc32b',  WP_PLUGIN_DIR ."/$slug/$file" ) );
					// Compare checksums:
					if ( $crc32b !== $checksum ) {
						$snapshot['plugins'][$slug][$file] = 1;
						nscan_log_warn( sprintf(
							__('Checksum mismatch: %s', 'ninjascanner'),
							WP_PLUGIN_DIR ."/$slug/$file"
						));
						++$failed;
						// Record type, version and slug for the report:
						$snapshot['abspath'][WP_PLUGIN_DIR ."/$slug/$file"]['slug'] = $slug;
						$snapshot['abspath'][WP_PLUGIN_DIR ."/$slug/$file"]['version'] = $version;
						$snapshot['abspath'][WP_PLUGIN_DIR ."/$slug/$file"]['type'] = 'plugin';
					} else {
						// Remove the file from our list if it matches:
						unset( $snapshot['plugins'][$slug][$file] );
						$snapshot['abspath'][WP_PLUGIN_DIR ."/$slug/$file"]['v'] = 2;
					}
				}
			}

		// Shall we extract the files from the archive?
		} else {
			// Extract the ZIP
			if ( nscan_extract_archive( $plugin_zip, NSCAN_CACHEDIR ."/$slug" ) === false ) {
				// Error, try next one:
				continue;
			}

			// Select algo:
			if ( empty( $nscan_options['scan_checksum'] ) || $nscan_options['scan_checksum'] == 1 ) {
				$algo = 'md5';
			} elseif ( $nscan_options['scan_checksum'] == 2 ) {
				$algo = 'sha1';
			} else {
				$algo = 'sha256';
			}

			nscan_log_debug( sprintf( __('Using %s algo', 'ninjascanner'), $algo ) );

			// Compare local files with archive files:
			foreach( $zip_files_list as $file => $checksum ) {

				// Make sure the file exists and is not on our ignored files list
				if ( file_exists( WP_PLUGIN_DIR ."/$slug/$file" ) && empty( $ignored_files[WP_PLUGIN_DIR ."/$slug/$file"] ) ) {

					$local_file = hash_file( $algo, WP_PLUGIN_DIR ."/$slug/$file" );
					$original_file = hash_file( $algo, NSCAN_CACHEDIR ."/$slug/$slug/$file" ); // NSCAN_CACHEDIR/slug/slug/*

					// Compare checksums:
					if ( $local_file !== $original_file ) {
						$snapshot['plugins'][$slug][$file] = 1;
						nscan_log_warn( sprintf(
							__('Checksum mismatch: %s', 'ninjascanner'),
							WP_PLUGIN_DIR ."/$slug/$file"
						));
						++$failed;
						// Record type, version and slug for the report:
						$snapshot['abspath'][WP_PLUGIN_DIR ."/$slug/$file"]['slug'] = $slug;
						$snapshot['abspath'][WP_PLUGIN_DIR ."/$slug/$file"]['version'] = $version;
						$snapshot['abspath'][WP_PLUGIN_DIR ."/$slug/$file"]['type'] = 'plugin';
					} else {
						// Remove the file from our list if it matches:
						unset( $snapshot['plugins'][$slug][$file] );
						$snapshot['abspath'][WP_PLUGIN_DIR ."/$slug/$file"]['v'] = 2;
					}
				}
			}
			// Remove the extracted files/directories:
			nscan_remove_dir( NSCAN_CACHEDIR ."/$slug" );
		}

		// Look for additional files in the plugins folder:
		foreach( $snapshot['plugins'][$slug] as $k => $v ) {
			if ( $excluded_folders && preg_match( "`$excluded_folders`i", WP_PLUGIN_DIR ."/$slug/$k" ) ) {
				// Ignore it, it's in the exclusion list:
				unset( $snapshot['plugins'][$slug][$k] );
				continue;
			}
			if ( $v == 0 ) { ++$unknown_count; }
		}
	}

	if ( $unknown_count ) {
		nscan_log_warn( sprintf(
			__('Additional/suspicious files: %s', 'ninjascanner'),
			$unknown_count
		));
	}

	if ( $failed ) {
		nscan_log_warn( sprintf(
			__('Total modified plugin files: %s', 'ninjascanner'),
			$failed
		));
		return false;
	} else {

		return true;
	}
}

// =====================================================================
// Extract a ZIP archive into the cache folder. Destination folder
// will match the plugin/theme slug.

function nscan_extract_archive( $zip_file, $destination_folder ) {

	if ( is_dir( $destination_folder ) ) {
		// The destination folder exists, let's delete it:
		nscan_remove_dir( $destination_folder );
	}

	if ( mkdir( $destination_folder ) === false ) {
		nscan_log_warn( sprintf(
			__('Cannot create folder %s. Is your filesystem read-only?', 'ninjascanner'),
			$destination_folder
		));
		return false;
	}

	$zip = new ZipArchive;
	if ( ( $res = $zip->open( $zip_file ) ) === true ) {

		$zip->extractTo( $destination_folder );
		$zip->close();

		return true;
	}

	nscan_log_error( sprintf(
		__('Unable to extract ZIP archive (error code: %s)', 'ninjascanner'),
		$res
	));
	// Delete destination folder:
	nscan_remove_dir( $destination_folder );

	return false;
}

// =====================================================================
// Recursively delete all files and directories. Used to delete
// extracted ZIP files (plugins and themes) in the cache folder
// after file integrity check:

function nscan_remove_dir( $dir ) {

	// Play safe: make sure that whatever we delete,
	// it's located inside our cache folder:
	if ( strpos( $dir, NSCAN_CACHEDIR ) === false ) {
		nscan_log_error( sprintf(
			__('Directory path does not match NSCAN_CACHEDIR: %s', 'ninjascanner'),
			$dir
		));
	}

	if ( is_dir( $dir ) ) {
		$files = scandir( $dir );
		foreach ( $files as $file ) {
			if ( $file == '.' || $file == '..' ) {
				continue;
			}
			if ( is_dir( "$dir/$file" ) ) {
				nscan_remove_dir( "$dir/$file" );
			} else {
				unlink( "$dir/$file" );
			}
     }
     rmdir( $dir );
   }
 }

// =====================================================================
// Return the list of files from a ZIP archive - as well as their
// corresponding CRC32B checksum - without extracting them.

function nscan_get_zip_files_list( $zip_file ) {

	nscan_log_debug( __('Building files list from ZIP archive', 'ninjascanner') );

	$zip = new ZipArchive();
	$zip_files_list = array();

	if ( ( $res = $zip->open( $zip_file ) ) === true ) {
		for ( $i = 0; $i < $zip->numFiles; ++$i ) {
			$stat = $zip->statIndex( $i );
			// Ignore folders:
			if ( substr( $stat['name'], -1 ) == '/' ) {
				continue;
			}
			// Remove the plugin slug + its following slash:
			$file_name =  substr( $stat['name'], strpos( $stat['name'], '/' ) + 1 );
			$zip_files_list[$file_name] = $stat['crc'];
			unset($stat);
		}
		$zip->close();

		// Make sure we have something:
		if ( count( $zip_files_list ) < 1 ) {
			nscan_log_error( __('Files list is empty. Skipping this archive', 'ninjascanner') );
			return false;
		}
		// Return the files list:
		return $zip_files_list;
	}

	nscan_log_error( sprintf(
		__('Unable to open ZIP archive (error code: %s)', 'ninjascanner'),
		$res
	));
	return false;

}

// =====================================================================
// Download a plugin or theme ZIP file from the wordpress.org repo
// and save it to the cache folder. The copy will be kept locally
// until NinjaScanner's garbage collector cron job deletes it.
// If it is a plugin and the operation failed (404 Not Found), this
// function is called once again in an attempt to downloaded the file
// from the trunk folder instead (some developers may not tag their plugin).

function nscan_wp_repo_download( $slug, $version, $type, $trunk = false ) {

	global $wp_version;

	if ( $type == 'plugin' ) {
		// Plugin URL:
		if ( $trunk ) {
			$url = NSCAN_PLUGINS_URL ."{$slug}.zip";
		} else {
			$url = NSCAN_PLUGINS_URL ."{$slug}.{$version}.zip";
		}

	} else {
		// Theme URL:
		$url = NSCAN_THEMES_URL ."{$slug}.{$version}.zip";
	}

	$res = wp_remote_get(
		$url,
		array(
			'timeout' => NSCAN_CURL_TIMEOUT,
			'httpversion' => '1.1' ,
			'user-agent' => 'Mozilla/5.0 (compatible; NinjaScanner/'.
									NSCAN_VERSION .'; WordPress/'. $wp_version . ')',
			'sslverify' => true
		)
	);

	if (! is_wp_error( $res ) ) {
		if ( $res['response']['code'] == 200 ) {
			// Save the ZIP file:
			file_put_contents( NSCAN_CACHEDIR ."/{$type}_{$slug}.{$version}.zip", $res['body'] );
			return true;
		} else {
			if ( $trunk || $type == 'theme' ) {
				// Probably not available in wordpress.org repo, ignore it:
				nscan_log_warn( sprintf(
					__('HTTP Error %s. Skipping %s %s, it may not be available in the repo', 'ninjascanner'),
					(int)$res['response']['code'],
					$slug,
					$version
				));
			}
			return false;
		}
	}

	// Unknown error:
	nscan_log_error( sprintf(
		__('%s. Skipping it. You may try again later', 'ninjascanner'),
		$res->get_error_message()
	));
	return false;
}

// =====================================================================
// Check NinjaScanner's files integrity by downloading its checksum
// hashes - or using the local cached copy. In case of mismatch,
// refuse to run (users can still bypass this by disabling the integrity
// checker from the settings page).

function nscan_check_ninjascanner_integrity() {

	global $wp_version;
	global $snapshot;
	$nscan_hashes = array();

	// Do we have a local cached version?
	if ( file_exists( NSCAN_HASHFILE ) ) {
		nscan_log_debug( __('Using local cached version of checksums', 'ninjascanner') );
		$nscan_hashes = json_decode( file_get_contents( NSCAN_HASHFILE ), true );
		// Make sure we have what we are expecting:
		if ( empty( $nscan_hashes['checksums']['ninjascanner/lib/constants.php'] ) ) {
			nscan_log_warn( __('Decoded hashes seem corrupted. Deleting local cached version', 'ninjascanner') );
			// Delete the file:
			unlink( NSCAN_HASHFILE );
			$nscan_hashes = array();
		}
	}

	// NinjaScanner's wordpress.og repo URL:
	$url = sprintf( NSCAN_SVN_PLUGINS, 'ninjascanner',	NSCAN_VERSION ) .'/checksum.txt';

	// Download them:
	if ( empty( $nscan_hashes ) ) {
		nscan_log_debug( __('Downloading checksums', 'ninjascanner') );
		$res = wp_remote_get(
			$url,
			array(
				'timeout' => NSCAN_CURL_TIMEOUT,
				'httpversion' => '1.1' ,
				'user-agent' => 'Mozilla/5.0 (compatible; NinjaScanner/'.
										NSCAN_VERSION .'; WordPress/'. $wp_version . ')',
				'sslverify' => true
			)
		);
		if ( is_wp_error( $res ) ) {
			nscan_log_error(
				sprintf( __('%s. Skipping this step', 'ninjascanner'), $res->get_error_message() )
			);
			// Don't return false, the server may be down. We'll attempt
			// to check the files again while checking all plugins integrity:
			return -1;
		}
		// Decode the content:
		$nscan_hashes = json_decode( $res['body'], true );
		// Make sure we have what we are expecting:
		if ( empty( $nscan_hashes['checksums']['ninjascanner/lib/constants.php'] ) ) {
			$msg = __('Fatal error: NinjaScanner files integrity check: Decoded hashes seem corrupted. Aborting.', 'ninjascanner');
			$snapshot['error'] = $msg;
			nscan_log_error( $msg );
			return false;
		}
		// Save it to disk:
		file_put_contents( NSCAN_HASHFILE, $res['body'] );
	}

	// Loop through the array and compare hashes:
	$failed = 0;
	$missing = 0;
	foreach( $nscan_hashes['checksums'] as $file => $checksum ) {

		// Use WP_PLUGIN_DIR as user may have changed the path:
		$tmpfile = WP_PLUGIN_DIR . "/$file";
		if ( file_exists( $tmpfile ) ) {
			// Checksum does not match?
			if ( hash_file( 'sha256', $tmpfile ) !== $checksum ) {
				++$failed;
				nscan_log_warn(
					sprintf( __( 'Checksum mismatch: %s', 'ninjascanner' ), $tmpfile )
				);
			}
		} else {
			// Missing file:
			++$missing;
			nscan_log_warn(
				sprintf( __( 'Missing file: %s', 'ninjascanner' ), $tmpfile )
			);
		}
	}

	if ( $failed || $missing ) {
		$msg = sprintf(
			__('Fatal error: Some NinjaScanner files have been modified (%s) or are missing (%s). Please reinstall NinjaScanner or disable NinjaScanner files integrity checker. Aborting.', 'ninjascanner'),
			"x$failed",
			"x$missing"
		);
		$snapshot['error'] = $msg;
		nscan_log_error( $msg );
		// Delete cached version:
		unlink( NSCAN_HASHFILE );
		return false;
	}

	// Checksums match:
	return true;
}

// =====================================================================
// Check WordPress core files integrity by downloading it from
// wordpress.org or using its local cached copy.
// The copy will be kept locally until the garbage collector cron job
// deletes it.

function nscan_check_wordpress_integrity( $step, $step_msg ) {

	$nscan_options = get_option( 'nscan_options' );
	global $snapshot, $wp_version, $wp_local_package;

	if ( empty( $wp_local_package ) ) {
		$wp_zip = "wordpress-{$wp_version}.zip";
		$wp_zip_url = "https://wordpress.org/{$wp_zip}";
	} else {
		$wp_zip = "wordpress-{$wp_version}-{$wp_local_package}.zip";
		$wp_zip_url = "https://de.wordpress.org/{$wp_zip}";
	}

	// Download it if we don't have a copy in our cache:
	if (! file_exists( NSCAN_CACHEDIR ."/$wp_zip" ) ) {

		$msg = sprintf( __('Downloading %s from wordpress.org', 'ninjascanner'), $wp_zip );
		nscan_log_debug( $msg );
		file_put_contents( NSCAN_LOCKFILE, "$step::$step_msg ($msg)" );

		$res = wp_remote_get(
			$wp_zip_url,
			array(
				'timeout' => NSCAN_CURL_TIMEOUT,
				'httpversion' => '1.1' ,
				'user-agent' => 'Mozilla/5.0 (compatible; NinjaScanner/'.
										NSCAN_VERSION .'; WordPress/'. $wp_version . ')',
				'sslverify' => true
			)
		);
		if ( is_wp_error( $res ) ) {
			// Save error:
			$err = sprintf(
				__('%s. Skipping this step', 'ninjascanner'), $res->get_error_message()
			);
			$snapshot['step_error'][$step] = $err;
			nscan_log_error( $err );
			return false;
		}
		if ( $res['response']['code'] == 200 ) {
			// Save the ZIP file:
			file_put_contents( NSCAN_CACHEDIR ."/$wp_zip", $res['body'] );

		} else {
			nscan_log_warn( sprintf(
				__('HTTP Error %s. Skipping this step, you may try again later', 'ninjascanner'),
				(int)$res['response']['code']
			));
			return false;
		}

	// Use the local copy:
	} else {
		nscan_log_debug(
			sprintf( __('Using local cached copy (%s)', 'ninjascanner'), $wp_zip )
		);
	}

	$zip_files_list = array();
	if ( ( $zip_files_list = nscan_get_zip_files_list(  NSCAN_CACHEDIR ."/$wp_zip" ) ) === false ) {
		// Save error:
		$err = __('Unable to retrieve ZIP files list. Skipping this step', 'ninjascanner');
		$snapshot['step_error'][$step] = $err;
		nscan_log_error( $err );
		return false;
	}

	$msg = __('Comparing with original files', 'ninjascanner');
	file_put_contents( NSCAN_LOCKFILE, "$step::$step_msg ($msg)" );

	// Shall we only use the CRC checksum to check the integrity of the files?
	if (! empty( $nscan_options['scan_zipcrc'] ) ) {
		nscan_log_debug( __('Using CRC-32B checksum', 'ninjascanner') );

		foreach( $zip_files_list as $file => $checksum ) {

			// Don't check bundled themes/plugins, because the blog may use newer versions,
			// but still check the index.php of the themes, plugins & wp-content folders
			// as well as the "Hello_Dolly" plugin:
			if ( $file == 'wp-content/index.php' || $file == 'wp-content/themes/index.php' ) {
				$tmpfile = str_replace( 'wp-content', WP_CONTENT_DIR, $file );

			} elseif ( $file == 'wp-content/plugins/index.php' || $file == 'wp-content/plugins/hello.php' ) {
				$tmpfile = str_replace( 'wp-content/plugins', WP_PLUGIN_DIR, $file );

			} elseif ( strpos( $file, 'wp-content/plugins/' ) !== false || strpos( $file, 'wp-content/themes/' ) !== false ) {
				continue;

			} else {
				$tmpfile = ABSPATH . $file;
			}

			if ( isset( $snapshot['abspath'][$tmpfile] ) ) {
				// Checksum does not match:
				$crc32b = hexdec( hash_file( 'crc32b',  $tmpfile ) );
				if ( $crc32b !== $checksum ) {
					$snapshot['core_failed_checksum'][$tmpfile] = 1;
					nscan_log_warn(
						sprintf( __( 'Checksum mismatch: %s', 'ninjascanner' ), $tmpfile )
					);
					$snapshot['abspath'][$tmpfile]['type'] = 'core';
				} else {
					$snapshot['abspath'][$tmpfile]['v'] = 1;
				}
			}
			// Used to check for additional files uploaded in
			// the wp-admin & wp-includes folders and ABSPATH:
			if ( isset( $snapshot['core_unknown'][$tmpfile] ) ) {
				unset( $snapshot['core_unknown'][$tmpfile] );
			}
			if ( isset( $snapshot['core_unknown_root'][$tmpfile] ) ) {
				unset( $snapshot['core_unknown_root'][$tmpfile] );
			}
		}

	// Shall we extract the files from the archive?
	} else {
		// Extract the ZIP
		if ( nscan_extract_archive( NSCAN_CACHEDIR ."/$wp_zip", NSCAN_CACHEDIR ."/$wp_version" ) === false ) {
			// Save error:
			$err = __('Unable to extract ZIP archive. Skipping this step', 'ninjascanner');
			$snapshot['step_error'][$step] = $err;
			nscan_log_error( $err );
			return false;
		}

		// Select algo:
		if ( empty( $nscan_options['scan_checksum'] ) || $nscan_options['scan_checksum'] == 1 ) {
			$algo = 'md5';
		} elseif ( $nscan_options['scan_checksum'] == 2 ) {
			$algo = 'sha1';
		} else {
			$algo = 'sha256';
		}
		nscan_log_debug( sprintf( __('Using %s algo', 'ninjascanner'), $algo ) );

		// Compare local files with archive files:
		foreach( $zip_files_list as $file => $checksum ) {

			// Don't check bundled themes/plugins, because the blog may use newer versions,
			// but still check the index.php of the themes, plugins & wp-content folders
			// as well as the "Hello_Dolly" plugin:
			if ( $file == 'wp-content/index.php' || $file == 'wp-content/themes/index.php' ) {
				$tmpfile = str_replace( 'wp-content', WP_CONTENT_DIR, $file );

			} elseif ( $file == 'wp-content/plugins/index.php' || $file == 'wp-content/plugins/hello.php' ) {
				$tmpfile = str_replace( 'wp-content/plugins', WP_PLUGIN_DIR, $file );

			} elseif ( strpos( $file, 'wp-content/plugins/' ) !== false || strpos( $file, 'wp-content/themes/' ) !== false ) {
				continue;

			} else {
				$tmpfile = ABSPATH . $file;
			}

			// Make sure the file exists:
			if ( isset( $snapshot['abspath'][$tmpfile] ) ) {
				$local_file = hash_file( $algo, $tmpfile );
				$original_file = hash_file( $algo, NSCAN_CACHEDIR ."/$wp_version/wordpress/$file" );

				// Compare checksums:
				if ( $local_file !== $original_file ) {
					$snapshot['core_failed_checksum'][$tmpfile] = 1;
					nscan_log_warn(
						sprintf( __( 'Checksum mismatch: %s', 'ninjascanner' ), $tmpfile )
					);
					$snapshot['abspath'][$tmpfile]['type'] = 'core';
				} else {
					$snapshot['abspath'][$tmpfile]['v'] = 1;
				}

			}
			// Used to check for additional files uploaded in
			// the wp-admin & wp-includes folders and ABSPATH:
			if ( isset( $snapshot['core_unknown'][$tmpfile] ) ) {
				unset( $snapshot['core_unknown'][$tmpfile] );
			}
			if ( isset( $snapshot['core_unknown_root'][$tmpfile] ) ) {
				unset( $snapshot['core_unknown_root'][$tmpfile] );
			}
		}
		// Remove the extracted files/directories:
		nscan_remove_dir( NSCAN_CACHEDIR ."/$wp_version" );
	}

	if (! empty( $snapshot['core_failed_checksum'] ) ) {
		nscan_log_warn( sprintf(
			__('Total modified core files: %s', 'ninjascanner'),
			count( $snapshot['core_failed_checksum'] )
		));
		return false;
	}
	// Checksums match:
	return true;
}

// =====================================================================
// Exit (if requested) if we reached max_execution_time:

function nscan_check_max_exec_time( $nscan_options ) {

	if (! empty( $nscan_options['scan_incremental_forced'] ) &&
		defined('NSCAN_MAX_EXEC_TIME') && time() >= NSCAN_MAX_EXEC_TIME ) {
		nscan_log_debug( __('NSCAN_MAX_EXEC_TIME reached; exiting process and attempting to restart', 'ninjascanner') );
		define( 'NSCAN_RESTART', true );
		nscan_shutdown();
		exit;
	}
}

// =====================================================================
// Build the list of all files inside the ABSPATH.

function nscan_build_files_list( $scan_dir, $no_symlink, $warn_symlink,
	$warn_hidden, $warn_unreadable, $warn_binary, $wp_plugins, $wp_themes ) {

	global $snapshot, $ignored_files;

  if ( is_dir( $scan_dir ) && is_readable( $scan_dir ) ) {

		if ( $dh = opendir( $scan_dir ) ) {
			while ( FALSE !== ( $file = readdir($dh) ) ) {

				if ( $file == '.' || $file == '..' ) { continue; }
				if ( strpos( $scan_dir, NSCAN_QUARANTINE ) !== false ) {
					continue;
				}
				$full_path = $scan_dir . '/' . $file;

				// Check if it is in the ignored files list:
				if (! empty( $ignored_files[$full_path] ) ) {
					if ( $ignored_files[$full_path] == filectime( $full_path ) ) {
						continue;

					} else {
						unset( $ignored_files[$full_path] );
					}
				}

				if ( is_readable( $full_path ) ) {
					// Directory:
					if ( is_dir( $full_path ) ) {
						if ( is_link( $full_path ) ) {
							if ( $warn_symlink ) {
								$snapshot['core_symlink'][$full_path] = 1;
							}
							// Follow symlinks?
							if ( $no_symlink ) { continue; }
						}
						nscan_build_files_list( $full_path, $no_symlink, $warn_symlink,
						$warn_hidden, $warn_unreadable, $warn_binary, $wp_plugins, $wp_themes );
					// File:
					} elseif ( is_file( $full_path ) ) {
						if ( $warn_symlink && is_link( $full_path ) ) {
							$snapshot['core_symlink'][$full_path] = 1;
						}

						$snapshot['abspath'][$full_path][0] = filectime( $full_path );
						$snapshot['abspath'][$full_path][1] = filesize( $full_path );
						if ( strpos( $full_path, "$wp_plugins/" ) !== false ) {
							$str = substr( $full_path, strlen( $wp_plugins ) + 1 );
							$list = explode( '/', $str, 2 );
							// Don't add plugins/hello.php and plugins/index.php, we'll check them with core files:
							if ( $list[0] != 'hello.php' &&  $list[0] != 'index.php' && isset( $list[1] ) ) {
								$snapshot['plugins'][$list[0]][$list[1]] = 0;
							}
							continue;
						}
						if ( strpos( $full_path, "$wp_themes/" ) !== false ) {
							$str = substr( $full_path, strlen( $wp_themes ) + 1 );
							$list = explode( '/', $str, 2 );
							// Don't add themes/index.php, we'll check it with core files:
							if ( $list[0] != "index.php" && isset( $list[1] ) ) {
								$snapshot['themes'][$list[0]][$list[1]] = 0;
							}
							continue;
						}

						// Look for additional files among WP system files:
						if ( strpos( $scan_dir, ABSPATH .'wp-admin' ) !== false || strpos( $scan_dir, ABSPATH .'wp-includes' ) !== false ) {
							$snapshot['core_unknown'][$full_path] = 0;
						}

						// Look for additional files in the ABSPATH:
						if ( preg_match( '`^'. ABSPATH .'wp-[^/\\\]+\.ph(?:p(?:[34x7]|5\d?)?|t(?:ml)?)$`', $full_path ) ) {
							if ( $full_path != ABSPATH .'wp-config.php' ) {
								$snapshot['core_unknown_root'][$full_path] = 0;
							}
						}

						// Look for hidden PHP scripts:
						if ( $warn_hidden && $file[0] == '.' && preg_match( '/\.ph(?:p([34x7]|5\d?)?|t(ml)?)$/', $file ) ) {
							$snapshot['core_hidden'][$full_path] = 1;
						}
						// Look for executable files:
						if ( $warn_binary ) {
							$data = file_get_contents( $full_path, false, null, 0, 4 );
							// We only look for ELF, PE/NE/MZ headers:
							if (preg_match('`^(?:\x7F\x45\x4C\x46|\x4D\x5A)`', $data) ) {
								$snapshot['core_binary'][$full_path] = 1;
							}
						}
					}

				// Unreadable file/dir:
				} else {
					if ( $warn_unreadable ) {
						$snapshot['core_unreadable'][$full_path] = 1;
					}
				}
			}
			closedir( $dh );
		}
   }
}

// =====================================================================
// EOF
