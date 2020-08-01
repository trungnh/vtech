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

var nscan_interval = 0;
var nscan_error = 0;
if (typeof nscani18n === 'undefined') {
	var nscani18n = '{""}';
}
var nscan_all_cleared = '<tr style="background-color:#F9F9F9;height:30px">' +
	'<td class="ns-icon">' +
	'<span class="dashicons dashicons-marker ns-ok-file-icon"></span>' +
	'</td>' +
	'<td class="ns-file-centered">'+ nscani18n.no_problem +'</td>' +
	'</tr>';

// =====================================================================
// Start a scan.

function nscanjs_start_scan( nscan_milliseconds, nonce, steps ) {

	// Change buttons status
	jQuery('#start-scan').prop('disabled', true);
	jQuery('#cancel-scan').prop('disabled', false);

	// Hide report
	jQuery('#nscan-report-div').fadeOut();
	// Hide Summary div
	jQuery('#last-scan-div').slideUp();
	// Show progress bar
	jQuery('#scan-progress-div').slideDown();

	// Hide potential messages
	jQuery('#summary-message').slideUp();

	// Show running notice
	jQuery('#summary-running').slideDown();

	// Request a scanning process
	var data = {
		'action': 'nscan_on_demand',
		'nscan_nonce': nonce
	};
	jQuery.ajax( {
		type: "POST",
		url: ajaxurl,
		headers: {
			'Accept-Language':'en-US,en;q=0.5',
			'User-Agent':'Mozilla/5.0 (X11; Linux x86_64; rv:60.0)',
		},
		data: data,
		dataType: "text",
		success: function( response ) {
			response = jQuery.trim( response );
			if ( response != '200' ) {
				nscanjs_cancel_scan( false, nonce );
				jQuery('#summary-message').html( '<p>'+ response +'</p>' );
				jQuery('#summary-message').slideDown();
				return;
			}
		},
		// Display non-200 HTTP response
		error: function( xhr, status, err ) {
			nscanjs_cancel_scan( false, nonce );
			// If the site or wp-admin is password-protected, we inform the user
			// that they can enter their username/password in the settings page:
			if ( xhr.status == 401 ) {
				jQuery('#summary-message').html( '<p>'+ nscani18n.http_error +' '+ xhr.status +' '+ err +'.<br />'+ nscani18n.http_auth +'</p>' );
				jQuery('#summary-message').slideDown();

			} else if ( xhr.status != 0 ) {
				jQuery('#summary-message').html( '<p>'+ nscani18n.http_error +' '+ xhr.status +' '+ err +'.</p>' );
				jQuery('#summary-message').slideDown();
			}
			return;
		}
	});

	// Check scanning process status
	nscan_interval = setInterval( nscanjs_is_running.bind( null, nonce, steps ), nscan_milliseconds );

}

// =====================================================================
// Cancel a scan.

function nscanjs_cancel_scan( prompt, nonce ) {

	if ( prompt == true ) {
		if (! confirm( nscani18n.cancel_scan ) ) {
			return false;
		}
	}

	// Clear timer:
	if ( nscan_interval != 0 ) {
		clearInterval( nscan_interval );
	}

	// Send a cancel request:
	var data = {
		'action': 'nscan_cancel',
		'nscan_nonce': nonce
	};
	jQuery.post(ajaxurl, data, function(response) {
		response = jQuery.trim( response );
		if ( response != 200 ) {
			alert( response );
		}
	});

	// Show Summary div
	jQuery('#last-scan-div').slideDown();
	// Hide progress bar
	jQuery('#scan-progress-div').slideUp();
	// Hide running notice
	jQuery('#summary-running').slideUp();

	// Change buttons status
	jQuery('#start-scan').prop('disabled', false);
	jQuery('#cancel-scan').prop('disabled', true);

	// Reinitiliaze ribon and message status
	jQuery('#scan-progress-text').text( nscani18n.wait );
	jQuery('#ns-span-progress').css('width', '0%');
	jQuery('#ns-div-progress').html( '' );

}

// =====================================================================
// Send AJAX request to check if the scanner is running.

function nscanjs_is_running( nonce, steps ) {

	var data = {
		'action': 'nscan_check_status',
		'nscan_nonce': nonce
	};
	jQuery.post(ajaxurl, data, function(response) {

		// (stopped|running|error)::(steps)?::(message)?
		response = jQuery.trim( response );
		var res = response.split('::');

		// Scan is still running:
		if ( res[0] == 'running' ) {

			// Display progress bar:
			var percent = parseInt( res[1] * (100 / steps) );
			jQuery('#ns-span-progress').css('width', percent + '%');
			jQuery('#ns-div-progress').html( nscani18n.step + ' ' + res[1] + '/' + steps );
			jQuery('#scan-progress-text').text( res[2] + '...' );

		// Scan has stopped:
		} else if ( res[0] == 'stopped' ) {
			if ( nscan_interval != 0 ) {
				clearInterval( nscan_interval );
			}

			// Reload interface and display report:
			var string = window.location.href;
			if ( string.indexOf( '&view-report=1' ) !== -1 ) {
				location.reload();
			} else {
				window.location.href = window.location.href + '&view-report=1';
			}

		// Error:
		} else if ( res[0] == 'error' ) {
			jQuery('#summary-message').html( '<p>'+ res[2] +'</p>' );
			jQuery('#summary-message').slideDown();
			nscanjs_cancel_scan( false, nonce );

		// Don't know what happened :/
		} else {
			jQuery('#summary-message').html( '<p>'+ nscani18n.unknown_error +' '+ res[0] +'</p>' );
			jQuery('#summary-message').slideDown();
			nscanjs_cancel_scan( false, nonce );
		}
	});
}

// =====================================================================
// Scan report functions.

// Roll-up/unroll tables:
function nscanjs_roll_unroll( id ) {

	if ( jQuery('#table-report-' + id).css('display') == 'none' ) {
		jQuery('#table-report-' + id).slideDown();

	} else {
		jQuery('#table-report-' + id).slideUp();
	}
}

// View file info:
function nscanjs_file_info( id ) {

	if ( jQuery('#file-info-' + id).css('display') == 'none' ) {
		jQuery('#file-info-' + id).slideDown();

	} else {
		jQuery('#file-info-' + id).slideUp();
	}
}

// View file content:
function nscanjs_file_operation( file, what, nonce, id, table_id, signature ) {

	// View / compare file:
	if ( what == 'view' || what == 'compare' ) {

		// Note: "file" is already base64-encoded.
		var url = "?page=NinjaScanner&nscanop="+ what +"&file="+ encodeURIComponent( file )
					+"&nscanop_nonce="+ nonce;

		// Highlight signature:
		if ( what == 'view' && typeof signature !== 'undefined' ) {
			url += '&signature=' + encodeURIComponent( signature );
		}
		win =	window.open( url, 'nscanop');

	// Move the file to the quarantine folder:
	} else if ( what == 'quarantine' ) {

		var data = {
			'action': 'nscan_quarantine',
			'nscanop_nonce': nonce,
			'file': file
		};
		jQuery.post(ajaxurl, data, function(response) {

			response = jQuery.trim( response );
			if ( response == 'success' || response == '404' ) {
				jQuery('#hide-row-' + id).css('background-color', '#F08F8F');
				jQuery('#hide-row-' + id).fadeOut( 400 );
				var total_items = jQuery('#total-items-row-' + table_id).html();
				if ( total_items > 0 ) {
					jQuery('#total-items-row-' + table_id).html( --total_items );
				}
				if ( total_items < 1 ) {
					jQuery('#table-all-rows-' + table_id).hide().html( nscan_all_cleared ).fadeIn('slow');
					jQuery('#div-all-rows-' + table_id).css('height','41px');
					jQuery('#div-all-rows-' + table_id).css('resize','none');
				}

			} else {
				alert( response );
			}

		});

	// Restore the original file (core, plugin or theme):
	} else if ( what == 'restore' ) {

		var data = {
			'action': 'nscan_restore',
			'nscanop_nonce': nonce,
			'file': file
		};
		jQuery.post(ajaxurl, data, function(response) {

			response = jQuery.trim( response );
			if ( response == 'success' ) {
				jQuery('#hide-row-' + id).css('background-color', '#8FF08F');
				jQuery('#hide-row-' + id).fadeOut( 400 );
				var total_items = jQuery('#total-items-row-' + table_id).html();
				if ( total_items > 0 ) {
					jQuery('#total-items-row-' + table_id).html( --total_items );
				}
				if ( total_items < 1 ) {
					jQuery('#table-all-rows-' + table_id).hide().html( nscan_all_cleared ).fadeIn('slow');
					jQuery('#div-all-rows-' + table_id).css('height','41px');
					jQuery('#div-all-rows-' + table_id).css('resize','none');
				}

			} else {

				alert( response );
			}
		});

	// Ignore file:
	} else if ( what == 'ignore' ) {

		var data = {
			'action': 'nscan_ignore',
			'nscanop_nonce': nonce,
			'file': file
		};
		jQuery.post(ajaxurl, data, function(response) {

			response = jQuery.trim( response );
			if ( response == 'success' || response == '404' ) {
				jQuery('#hide-row-' + id).css('background-color', '#8FC9F0');
				jQuery('#hide-row-' + id).fadeOut( 400 );
				var total_items = jQuery('#total-items-row-' + table_id).html();
				if ( total_items > 0 ) {
					jQuery('#total-items-row-' + table_id).html( --total_items );
				}
				if ( total_items < 1 ) {
					jQuery('#table-all-rows-' + table_id).hide().html( nscan_all_cleared ).fadeIn('slow');
					jQuery('#div-all-rows-' + table_id).css('height','41px');
					jQuery('#div-all-rows-' + table_id).css('resize','none');
				}

			} else {
				alert( response );
			}
		});

	} else {
		alert( nscani18n.unknown_action );
	}
}

// View post/pages:
function nscanjs_view_post( post_id, dashboard_url ) {

	var url = dashboard_url + 'post.php?post=' + post_id + '&action=edit';
	win =	window.open( url, 'nscanop' );
}

// =====================================================================
// Settings page JS functions.

function nscanjs_toggle_settings(what) {
	if ( what == 1) {
		jQuery("#nscan-advanced-settings").slideDown();
		jQuery("#nscan-show-advanced-settings").hide();
		jQuery("#nscan-show-nerds-settings").show();
	} else {
		jQuery("#nscan-nerds-settings").slideDown();
		jQuery("#nscan-show-nerds-settings").hide();
	}
}

function nscanjs_slow_scan_disable(what) {
	if ( document.getElementById(what).checked == false ) {
		if ( confirm( nscani18n.slow_down_scan_disable ) ) {
			return true;
		}
		return false;
	}
}

function nscanjs_slow_scan_enable(what) {
	if ( document.getElementById(what).checked == true ) {
		if ( confirm( nscani18n.slow_down_scan_enable ) ) {
			return true;
		}
		return false;
	}
}

function nscanjs_force_restart_enable(what) {
	if ( document.getElementById(what).checked == true ) {
		if ( confirm( nscani18n.force_restart_enable ) ) {
			return true;
		}
		return false;
	}
}

function nscanjs_restore_settings() {
	if ( confirm( nscani18n.restore_settings ) ) {
		return true;
	}
	return false;
}

function nscanjs_clear_cache() {
	if ( confirm( nscani18n.clear_cache_now ) ) {
		return true;
	}
	return false;
}

// Verify the validity of the user's Google API key:
function nscanjs_gsb_check_key( apikey, nonce ) {

	if (! apikey ) {
		alert( nscani18n.empty_apikey );
		jQuery('#nsgsb').focus();
		return false;
	}
	if (! nonce ) {
		return false;
	}

	jQuery('#nsgsb-button').hide();
	jQuery('#nsgsb-gif').show();

	var data = {
		'action': 'nscan_checkapikey',
		'nscanop_nonce': nonce,
		'api_key': apikey
	};
	jQuery.post(ajaxurl, data, function(response) {

		response = jQuery.trim( response );
		if ( response == 'success' ) {
			alert( nscani18n.success_apikey );

		} else {
			alert( response );
			jQuery('#nsgsb').select();
		}

		jQuery('#nsgsb-gif').hide();
		jQuery('#nsgsb-button').show();

	});
	return true;
}

// =====================================================================
// Quarantine page.

function nscanjs_quarantine_form(what) {

	if ( document.getElementById('qf').selectedIndex == -1 ) {
		alert( nscani18n.select_elements );
		return false;
	}

	// Permanently delete files:
	if ( what == 1 ) {
		if ( confirm( nscani18n.permanently_delete ) ) {
			return true;
		}
	// Restore quarantined files:
	} else {
		if ( confirm( nscani18n.restore_file ) ) {
			return true;
		}
	}
	return false;
}

// =====================================================================
// Ignored files list page.

function nscanjs_remove_ignored() {

	if ( document.getElementById('if').selectedIndex == -1 ) {
		alert( nscani18n.select_elements );
		return false;
	}
}

// =====================================================================
// Filter the debugging log.

function nscanjs_filter_log() {

	// Create bitmask:
	var bitmask = 0;
	if ( document.nscanlogform.info.checked == true ) { bitmask += 1; }
	if ( document.nscanlogform.warn.checked == true ) { bitmask += 2; }
	if ( document.nscanlogform.error.checked == true ) { bitmask += 4; }
	if ( document.nscanlogform.debug.checked == true ) { bitmask += 8; }

	// Clear the textarea:
	document.nscanlogform.nscantxtlog.value = '';

	// Browser through our array and return only selected verbosity:
	var nscan_count = 0;
	for ( i = 0; i < nscan_array.length; ++i ) {
		var line = decodeURIComponent( nscan_array[i] );
		var line_array = line.split( '~~', 2 );
		if ( line_array[0] & bitmask ) {
			document.nscanlogform.nscantxtlog.value += line_array[1];
			++nscan_count;
		}
	}
	if ( nscan_count == 0 ) {
		document.nscanlogform.nscantxtlog.value = '\n  > ' + nscani18n.empty_log;
	}
}

// =====================================================================
// Highlight code.

function nscanjs_highlight() {

	var nscan_content = document.getElementById('nscan-highlight').innerHTML;
	nscan_content = nscan_content.replace(/NSCANFOO/g, '<font style="background-color:yellow">');
	nscan_content = nscan_content.replace(/NSCANBAR/g, '</font>');
	document.getElementById('nscan-highlight').innerHTML = nscan_content;

}

// =====================================================================
// EOF
