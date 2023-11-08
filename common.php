<?php
// common functions for Standard and Cloud plugins

// TODO: make sure to update timestamp field in table for image record
// TODO: port scan optimizations from core, and do the batch insert record stuff for more resiliant processing

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ewwwio_memory( $function ) {
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) ) {
		global $ewww_memory;
//		$ewww_memory .= $function . ': ' . memory_get_usage(true) . "\n";
	}
}

if ( ! function_exists( 'boolval' ) ) {
	function boolval( $value ) {
		return (bool) $value;
	}
}

// function to check if set_time_limit() is disabled
function ewww_image_optimizer_stl_check() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ewww_image_optimizer_safemode_check() ) {
                return false;
        }
	if ( defined( 'EWWW_IMAGE_OPTIMIZER_DISABLE_STL' ) && EWWW_IMAGE_OPTIMIZER_DISABLE_STL ) {
                ewwwio_debug_message( 'stl disabled by user' );
                return false;
        }
	$disabled = ini_get('disable_functions');
	ewwwio_debug_message( "disable_functions = $disabled" );
	if ( preg_match( '/set_time_limit/', $disabled ) ) {
		ewwwio_memory( __FUNCTION__ );
		return false;
	} elseif ( function_exists( 'set_time_limit' ) ) {
		ewwwio_memory( __FUNCTION__ );
		return true;
	} else {
		ewwwio_debug_message( 'set_time_limit does not exist' );
                return false;
        }
}

/**
 * Plugin initialization function
 */
function ewww_image_optimizer_upgrade() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	ewwwio_memory( __FUNCTION__ );
	if ( get_option( 'ewww_image_optimizer_version' ) < EWWW_IMAGE_OPTIMIZER_VERSION ) {
		ewww_image_optimizer_set_defaults();
		update_option( 'ewww_image_optimizer_version', EWWW_IMAGE_OPTIMIZER_VERSION );
	}
	ewwwio_memory( __FUNCTION__ );
//	ewww_image_optimizer_debug_log();
}

// Plugin initialization for admin area
function ewww_image_optimizer_admin_init() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	ewwwio_memory( __FUNCTION__ );
	ewww_image_optimizer_cloud_init();
	ewww_image_optimizer_upgrade();

	ewww_image_optimizer_exec_init();
	// require the files that do the bulk processing 
	require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'aux-optimize.php' );
	ewwwio_memory( __FUNCTION__ );
//	ewww_image_optimizer_debug_log();
}

// sets all the tool constants to false
function ewww_image_optimizer_disable_tools() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_JPEGTRAN' ) ) {
		define('EWWW_IMAGE_OPTIMIZER_JPEGTRAN', false);
	}
	if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_OPTIPNG' ) ) {
		define('EWWW_IMAGE_OPTIMIZER_OPTIPNG', false);
	}
	if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_PNGOUT' ) ) {
		define('EWWW_IMAGE_OPTIMIZER_PNGOUT', false);
	}
	if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_GIFSICLE' ) ) {
		define('EWWW_IMAGE_OPTIMIZER_GIFSICLE', false);
	}
	if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_PNGQUANT' ) ) {
		define('EWWW_IMAGE_OPTIMIZER_PNGQUANT', false);
	}
	if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_WEBP' ) ) {
		define('EWWW_IMAGE_OPTIMIZER_WEBP', false);
	}
	ewwwio_memory( __FUNCTION__ );
}

// generates css include for progressbars to match admin style
function ewww_image_optimizer_progressbar_style() {
	wp_add_inline_style( 'jquery-ui-progressbar', ".ui-widget-header { background-color: " . ewww_image_optimizer_admin_background() . "; }" );
	ewwwio_memory( __FUNCTION__ );
}

// determines the background color to use based on the selected theme
function ewww_image_optimizer_admin_background() {
	if ( function_exists( 'wp_add_inline_style' ) ) {
		$user_info = wp_get_current_user();
		switch( $user_info->admin_color ) {
			case 'midnight':
				return "#e14d43";
			case 'blue':
				return "#096484";
			case 'light':
				return "#04a4cc";
			case 'ectoplasm':
				return "#a3b745";
			case 'coffee':
				return "#c7a589";
			case 'ocean':
				return "#9ebaa0";
			case 'sunrise':
				return "#dd823b";
			default:
				return "#0073aa";
		}
	}
	ewwwio_memory( __FUNCTION__ );
}

// tells WP to ignore the 'large network' detection by filtering the results of wp_is_large_network()
function ewww_image_optimizer_large_network() {
	return false;
}

// lets the user know their network settings have been saved
function ewww_image_optimizer_network_settings_saved() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	echo "<div id='ewww-image-optimizer-settings-saved' class='updated fade'><p><strong>" . esc_html__('Settings saved', EWWW_IMAGE_OPTIMIZER_DOMAIN) . ".</strong></p></div>";
}

// adds a global settings page to the network admin settings menu
function ewww_image_optimizer_network_admin_menu() {
	if ( ! function_exists( 'is_plugin_active_for_network' ) && is_multisite() ) {
		// need to include the plugin library for the is_plugin_active function
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}
	if ( is_multisite() && is_plugin_active_for_network( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE_REL ) ) {
		// add options page to the settings menu
		$permissions = apply_filters( 'ewww_image_optimizer_superadmin_permissions', '' );
		$ewww_network_options_page = add_submenu_page(
			'settings.php',				//slug of parent
			'EWWW Image Optimizer',			//Title
			'EWWW Image Optimizer',			//Sub-menu title
			$permissions,				//Security
			EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE,	//File to open
			'ewww_image_optimizer_options'		//Function to call
		);
	} 
}

// adds the bulk optimize and settings page to the admin menu
function ewww_image_optimizer_admin_menu() {
	$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
	// adds bulk optimize to the media library menu
	$ewww_bulk_page = add_media_page( esc_html__( 'Bulk Optimize', EWWW_IMAGE_OPTIMIZER_DOMAIN ), esc_html__( 'Bulk Optimize', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $permissions, 'ewww-image-optimizer-bulk', 'ewww_image_optimizer_bulk_preview' );
	$ewww_unoptimized_page = add_media_page( esc_html__( 'Unoptimized Images', EWWW_IMAGE_OPTIMIZER_DOMAIN ), esc_html__( 'Unoptimized Images', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $permissions, 'ewww-image-optimizer-unoptimized', 'ewww_image_optimizer_display_unoptimized_media' );
	$ewww_webp_migrate_page = add_submenu_page( null, esc_html__( 'Migrate WebP Images', EWWW_IMAGE_OPTIMIZER_DOMAIN ), esc_html__( 'Migrate WebP Images', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $permissions, 'ewww-image-optimizer-webp-migrate', 'ewww_image_optimizer_webp_migrate_preview' );
	if ( ! function_exists( 'is_plugin_active' ) ) {
		// need to include the plugin library for the is_plugin_active function
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}
	if ( ! is_plugin_active_for_network( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE_REL ) ) {
		// add options page to the settings menu
		$ewww_options_page = add_options_page(
			'EWWW Image Optimizer',		//Title
			'EWWW Image Optimizer',		//Sub-menu title
			apply_filters( 'ewww_image_optimizer_admin_permissions', '' ),		//Security
			EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE,			//File to open
			'ewww_image_optimizer_options'	//Function to call
		);
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) ) {
		add_media_page( esc_html__( 'Dynamic Image Debugging', EWWW_IMAGE_OPTIMIZER_DOMAIN ), esc_html__( 'Dynamic Image Debugging', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $permissions, 'ewww-image-optimizer-dynamic-debug', 'ewww_image_optimizer_dynamic_image_debug' );
	}
	if ( is_plugin_active( 'image-store/ImStore.php' ) || is_plugin_active_for_network( 'image-store/ImStore.php' ) ) {
		$ims_menu ='edit.php?post_type=ims_gallery';
		$ewww_ims_page = add_submenu_page( $ims_menu, esc_html__( 'Image Store Optimize', EWWW_IMAGE_OPTIMIZER_DOMAIN ), esc_html__( 'Optimize', EWWW_IMAGE_OPTIMIZER_DOMAIN ), 'ims_change_settings', 'ewww-ims-optimize', 'ewww_image_optimizer_ims');
//		add_action( 'admin_footer-' . $ewww_ims_page, 'ewww_image_optimizer_debug' );
	}
}

// enqueue custom jquery stylesheet for bulk optimizer
function ewww_image_optimizer_media_scripts( $hook ) {
	if ( $hook == 'upload.php' ) {
		wp_enqueue_script( 'jquery-ui-tooltip' );
		wp_enqueue_style( 'jquery-ui-tooltip-custom', plugins_url( '/includes/jquery-ui-1.10.1.custom.css', __FILE__ ) );
	}
}

// used to output debug messages to a logfile in the plugin folder in cases where output to the screen is a bad idea
function ewww_image_optimizer_debug_log() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $ewww_debug;
	if (! empty( $ewww_debug ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) ) {
		$timestamp = date( 'y-m-d h:i:s.u' ) . "\n";
		if ( ! file_exists( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'debug.log' ) ) {
			touch( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'debug.log' );
		}
		$ewww_debug_log = str_replace( '<br>', "\n", $ewww_debug );
		file_put_contents( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'debug.log', $timestamp . $ewww_debug_log, FILE_APPEND );
	}
	$ewww_debug = '';
	ewwwio_memory( __FUNCTION__ );
}

// adds a link on the Plugins page for the EWWW IO settings
function ewww_image_optimizer_settings_link( $links ) {
	if ( ! function_exists( 'is_plugin_active_for_network' ) && is_multisite() ) {
		// need to include the plugin library for the is_plugin_active function
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}
	// load the html for the settings link
	if ( is_multisite() && is_plugin_active_for_network( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE_REL ) ) {
		$settings_link = '<a href="network/settings.php?page=' . plugin_basename( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE ) . '">' . esc_html__( 'Settings', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . '</a>';
	} else {
		$settings_link = '<a href="options-general.php?page=' . plugin_basename( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE ) . '">' . esc_html__( 'Settings', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . '</a>';
	}
	// load the settings link into the plugin links array
	array_unshift( $links, $settings_link );
	// send back the plugin links array
	return $links;
}

// check for GD support of both PNG and JPG
function ewww_image_optimizer_gd_support() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( function_exists( 'gd_info' ) ) {
		$gd_support = gd_info();
		ewwwio_debug_message( "GD found, supports:" ); 
		foreach ( $gd_support as $supports => $supported ) {
			 ewwwio_debug_message( "$supports: $supported" );
		}
		ewwwio_memory( __FUNCTION__ );
		if ( ( ! empty( $gd_support["JPEG Support"] ) || ! empty( $gd_support["JPG Support"] ) ) && ! empty( $gd_support["PNG Support"] ) ) {
			return TRUE;
		}
	}
	return FALSE;
}

// check for IMagick support of both PNG and JPG
function ewww_image_optimizer_imagick_support() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( extension_loaded( 'imagick' ) ) {
		$imagick = new Imagick();
		$formats = $imagick->queryFormats();
		if ( in_array( 'PNG', $formats ) && in_array( 'JPG', $formats ) ) {
			return true;
		}
	}
	return false;
}

// check for IMagick support of both PNG and JPG
function ewww_image_optimizer_gmagick_support() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( extension_loaded( 'gmagick' ) ) {
		$gmagick = new Gmagick();
		$formats = $gmagick->queryFormats();
		if ( in_array( 'PNG', $formats ) && in_array( 'JPG', $formats ) ) {
			return true;
		}
	}
	return false;
}

function ewww_image_optimizer_disable_resizes_sanitize( $disabled_resizes ) {
	if ( is_array( $disabled_resizes ) ) {
		return $disabled_resizes;
	} else {
		return '';
	}
}

function ewww_image_optimizer_aux_paths_sanitize( $input ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if (empty($input)) {
		return '';
	}
	$path_array = array();
	$paths = explode("\n", $input);
	foreach ($paths as $path) {
		$path = sanitize_text_field( $path );
		ewwwio_debug_message( "validating auxiliary path: $path" );
		// retrieve the location of the wordpress upload folder
		$upload_dir = apply_filters( 'ewww_image_optimizer_folder_restriction', wp_upload_dir() );
		// retrieve the path of the upload folder
		$upload_path = trailingslashit($upload_dir['basedir']);
		if ( is_dir( $path ) && ( strpos( $path, ABSPATH ) === 0 || strpos( $path, $upload_path ) === 0 ) ) {
			$path_array[] = $path;
		}
	}
//	ewww_image_optimizer_debug_log();
	ewwwio_memory( __FUNCTION__ );
	return $path_array;
}

// replacement for escapeshellarg() that won't kill non-ASCII characters
function ewww_image_optimizer_escapeshellarg( $arg ) {
	if ( PHP_OS === 'WINNT' ) {
		$safe_arg = '"' . $arg . '"';
	} else {
		$safe_arg = "'" . str_replace("'", "'\"'\"'", $arg) . "'";
	}
	ewwwio_memory( __FUNCTION__ );
	return $safe_arg;
}

// Retrieves/sanitizes jpg background fill setting or returns null for png2jpg conversions
function ewww_image_optimizer_jpg_background( $background = null ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( $background === null ) {
		// retrieve the user-supplied value for jpg background color
		$background = ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_background' );
	}
	//verify that the supplied value is in hex notation
	if ( preg_match( '/^\#*([0-9a-fA-F]){6}$/', $background ) ) {
		// we remove a leading # symbol, since we take care of it later
		preg_replace( '/#/', '', $background );
		// send back the verified, cleaned-up background color
		ewwwio_debug_message( "background: $background" );
		ewwwio_memory( __FUNCTION__ );
		return $background;
	} else {
		// send back a blank value
		ewwwio_memory( __FUNCTION__ );
		return NULL;
	}
}

// Retrieves/sanitizes the jpg quality setting for png2jpg conversion or returns null
function ewww_image_optimizer_jpg_quality( $quality = null ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( $quality === null ) {
		// retrieve the user-supplied value for jpg quality
		$quality = ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_quality' );
	}
	// verify that the quality level is an integer, 1-100
	if ( preg_match( '/^(100|[1-9][0-9]?)$/', $quality ) ) {
		ewwwio_debug_message( "quality: $quality" );
		// send back the valid quality level
		ewwwio_memory( __FUNCTION__ );
		return $quality;
	} else {
		// send back nothing
		ewwwio_memory( __FUNCTION__ );
		return NULL;
	}
}

function ewww_image_optimizer_set_jpg_quality( $quality ) {
	$new_quality = ewww_image_optimizer_jpg_quality();
	if ( ! empty( $new_quality ) ) {
		return $new_quality;
	}
	return $quality;
}
		

// check filesize, and prevent errors by ensuring file exists, and that the cache has been cleared
function ewww_image_optimizer_filesize( $file ) {
	if ( is_file( $file ) ) {
		// flush the cache for filesize
		clearstatcache();
		// find out the size of the new PNG file
		return filesize( $file );
	} else {
		return 0;
	}
}

/**
 * Manually process an image from the Media Library
 */
function ewww_image_optimizer_manual() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $ewww_defer;
	$ewww_defer = false;
	// check permissions of current user
	$permissions = apply_filters( 'ewww_image_optimizer_manual_permissions', '' );
	if ( FALSE === current_user_can( $permissions ) ) {
		// display error message if insufficient permissions
		wp_die( esc_html__( 'You do not have permission to optimize images.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
	}
	// make sure we didn't accidentally get to this page without an attachment to work on
	if ( FALSE === isset($_GET['ewww_attachment_ID'])) {
		// display an error message since we don't have anything to work on
		wp_die( esc_html__('No attachment ID was provided.', EWWW_IMAGE_OPTIMIZER_DOMAIN));
	}
	session_write_close();
	// store the attachment ID value
	$attachment_ID = intval($_GET['ewww_attachment_ID']);
	if ( empty( $_REQUEST['ewww_manual_nonce'] ) || ! wp_verify_nonce( $_REQUEST['ewww_manual_nonce'], "ewww-manual-$attachment_ID" ) ) {
		wp_die( esc_html__( 'Access denied.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
	}
	// retrieve the existing attachment metadata
	$original_meta = wp_get_attachment_metadata( $attachment_ID );
	// if the call was to optimize...
	if ($_REQUEST['action'] === 'ewww_image_optimizer_manual_optimize') {
		// call the optimize from metadata function and store the resulting new metadata
		$new_meta = ewww_image_optimizer_resize_from_meta_data($original_meta, $attachment_ID);
	} elseif ($_REQUEST['action'] === 'ewww_image_optimizer_manual_restore') {
		$new_meta = ewww_image_optimizer_restore_from_meta_data($original_meta, $attachment_ID);
	}
	global $ewww_attachment;
	$ewww_attachment['id'] = $attachment_ID;
	$ewww_attachment['meta'] = $new_meta;
	add_filter( 'w3tc_cdn_update_attachment_metadata', 'ewww_image_optimizer_w3tc_update_files' );
	// update the attachment metadata in the database
	wp_update_attachment_metadata( $attachment_ID, $new_meta );
	ewww_image_optimizer_debug_log();
	// store the referring webpage location
	$sendback = wp_get_referer();
	// sanitize the referring webpage location
	$sendback = preg_replace('|[^a-z0-9-~+_.?#=&;,/:]|i', '', $sendback);
	// send the user back where they came from
	wp_redirect( $sendback );
	// we are done, nothing to see here
	ewwwio_memory( __FUNCTION__ );
	exit(0);
}

/**
 * Manually restore a converted image
 */
function ewww_image_optimizer_restore_from_meta_data( $meta, $id ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// get the filepath
	list( $file_path, $upload_path ) = ewww_image_optimizer_attachment_path( $meta, $id );
	$file_path = get_attached_file( $id );
	if ( ! empty( $meta['converted'] ) ) {
		if ( file_exists( $meta['orig_file'] ) ) {
			// update the filename in the metadata
			$meta['file'] = $meta['orig_file'];
			// update the optimization results in the metadata
			$meta['ewww_image_optimizer'] = __( 'Original Restored', EWWW_IMAGE_OPTIMIZER_DOMAIN );
			$meta['orig_file'] = $file_path;
			$meta['real_orig_file'] = $file_path;
			$meta['converted'] = 0;
			unlink( $meta['orig_file'] );
			unset( $meta['orig_file'] );
			$meta['file'] = str_replace($upload_path, '', $meta['file']);
			// if we don't already have the update attachment filter
			if (FALSE === has_filter('wp_update_attachment_metadata', 'ewww_image_optimizer_update_attachment'))
				// add the update attachment filter
				add_filter('wp_update_attachment_metadata', 'ewww_image_optimizer_update_attachment', 10, 2);
		} else {
			remove_filter('wp_update_attachment_metadata', 'ewww_image_optimizer_update_attachment', 10);
		}
	}
	if ( isset( $meta['sizes'] ) ) {
		// process each resized version
		$processed = array();
		// meta sizes don't contain a path, so we calculate one
		$base_dir = trailingslashit( dirname( $file_path ) );
		foreach( $meta['sizes'] as $size => $data ) {
			// check through all the sizes we've processed so far
			foreach( $processed as $proc => $scan ) {
				// if a previous resize had identical dimensions
				if ( $scan['height'] == $data['height'] && $scan['width'] == $data['width'] && isset( $meta['sizes'][ $proc ]['converted'] ) ) {
					// point this resize at the same image as the previous one
					$meta['sizes'][ $size ]['file'] = $meta['sizes'][ $proc ]['file'];
				}
			}
			if ( isset( $data['converted'] ) ) {
				// if this is a unique size
				if ( file_exists( $base_dir . $data['orig_file'] ) ) {
					// update the filename
					$meta['sizes'][ $size ]['file'] = $data['orig_file'];
					// update the optimization results
					$meta['sizes'][ $size ]['ewww_image_optimizer'] = __( 'Original Restored', EWWW_IMAGE_OPTIMIZER_DOMAIN );
					$meta['sizes'][ $size ]['orig_file'] = $data['file'];
					$meta['sizes'][ $size ]['real_orig_file'] = $data['file'];
					$meta['sizes'][ $size ]['converted'] = 0;
						// if we don't already have the update attachment filter
						if ( FALSE === has_filter( 'wp_update_attachment_metadata', 'ewww_image_optimizer_update_attachment' ) ) {
							// add the update attachment filter
							add_filter( 'wp_update_attachment_metadata', 'ewww_image_optimizer_update_attachment', 10, 2 );
						}
					unlink( $base_dir . $data['file'] );
					unset( $meta['sizes'][ $size ]['orig_file'] );
				}
				// store info on the sizes we've processed, so we can check the list for duplicate sizes
				$processed[$size]['width'] = $data['width'];
				$processed[$size]['height'] = $data['height'];
			}		
		}
	}
	ewwwio_memory( __FUNCTION__ );
	return $meta;
}

// deletes 'orig_file' when an attachment is being deleted
function ewww_image_optimizer_delete ($id) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	// retrieve the image metadata
	$meta = wp_get_attachment_metadata($id);
	// if the attachment has an original file set
	if ( ! empty( $meta['orig_file'] ) ) {
		unset($rows);
		// get the filepath from the metadata
		$file_path = $meta['orig_file'];
		// get the filename
		$filename = basename($file_path);
		// delete any residual webp versions
		$webpfile = $filename . '.webp';
		$webpfileold = preg_replace( '/\.\w+$/', '.webp', $filename );
		if ( file_exists( $webpfile) ) {
			unlink( $webpfile );
		}
		if ( file_exists( $webpfileold) ) {
			unlink( $webpfileold );
		}
		// retrieve any posts that link the original image
		$esql = "SELECT ID, post_content FROM $wpdb->posts WHERE post_content LIKE '%$filename%'";
		$rows = $wpdb->get_row($esql);
		// if the original file still exists and no posts contain links to the image
		if ( file_exists( $file_path ) && empty( $rows ) ) {
			unlink( $file_path );
			$wpdb->delete( $wpdb->ewwwio_images, array( 'path' => $file_path ) );
		}
	}
	// remove the regular image from the ewwwio_images tables
	list( $file_path, $upload_path ) = ewww_image_optimizer_attachment_path( $meta, $id );
	$wpdb->delete($wpdb->ewwwio_images, array('path' => $file_path));
	// resized versions, so we can continue
	if (isset($meta['sizes']) ) {
		// one way or another, $file_path is now set, and we can get the base folder name
		$base_dir = dirname($file_path) . '/';
		// check each resized version
		foreach($meta['sizes'] as $size => $data) {
			// delete any residual webp versions
			$webpfile = $base_dir . $data['file'] . '.webp';
			$webpfileold = preg_replace( '/\.\w+$/', '.webp', $base_dir . $data['file'] );
			if ( file_exists( $webpfile) ) {
				unlink( $webpfile );
			}
			if ( file_exists( $webpfileold) ) {
				unlink( $webpfileold );
			}
			$wpdb->delete($wpdb->ewwwio_images, array('path' => $base_dir . $data['file']));
			// if the original resize is set, and still exists
			if (!empty($data['orig_file']) && file_exists($base_dir . $data['orig_file'])) {
				unset($srows);
				// retrieve the filename from the metadata
				$filename = $data['orig_file'];
				// retrieve any posts that link the image
				$esql = "SELECT ID, post_content FROM $wpdb->posts WHERE post_content LIKE '%$filename%'";
				$srows = $wpdb->get_row($esql);
				// if there are no posts containing links to the original, delete it
				if(empty($srows)) {
					unlink($base_dir . $data['orig_file']);
					$wpdb->delete($wpdb->ewwwio_images, array('path' => $base_dir . $data['orig_file']));
				}
			}
		}
	}
	ewwwio_memory( __FUNCTION__ );
	return;
}

function ewww_image_optimizer_cloud_key_sanitize( $key ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$key = trim( $key );
	ewwwio_debug_message( print_r( $_REQUEST, true ) );
	if ( ewww_image_optimizer_cloud_verify( false, $key ) ) {
		ewwwio_debug_message( 'sanitize (verification) successful' );
		ewwwio_memory( __FUNCTION__ );
//		ewww_image_optimizer_debug_log();
		return $key;
	} else {
		ewwwio_debug_message( 'sanitize (verification) failed' );
		ewwwio_memory( __FUNCTION__ );
//		ewww_image_optimizer_debug_log();
		return '';
	}
}

function ewww_image_optimizer_full_cloud() {
//	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) > 10 && ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) > 10 ) {
//		ewwwio_debug_message( 'all cloud mode enabled, no local' );
		return true;
	} elseif ( EWWW_IMAGE_OPTIMIZER_DOMAIN == 'ewww-image-optimizer-cloud' ) {
//		ewwwio_debug_message( 'cloud-only plugin, no local' );
		return true;
	}
//	ewwwio_debug_message( 'local mode allowed' );
	return false;
}

// turns on the cloud settings when they are all disabled
function ewww_image_optimizer_cloud_enable() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	add_option( 'ewww_image_optimizer_jpg_level', '20' );
	add_option( 'ewww_image_optimizer_png_level', '20' );
	add_option( 'ewww_image_optimizer_gif_level', '10' );
	add_option( 'ewww_image_optimizer_pdf_level', '10' );
	// just to make sure they get set with & without a database
	ewww_image_optimizer_set_option('ewww_image_optimizer_jpg_level', 20);
	ewww_image_optimizer_set_option('ewww_image_optimizer_png_level', 20);
	ewww_image_optimizer_set_option('ewww_image_optimizer_gif_level', 10);
	ewww_image_optimizer_set_option('ewww_image_optimizer_pdf_level', 10);
}

// adds our version to the useragent for http requests
function ewww_image_optimizer_cloud_useragent() {
	return 'EWWW SILO/' . EWWW_IMAGE_OPTIMIZER_VERSION;
}

// submits the api key for verification
function ewww_image_optimizer_cloud_verify( $cache = true, $api_key = '' ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( empty( $api_key ) ) {
		$api_key = ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' );
	} elseif ( empty( $api_key ) && ! empty( $_POST['ewww_image_optimizer_cloud_key'] ) ) {
		$api_key = $_POST['ewww_image_optimizer_cloud_key'];
	}
	if ( empty( $api_key ) ) {
		ewwwio_debug_message( 'no api key' );
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) > 10 ) {
			update_option( 'ewww_image_optimizer_jpg_level', 10 );
		}
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) > 10 && ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) != 40 ) {
			update_option( 'ewww_image_optimizer_png_level', 10 );
		}
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' ) > 0 ) {
			update_option( 'ewww_image_optimizer_pdf_level', 0 );
		}
		return false;
	}
	$ewww_cloud_status = get_transient( 'ewww_image_optimizer_cloud_status' );
	if ( false && $cache && preg_match( '/great/', $ewww_cloud_status ) ) {
		ewwwio_debug_message( 'using cached verification' );
		return $ewww_cloud_status;
	}
		$result = ewww_image_optimizer_cloud_post_key( 'optimize.exactlywww.com', 'https', $api_key );
		if ( empty( $result->success ) ) { 
			$result->throw_for_status( false );
			ewwwio_debug_message( "verification failed" );
		} elseif ( ! empty( $result->body ) && preg_match( '/(great|exceeded)/', $result->body ) ) {
			$verified = $result->body;
			ewwwio_debug_message( "verification success" );
		} else {
			ewwwio_debug_message( "verification failed" );
			ewwwio_debug_message( print_r( $result, true ) );
		}
	if ( empty( $verified ) ) {
		ewwwio_memory( __FUNCTION__ );
		return FALSE;
	} else {
		set_transient( 'ewww_image_optimizer_cloud_status', $verified, 3600 ); 
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) < 20 && ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) < 20 && ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_level' ) < 20 && ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' ) == 0 ) {
			ewww_image_optimizer_cloud_enable();
		}
		ewwwio_debug_message( "verification body contents: " . $result->body );
		ewwwio_memory( __FUNCTION__ );
		return $verified;
	}
}

function ewww_image_optimizer_cloud_post_key( $ip, $transport, $key ) {
	$useragent = ewww_image_optimizer_cloud_useragent();
	$result = Requests::post( "$transport://$ip/verify/", array(), array( 'api_key' => $key ), array( 'timeout' => 5, 'useragent' => $useragent ) );
	return $result;
}

// checks the provided api key for quota information
function ewww_image_optimizer_cloud_quota() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$api_key = ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' );
	$url = "https://optimize.exactlywww.com/quota/";
	$useragent = ewww_image_optimizer_cloud_useragent();
	$result = Requests::post( $url, array(), array( 'api_key' => $api_key ), array( 'timeout' => 5, 'useragent' => $useragent ) );
	/*$result = wp_remote_post( $url, array(
		'timeout' => 5,
		'sslverify' => false,
		'body' => array( 'api_key' => $api_key )
	) );*/
	if ( ! $result->success ) {
		$result->throw_for_status( false );
		ewwwio_debug_message( "quota request failed: $error_message" );
		ewwwio_memory( __FUNCTION__ );
		return '';
	} elseif ( ! empty( $result->body ) ) {
		ewwwio_debug_message( "quota data retrieved: " . $result->body );
		$quota = explode( ' ', $result->body );
		ewwwio_memory( __FUNCTION__ );
		if ( $quota[0] == 0 && $quota[1] > 0 ) {
			return esc_html( sprintf( _n( 'optimized %1$d images, usage will reset in %2$d day.', 'optimized %1$d images, usage will reset in %2$d days.', $quota[2], EWWW_IMAGE_OPTIMIZER_DOMAIN ), $quota[1], $quota[2] ) );
		} elseif ( $quota[0] == 0 && $quota[1] < 0 ) {
			return esc_html( sprintf( _n( '%1$d image credit remaining.', '%1$d image credits remaining.', abs( $quota[1] ), EWWW_IMAGE_OPTIMIZER_DOMAIN ), abs( $quota[1] ) ) );
		} elseif ( $quota[0] > 0 && $quota[1] < 0 ) {
			$real_quota = $quota[0] - $quota[1];
			return esc_html( sprintf( _n( '%1$d image credit remaining.', '%1$d image credits remaining.', $real_quota, EWWW_IMAGE_OPTIMIZER_DOMAIN ), $real_quota ) );
		} else {
			return esc_html( sprintf( _n( 'used %1$d of %2$d, usage will reset in %3$d day.', 'used %1$d of %2$d, usage will reset in %3$d days.', $quota[2], EWWW_IMAGE_OPTIMIZER_DOMAIN ), $quota[1], $quota[0], $quota[2] ) );
		}
	}
}

/* submits an image to the cloud optimizer and saves the optimized image to disk
 *
 * Returns an array of the $file, $results, $converted to tell us if an image changes formats, and the $original file if it did.
 *
 * @param   string $file		Full absolute path to the image file
 * @param   string $type		mimetype of $file
 * @param   boolean $convert		true says we want to attempt conversion of $file
 * @param   string $newfile		filename of new converted image
 * @param   string $newtype		mimetype of $newfile
 * @param   boolean $fullsize		is this the full-size original?
 * @param   array $jpg_params		r, g, b values and jpg quality setting for conversion
 * @returns array
*/
function ewww_image_optimizer_cloud_optimizer( $file, $type, $convert = false, $newfile = null, $newtype = null, $fullsize = false, $jpg_params = array( 'r' => '255', 'g' => '255', 'b' => '255', 'quality' => null ) ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );

	$ewww_status = get_transient( 'ewww_image_optimizer_cloud_status' );
	$started = microtime( true );
	if ( preg_match( '/exceeded/', $ewww_status ) ) {
		if ( ! ewww_image_optimizer_cloud_verify() ) { 
			return array( $file, false, 'key verification failed', 0 );
		}
	}

	global $ewwwio_cli;
	$ewwwio_cli->line( ewww_image_optimizer_cloud_quota() );

	// calculate how much time has elapsed since we started
	$elapsed = microtime( true ) - $started;
	// output how much time has elapsed since we started
	ewwwio_debug_message( sprintf( 'Cloud verify took %.3f seconds', $elapsed ) );
	$ewww_status = get_transient( 'ewww_image_optimizer_cloud_status' );
	if ( ! empty ( $ewww_status ) && preg_match( '/exceeded/', $ewww_status ) ) {
		ewwwio_debug_message( 'license exceeded, image not processed' );
		return array($file, false, 'exceeded', 0);
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_metadata_skip_full' ) && $fullsize ) {
		$metadata = 1;
	} elseif ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpegtran_copy' ) ){
        	// don't copy metadata
                $metadata = 0;
        } else {
                // copy all the metadata
                $metadata = 1;
        }
	if ( empty( $convert ) ) {
		$convert = 0;
	} else {
		$convert = 1;
	}
	$lossy_fast = 0;
	if ( ewww_image_optimizer_get_option('ewww_image_optimizer_lossy_skip_full') && $fullsize ) {
		$lossy = 0;
	} elseif ( $type == 'image/png' && ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) >= 40 ) {
		$lossy = 1;
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) == 40 ) {
			$lossy_fast = 1;
		}
	} elseif ( $type == 'image/jpeg' && ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) >= 30 ) {
		$lossy = 1;
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) == 30 ) {
			$lossy_fast = 1;
		}
	} elseif ( $type == 'application/pdf' && ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' ) == 20 ) {
		$lossy = 1;
	} else {
		$lossy = 0;
	}
	if ( $newtype == 'image/webp' ) {
		$webp = 1;
	} else {
		$webp = 0;
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) == 30 ) {
		$png_compress = 1;
	} else {
		$png_compress = 0;
	}
	ewwwio_debug_message( "file: $file " );
	ewwwio_debug_message( "type: $type" );
	ewwwio_debug_message( "convert: $convert" );
	ewwwio_debug_message( "newfile: $newfile" );
	ewwwio_debug_message( "newtype: $newtype" );
	ewwwio_debug_message( "webp: $webp" );
	ewwwio_debug_message( "jpg_params: " . print_r($jpg_params, true) );
	$api_key = ewww_image_optimizer_get_option('ewww_image_optimizer_cloud_key');
	$url = "https://optimize.exactlywww.com/";
	$boundary = generate_password( 24 );

	$useragent = ewww_image_optimizer_cloud_useragent();
	$headers = array(
        	'content-type' => 'multipart/form-data; boundary=' . $boundary,
//		'timeout' => 90,
//		'httpversion' => '1.0',
//		'blocking' => true
		);
	$post_fields = array(
		'filename' => $file,
		'convert' => $convert, 
		'metadata' => $metadata, 
		'api_key' => $api_key,
		'red' => $jpg_params['r'],
		'green' => $jpg_params['g'],
		'blue' => $jpg_params['b'],
		'quality' => $jpg_params['quality'],
		'compress' => $png_compress,
		'lossy' => $lossy,
		'lossy_fast' => $lossy_fast,
		'webp' => $webp,
	);

	$payload = '';
	foreach ($post_fields as $name => $value) {
        	$payload .= '--' . $boundary;
	        $payload .= "\r\n";
	        $payload .= 'Content-Disposition: form-data; name="' . $name .'"' . "\r\n\r\n";
	        $payload .= $value;
	        $payload .= "\r\n";
	}

	$payload .= '--' . $boundary;
	$payload .= "\r\n";
	$payload .= 'Content-Disposition: form-data; name="file"; filename="' . basename($file) . '"' . "\r\n";
	$payload .= 'Content-Type: ' . $type . "\r\n";
	$payload .= "\r\n";
	$payload .= file_get_contents($file);
	$payload .= "\r\n";
	$payload .= '--' . $boundary;
	$payload .= 'Content-Disposition: form-data; name="submitHandler"' . "\r\n";
	$payload .= "\r\n";
	$payload .= "Upload\r\n";
	$payload .= '--' . $boundary . '--';

	// retrieve the time when the optimizer starts
//	$started = microtime(true);
	$response = Requests::post(
		$url,
		$headers,
		$payload,
		array(
			'timeout' => 90,
			'useragent' => $useragent,
		)
	);
/*	$response = wp_remote_post( $url, array(
		'timeout' => 90,
		'headers' => $headers,
		'sslverify' => false,
		'body' => $payload,
		) );*/
//	$elapsed = microtime(true) - $started;
//	$ewww_debug .= "processing image via cloud took $elapsed seconds<br>";
	if ( ! $response->success ) {
		$response->throw_for_status( false );
		ewwwio_debug_message( "optimize failed, see exception" );
		return array( $file, false, 'cloud optimize failed', 0 );
	} else {
		$tempfile = $file . ".tmp";
		file_put_contents( $tempfile, $response->body );
		$orig_size = filesize( $file );
		$newsize = $orig_size;
		$converted = false;
		$msg = '';
		if ( preg_match( '/exceeded/', $response->body ) ) {
			ewwwio_debug_message( 'License Exceeded' );
			set_transient( 'ewww_image_optimizer_cloud_status', 'exceeded', 3600 );
			$msg = 'exceeded';
			unlink( $tempfile );
		} elseif ( ewww_image_optimizer_mimetype( $tempfile, 'i' ) == $type ) {
			$newsize = filesize( $tempfile );
			ewwwio_debug_message( "cloud results: $newsize (new) vs. $orig_size (original)" );
			rename( $tempfile, $file );
		} elseif ( ewww_image_optimizer_mimetype( $tempfile, 'i' ) == 'image/webp' ) {
			$newsize = filesize( $tempfile );
			ewwwio_debug_message( "cloud results: $newsize (new) vs. $orig_size (original)" );
			rename( $tempfile, $newfile );
		} elseif ( ewww_image_optimizer_mimetype( $tempfile, 'i' ) == $newtype ) {
			$converted = true;
			$newsize = filesize( $tempfile );
			ewwwio_debug_message( "cloud results: $newsize (new) vs. $orig_size (original)" );
			rename( $tempfile, $newfile );
			$file = $newfile;
		} else {
			unlink( $tempfile );
		}
		ewwwio_memory( __FUNCTION__ );
		return array( $file, $converted, $msg, $newsize );
	}
}

// check the database to see if we've done this image before
function ewww_image_optimizer_check_table( $file, $orig_size ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	ewwwio_debug_message( "checking for $file with size: $orig_size" );
	$image = ewww_image_optimizer_find_already_optimized( $file );
	if ( is_array( $image ) && $image['image_size'] == $orig_size ) {
		$prev_string = " - " . __( 'Previously Optimized', EWWW_IMAGE_OPTIMIZER_DOMAIN );
		if ( preg_match( '/' . __( 'License exceeded', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '/', $image['results'] ) ) {
			return;
		}
		$already_optimized = preg_replace( "/$prev_string/", '', $image['results'] );
		$already_optimized = $already_optimized . $prev_string;
		ewwwio_debug_message( "already optimized: {$image['path']} - $already_optimized" );
		ewwwio_memory( __FUNCTION__ );
		return $already_optimized;
	}
}

// receives a path, optimized size, and an original size to insert into ewwwwio_images table
// if this is a $new image, copy the result stored in the database
function ewww_image_optimizer_update_table( $attachment, $opt_size, $orig_size, $preserve_results = false ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	$already_optimized = ewww_image_optimizer_find_already_optimized( $attachment );
	if ( $already_optimized && $opt_size >= $orig_size ) {
		$prev_string = ' - ' . __( 'Previously Optimized', EWWW_IMAGE_OPTIMIZER_DOMAIN );
	} else {
		$prev_string = '';
	}
	if ( is_array( $already_optimized ) && ! empty( $already_optimized['orig_size'] ) && $already_optimized['orig_size'] > $orig_size ) {
		$orig_size = $already_optimized['orig_size'];
	}
	ewwwio_debug_message( "savings: $opt_size (new) vs. $orig_size (orig)" );
	if ( is_array( $already_optimized ) && ! empty( $already_optimized['results'] ) && $preserve_results && $opt_size == $orig_size) {
		$results_msg = $already_optimized['results'];
	} elseif ( $opt_size >= $orig_size ) {
		ewwwio_debug_message( "original and new file are same size (or something weird made the new one larger), no savings" );
		$results_msg = __( 'No savings', EWWW_IMAGE_OPTIMIZER_DOMAIN );
	} else {
		// calculate how much space was saved
		$savings = intval( $orig_size ) - intval( $opt_size );
		// convert it to human readable format
		$savings_str = size_format( $savings, 1 );
		// replace spaces and extra decimals with proper html entity encoding
		$savings_str = preg_replace( '/\.0 B /', ' B', $savings_str );
		$savings_str = str_replace( ' ', '&nbsp;', $savings_str );
		// determine the percentage savings
		$percent = 100 - ( 100 * ( $opt_size / $orig_size ) );
		// use the percentage and the savings size to output a nice message to the user
		$results_msg = sprintf( __( "Reduced by %01.1f%% (%s)", EWWW_IMAGE_OPTIMIZER_DOMAIN ),
			$percent,
			$savings_str
		) . $prev_string;
		ewwwio_debug_message( "original and new file are different size: $results_msg" );
	}
	if ( empty( $already_optimized ) ) {
		ewwwio_debug_message( "creating new record, path: $attachment, size: $opt_size" );
		// store info on the current image for future reference
		$wpdb->insert( $wpdb->ewwwio_images, array(
			'path' => $attachment,
			'image_size' => $opt_size,
			'orig_size' => $orig_size,
			'results' => $results_msg,
			'updated' => date( 'Y-m-d H:i:s' ),
			'updates' => 1,
		) );
	} else {
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) ) {
			$trace = ewwwio_debug_backtrace();
		} else {
			$trace = '';
		}
		ewwwio_debug_message( "updating existing record ({$already_optimized['id']}), path: $attachment, size: $opt_size" );
		// store info on the current image for future reference
		$wpdb->update( $wpdb->ewwwio_images,
			array(
				'image_size' => $opt_size,
				'results' => $results_msg,
				'updates' => $already_optimized['updates'] + 1,
				'trace' => $trace,
			),
			array(
				'id' => $already_optimized['id'],
			)
		);
	}
	ewwwio_memory( __FUNCTION__ );
	$wpdb->flush();
	ewwwio_memory( __FUNCTION__ );
	return $results_msg;
}

// called to process each image in the loop for images outside of media library
function ewww_image_optimizer_aux_images_loop( $attachment = null, $auto = false ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $ewww_defer;
	$ewww_defer = false;
	$output = array();
	// verify that an authorized user has started the optimizer
	$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
	if ( ! $auto && ( empty( $_REQUEST['ewww_wpnonce'] ) || ! wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-bulk' ) || ! current_user_can( $permissions ) ) ) {
		$output['error'] = esc_html__( 'Access token has expired, please reload the page.', EWWW_IMAGE_OPTIMIZER_DOMAIN );
		echo json_encode( $output );
		die();
	}
	session_write_close();
	if ( ! empty( $_REQUEST['ewww_wpnonce'] ) ) {
		// find out if our nonce is on it's last leg/tick
		$tick = wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-bulk' );
		if ( $tick === 2 ) {
			ewwwio_debug_message( 'nonce on its last leg' );
			$output['new_nonce'] = wp_create_nonce( 'ewww-image-optimizer-bulk' );
		} else {
			ewwwio_debug_message( 'nonce still alive and kicking' );
			$output['new_nonce'] = '';
		}
	}
	// retrieve the time when the optimizer starts
	$started = microtime( true );
	// get the 'aux attachments' with a list of attachments remaining
	$attachments = get_option( 'ewww_image_optimizer_aux_attachments' );
	if ( empty( $attachment ) ) {
		$attachment = array_shift( $attachments );
	}
	// do the optimization for the current image
	$results = ewww_image_optimizer( $attachment );
	//global $ewww_exceed;
	$ewww_status = get_transient( 'ewww_image_optimizer_cloud_status' );
	if ( ! empty ( $ewww_status ) && preg_match( '/exceeded/', $ewww_status ) ) {
		if ( ! $auto ) {
			$output['error'] = esc_html__( 'License Exceeded', EWWW_IMAGE_OPTIMIZER_DOMAIN );
			echo json_encode( $output );
		}
		die();
	}
	// store the updated list of attachment IDs back in the 'bulk_attachments' option
	update_option( 'ewww_image_optimizer_aux_attachments', $attachments, false );
	if ( ! $auto ) {
		// output the path
		$output['results'] = sprintf( "<p>" . esc_html__( 'Optimized', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . " <strong>%s</strong><br>", esc_html( $attachment ) );
		// tell the user what the results were for the original image
		$output['results'] .= sprintf( "%s<br>", $results[1] );
		// calculate how much time has elapsed since we started
		$elapsed = microtime( true ) - $started;
		// output how much time has elapsed since we started
		$output['results'] .= sprintf( esc_html__( 'Elapsed: %.3f seconds', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</p>", $elapsed );
		if ( get_site_option( 'ewww_image_optimizer_debug' ) ) {
			global $ewww_debug;
			$output['results'] .= '<div style="background-color:#ffff99;">' . $ewww_debug . '</div>';
		}
		if ( ! empty( $attachments ) ) {
			$next_file = array_shift( $attachments );
			$loading_image = plugins_url( '/images/wpspin.gif', __FILE__ );
			$output['next_file'] = "<p>" . esc_html__( 'Optimizing', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . " <b>$next_file</b>&nbsp;<img src='$loading_image' alt='loading'/></p>";
		}
		echo json_encode( $output );
		ewwwio_memory( __FUNCTION__ );
		die();
	}
	ewwwio_memory( __FUNCTION__ );
}

// processes metadata and looks for any webp version to insert in the meta
function ewww_image_optimizer_update_attachment_metadata( $meta, $ID ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	ewwwio_debug_message( "attachment id: $ID" );
	list( $file_path, $upload_path ) = ewww_image_optimizer_attachment_path( $meta, $ID );
	// don't do anything else if the attachment path can't be retrieved
	if ( ! is_file( $file_path ) ) {
		ewwwio_debug_message( "could not retrieve path" );
		return $meta;
	}
	ewwwio_debug_message( "retrieved file path: $file_path" );
	if ( is_file( $file_path . '.webp' ) ) {
		$meta['sizes']['webp-full'] = array(
			'file' => pathinfo( $file_path, PATHINFO_BASENAME ) . '.webp',
			'width' => 0,
			'height' => 0,
			'mime-type' => 'image/webp',
		);
		
	}
	// if the file was converted
	// resized versions, so we can continue
	if ( isset( $meta['sizes'] ) ) {
		ewwwio_debug_message( 'processing resizes for webp updates' );
		// meta sizes don't contain a path, so we use the foldername from the original to generate one
		$base_dir = trailingslashit( dirname( $file_path ) );
		// process each resized version
		$processed = array();
		foreach( $meta['sizes'] as $size => $data ) {
			$resize_path = $base_dir . $data['file'];
			// update the webp paths
			if ( is_file( $resize_path . '.webp' ) ) {
				$meta['sizes'][ 'webp-' . $size ] = array(
					'file' => $data['file'] . '.webp',
					'width' => 0,
					'height' => 0,
					'mime-type' => 'image/webp',
				);
			}
		}
	}
	ewwwio_memory( __FUNCTION__ );
	// send back the updated metadata
	return $meta;
}

// looks for a retina version of the original file so that we can optimize that too
function ewww_image_optimizer_hidpi_optimize( $orig_path ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$hidpi_suffix = apply_filters( 'ewww_image_optimizer_hidpi_suffix', '@2x' );
	$pathinfo = pathinfo( $orig_path );
	$hidpi_path = $pathinfo['dirname'] . '/' . $pathinfo['filename'] . $hidpi_suffix . '.' . $pathinfo['extension'];
	if ( ewww_image_optimizer_test_parallel_opt() ) {
	//if ( ewww_image_optimizer_test_parallel_opt( $ID ) ) {
		if ( ! empty( $_REQUEST['ewww_force'] ) ) {
			$force = true;
		} else {
			$force = false;
		}
		add_filter( 'http_headers_useragent', 'ewww_image_optimizer_cloud_useragent', PHP_INT_MAX );
		global $ewwwio_async_optimize_media;
		$async_path = str_replace( ABSPATH, '', $hidpi_path );
		$ewwwio_async_optimize_media->data( array( 'ewwwio_path' => $async_path, 'ewww_force' => $force ) )->dispatch();
	} else {
		ewww_image_optimizer( $hidpi_path );
	}
}

function ewww_image_optimizer_remote_fetch( $id, $meta ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $as3cf;
	if ( ! function_exists( 'download_url' ) ) {
		require_once( ABSPATH . '/wp-admin/includes/file.php' );
	}
	if ( class_exists( 'Amazon_S3_And_CloudFront' ) ) {
		$full_url = get_attached_file( $id );
		if ( strpos( $full_url, 's3' ) === 0 ) {
			$full_url = $as3cf->get_attachment_url( $id, null, null, $meta );
		}
		$filename = get_attached_file( $id, true );
		ewwwio_debug_message( "amazon s3 fullsize url: $full_url" );
		ewwwio_debug_message( "unfiltered fullsize path: $filename" );
		$temp_file = download_url( $full_url );
		if ( ! is_wp_error( $temp_file ) ) {
			rename( $temp_file, $filename );
		}
		// resized versions, so we'll grab those too
		if ( isset( $meta['sizes'] ) ) {
			$disabled_sizes = ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_resizes_opt' );
			ewwwio_debug_message( 'retrieving resizes' );
			// meta sizes don't contain a path, so we calculate one
			$base_dir = trailingslashit( dirname( $filename) );
			// process each resized version
			$processed = array();
			foreach($meta['sizes'] as $size => $data) {
				ewwwio_debug_message( "processing size: $size" );
				if ( preg_match('/webp/', $size) ) {
					continue;
				}
				if ( ! empty( $disabled_sizes[$size] ) ) {
					continue;
				}
				// initialize $dup_size
				$dup_size = false;
				// check through all the sizes we've processed so far
				foreach($processed as $proc => $scan) {
					// if a previous resize had identical dimensions
					if ($scan['height'] == $data['height'] && $scan['width'] == $data['width']) {
						// found a duplicate resize
						$dup_size = true;
					}
				}
				// if this is a unique size
				if (!$dup_size) {
					$resize_path = $base_dir . $data['file'];
					$resize_url = $as3cf->get_attachment_url( $id, null, $size, $meta );
					ewwwio_debug_message( "fetching $resize_url to $resize_path" );
					$temp_file = download_url( $resize_url );
					if ( ! is_wp_error( $temp_file ) ) {
						rename( $temp_file, $resize_path );
					}
				}
				// store info on the sizes we've processed, so we can check the list for duplicate sizes
				$processed[$size]['width'] = $data['width'];
				$processed[$size]['height'] = $data['height'];
			}
		}
	}
	if ( class_exists( 'WindowsAzureStorageUtil' ) && get_option( 'azure_storage_use_for_default_upload' ) ) {
		$full_url = $meta['url'];
		$filename = $meta['file'];
		ewwwio_debug_message( "azure fullsize url: $full_url" );
		ewwwio_debug_message( "fullsize path: $filename" );
		$temp_file = download_url( $full_url );
		if ( ! is_wp_error( $temp_file ) ) {
			rename( $temp_file, $filename );
		}
		// resized versions, so we'll grab those too
		if (isset($meta['sizes']) ) {
			$disabled_sizes = ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_resizes_opt' );
			ewwwio_debug_message( 'retrieving resizes' );
			// meta sizes don't contain a path, so we calculate one
			$base_dir = trailingslashit( dirname( $filename) );
			$base_url = trailingslashit( dirname( $full_url ) );
			// process each resized version
			$processed = array();
			foreach($meta['sizes'] as $size => $data) {
				ewwwio_debug_message( "processing size: $size" );
				if ( preg_match('/webp/', $size) ) {
					continue;
				}
				if ( ! empty( $disabled_sizes[$size] ) ) {
					continue;
				}
				// initialize $dup_size
				$dup_size = false;
				// check through all the sizes we've processed so far
				foreach($processed as $proc => $scan) {
					// if a previous resize had identical dimensions
					if ($scan['height'] == $data['height'] && $scan['width'] == $data['width']) {
						// found a duplicate resize
						$dup_size = true;
					}
				}
				// if this is a unique size
				if (!$dup_size) {
					$resize_path = $base_dir . $data['file'];
					$resize_url = $base_url . $data['file'];
					ewwwio_debug_message( "fetching $resize_url to $resize_path" );
					$temp_file = download_url( $resize_url );
					if ( ! is_wp_error( $temp_file ) ) {
						rename( $temp_file, $resize_path );
					}
				}
				// store info on the sizes we've processed, so we can check the list for duplicate sizes
				$processed[$size]['width'] = $data['width'];
				$processed[$size]['height'] = $data['height'];
			}
		}
	}
	if ( ! empty( $filename ) && file_exists( $filename ) ) {
		return $filename;
	} else {
		return false;
	}
}

function ewww_image_optimizer_check_table_as3cf( $meta, $ID, $s3_path ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$local_path = get_attached_file( $ID, true );
	ewwwio_debug_message( "unfiltered local path: $local_path" );
	if ( $local_path !== $s3_path ) {
		ewww_image_optimizer_update_table_as3cf( $local_path, $s3_path );
	}
	if ( isset( $meta['sizes'] ) ) {
		ewwwio_debug_message( 'updating s3 resizes' );
		// meta sizes don't contain a path, so we calculate one
		$local_dir = trailingslashit( dirname( $local_path ) );
		$s3_dir = trailingslashit( dirname( $s3_path ) );
		// process each resized version
		$processed = array();
		foreach ( $meta['sizes'] as $size => $data ) {
			if ( strpos( $size, 'webp') === 0 ) {
				continue;
			}
			// check through all the sizes we've processed so far
			foreach ( $processed as $proc => $scan ) {
				// if a previous resize had identical dimensions
				if ( $scan['height'] === $data['height'] && $scan['width'] === $data['width'] ) {
					// found a duplicate resize
					continue;
				}
			}
			// if this is a unique size
			$local_resize_path = $local_dir . $data['file'];
			$s3_resize_path = $s3_dir . $data['file'];
			if ( $local_resize_path !== $s3_resize_path ) {
				ewww_image_optimizer_update_table_as3cf( $local_resize_path, $s3_resize_path );
			}
			// store info on the sizes we've processed, so we can check the list for duplicate sizes
			$processed[ $size ]['width'] = $data['width'];
			$processed[ $size ]['height'] = $data['height'];
		}
	}
	global $wpdb;
	$wpdb->flush();
}

function ewww_image_optimizer_update_table_as3cf( $local_path, $s3_path ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// first we need to see if anything matches the old local path
//	$s3_query = $wpdb->prepare( "SELECT id,path,orig_size,results FROM $wpdb->ewwwio_images WHERE path = %s", $s3_path );
//	$s3_images = $wpdb->get_results( $s3_query, ARRAY_A );
	$s3_image = ewww_image_optimizer_find_already_optimized( $s3_path );
	ewwwio_debug_message( "looking for $s3_path" );
	if ( is_array( $s3_image ) ) {
/*		foreach ( $s3_images as $s3_image ) {
			if ( $s3_image['path'] !== $s3_path ) {
				ewwwio_debug_message( "{$s3_image['path']} does not match $s3_path, continuing our search" );
			} else {*/
				global $wpdb;
				ewwwio_debug_message( "found $s3_path in db" );
				// when we find a match by the s3 path, we need to find out if there are already records for the local path
				$found_local_image = ewww_image_optimizer_find_already_optimized( $local_path );
//				$local_query = $wpdb->prepare( "SELECT id,path,orig_size FROM $wpdb->ewwwio_images WHERE path = %s", $local_path );
//				$local_images = $wpdb->get_results( $local_query, ARRAY_A );
				ewwwio_debug_message( "looking for $local_path" );
/*				foreach ( $local_images as $local_image ) {
					if ( $local_image['path'] === $local_path ) {
						$found_local_image = $local_image;
						break;
					}
				}*/
				// if we found records for both local and s3 paths, we delete the s3 record, but store the original size in the local record
				if ( ! empty( $found_local_image ) && is_array( $found_local_image ) ) {
					ewwwio_debug_message( "found $local_path in db" );
					$wpdb->delete( $wpdb->ewwwio_images,
						array(
							'id' => $s3_image['id'],
						),
						array(
							'%d'
						)
					);
					if ( $s3_image['orig_size'] > $found_local_image['orig_size'] ) {
						$wpdb->update( $wpdb->ewwwio_images,
							array(
								'orig_size' => $s3_image['orig_size'],
								'results' => $s3_image['results'],
							),
							array(
								'id' => $found_local_image['id'],
							)
						);
					}
				// if we just found an s3 path and no local match, then we just update the path in the table to the local path
				} else {
					ewwwio_debug_message( "just updating s3 to local" );
					$wpdb->update( $wpdb->ewwwio_images,
						array(
							'path' => $local_path,
						),
						array(
							'id' => $s3_image['id'],
						)
					);
				}
				//break;
//			}
//		}
	}
}	

// resizes Media Library uploads based on the maximum dimensions specified by the user
function ewww_image_optimizer_resize_upload( $file ) {
	// parts adapted from Imsanity (THANKS Jason!)
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ! $file ) {
		return false;
	}
//	ewwwio_debug_message( print_r( $_SERVER, true ) );
	if ( ! empty( $_REQUEST['post_id'] ) || ( ! empty( $_REQUEST['action'] ) && $_REQUEST['action'] === 'upload-attachment' ) || ( ! empty( $_SERVER['HTTP_REFERER'] ) && strpos( $_SERVER['HTTP_REFERER'], 'media-new.php' ) ) ) {
		$maxwidth = ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxmediawidth' );
		$maxheight = ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxmediaheight' );
		ewwwio_debug_message( 'resizing image from media library or attached to post' );
	} else {
		$maxwidth = ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxotherwidth' );
		$maxheight = ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxotherheight' );
		ewwwio_debug_message( 'resizing images from somewhere else' );
	}

	// allow other developers to modify the dimensions to their liking based on whatever parameters they might choose
	list( $maxwidth, $maxheight ) = apply_filters( 'ewww_image_optimizer_resize_dimensions', array( $maxwidth, $maxheight ) );

	//check that options are not == 0
	if ( $maxwidth == 0 && $maxheight == 0 ) {
		return false;
	}
	//check file type
	$type = ewww_image_optimizer_mimetype( $file, 'i' );
	if ( strpos( $type, 'image' ) === FALSE ) {
		ewwwio_debug_message( 'not an image, cannot resize' );
		return false;
	}
	//check file size (dimensions)
	list( $oldwidth, $oldheight ) = getimagesize( $file );
	if ( $oldwidth <= $maxwidth && $oldheight <= $maxheight ) {
		ewwwio_debug_message( 'image too small for resizing' );
		return false;
	}
	list( $newwidth, $newheight ) = wp_constrain_dimensions( $oldwidth, $oldheight, $maxwidth, $maxheight );
	if ( ! function_exists( 'wp_get_image_editor' ) ) {
		ewwwio_debug_message( 'no image editor function' );
		return false;
	}
	remove_filter( 'wp_image_editors', 'ewww_image_optimizer_load_editor', 60 );
	$editor = wp_get_image_editor( $file );
	if ( is_wp_error( $editor ) ) {
		ewwwio_debug_message( 'could not get image editor' );
		return false;
	}
	if ( function_exists( 'exif_read_data' ) && $type === 'image/jpeg' ) {
		$exif = @exif_read_data( $file );
		if ( is_array( $exif ) && array_key_exists( 'Orientation', $exif ) ) {
			$orientation = $exif['Orientation'];
			switch( $orientation ) {
				case 3:
					$editor->rotate( 180 );
					break;
				case 6:
					$editor->rotate( -90 );
					break;
				case 8:
					$editor->rotate( 90 );
					break;
			}
		}
	}
	$resized_image = $editor->resize( $newwidth, $newheight );
	if ( is_wp_error( $resized_image ) ) {
		ewwwio_debug_message( 'error during resizing' );
		return false;
	}
	$new_file = $editor->generate_filename( 'tmp' );
	$orig_size = filesize( $file );
	$saved = $editor->save( $new_file );
	if ( is_wp_error( $saved ) ) {
		ewwwio_debug_message( 'error saving resized image' );
	}
	add_filter( 'wp_image_editors', 'ewww_image_optimizer_load_editor', 60 );
	$new_size = ewww_image_optimizer_filesize( $new_file );
	if ( $new_size && $new_size < $orig_size ) {
		// generate a retina file from the full original if they have WP Retina 2x Pro
		if ( function_exists( 'wr2x_is_pro' ) && wr2x_is_pro() ) {
			$full_size_needed = wr2x_getoption( "full_size", "wr2x_basics", false );
			if ( $full_size_needed ) {
				// Is the file related to this size there?
				$retina_file = '';
	
				$pathinfo = pathinfo( $file ) ;
				$retina_file = trailingslashit( $pathinfo['dirname'] ) . $pathinfo['filename'] . wr2x_retina_extension() . $pathinfo['extension'];
	
				if ( $retina_file && ! file_exists( $retina_file ) && wr2x_are_dimensions_ok( $oldwidth, $oldheight, $newwidth * 2, $newheight * 2 ) ) {
					$image = wr2x_vt_resize( $file, $newwidth * 2, $newheight * 2, false, $retina_file );
				}
			}
		}
		rename( $new_file, $file );
		// store info on the current image for future reference
		global $wpdb;
		$already_optimized = ewww_image_optimizer_find_already_optimized( $file );
		// if the original file has never been optimized, then just update the record that was created with the proper filename (because the resized file has usually been optimized)
		if ( empty( $already_optimized ) ) {
			$tmp_exists = $wpdb->update( $wpdb->ewwwio_images,
				array(
					'path' => $file,
					'orig_size' => $orig_size,
				),
				array(
					'path' => $new_file,
				)
			);
			// if the tmp file didn't get optimized (and it shouldn't), then just insert a dummy record to be updated shortly
			if ( ! $tmp_exists ) {
				$wpdb->insert( $wpdb->ewwwio_images, array(
					'path' => $file,
					'orig_size' => $orig_size,
				) );
			}
		// otherwise, we delete the record created from optimizing the resized file, and update our records for the original file
		} else {
			$temp_optimized = ewww_image_optimizer_find_already_optimized( $new_file );
			if ( is_array( $temp_optimized ) && ! empty( $temp_optimized['id'] ) ) {
				$wpdb->delete( $wpdb->ewwwio_images,
					array(
						'id' => $temp_optimized['id'],
					),
					array(
						'%d',
					)
				);
			}
			// should not need this, as the image will get optimized shortly
			//ewww_image_optimizer_update_table( $file, $new_size, $orig_size );
		}
		return array( $newwidth, $newheight );
	}
	if ( file_exists( $new_file ) ) {
		unlink( $new_file );
	}
	return false;
}

function ewww_image_optimizer_find_already_optimized( $attachment ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	$query = $wpdb->prepare( "SELECT * FROM $wpdb->ewwwio_images WHERE path = %s", $attachment );
	$optimized_query = $wpdb->get_results( $query, ARRAY_A );
	if ( ! empty( $optimized_query ) ) {
		foreach ( $optimized_query as $image ) {
			if ( $image['path'] != $attachment ) {
				ewwwio_debug_message( "{$image['path']} does not match $attachment, continuing our search" );
			} else {
				ewwwio_debug_message( "found a match for $attachment" );
				return $image;
			}
		}
	}
	return false;
}

function ewww_image_optimizer_test_background_opt() {
	if ( ewww_image_optimizer_detect_wpsf_location_lock() ) {
		return false;
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) ) {
		return false;
	}
	return true;
}

function ewww_image_optimizer_test_parallel_opt( $id = 0 ) {
	if ( ewww_image_optimizer_detect_wpsf_location_lock() ) {
		return false;
	}
	if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_parallel_optimization') ) {
		return false;
	}
	if ( empty( $id ) ) {
		return true;
	}
	$type = get_post_mime_type( $id );
	if ( $type == 'image/jpeg' && ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_to_png' ) ) {
		return false;
	}
	if ( $type == 'image/png' && ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_to_jpg' ) ) {
		return false;
	}
	if ( $type == 'image/gif' && ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_to_png' ) ) {
		return false;
	}
	if ( $type == 'application/pdf' ) {
		return false;
	}
	return true;
}

// takes a human-readable size, and generates an approximate byte-size
function ewww_image_optimizer_size_unformat( $formatted ) {
	$size_parts = explode( '&nbsp;', $formatted );
	switch ( $size_parts[1] ) {
		case 'B':
			return intval( $size_parts[0] );
		case 'kB':
			return intval( $size_parts[0] * 1024 );
		case 'MB':
			return intval( $size_parts[0] * 1048576 );
		case 'GB':
			return intval( $size_parts[0] * 1073741824 );
		case 'TB':
			return intval( $size_parts[0] * 1099511627776 );
		default:
			return 0;
	}
}

// generate a unique filename for a converted image
function ewww_image_optimizer_unique_filename( $file, $fileext ) {
	// strip the file extension
	$filename = preg_replace( '/\.\w+$/', '', $file );
	// set the increment to 1 (we always rename converted files with an increment)
	$filenum = 1;
	// while a file exists with the current increment
	while ( file_exists( $filename . '-' . $filenum . $fileext ) ) {
		// increment the increment...
		$filenum++;
	}
	// all done, let's reconstruct the filename
	ewwwio_memory( __FUNCTION__ );
	return array( $filename . '-' . $filenum . $fileext, $filenum );
}

/**
 * Check the submitted PNG to see if it has transparency
 */
function ewww_image_optimizer_png_alpha( $filename ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// determine what color type is stored in the file
	$color_type = ord( @file_get_contents( $filename, NULL, NULL, 25, 1 ) );
	ewwwio_debug_message( "color type: $color_type" );
	// if it is set to RGB alpha or Grayscale alpha
	if ( $color_type == 4 || $color_type == 6 ) {
		ewwwio_debug_message( 'transparency found' );
		return true;
	} elseif ( $color_type == 3 && ewww_image_optimizer_gd_support() ) {
		$image = imagecreatefrompng( $filename );
		if ( imagecolortransparent( $image ) >= 0 ) {
			ewwwio_debug_message( 'transparency found' );
			return true;
		}
		list( $width, $height ) = getimagesize( $filename );
		ewwwio_debug_message( "image dimensions: $width x $height" );
		ewwwio_debug_message( 'preparing to scan image' );
		for ( $y = 0; $y < $height; $y++ ) {
			for ( $x = 0; $x < $width; $x++ ) {
				$color = imagecolorat( $image, $x, $y );
				$rgb = imagecolorsforindex( $image, $color );
				if ( $rgb['alpha'] > 0 ) {
					ewwwio_debug_message( 'transparency found' );
					return true;
				}
			}
		}
	}
	ewwwio_debug_message( 'no transparency' );
	ewwwio_memory( __FUNCTION__ );
	return false;
}

/**
 * Check the submitted GIF to see if it is animated
 */
function ewww_image_optimizer_is_animated( $filename ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// if we can't open the file in read-only buffered mode
	if(!($fh = @fopen($filename, 'rb'))) {
		return false;
	}
	// initialize $count
	$count = 0;
   
	// We read through the file til we reach the end of the file, or we've found
	// at least 2 frame headers
	while(!feof($fh) && $count < 2) {
		$chunk = fread($fh, 1024 * 100); //read 100kb at a time
		$count += preg_match_all('#\x00\x21\xF9\x04.{4}\x00(\x2C|\x21)#s', $chunk, $matches);
	}
	fclose($fh);
	ewwwio_debug_message( "scanned GIF and found $count frames" );
	// return TRUE if there was more than one frame, or FALSE if there was only one
	ewwwio_memory( __FUNCTION__ );
	return $count > 1;
}

// test mimetype based on file extension instead of file contents
// only use for places where speed outweighs accuracy
function ewww_image_optimizer_quick_mimetype( $path ) {
	$pathextension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
	switch ( $pathextension ) {
		case 'jpg':
		case 'jpeg':
		case 'jpe':
			return 'image/jpeg';
		case 'png':
			return 'image/png';
		case 'gif':
			return 'image/gif';
		case 'pdf':
			return 'application/pdf';
		default:
			return false;
	}
}

// make sure an array/object can be parsed by a foreach()
function ewww_image_optimizer_iterable( $var ) {
	return ! empty( $var ) && ( is_array( $var ) || is_object( $var ) );
}

/**
 * Print column header for optimizer results in the media library using
 * the `manage_media_columns` hook.
 */
function ewww_image_optimizer_columns( $defaults ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$defaults['ewww-image-optimizer'] = esc_html__( 'Image Optimizer', EWWW_IMAGE_OPTIMIZER_DOMAIN );
	ewwwio_memory( __FUNCTION__ );
	return $defaults;
}

/**
 * Print column data for optimizer results in the media library using
 * the `manage_media_custom_column` hook.
 */
function ewww_image_optimizer_custom_column( $column_name, $id ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// once we get to the EWWW IO custom column
	if ( $column_name == 'ewww-image-optimizer' ) {
		// retrieve the metadata
		$meta = wp_get_attachment_metadata( $id );
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) ) {
			$print_meta = print_r( $meta, TRUE );
			$print_meta = preg_replace( array('/ /', '/\n+/' ), array( '&nbsp;', '<br />' ), $print_meta );
			echo '<div style="background-color:#ffff99;font-size: 10px;padding: 10px;margin:-10px -10px 10px;line-height: 1.1em">' . $print_meta . '</div>';
		}
		$ewww_cdn = false;
		if( ! empty( $meta['cloudinary'] ) ) {
			esc_html_e( 'Cloudinary image', EWWW_IMAGE_OPTIMIZER_DOMAIN );
			return;
		}
		if ( class_exists( 'WindowsAzureStorageUtil' ) && ! empty( $meta['url'] ) ) {
			esc_html_e( 'Azure Storage image', EWWW_IMAGE_OPTIMIZER_DOMAIN );
			$ewww_cdn = true;
		}
		if ( class_exists( 'Amazon_S3_And_CloudFront' ) && preg_match( '/^(http|s3)\w*:/', get_attached_file( $id ) ) ) {
			esc_html_e( 'Amazon S3 image', EWWW_IMAGE_OPTIMIZER_DOMAIN );
			$ewww_cdn = true;
		}
		list( $file_path, $upload_path ) = ewww_image_optimizer_attachment_path( $meta, $id );
		// if the file does not exist
		if ( empty( $file_path ) && ! $ewww_cdn ) {
			esc_html_e( 'Could not retrieve file path.', EWWW_IMAGE_OPTIMIZER_DOMAIN );
			ewww_image_optimizer_debug_log();
			return;
		}
		$msg = '';
		$convert_desc = '';
		$convert_link = '';
		if ( $ewww_cdn ) {
			$type = get_post_mime_type( $id );
		} else {
			// retrieve the mimetype of the attachment
			$type = ewww_image_optimizer_mimetype( $file_path, 'i' );
			// get a human readable filesize
			$file_size = size_format( filesize( $file_path ), 2 );
			$file_size = preg_replace( '/\.00 B /', ' B', $file_size );
		}
		$skip = ewww_image_optimizer_skip_tools();
		// run the appropriate code based on the mimetype
		switch( $type ) {
			case 'image/jpeg':
				// if jpegtran is missing, tell them that
				if( ! EWWW_IMAGE_OPTIMIZER_JPEGTRAN && ! $skip['jpegtran'] ) {
					$valid = false;
					$msg = '<br>' . wp_kses( sprintf( __( '%s is missing', EWWW_IMAGE_OPTIMIZER_DOMAIN), '<em>jpegtran</em>' ), array( 'em' => array() ) );
				} else {
					$convert_link = esc_html__('JPG to PNG', EWWW_IMAGE_OPTIMIZER_DOMAIN);
					$convert_desc = esc_attr__( 'WARNING: Removes metadata. Requires GD or ImageMagick. PNG is generally much better than JPG for logos and other images with a limited range of colors.', EWWW_IMAGE_OPTIMIZER_DOMAIN );
				}
				break; 
			case 'image/png':
				// if pngout and optipng are missing, tell the user
				if( ! EWWW_IMAGE_OPTIMIZER_PNGOUT && ! EWWW_IMAGE_OPTIMIZER_OPTIPNG && ! $skip['optipng'] && ! $skip['pngout'] ) {
					$valid = false;
					$msg = '<br>' . wp_kses( sprintf( __( '%s is missing', EWWW_IMAGE_OPTIMIZER_DOMAIN ), '<em>optipng/pngout</em>' ), array( 'em' => array() ) );
				} else {
					$convert_link = esc_html__('PNG to JPG', EWWW_IMAGE_OPTIMIZER_DOMAIN);
					$convert_desc = esc_attr__('WARNING: This is not a lossless conversion and requires GD or ImageMagick. JPG is much better than PNG for photographic use because it compresses the image and discards data. Transparent images will only be converted if a background color has been set.', EWWW_IMAGE_OPTIMIZER_DOMAIN);
				}
				break;
			case 'image/gif':
				// if gifsicle is missing, tell the user
				if( ! EWWW_IMAGE_OPTIMIZER_GIFSICLE && ! $skip['gifsicle'] ) {
					$valid = false;
					$msg = '<br>' . wp_kses( sprintf( __( '%s is missing', EWWW_IMAGE_OPTIMIZER_DOMAIN ), '<em>gifsicle</em>' ), array( 'em' => array() ) );
				} else {
					$convert_link = esc_html__('GIF to PNG', EWWW_IMAGE_OPTIMIZER_DOMAIN);
					$convert_desc = esc_attr__('PNG is generally better than GIF, but does not support animation. Animated images will not be converted.', EWWW_IMAGE_OPTIMIZER_DOMAIN);
				}
				break;
			case 'application/pdf':
				$convert_desc = '';
				break;
			default:
				// not a supported mimetype
				esc_html_e( 'Unsupported file type', EWWW_IMAGE_OPTIMIZER_DOMAIN );
				ewww_image_optimizer_debug_log();
				return;
		}
		$ewww_manual_nonce = wp_create_nonce( "ewww-manual-$id" );
		if ( $ewww_cdn ) {
			// if the optimizer metadata exists
			if ( ! empty( $meta['ewww_image_optimizer'] ) ) {
				// output the optimizer results
				echo "<br>" . esc_html( $meta['ewww_image_optimizer'] );
				if ( current_user_can( apply_filters( 'ewww_image_optimizer_manual_permissions', '' ) ) ) {
					// output a link to re-optimize manually
					printf("<br><a href=\"admin.php?action=ewww_image_optimizer_manual_optimize&amp;ewww_manual_nonce=$ewww_manual_nonce&amp;ewww_force=1&amp;ewww_attachment_ID=%d\">%s</a>",
						$id,
						esc_html__( 'Re-optimize', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
				}
			} elseif ( get_transient( 'ewwwio-background-in-progress-' . $id ) ) {
				esc_html_e( 'In Progress', EWWW_IMAGE_OPTIMIZER_DOMAIN );
			} elseif ( current_user_can( apply_filters( 'ewww_image_optimizer_manual_permissions', '' ) ) ) {
				// and give the user the option to optimize the image right now
				printf( "<br><a href=\"admin.php?action=ewww_image_optimizer_manual_optimize&amp;ewww_manual_nonce=$ewww_manual_nonce&amp;ewww_attachment_ID=%d\">%s</a>", $id, esc_html__( 'Optimize now!', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
			}
			return;
		}
		// if the optimizer metadata exists
		if ( ! empty( $meta['ewww_image_optimizer'] ) ) {
			// output the optimizer results
			echo esc_html( $meta['ewww_image_optimizer'] );
			// output the filesize
			echo "<br>" . sprintf( esc_html__( 'Image Size: %s', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $file_size );
			if ( empty( $msg ) && current_user_can( apply_filters( 'ewww_image_optimizer_manual_permissions', '' ) ) ) {
				// output a link to re-optimize manually
				printf("<br><a href=\"admin.php?action=ewww_image_optimizer_manual_optimize&amp;ewww_manual_nonce=$ewww_manual_nonce&amp;ewww_force=1&amp;ewww_attachment_ID=%d\">%s</a>",
					$id,
					esc_html__( 'Re-optimize', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
				if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_convert_links' ) && 'ims_image' != get_post_type( $id ) && ! empty( $convert_desc ) ) {
					echo " | <a class='ewww-convert' title='$convert_desc' href='admin.php?action=ewww_image_optimizer_manual_optimize&amp;ewww_manual_nonce=$ewww_manual_nonce&amp;ewww_attachment_ID=$id&amp;ewww_convert=1&amp;ewww_force=1'>$convert_link</a>";
				}
			} else {
				echo $msg;
			}
			$restorable = false;
			if ( ! empty( $meta['converted'] ) ) {
				if ( ! empty( $meta['orig_file'] ) && file_exists( $meta['orig_file'] ) ) {
					$restorable = true;
				}
			}
			if ( isset( $meta['sizes'] ) ) {
				// meta sizes don't contain a path, so we calculate one
				$base_dir = trailingslashit( dirname( $file_path ) );
				foreach( $meta['sizes'] as $size => $data ) {
					if ( ! empty( $data['converted'] ) ) {
						if ( ! empty( $data['orig_file'] ) && file_exists( $base_dir . $data['orig_file'] ) ) {
							$restorable = true;
						}
					}		
				}
			}
			if ( $restorable && current_user_can( apply_filters( 'ewww_image_optimizer_manual_permissions', '' ) ) ) {
				printf( "<br><a href=\"admin.php?action=ewww_image_optimizer_manual_restore&amp;ewww_manual_nonce=$ewww_manual_nonce&amp;ewww_attachment_ID=%d\">%s</a>",
					$id,
					esc_html__( 'Restore original', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
			}

			// link to webp upgrade script
			$oldwebpfile = preg_replace('/\.\w+$/', '.webp', $file_path);
			if ( file_exists( $oldwebpfile ) && current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
				echo "<br><a href='options.php?page=ewww-image-optimizer-webp-migrate'>" . esc_html__( 'Run WebP upgrade', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</a>";
			}

			// determine filepath for webp
			$webpfile = $file_path . '.webp';
			$webp_size = ewww_image_optimizer_filesize( $webpfile );
			if ( $webp_size ) {
				$webp_size = size_format( $webp_size, 2 );
				$webpurl = esc_url( wp_get_attachment_url( $id ) . '.webp' );
				// get a human readable filesize
				$webp_size = preg_replace( '/\.00 B /', ' B', $webp_size );
				echo "<br>WebP: <a href='$webpurl'>$webp_size</a>";
			}
		} elseif ( get_transient( 'ewwwio-background-in-progress-' . $id ) ) {
			esc_html_e( 'In Progress', EWWW_IMAGE_OPTIMIZER_DOMAIN );
		} else {
			// otherwise, this must be an image we haven't processed
			esc_html_e( 'Not processed', EWWW_IMAGE_OPTIMIZER_DOMAIN );
			// tell them the filesize
			echo "<br>" . sprintf( esc_html__( 'Image Size: %s', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $file_size );
			if ( empty( $msg ) && current_user_can( apply_filters( 'ewww_image_optimizer_manual_permissions', '' ) ) ) {
				// and give the user the option to optimize the image right now
				printf( "<br><a href=\"admin.php?action=ewww_image_optimizer_manual_optimize&amp;ewww_manual_nonce=$ewww_manual_nonce&amp;ewww_attachment_ID=%d\">%s</a>", $id, esc_html__( 'Optimize now!', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
				if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_convert_links' ) && 'ims_image' != get_post_type( $id ) && ! empty( $convert_desc ) ) {
					echo " | <a class='ewww-convert' title='$convert_desc' href='admin.php?action=ewww_image_optimizer_manual_optimize&amp;ewww_manual_nonce=$ewww_manual_nonce&amp;ewww_attachment_ID=$id&amp;ewww_convert=1&amp;ewww_force=1'>$convert_link</a>";
				}
			} else {
				echo $msg;
			}
		}
	}
	ewwwio_memory( __FUNCTION__ );
}

// display a page of unprocessed images from Media library
function ewww_image_optimizer_display_unoptimized_media() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$bulk_resume = get_option( 'ewww_image_optimizer_bulk_resume' );
	update_option( 'ewww_image_optimizer_bulk_resume', '' );
	$attachments = ewww_image_optimizer_count_optimized( 'media', true );
	update_option( 'ewww_image_optimizer_bulk_resume', $bulk_resume );
	echo "<div class='wrap'><h1>" . esc_html__( 'Unoptimized Images', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</h1>";
	printf( '<p>' . esc_html__( 'We have %d images to optimize.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . '</p>', count( $attachments ) );
	if ( count( $attachments ) != 0 ) {
		sort( $attachments, SORT_NUMERIC );
		$image_string = implode( ',', $attachments );
		echo '<form method="post" action="upload.php?page=ewww-image-optimizer-bulk">'
			. "<input type='hidden' name='ids' value='$image_string' />"
			. '<input type="submit" class="button-secondary action" value="' . esc_html__( 'Optimize All Images', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . '" />'
			. '</form>';
		if ( count( $attachments ) < 500 ) {
			sort( $attachments, SORT_NUMERIC );
			$image_string = implode( ',', $attachments );
			echo '<table class="wp-list-table widefat media" cellspacing="0"><thead><tr><th>ID</th><th>&nbsp;</th><th>' . esc_html__('Title', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</th><th>' . esc_html__('Image Optimizer', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</th></tr></thead>';
			$alternate = true;
			foreach ( $attachments as $ID ) {
				$image_name = get_the_title( $ID );
?>				<tr<?php if( $alternate ) echo " class='alternate'"; ?>><td><?php echo $ID; ?></td>
<?php				echo "<td style='width:80px' class='column-icon'>" . wp_get_attachment_image( $ID, 'thumbnail' ) . "</td>";
				echo "<td class='title'>$image_name</td>";
				echo "<td>";
				ewww_image_optimizer_custom_column( 'ewww-image-optimizer', $ID );
				echo "</td></tr>";
				$alternate = ! $alternate;
			}
			echo '</table>';
		} else {
			echo '<p>' . esc_html__( 'There are too many images to display.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . '</p>'; 
		}
	}
	echo '</div>';
	return;	
}

// retrieve an option: use 'site' setting if plugin is network activated, otherwise use 'blog' setting
function ewww_image_optimizer_get_option( $option_name ) {
	global $ewwwio_settings;
/*	if ( isset( $ewwwio_settings[ $option_name ] ) ) {
		return $ewwwio_settings[ $option_name ];
	}*/
	$option_value = get_option( $option_name );
//	$ewwwio_settings[ $option_name ] = $option_value;
	return $option_value;
}

// set an option: use 'site' setting if plugin is network activated, otherwise use 'blog' setting
function ewww_image_optimizer_set_option( $option_name, $option_value ) {
	$success = update_option( $option_name, $option_value );
	return $success;
}

function ewww_image_optimizer_settings_script( $hook ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	// make sure we are being called from the bulk optimization page
	if ( strpos( $hook,'settings_page_ewww-image-optimizer' ) !== 0 ) {
		return;
	}
	wp_enqueue_script( 'ewwwbulkscript', plugins_url( '/includes/eio.js', __FILE__ ), array( 'jquery' ) );
	wp_enqueue_script( 'postbox' );
	wp_enqueue_script( 'dashboard' );
	wp_localize_script( 'ewwwbulkscript', 'ewww_vars', array(
			'_wpnonce' => wp_create_nonce( 'ewww-image-optimizer-settings' ),
		)
	);
	ewwwio_memory( __FUNCTION__ );
	return;
}

function ewww_image_optimizer_savings() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	if ( ! function_exists( 'is_plugin_active_for_network' ) && is_multisite() ) {
		// need to include the plugin library for the is_plugin_active function
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}
	if ( is_multisite() && is_plugin_active_for_network( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE_REL ) ) {
		ewwwio_debug_message( 'querying savings for multi-site' );
		if ( function_exists( 'wp_get_sites' ) ) {
			ewwwio_debug_message( 'retrieving list of sites the easy way' );
			add_filter( 'wp_is_large_network', 'ewww_image_optimizer_large_network', 20, 0 );
			$blogs = wp_get_sites( array(
				'network_id' => $wpdb->siteid,
				'limit' => 10000
			) );
			remove_filter( 'wp_is_large_network', 'ewww_image_optimizer_large_network', 20, 0 );
		} else {
			ewwwio_debug_message( 'retrieving list of sites the hard way' );
			$query = "SELECT blog_id FROM {$wpdb->blogs} WHERE site_id = '{$wpdb->siteid}' ";
			$blogs = $wpdb->get_results( $query, ARRAY_A );
		}
		$total_savings = 0;
		foreach ( $blogs as $blog ) {
			switch_to_blog( $blog['blog_id'] );
			ewwwio_debug_message( "getting savings for site: {$blog['blog_id']}" );
			$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'ewwwio_images WHERE image_size > orig_size' );
			$total_query = "SELECT SUM(orig_size-image_size) FROM $wpdb->ewwwio_images";
			ewwwio_debug_message( "query to be performed: $total_query" );
			$savings = $wpdb->get_var($total_query);
			ewwwio_debug_message( "savings found: $savings" );
			$total_savings += $savings;
		}
		restore_current_blog();
	} else {
		ewwwio_debug_message( 'querying savings for single site' );
		$total_savings = 0;
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'ewwwio_images WHERE image_size > orig_size' );
		$total_query = "SELECT SUM(orig_size-image_size) FROM $wpdb->ewwwio_images";
		ewwwio_debug_message( "query to be performed: $total_query" );
		$total_savings = $wpdb->get_var($total_query);
		ewwwio_debug_message( "savings found: $total_savings" );
	}
	return $total_savings;
}

function ewww_image_optimizer_htaccess_path() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$htpath = get_home_path();
	if ( get_option( 'siteurl' ) !== get_option( 'home' ) ) {
		ewwwio_debug_message( 'WordPress Address and Site Address are different, possible subdir install' );
		$path_diff = str_replace(  get_option( 'home' ), '', get_option( 'siteurl' ) );
		$newhtpath = trailingslashit( $htpath . $path_diff ) . '.htaccess';
		if ( is_file( $newhtpath ) ) {
			ewwwio_debug_message( 'subdir install confirmed' );
			return $newhtpath;
		}
	}
	return $htpath . '.htaccess';
}

function ewww_image_optimizer_webp_rewrite() {
	// verify that the user is properly authorized
	if ( ! wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-settings' ) ) {
		wp_die( esc_html__( 'Access denied.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
	}
	if ( $ewww_rules = ewww_image_optimizer_webp_rewrite_verify() ) {
		if ( insert_with_markers( ewww_image_optimizer_htaccess_path(), 'EWWWIO', $ewww_rules ) && ! ewww_image_optimizer_webp_rewrite_verify() ) {
			esc_html_e( 'Insertion successful', EWWW_IMAGE_OPTIMIZER_DOMAIN );
		} else {
			esc_html_e( 'Insertion failed', EWWW_IMAGE_OPTIMIZER_DOMAIN );
		}
	}
	die();
}

// if rules are present, stay silent, otherwise, give us some rules to insert!
function ewww_image_optimizer_webp_rewrite_verify() {
	$current_rules = extract_from_markers( ewww_image_optimizer_htaccess_path(), 'EWWWIO' ) ;
	$ewww_rules = array(
		"<IfModule mod_rewrite.c>",
		"RewriteEngine On",
		"RewriteCond %{HTTP_ACCEPT} image/webp",
		"RewriteCond %{REQUEST_FILENAME} (.*)\.(jpe?g|png)$",
		"RewriteCond %{REQUEST_FILENAME}.webp -f",
		"RewriteRule (.+)\.(jpe?g|png)$ %{REQUEST_FILENAME}.webp [T=image/webp,E=accept:1]",
		"</IfModule>",
		"<IfModule mod_headers.c>",
		"Header append Vary Accept env=REDIRECT_accept",
		"</IfModule>",
		"AddType image/webp .webp",
	);
	if ( array_diff( $ewww_rules, $current_rules ) ) {
		ewwwio_memory( __FUNCTION__ );
		return $ewww_rules;
	} else {
		ewwwio_memory( __FUNCTION__ );
		return;
	}
}

function ewww_image_optimizer_get_image_sizes() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $_wp_additional_image_sizes;
	$sizes = array();
	$image_sizes = get_intermediate_image_sizes();
	ewwwio_debug_message( print_r( $image_sizes, true ) );
//	ewwwio_debug_message( print_r( $_wp_additional_image_sizes, true ) );
	foreach( $image_sizes as $_size ) {
		if ( in_array( $_size, array( 'thumbnail', 'medium', 'medium_large', 'large' ) ) ) {
			$sizes[ $_size ]['width'] = get_option( $_size . '_size_w' );
			$sizes[ $_size ]['height'] = get_option( $_size . '_size_h' );
			if ( $_size === 'medium_large' && $sizes[ $_size ]['width'] == 0 ) {
				$sizes[ $_size ]['width'] = '768';
			}
			if ( $_size === 'medium_large' && $sizes[ $_size ]['height'] == 0 ) {
				$sizes[ $_size ]['height'] = '9999';
			}
		} elseif ( isset( $_wp_additional_image_sizes[ $_size ] ) ) {
			$sizes[ $_size ] = array( 
				'width' => $_wp_additional_image_sizes[ $_size ]['width'],
				'height' => $_wp_additional_image_sizes[ $_size ]['height'],
			);
		}
	}
	ewwwio_debug_message( print_r( $sizes, true ) );
	return $sizes;
}

function ewwwio_debug_message( $message ) {
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) ) {
		global $ewww_debug;
		$ewww_debug .= "$message<br>";
		echo $message . "\n";
	}
}

function ewwwio_debug_backtrace() {
	if ( defined( 'DEBUG_BACKTRACE_IGNORE_ARGS' ) ) {
		$backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );
	} else {
		$backtrace = debug_backtrace( false );
	}
	array_shift( $backtrace );
	array_shift( $backtrace );
	return maybe_serialize( $backtrace );
}

function ewww_image_optimizer_dynamic_image_debug() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	echo "<div class='wrap'><h1>" . esc_html__( 'Dynamic Image Debugging', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</h1>";
	global $wpdb;
	$debug_images = $wpdb->get_results( "SELECT path,updates,updated,trace FROM $wpdb->ewwwio_images WHERE trace IS NOT NULL ORDER BY updated DESC LIMIT 100" );
	if ( count( $debug_images ) != 0 ) {
		foreach ( $debug_images as $image ) {
			$trace = unserialize( $image->trace );
			echo '<p><b>' . esc_html__( 'File path', EWWW_IMAGE_OPTIMIZER_DOMAIN) . ': ' . $image->path . '</b><br>';
			echo esc_html__( 'Number of attempted optimizations', EWWW_IMAGE_OPTIMIZER_DOMAIN) . ': ' . $image->updates . '<br>';
			echo esc_html__( 'Last attempted', EWWW_IMAGE_OPTIMIZER_DOMAIN) . ': ' . $image->updated . '<br>';
			echo esc_html__( 'PHP trace', EWWW_IMAGE_OPTIMIZER_DOMAIN) . ':<br>';
			$i = 0;
			if ( is_array( $trace ) ) {
				foreach ( $trace as $function ) {
					if ( ! empty( $function['file'] ) && ! empty( $function['line'] ) ) {
						echo "#$i {$function['function']}() called at {$function['file']}:{$function['line']}<br>";
					} else {
						echo "#$i {$function['function']}() called<br>";
					}
					$i++;
				}
			} else {
				esc_html_e( 'Cannot display trace',  EWWW_IMAGE_OPTIMIZER_DOMAIN);
			}
			echo '</p>';
		}
	}
	echo '</div>';
	return;
}

function ewwwio_memory_output() {
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) ) {
		global $ewww_memory;
		$timestamp = date('y-m-d h:i:s.u') . "  ";
		if (!file_exists(EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'memory.log'))
			touch(EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'memory.log');
		file_put_contents(EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'memory.log', $timestamp . $ewww_memory, FILE_APPEND);
	}
}

// EWWW replacements

// adds table to db for storing status of auxiliary images that have been optimized
function ewww_image_optimizer_install_sqlite_table() {
	global $wpdb;
	if ( ! isset( $wpdb ) && ! defined( 'DB_NAME' ) ) {
		$wpdb = new wpdb( ABSPATH . 'ewwwio.db' );
	}

	if ( ! isset( $wpdb->ewwwio_images ) ) {
		$wpdb->ewwwio_images = $wpdb->prefix . "ewwwio_images";
	}

	// create a table with 4 columns: an id, the file path, the md5sum, and the optimization results
	$images_sql = "CREATE TABLE $wpdb->ewwwio_images (
		id integer PRIMARY KEY AUTOINCREMENT,
		path text NOT NULL UNIQUE,
		results text NOT NULL,
		image_size int unsigned,
		orig_size int unsigned,
		updates int unsigned,
		updated timestamp,
		trace blob
	);";
	
	$images = $wpdb->query( $images_sql );

	$options_sql = "CREATE TABLE $wpdb->options (
		option_id integer PRIMARY KEY AUTOINCREMENT,
		option_name text UNIQUE DEFAULT NULL, 
		option_value text NOT NULL,
		autoload text DEFAULT 'yes'
	);";
	$options = $wpdb->query( $options_sql );
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
}

// adds table to db for storing status of auxiliary images that have been optimized
function ewww_image_optimizer_install_mysql_table() {
	global $wpdb;
	if ( ! isset( $wpdb ) && defined( 'DB_NAME' ) && DB_NAME ) {
		$wpdb = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
	}
	if ( ! isset( $wpdb->ewwwio_images ) ) {
		$wpdb->ewwwio_images = $wpdb->prefix . "ewwwio_images";
	}

	//see if the path column exists, and what collation it uses to determine the column index size
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$wpdb->ewwwio_images'" ) == $wpdb->ewwwio_images ) {
		return;
	}
	// get the current wpdb charset and collation
	$charset_collate = $wpdb->get_charset_collate();

	// create a table with 4 columns: an id, the file path, the md5sum, and the optimization results
	$images_sql = "CREATE TABLE $wpdb->ewwwio_images (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		path text NOT NULL,
		results varchar(55) NOT NULL,
		image_size int(10) unsigned,
		orig_size int(10) unsigned,
		updates int(5) unsigned,
		updated timestamp DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
		trace blob,
		UNIQUE KEY id (id),
		KEY path_image_size (path(191),image_size)
	) $charset_collate;";

	$images = $wpdb->query( $images_sql );
	$options_sql = "CREATE TABLE $wpdb->options (
		option_id bigint(20) unsigned NOT NULL auto_increment,
		option_name varchar(191) NOT NULL default '',
		option_value longtext NOT NULL,
		autoload varchar(20) NOT NULL default 'yes',
		PRIMARY KEY  (option_id),
		UNIQUE KEY option_name (option_name)
		) $charset_collate;";
	$options = $wpdb->query( $options_sql );

	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
}

// WP replacement code
function plugin_dir_path( $file ) {
	return trailingslashit( dirname( $file ) );
}

function trailingslashit( $string ) {
	return untrailingslashit( $string ) . '/';
}

function untrailingslashit( $string ) {
	return rtrim( $string, '/\\' );
}

function _doing_it_wrong( $function, $message, $version ) {
	trigger_error( $function . ': ' . $message );
}

function maybe_serialize( $data ) {
	if ( is_array( $data ) || is_object( $data ) )
		return serialize( $data );
	if ( is_serialized( $data, false ) )
		return serialize( $data );

	return $data;
}

function maybe_unserialize( $original ) {
	if ( is_serialized( $original ) ) // don't attempt to unserialize data that wasn't serialized going in
		return @unserialize( $original );
	return $original;
}

function is_serialized( $data, $strict = true ) {
    // if it isn't a string, it isn't serialized.
    if ( ! is_string( $data ) ) {
        return false;
    }
    $data = trim( $data );
    if ( 'N;' == $data ) {
        return true;
    }
    if ( strlen( $data ) < 4 ) {
        return false;
    }
    if ( ':' !== $data[1] ) {
        return false;
    }
    if ( $strict ) {
        $lastc = substr( $data, -1 );
        if ( ';' !== $lastc && '}' !== $lastc ) {
            return false;
        }
    } else {
        $semicolon = strpos( $data, ';' );
        $brace     = strpos( $data, '}' );
        // Either ; or } must exist.
        if ( false === $semicolon && false === $brace )
            return false;
        // But neither must be in the first X characters.
        if ( false !== $semicolon && $semicolon < 3 )
            return false;
        if ( false !== $brace && $brace < 4 )
            return false;
    }
    $token = $data[0];
    switch ( $token ) {
        case 's' :
            if ( $strict ) {
                if ( '"' !== substr( $data, -2, 1 ) ) {
                    return false;
                }
            } elseif ( false === strpos( $data, '"' ) ) {
                return false;
            }
            // or else fall through
        case 'a' :
        case 'O' :
            return (bool) preg_match( "/^{$token}:[0-9]+:/s", $data );
        case 'b' :
        case 'i' :
        case 'd' :
            $end = $strict ? '$' : '';
            return (bool) preg_match( "/^{$token}:[0-9.E-]+;$end/", $data );
    }
    return false;
}

function mbstring_binary_safe_encoding( $reset = false ) {
	static $encodings = array();
	static $overloaded = null;
 
	if ( is_null( $overloaded ) )
		$overloaded = function_exists( 'mb_internal_encoding' ) && ( ini_get( 'mbstring.func_overload' ) & 2 );
 
	if ( false === $overloaded )
		return;
 
	if ( ! $reset ) {
		$encoding = mb_internal_encoding();
		array_push( $encodings, $encoding );
		mb_internal_encoding( 'ISO-8859-1' );
	}
 
	if ( $reset && $encodings ) {
		$encoding = array_pop( $encodings );
		mb_internal_encoding( $encoding );
	}
}

function reset_mbstring_encoding() {
	mbstring_binary_safe_encoding( true );
}

function wp_upload_dir() {
	return array( 'basedir' => ABSPATH );
}

function size_format( $bytes, $decimals = 0 ) {
    $quant = array(
        'TB' => 1024 * 1024 * 1024 * 1024,
        'GB' => 1024 * 1024 * 1024,
        'MB' => 1024 * 1024,
        'KB' => 1024,
        'B'  => 1,
    );
 
    if ( 0 === $bytes ) {
        return number_format_i18n( 0, $decimals ) . ' B';
    }
 
    foreach ( $quant as $unit => $mag ) {
        if ( doubleval( $bytes ) >= $mag ) {
            return number_format_i18n( $bytes / $mag, $decimals ) . ' ' . $unit;
        }
    }
 
    return false;
}

function number_format_i18n( $number, $decimals = 0 ) {
    /*if ( isset( $wp_locale ) ) {
        $formatted = number_format( $number, absint( $decimals ), $wp_locale->number_format['decimal_point'], $wp_locale->number_format['thousands_sep'] );
    } else {*/
    $formatted = number_format( $number, absint( $decimals ) );
    return $formatted;
}

function absint( $maybeint ) {
	return abs( intval( $maybeint ) );
}

function esc_html( $string ) { // TODO: make this do something
	return $string;
}

// WP translation function replacements
function __( $string ) {
	return $string;
}

function _e( $string ) {
	echo $string;
}

function _x( $string ) {
	return $string;
}
function _n( $string1, $string2, $count ) {
	if ( $count == 1 ) {
		return $string1;
	} else {
		return $string2;
	}
}

// stubs
function add_action() {
}

function add_filter() {
}

function apply_filters( $hook, $data ) {
	return $data;
}

function do_action() {
}

// WP option replacements
function get_option( $option, $default = false ) {
	global $wpdb;
	global $alloptions;

	$option = trim( $option );
	if ( empty( $option ) )
		return false;
	if ( empty( $alloptions ) ) {
		load_alloptions();
	}
	if ( isset( $alloptions[$option] ) ) {
		$value = maybe_unserialize( $alloptions[$option] );
		//if ( $option != 'ewww_image_optimizer_debug' )
		//	echo "getting autoloaded: $option: $value\n";
	} else {
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1", $option ) );
		// Has to be get_row instead of get_var because of funkiness with 0, false, null values
		if ( is_array( $row ) ) {
	//	print_r( $row );
	//	echo "\n";
			$value = $row['option_value'];
			//if ( $option != 'ewww_image_optimizer_debug' )
			//	echo "getting $option from db: $value\n";
			$alloptions[$option] = $value;
		} else { // option does not exist, so we must cache its non-existence
			return $default;
		}
	}

	return maybe_unserialize( $value );
}

function load_alloptions() {
	global $wpdb;
	global $alloptions;
	global $ewwwio_settings; // allow overrides from config.php

	if ( ! $alloptions ) {
		$suppress = $wpdb->suppress_errors();
		if ( ! $alloptions_db = $wpdb->get_results( "SELECT option_name, option_value FROM $wpdb->options WHERE autoload = 'yes'" ) )
			$alloptions_db = $wpdb->get_results( "SELECT option_name, option_value FROM $wpdb->options" );
		$wpdb->suppress_errors($suppress);
		$alloptions = array();
		foreach ( (array) $alloptions_db as $o ) {
			$option_name = $o['option_name'];
			$alloptions[ $option_name ] = $o['option_value'];
		}
		if ( isset( $ewwwio_settings ) && is_iterable( $ewwwio_settings ) ) {
			foreach ( $ewwwio_settings as $name => $value ) {
				//echo "setting $name to $value\n";
				$alloptions[ $name ] = $value;
			}
		}
	}


}

function update_option( $option, $value, $autoload = null ) {
	global $wpdb;

	$option = trim($option);
	if ( empty($option) )
		return false;

	$old_value = get_option( $option );

	// If the new and old values are the same, no need to update.
	if ( $value === $old_value )
		return false;

	$serialized_value = maybe_serialize( $value );

	$update_args = array(
		'option_value' => $serialized_value,
	);

	if ( null !== $autoload ) {
		$update_args['autoload'] = ( 'no' === $autoload || false === $autoload ) ? 'no' : 'yes';
	}

	$result = $wpdb->update( $wpdb->options, $update_args, array( 'option_name' => $option ) );
	if ( ! $result && $wpdb->ready )
		return false;
	global $alloptions;
	if ( isset( $alloptions[$option] ) ) {
		$alloptions[ $option ] = $serialized_value;
	}

	return true;
}

function add_option( $option, $value = '', $deprecated = '', $autoload = 'yes' ) {
	global $wpdb;

	$option = trim($option);
	if ( empty($option) )
		return false;

	$serialized_value = maybe_serialize( $value );
	$autoload = ( 'no' === $autoload || false === $autoload ) ? 'no' : 'yes';

	if ( $wpdb->is_mysql ) {
		//$result = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO `$wpdb->options` (`option_name`, `option_value`, `autoload`) VALUES (%s, %s, %s) ON DUPLICATE KEY UPDATE `option_name` = VALUES(`option_name`), `option_value` = VALUES(`option_value`), `autoload` = VALUES(`autoload`)", $option, $serialized_value, $autoload ) );
		$result = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO `$wpdb->options` (`option_name`, `option_value`, `autoload`) VALUES (%s, %s, %s)", $option, $serialized_value, $autoload ) );
	} else {
		$result = $wpdb->query( $wpdb->prepare( "INSERT OR IGNORE INTO `$wpdb->options` (`option_name`, `option_value`, `autoload`) VALUES (%s, %s, %s)", $option, $serialized_value, $autoload ) );
	}
	if ( ! $result && $wpdb->ready )
		return false;

	if ( 'yes' == $autoload ) {
		global $alloptions;
		$alloptions[ $option ] = $serialized_value;
	}

	return true;
}

/**
 * Removes option by name. Prevents removal of protected WordPress options.
 *
 * @since 1.2.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string $option Name of option to remove. Expected to not be SQL-escaped.
 * @return bool True, if option is successfully deleted. False on failure.
 */
function delete_option( $option ) {
	global $wpdb;

	$option = trim( $option );
	if ( empty( $option ) )
		return false;

	// Get the ID, if no ID then return
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT autoload FROM $wpdb->options WHERE option_name = %s", $option ) );
	if ( is_null( $row ) )
		return false;

	$result = $wpdb->delete( $wpdb->options, array( 'option_name' => $option ) );
		if ( 'yes' == $row['autoload'] ) {
			global $alloptions;
			if ( is_array( $alloptions ) && isset( $alloptions[$option] ) ) {
				unset( $alloptions[$option] );
			}
		}
	if ( $result ) {
		return true;
	}
	return false;
}

function delete_transient( $transient ) {

	$option_timeout = '_transient_timeout_' . $transient;
	$option = '_transient_' . $transient;
	$result = delete_option( $option );
	if ( $result )
		delete_option( $option_timeout );

	return $result;
}

function get_transient( $transient ) {

	$transient_option = '_transient_' . $transient;
	// If option is not in alloptions, it is not autoloaded and thus has a timeout
	global $alloptions;
	if ( ! isset( $alloptions[$transient_option] ) ) {
		$transient_timeout = '_transient_timeout_' . $transient;
		$timeout = get_option( $transient_timeout );
		if ( false !== $timeout && $timeout < time() ) {
			delete_option( $transient_option  );
			delete_option( $transient_timeout );
			$value = false;
		}
	}

	if ( ! isset( $value ) )
		$value = get_option( $transient_option );

	return $value;
}

function set_transient( $transient, $value, $expiration = 0 ) {

	$expiration = (int) $expiration;

	$transient_timeout = '_transient_timeout_' . $transient;
	$transient_option = '_transient_' . $transient;
	if ( false === get_option( $transient_option ) ) {
		$autoload = 'yes';
		if ( $expiration ) {
			$autoload = 'no';
			add_option( $transient_timeout, time() + $expiration, '', 'no' );
		}
		$result = add_option( $transient_option, $value, '', $autoload );
	} else {
		// If expiration is requested, but the transient has no timeout option,
		// delete, then re-create transient rather than update.
		$update = true;
		if ( $expiration ) {
			if ( false === get_option( $transient_timeout ) ) {
				delete_option( $transient_option );
				add_option( $transient_timeout, time() + $expiration, '', 'no' );
				$result = add_option( $transient_option, $value, '', 'no' );
				$update = false;
			} else {
				update_option( $transient_timeout, time() + $expiration );
			}
		}
		if ( $update ) {
			$result = update_option( $transient_option, $value );
		}
	}
	return $result;
}

//basically just used to generate random strings
function generate_password( $length = 12 ) {
	$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
	$password = '';
	for ( $i = 0; $i < $length; $i++ ) {
		$password .= substr( $chars, rand( 0, strlen( $chars ) - 1 ), 1 );
	}
	return $password;
}
?>
