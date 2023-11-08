<?php
if ( empty( $argv ) ) {
	echo "This can only be run from the command-line.";
	die;
}
if ( ! defined( 'EWWW_CLI' ) ) {
	define( 'EWWW_CLI', true );
}
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __file__ ) . '/' );
}
if ( file_exists( ABSPATH . 'config.php' ) ) {
	include_once( ABSPATH . 'config.php' );
}

require( ABSPATH . 'classes/Requests/library/Requests.php' );
Requests::register_autoloader();

require( ABSPATH . 'common.php' );
if ( defined( 'DB_NAME' ) && DB_NAME ) {
	require( ABSPATH . 'mysql-db.php' );
} else {
	require( ABSPATH . 'sqlite3-db.php' );
}
require( ABSPATH . 'iocli.php' );
require( ABSPATH . 'ewww-image-optimizer.php' );
require( ABSPATH . 'silo.php' );

$args = $ewwwio_cli->parse_args();
//print_r( $args );
//echo "\n";
if ( isset( $args['help'] ) || isset( $args['h'] ) ) {
	$ewwwio_cli->usage();
	exit;
}
if ( isset( $args['single'] ) ) {
	$ewwwio_cli->single( $args );
} else {
	$ewwwio_cli->optimize( $args );
}
