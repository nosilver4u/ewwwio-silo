<?php

// TODO: make sure to update timestamp field in table for image record
// TODO: port scan optimizations from core, and do the batch insert record stuff for more resiliant processing


if ( empty( $argv ) ) {
	echo "See --help for command-line options.";
	die;
}
if ( ! defined( 'EWWW_CLI' ) ) {
	define( 'EWWW_CLI', true );
}
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __file__ ) . '/' );
}

$ewwwio_settings = array();
if ( file_exists( ABSPATH . 'config.php' ) ) {
	include_once ABSPATH . 'config.php';
}

require_once __DIR__ . '/vendor/rmccue/requests/src/Autoload.php';
WpOrg\Requests\Autoload::register();

require ABSPATH . 'functions.php';
require ABSPATH . 'classes/class-colors.php';
require ABSPATH . 'classes/class-base.php';
require ABSPATH . 'classes/class-silo.php';

/**
 * The main function to return a single EWWW\SILO object to functions elsewhere.
 *
 * @return object object|EWWW\SILO The one true EWWW\SILO instance.
 */
function ewwwio() {
	return EWWW\SILO::instance();
}
ewwwio();

if ( ewwwio()->help ) {
	ewwwio()->commands->usage();
	exit;
}
if ( ! empty( ewwwio()->file ) && is_file( ewwwio()->file ) ) {
	ewwwio()->commands->single_optimize();
} else {
	ewwwio()->commands->folder_optimize();
}
