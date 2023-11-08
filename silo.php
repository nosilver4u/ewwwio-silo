<?php
// TODO: cleanup massively
define( 'EWWW_IMAGE_OPTIMIZER_VERSION', '10.0' );
// Constants
define( 'EWWW_IMAGE_OPTIMIZER_DOMAIN', 'ewww-image-optimizer' );
// this is the full path of the plugin file itself
define( 'EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE', __FILE__ );
// this is the path of the plugin file relative to the plugins/ folder
define( 'EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE_REL', 'ewww-image-optimizer/ewww-image-optimizer.php' );
if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_TOOL_PATH' ) ) {
	// the folder where we install optimization tools
	define( 'EWWW_IMAGE_OPTIMIZER_TOOL_PATH', ABSPATH . 'tools/' );
}
// this is the full system path to the plugin folder
define( 'EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH', ABSPATH );
// this is the full system path to the bundled binaries
define( 'EWWW_IMAGE_OPTIMIZER_BINARY_PATH', ABSPATH . 'binaries/' );
// this is the full system path to the plugin images for testing
define( 'EWWW_IMAGE_OPTIMIZER_IMAGES_PATH', ABSPATH . 'images/' );

// initialize a couple globals
$ewww_debug = '';
$ewww_defer = true;

// Create SQLite3 table
if ( ! file_exists( ABSPATH . 'ewwwio.db' ) && ! defined( 'DB_NAME' ) ) {
	ewww_image_optimizer_install_sqlite_table();
}

// setup custom $wpdb attribute for our database
global $wpdb;

if ( ! isset( $wpdb ) && ! defined( 'DB_NAME' ) ) {
	$wpdb = new wpdb( ABSPATH . 'ewwwio.db' );
} elseif ( ! isset( $wpdb ) && defined( 'DB_NAME' ) && DB_NAME ) {
	$wpdb = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
}

// Create MySQL tables
if ( defined( 'DB_NAME' ) && DB_NAME ) {
	ewww_image_optimizer_install_mysql_table();
}

if ( ! isset( $wpdb->ewwwio_images ) ) {
	$wpdb->ewwwio_images = $wpdb->prefix . "ewwwio_images";
}

//let's get going
ewwwio_debug_message( 'EWWW IO version: ' . EWWW_IMAGE_OPTIMIZER_VERSION );

// check the PHP version
if ( ! defined( 'PHP_VERSION_ID' ) ) {
	$php_version = explode( '.', PHP_VERSION );
	define( 'PHP_VERSION_ID', ( $version[0] * 10000 + $version[1] * 100 + $version[2] ) );
}
if ( defined( 'PHP_VERSION_ID' ) ) {
	ewwwio_debug_message( 'PHP version: ' . PHP_VERSION_ID );
}

ewww_image_optimizer_cloud_verify();
ewww_image_optimizer_admin_init();
if ( ! class_exists( 'SQLite3' ) && ! defined( 'DB_NAME' ) ) {
	ewww_image_optimizer_cloud_verify(); //run it again to override defaults if necessary
}

