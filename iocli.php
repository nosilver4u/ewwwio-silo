<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * implements cli extension for bulk optimizing
 */
class EWWWIO_CLI {

	public $force = false;
	public $delay = 0;

	function usage() {
		echo 	"usage: php cli.php [options]\n" .
			"\n" .
			"OPTIONS\n" .
			"\t--delay\toptional, number of seconds to pause between images\n" .
			"\t--force\toptional, re-optimize images that have already been processed\n" .
			"\t--reset\toptional, start the optimizer back at the beginning instead of resuming from last position\n" .
			"\t--noprompt\tdo not prompt, just start optimizing\n" .
			"\t--single\toptimize only one image, specified with the -f option\n" .
			"\t-f <folder> or <file>\n" .
			"\n" .
			"EXAMPLES\n" .
			"\tphp cli.php --delay 5 --noprompt\n" .
			"\tphp cli.php --delay 5 --noprompt -f /var/www/images\n" .
			"\tphp cli.php --single -f /var/www/images/image.jpg\n"
		;
	}

	function optimize( $args ) {
		if ( ! class_exists( 'SQLite3' ) && ! defined( 'DB_NAME' ) ) {
			$this->warning( __( 'The SQLite3 extension is missing, so we will not be able to keep track of what images have been optimized!', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
		}
		if ( empty( $args['delay'] ) ) {
			$this->delay = ewww_image_optimizer_get_option( 'ewww_image_optimizer_delay' );
		} else {
			$this->delay = $args['delay'];
		}
		if ( isset( $args['reset'] ) ) {
			update_option( 'ewww_image_optimizer_bulk_resume', '' );
			update_option( 'ewww_image_optimizer_bulk_attachments', '', 'no' );
			$this->line( __('Bulk status has been reset, starting from the beginning.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
		}
		if ( isset( $args['force'] ) ) {
			$this->line( __('Forcing re-optimization of previously processed images.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
			$this->force = true;
		}
		$this->line( sprintf( __('Optimizing with a %1$d second pause between images.', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $this->delay ) );
		// let's get started, shall we?
//		ewww_image_optimizer_admin_init();
		// and what shall we do?
				//$this->line( 'folder provided: ' . $args['f'] );
		$this->line( __( 'Scanning, this could take a while', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
		if ( ! empty( $args['f'] ) ) {
			$other_attachments = ewww_image_optimizer_scan_other( $args['f'] );
		} else {
			$other_attachments = ewww_image_optimizer_scan_other();
		}
		if ( empty( $this->force ) && empty( count( $other_attachments ) ) ) {
			$this->line( sprintf( __( '%1$d images need optimizing.', EWWW_IMAGE_OPTIMIZER_DOMAIN ), count($other_attachments) ) );
			$this->success( __('Finished Optimization!', EWWW_IMAGE_OPTIMIZER_DOMAIN) );
			return;
		}
		if ( ! isset( $args['noprompt'] ) ) {
			$this->confirm( sprintf( __( '%1$d images need optimizing.', EWWW_IMAGE_OPTIMIZER_DOMAIN ), count($other_attachments) ) );
		}
		ewww_image_optimizer_bulk_other( $other_attachments );
	}

	function single( $args ) {
		if ( ! class_exists( 'SQLite3' ) && ! defined( 'DB_NAME' ) ) {
			$this->warning( __( 'The SQLite3 extension is missing, so we will not be able to keep track of what images have been optimized!', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
		}
		if ( isset( $args['force'] ) ) {
			$this->line( __('Forcing re-optimization of previously processed images.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
			$this->force = true;
		}
		if ( empty( $args['f'] ) ) {
			$this->error( __('Must specify a file with -f', EWWW_IMAGE_OPTIMIZER_DOMAIN) );
		}
		$attachment = trim( $args['f'] );
		// retrieve the time when the optimizer starts
		$started = microtime( true );
		// do the optimization for the current image
		$results = ewww_image_optimizer( $attachment, 4, false, false );
		// output the path
		EWWWIO_CLI::line( __('Optimized image:', EWWW_IMAGE_OPTIMIZER_DOMAIN) . ' ' . $attachment );
		// tell the user what the results were for the original image
		EWWWIO_CLI::success( html_entity_decode( $results[1] ) );
		// calculate how much time has elapsed since we started
		$elapsed = microtime(true) - $started;
		// output how much time has elapsed since we started
		EWWWIO_CLI::line( sprintf( __( 'Elapsed: %.3f seconds', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $elapsed) );
	}

	function parse_args() {
		$user_args = getopt( 'f:h', array(
			'delay:',
			'force',
			'help',
			'noprompt',
			'reset',
			'single',
		) );
		return $user_args;
	}
	function success( $message ) {
		if ( ! defined( 'EWWW_CLI' ) || ! EWWW_CLI ) {
			return;
		}
		global $colors;
		echo $colors->getColoredString( $message, 'green' ) . "\n";
	}
	function warning( $message ) {
		if ( ! defined( 'EWWW_CLI' ) || ! EWWW_CLI ) {
			return;
		}
		global $colors;
		echo $colors->getColoredString( $message, 'red' ) . "\n";
	}
	function error( $message ) {
		if ( ! defined( 'EWWW_CLI' ) || ! EWWW_CLI ) {
			return;
		}
		global $colors;
		echo $colors->getColoredString( $message, 'red' ) . "\n";
		error_log( $message );
		exit;
	}
	function line( $message ) {
		if ( ! defined( 'EWWW_CLI' ) || ! EWWW_CLI ) {
			return;
		}
		echo $message . "\n";
	}
	function confirm( $question ) {
		if ( ! defined( 'EWWW_CLI' ) || ! EWWW_CLI ) {
			return;
		}
		echo $question . " [y/n] ";
		$answer = strtolower( trim( fgets( STDIN ) ) );
		if ( 'y' != $answer )
			exit;
	}
}
global $ewwwio_cli;
$ewwwio_cli = new EWWWIO_CLI();

// displays the 'Optimize Everything Else' section of the Bulk Optimize page
function ewww_image_optimizer_scan_other( $folder = null ) {
	global $wpdb;
//	$aux_resume = get_option('ewww_image_optimizer_aux_resume');
	// initialize the $attachments variable for auxiliary images
	$attachments = null;
	// check the 'bulk resume' option
//	$resume = get_option('ewww_image_optimizer_aux_resume');
        // check if there is a previous bulk operation to resume
	if ( $folder && is_dir( $folder ) ) {
		$attachments = ewww_image_optimizer_image_scan( $folder );
		// store the filenames we retrieved in the 'bulk_attachments' option so we can keep track of our progress in the database
		update_option('ewww_image_optimizer_bulk_attachments', $attachments, 'no');
        } elseif ( get_option( 'ewww_image_optimizer_bulk_resume' ) ) {
		// retrieve the attachment IDs that have not been finished from the 'bulk attachments' option
		$attachments = get_option('ewww_image_optimizer_bulk_attachments');
	} else {
		// collect a list of images in auxiliary folders provided by user
		if ( $aux_paths = ewww_image_optimizer_get_option( 'ewww_image_optimizer_aux_paths' ) ) {
			foreach ( $aux_paths as $aux_path ) {
				$attachments = array_merge( $attachments, ewww_image_optimizer_image_scan( $aux_path ) );
			}
		}
		// store the filenames we retrieved in the 'bulk_attachments' option so we can keep track of our progress in the database
		update_option( 'ewww_image_optimizer_bulk_attachments', $attachments, 'no' );
	}
	return $attachments;
}

function ewww_image_optimizer_bulk_other( $attachments ) {
	global $ewwwio_cli;
	// update the 'aux resume' option to show that an operation is in progress
	update_option('ewww_image_optimizer_bulk_resume', 'true');
	// store the time and number of images for later display
	$count = count( $attachments );
	$current = 0;
//	update_option('ewww_image_optimizer_bulk_last', array(time(), $count));
	foreach ( $attachments as $attachment ) {
		sleep( $ewwwio_cli->delay );
		// retrieve the time when the optimizer starts
		$started = microtime( true );
		// get the 'aux attachments' with a list of attachments remaining
		$attachments_left = get_option('ewww_image_optimizer_bulk_attachments');
		// do the optimization for the current image
		$results = ewww_image_optimizer( $attachment, 4, false, false );
		// remove the first element fromt the $attachments array
		if ( ! empty( $attachments_left ) ) {
			array_shift( $attachments_left );
		}
		// store the updated list of attachments back in the 'bulk_attachments' option
		update_option( 'ewww_image_optimizer_bulk_attachments', $attachments_left );
		$current++;
		// output the path
		EWWWIO_CLI::line( "($current/$count) " . __('Optimized image:', EWWW_IMAGE_OPTIMIZER_DOMAIN) . ' ' . $attachment );
		// tell the user what the results were for the original image
		EWWWIO_CLI::line( html_entity_decode( $results[1] ) );
		// calculate how much time has elapsed since we started
		$elapsed = microtime(true) - $started;
		// output how much time has elapsed since we started
		EWWWIO_CLI::line( sprintf( __( 'Elapsed: %.3f seconds', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $elapsed) );
	} 
//	$stored_last = get_option('ewww_image_optimizer_aux_last');
//	update_option('ewww_image_optimizer_aux_last', array(time(), $stored_last[1]));
	// all done, so we can update the bulk options with empty values
	update_option('ewww_image_optimizer_bulk_resume', '');
	update_option('ewww_image_optimizer_bulk_attachments', '');
	// and let the user know we are done
	EWWWIO_CLI::success( __('Finished Optimization!', EWWW_IMAGE_OPTIMIZER_DOMAIN) );
}
// from https://www.if-not-true-then-false.com/2010/php-class-for-coloring-php-command-line-cli-scripts-output-php-output-colorizing-using-bash-shell-colors/
class Colors {
	private $foreground_colors = array();
	private $background_colors = array();

	public function __construct() {
		// Set up shell colors
		$this->foreground_colors['black'] = '0;30';
		$this->foreground_colors['dark_gray'] = '1;30';
		$this->foreground_colors['blue'] = '0;34';
		$this->foreground_colors['light_blue'] = '1;34';
		$this->foreground_colors['green'] = '0;32';
		$this->foreground_colors['light_green'] = '1;32';
		$this->foreground_colors['cyan'] = '0;36';
		$this->foreground_colors['light_cyan'] = '1;36';
		$this->foreground_colors['red'] = '0;31';
		$this->foreground_colors['light_red'] = '1;31';
		$this->foreground_colors['purple'] = '0;35';
		$this->foreground_colors['light_purple'] = '1;35';
		$this->foreground_colors['brown'] = '0;33';
		$this->foreground_colors['yellow'] = '1;33';
		$this->foreground_colors['light_gray'] = '0;37';
		$this->foreground_colors['white'] = '1;37';

		$this->background_colors['black'] = '40';
		$this->background_colors['red'] = '41';
		$this->background_colors['green'] = '42';
		$this->background_colors['yellow'] = '43';
		$this->background_colors['blue'] = '44';
		$this->background_colors['magenta'] = '45';
		$this->background_colors['cyan'] = '46';
		$this->background_colors['light_gray'] = '47';
	}

	// Returns colored string
	public function getColoredString($string, $foreground_color = null, $background_color = null) {
		$colored_string = "";

		// Check if given foreground color found
		if (isset($this->foreground_colors[$foreground_color])) {
			$colored_string .= "\033[" . $this->foreground_colors[$foreground_color] . "m";
		}
		// Check if given background color found
		if (isset($this->background_colors[$background_color])) {
			$colored_string .= "\033[" . $this->background_colors[$background_color] . "m";
		}

		// Add string and end coloring
		$colored_string .=  $string . "\033[0m";

		return $colored_string;
	}

	// Returns all foreground color names
	public function getForegroundColors() {
		return array_keys($this->foreground_colors);
	}

	// Returns all background color names
	public function getBackgroundColors() {
		return array_keys($this->background_colors);
	}
}
global $colors;
$colors = new Colors();
