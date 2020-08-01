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
 +=====================================================================+
*/

if (! defined( 'ABSPATH' ) ) { die( 'Forbidden' ); }

$nscan_options = get_option( 'nscan_options' );

$message = __('NinjaScanner is running in the background.', 'ninjascanner') . ' ' .
	__('Meanwhile, you can leave this page and keep working on your blog as usual, or even log out of the WordPress dashboard.', 'ninjascanner');
if (! empty( $nscan_options['admin_email'] ) ) {
	$message .=  ' ' . sprintf( __('A report will be sent to %s as soon as the scan has finished.', 'ninjascanner' ), '<code>'. htmlspecialchars( $nscan_options['admin_email'] ) .'</code>' );
}

$res = nscan_is_scan_running();
if ( $res[0] == 1 ) {
	$nscan_is_running = 1;
	echo '<div class="notice-info notice is-dismissible" id="summary-running"><p>'. $message .'</p></div>';

} else {
	$nscan_is_running = 0;
}

// View scan report
if (! empty( $_REQUEST['view-report'] ) ) {
	$viewing_report = 1;
	$report = array();
	require_once __DIR__ . '/report_html.php';
	$report = html_report();
	if (! empty( $report['error'] ) ) {
		$message = $report['error'];
		echo '<div class="error is-dismissible notice"><p>'. $report['error'] .'</p></div>';
	}
}

// AJAX security nonce:
$nscan_nonce = wp_create_nonce( 'nscan_on_demand_nonce' );

// Used to display running messages while scanning (e.g., scan errors etc):
echo '<div class="error notice" style="display:none" id="summary-message"><p></p></div>';
echo '<div class="notice-info notice is-dismissible" style="display:none" id="summary-running"><p>'. $message .'</p></div>';

echo nscan_display_tabs( 1 );

if ( nscan_is_valid() > 0 ) {
	$premium = '';
} else {
	$premium = ' <sup><font color="#D60404">'.
	__('Premium only', 'ninjascanner'). '</font></sup>';
}

?>
<form method="post">

	<?php
	// Check and display scan status or the last scan date/time:
	nscan_get_blogtimezone();
	$scan_status = __('None', 'ninjascanner');

	if ( $nscan_is_running ) {
		$show_status_bar = '';
		$show_last_scan = ' style="display:none"';
		$scan_button = ' disabled="disabled"';
		$cancel_button = '';

	} else {
		if (! isset( $viewing_report ) ) {
			$show_status_bar = ' style="display:none"';
			$show_last_scan = '';
			$scan_button = '';
			$cancel_button = ' disabled="disabled"';
			// Last scan date:
			if ( file_exists( NSCAN_SNAPSHOT ) ) {
				$ctime = filemtime( NSCAN_SNAPSHOT );
				if ( date( 'Y-m-d' ) == date( 'Y-m-d', $ctime ) ) {
					$scan_status = __('Today', 'ninjascanner') . date_i18n( ' @ g:i A', $ctime );
				} else {
					$scan_status = date_i18n( 'F d, Y @ g:i A', $ctime );
				}
			}
		} else {
			$show_status_bar = ' style="display:none"';
			$show_last_scan = ' style="display:none"';
			$scan_button = '';
			$cancel_button = ' disabled="disabled"';
		}
	}

	// Check for next scheduled scan:
	$ns_msg = __('None', 'ninjascanner');
	if ( $nextcron = wp_next_scheduled('nscan_scheduled_scan') ) {
		$sched = new DateTime( date( 'M d, Y H:i:s', $nextcron ) );
		$now = new DateTime( date( 'M d, Y H:i:s', time() ) );
		$diff = $now->diff( $sched );
		$day    = sprintf( _n( '%s day', '%s days', $diff->format('%a') % 7, 'ninjascanner' ), $diff->format('%a') % 7 );
		$hour   = sprintf( _n( '%s hour', '%s hours', $diff->format('%h'), 'ninjascanner' ), $diff->format('%h') );
		$minute = sprintf( _n( '%s minute', '%s minutes', $diff->format('%i'), 'ninjascanner' ), $diff->format('%i') );
		$second = sprintf( _n( '%s second', '%s seconds', $diff->format('%s'), 'ninjascanner' ), $diff->format('%s') );
		$ns_msg = sprintf( __('In approximately %s, %s, %s and %s.', 'ninjascanner'), $day , $hour, $minute, $second );
	}

	?>
	<div id="last-scan-div"<?php echo $show_last_scan ?>>
		<table class="form-table">
			<tr>
				<th scope="row"><?php _e('Last scan', 'ninjascanner') ?></th>
				<td><?php
					echo '<p>' . $scan_status . '</p>';
					// Display a button to view last scan report only
					// if a scan is not currenly running:
					if ( file_exists( NSCAN_SNAPSHOT ) && ! $nscan_is_running ) {
					?>
						<p><input class="button-secondary" name="view-report" value="<?php _e('View Scan Report', 'ninjascanner') ?>" type="submit"></p>
					<?php
					}
				?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php _e('Next scheduled scan', 'ninjascanner') ?></th>
				<td><?php echo $ns_msg; echo $premium; ?></td>
			</tr>
		</table>
	</div>

	<br />

	<input type="button" id="start-scan" onClick="nscanjs_start_scan(<?php echo (int) NSCAN_MILLISECONDS .",'{$nscan_nonce}',". NS_TOTAL_STEPS  ?>)" name="start-scan" class="button-primary" value="<?php _e('Scan Your Blog', 'ninjascanner') ?> »"<?php echo $scan_button ?> />
	&nbsp;&nbsp;&nbsp;&nbsp;
	<input type="button" id="cancel-scan" onClick="nscanjs_cancel_scan(true, '<?php echo $nscan_nonce; ?>')" name="cancel-scan" class="button-secondary" value="<?php _e('Cancel Scan', 'ninjascanner') ?>"<?php echo $cancel_button ?> />

</form>

<div id="scan-progress-div"<?php echo $show_status_bar ?>>
	<br />
	<div class="scan-progress-bar"><span id="ns-span-progress" style="width:0%"></span><div id="ns-div-progress" class="progress-text"></div></div>
	<div><img style="vertical-align:middle;" src="<?php echo plugins_url() ?>/ninjascanner/static/progress.gif" />&nbsp;&nbsp;<font id="scan-progress-text"><?php _e('Please wait...', 'ninjascanner') ?></font></div>
</div>
<?php
// If scan is already running when loading this page, display its status:
if ( $nscan_is_running ) {
?>
	<script type="text/javascript" >
		var nscan_milliseconds = <?php echo (int) NSCAN_MILLISECONDS; ?>;
		var nonce = '<?php echo $nscan_nonce; ?>';
		var steps = <?php echo (int) NS_TOTAL_STEPS; ?>;
		jQuery(document).ready(function($) {
			nscan_interval = setInterval( nscanjs_is_running.bind( null, nonce, steps ), nscan_milliseconds );
		});
	</script>
<?php
}

// Don't delete (see file_quarantine.php):
echo '<!-- NinjaScanner Quarantine -->';

if (! empty( $report['body'] ) && empty( $report['error'] ) ) {
	?>
	<div id="nscan-report-div">
		<br />
		<?php echo $report['body']; ?>
	</div>
	<?php
}

// =====================================================================
// EOF
