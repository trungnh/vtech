<?php // NinjaFirewall's dropins.php ~ Do not delete!
if (! defined( 'NFW_ENGINE_VERSION' ) ) { die( 'Forbidden' ); }
// ---------------------------------------------------------------------
if ( isset( $_REQUEST['action'] ) ) {

	if ( is_super_admin() ) { return; }

	if ( in_array( $_REQUEST['action'], array( 'fusion_builder_update_layout', 'fusion_builder_delete_layout', 'fusion_builder_save_layout' ) ) ) {
		if ( $_REQUEST['action'] == 'fusion_builder_delete_layout' ) {
			if (! empty( $_POST['fusion_layout_id'] ) && current_user_can( 'delete_post', $_POST['fusion_layout_id'] ) ) {
				return;
			}
		} elseif ( $_REQUEST['action'] == 'fusion_builder_save_layout' ) {
			if ( current_user_can( 'edit_posts' ) && in_array( @$_POST['fusion_layout_post_type'], array('fusion_template', 'fusion_element') ) ) {
				return;
			}
		} elseif ( $_REQUEST['action'] == 'fusion_builder_update_layout' ) {
			if (! empty( $_POST['fusion_layout_id'] ) && current_user_can( 'edit_post', $_POST['fusion_layout_id'] ) ) {
				return;
			}
		}
		nfw_dropin_block( "REQUEST:action = {$_REQUEST['action']}", 3, 1601);

	} elseif ( $_REQUEST['action'] == 'uael_register_user' ) {
		if (! get_option( 'users_can_register' ) ) {
			nfw_dropin_block( "REQUEST:action = {$_REQUEST['action']}", 3, 1602 );
		}
	// 2020-05-28
	} elseif ( $_REQUEST['action'] == 'pagelayer_save_content' ) {
		if (! empty( $_GET['postID'] ) ) {
			if ( nfw_can_edit( $_GET['postID'] ) == true ) {
				return;
			}
			nfw_dropin_block( "REQUEST:action = {$_REQUEST['action']}", 3, 1603 );
		}

	} else {
		$nfw_act_hash = sha1( $_REQUEST['action'] );

		// 2020-05-29
		if ( in_array( $nfw_act_hash, array( 'bbd520db4ea2ce37b6c643a45ec41ee7d3442d13', '7d1f331c2a23900e6e8dd149a84c58375894fafd', '6af055f5d13282051b6b6b6ed7fde578d02006bf' ) ) ) {
			if ( current_user_can('manage_options') ) {
				return;
			}
			nfw_dropin_block( "REQUEST:action = {$_REQUEST['action']}", 3, 1604 );
		} elseif ( $nfw_act_hash == 'ffc236eee305fe95922f251227182b1119d55d8a' && isset( $_POST['id'] ) ) {
			if ( nfw_can_edit( $_POST['id'] ) == true ) {
				return;
			}
			nfw_dropin_block( "REQUEST:action = {$_REQUEST['action']}", 3, 1605 );

		// 2020-05-29
		} elseif ( in_array( $nfw_act_hash, array( '55b6d787e58fd473c5122ba82eb35918f1983efb', '0b71d91c344911a9afa178e0accf9a85f479e5c7', '740f19c7c26d012d9aedf2ee2e5155adad8c7347', '9633391dc1279e138048e485ba3c0e86419ac519', '7ca0c8c7b219d43dddf78df42ada01d651888b6d', 'c6228eb1447f99d77ece0e09b7e66e48ea70d761', '5095f8c9da36ae83535c89a3b5ece239f8c80197',
		'ac1834a3a3eab795aa3ce6e94052a4af72c6a873', 'd3e7c6ce6088f20e531a2ad8eaa713bc87bf9113' ) ) ) {
			if ( current_user_can('brizy_edit_whole_page') || current_user_can('brizy_edit_content_only') ) {
				return;
			}
			nfw_dropin_block( "REQUEST:action = {$_REQUEST['action']}", 3, 1606);

		// 2020-06-04
		} elseif ( in_array( $nfw_act_hash, array( '92b22d9fb1cbee75938eb8831132a82dd8d13dbd', '0cbe0bdd5014b7400538ec3f7f2a9667df8cbede', 'c2690ab28caced28908121bce67cdd627d35a734' ) ) ) {
			nfw_dropin_block( "REQUEST:action = {$_REQUEST['action']}", 3, 1607);

		// 2020-07-16
		} elseif ( $nfw_act_hash == '08567ba37e087eb08de4d2340192e07616a72d31' && isset( $_POST['id'] ) ) {
			$post = get_post( (int)$_POST['id'] );
			if (! empty( $post->post_password ) || $post->post_status != 'publish' ) {
				nfw_dropin_block( "REQUEST:action = {$_REQUEST['action']}", 3, 1608);
			}
		} elseif ( $nfw_act_hash == 'c96f029c026116ebcf31aa3305c1f3c31600dfbb' ) {
			if (! current_user_can('publish_pages') ) {
				nfw_dropin_block( "REQUEST:action = {$_REQUEST['action']}", 3, 1609);
			}
		} elseif( $nfw_act_hash == '0ee6ca2cf012a9e859864a718d70dde5ebb01ff5' ) {
			if (! empty( $_REQUEST['status'] ) && $_REQUEST['status'] === 'disable-cmp' && get_option('niteoCS_counter_date' ) > time() ) {
				nfw_dropin_block( "REQUEST:action = {$_REQUEST['action']}", 3, 1610);
			}
		}

	}
}
// ---------------------------------------------------------------------
function nfw_dropin_block( $message, $level, $rule ) {
	nfw_log2('WP vulnerability', $message, $level, $rule);
	exit("Error: please contact the administrator.");
}
// ---------------------------------------------------------------------
function nfw_can_edit( $postid ) {
	$type = get_post_type( (int) $postid );
	if ( ( $type == 'page' || $type == 'post' ) && ! current_user_can( "edit_{$type}", $postid ) ) {
		return false;
	}
	return true;
}
// ---------------------------------------------------------------------
