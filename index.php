<?php
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', trailingslashit( dirname( __file__ ) );
}
if ( file_exists( ABSPATH . 'config.php' ) ) {
	include_once( ABSPATH . 'config.php' );
}
require( ABSPATH . 'silo.php' );
require( ABSPATH . 'ewww-image-optimizer.php' );
?>
