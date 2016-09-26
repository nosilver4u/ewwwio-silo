<?php
// TODO: cleanup massively
// TODO: if SQLite3 class does not exist, alert the user, and then proceed without tracking, and load defaults from config.php (if exists)
define( 'EWWW_IMAGE_OPTIMIZER_VERSION', '10.0' );
// Constants
define( 'EWWW_IMAGE_OPTIMIZER_DOMAIN', 'ewww-image-optimizer' );
// this is the full path of the plugin file itself
define( 'EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE', __FILE__ );
// this is the path of the plugin file relative to the plugins/ folder
define( 'EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE_REL', 'ewww-image-optimizer/ewww-image-optimizer.php' );
// the folder where we install optimization tools
define( 'EWWW_IMAGE_OPTIMIZER_TOOL_PATH', ABSPATH . 'tools/' );
// this is the full system path to the plugin folder
define( 'EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH', ABSPATH );
// this is the full system path to the bundled binaries
define( 'EWWW_IMAGE_OPTIMIZER_BINARY_PATH', ABSPATH . 'binaries/' );
// this is the full system path to the plugin images for testing
define( 'EWWW_IMAGE_OPTIMIZER_IMAGES_PATH', ABSPATH . 'images/' );

// initialize a couple globals
$ewww_debug = '';
$ewww_defer = true;

if ( ! file_exists( ABSPATH . 'ewwwio.db' ) ) {
	ewww_image_optimizer_install_table();
}

// setup custom $wpdb attribute for our database
global $wpdb;

if ( ! isset( $wpdb ) ) {
	$wpdb = new wpdb( ABSPATH . 'ewwwio.db' );
}

if ( ! isset( $wpdb->ewwwio_images ) ) {
	$wpdb->ewwwio_images = $wpdb->prefix . "ewwwio_images";
}

//let's get going
$disabled = ini_get( 'disable_functions' );
if ( ! preg_match( '/get_current_user/', $disabled ) ) {
	ewwwio_debug_message( get_current_user() );
}

ewwwio_debug_message( 'EWWW IO version: ' . EWWW_IMAGE_OPTIMIZER_VERSION );

// check the PHP version
if ( ! defined( 'PHP_VERSION_ID' ) ) {
	$php_version = explode( '.', PHP_VERSION );
	define( 'PHP_VERSION_ID', ( $version[0] * 10000 + $version[1] * 100 + $version[2] ) );
}
if ( defined( 'PHP_VERSION_ID' ) ) {
	ewwwio_debug_message( 'PHP version: ' . PHP_VERSION_ID );
}

ewww_image_optimizer_admin_init();

// Hooks
// TODO: add alternative post indicator on settings to install pngout
add_action( 'admin_action_ewww_image_optimizer_install_pngout', 'ewww_image_optimizer_install_pngout' );
