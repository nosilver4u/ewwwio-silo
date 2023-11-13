<?php

namespace EWWW;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Implements CLI commands for image optimizing
 */
class Commands extends Base {

	public function usage() {
		echo 	"usage: php cli.php [options]\n" .
			"\n" .
			"OPTIONS\n" .
			"\t--delay\t\toptional, number of seconds to pause between images\n" .
			"\t--debug\t\toptional, enable detailed debugging output\n" .
			"\t--force\t\toptional, re-optimize images that have already been processed\n" .
			"\t--reset\t\toptional, start the optimizer back at the beginning instead of resuming from last position\n" .
			"\t--noprompt\tdo not prompt, just start optimizing\n" .
			"\t-f\t\toptional <folder> or <file>\n" .
			"\n" .
			"EXAMPLES\n" .
			"\tphp cli.php --delay 5 --noprompt\n" .
			"\tphp cli.php --delay 5 --noprompt -f /var/www/images\n" .
			"\tphp cli.php -f /var/www/images/image.jpg\n"
		;
	}

	public function folder_optimize() {
		if ( ! class_exists( 'SQLite3' ) && ! defined( 'DB_NAME' ) ) {
			$this->warning( __( 'The SQLite3 extension is missing, so we will not be able to keep track of what images have been optimized!', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
		}
		if ( ! $this->delay ) {
			$this->delay = (int) $this->get_option( 'ewww_image_optimizer_delay' );
		}
		if ( $this->reset ) {
			update_option( 'ewww_image_optimizer_bulk_resume', '' );
			update_option( 'ewww_image_optimizer_bulk_attachments', '', 'no' );
			$this->line( __('Bulk status has been reset, starting from the beginning.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
		}
		if ( $this->force ) {
			$this->line( __('Forcing re-optimization of previously processed images.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
		}
		$this->line( sprintf( __('Optimizing with a %1$d second pause between images.', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $this->delay ) );

		$this->line( __( 'Scanning, this could take a while', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
		if ( ! empty( $this->file ) ) {
			$other_attachments = $this->scan_folders( $this->file );
		} else {
			$aux_paths = $this->get_option( 'ewww_image_optimizer_aux_paths' );
			if ( ! is_array( $aux_paths ) || empty( $aux_paths ) ) {
				$this->error( __( 'Must specify a file/folder with -f or configure ewww_image_optimizer_aux_paths in config.php.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
			}
			$other_attachments = $this->scan_folders();
		}
		if ( empty( $this->force ) && empty( count( $other_attachments ) ) ) {
			$this->line( sprintf( __( '%1$d images need optimizing.', EWWW_IMAGE_OPTIMIZER_DOMAIN ), count($other_attachments) ) );
			$this->success( __( 'Finished Optimization!', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
			return;
		}
		if ( ! $this->noprompt ) {
			$this->confirm( sprintf( __( '%1$d images need optimizing.', EWWW_IMAGE_OPTIMIZER_DOMAIN ), count($other_attachments) ) );
		}
		$this->bulk_process( $other_attachments );
	}

	public function single_optimize() {
		if ( ! class_exists( 'SQLite3' ) && ! defined( 'DB_NAME' ) ) {
			$this->warning( __( 'The SQLite3 extension is missing, so we will not be able to keep track of what images have been optimized!', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
		}
		if ( $this->force ) {
			$this->line( __( 'Forcing re-optimization of previously processed images.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
		}
		if ( empty( $this->file ) ) {
			$this->error( __( 'Must specify a file with -f', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
		}
		$attachment = trim( $this->file );
		// retrieve the time when the optimizer starts
		$started = microtime( true );
		// do the optimization for the current image
		$results = $this->optimize( $attachment );
		// output the path
		$this->line( __( 'Optimized image:', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . ' ' . $attachment );
		// tell the user what the results were for the original image
		$this->success( html_entity_decode( $results[1] ) );
		// calculate how much time has elapsed since we started
		$elapsed = microtime(true) - $started;
		// output how much time has elapsed since we started
		$this->line( sprintf( __( 'Elapsed: %.3f seconds', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $elapsed) );
	}

	// displays the 'Optimize Everything Else' section of the Bulk Optimize page
	private function scan_folders( $folder = null ) {
		global $wpdb;

		// initialize the $attachments variable for auxiliary images
		$attachments = null;

	    // check if there is a previous bulk operation to resume
		if ( $folder && is_dir( $folder ) ) {
			$attachments = $this->scan_folder( $folder );
			// store the filenames we retrieved in the 'bulk_attachments' option so we can keep track of our progress in the database
			update_option('ewww_image_optimizer_bulk_attachments', $attachments, 'no');
	        } elseif ( get_option( 'ewww_image_optimizer_bulk_resume' ) ) {
			// retrieve the attachment IDs that have not been finished from the 'bulk attachments' option
			$attachments = get_option('ewww_image_optimizer_bulk_attachments');
		} else {
			// collect a list of images in auxiliary folders provided by user
			$aux_paths = $this->get_option( 'ewww_image_optimizer_aux_paths' );
			if ( is_array( $aux_paths ) && ! empty( $aux_paths ) ) {
				foreach ( $aux_paths as $aux_path ) {
					$attachments = array_merge( $attachments, $this->scan_folder( $aux_path ) );
				}
			}
			// store the filenames we retrieved in the 'bulk_attachments' option so we can keep track of our progress in the database
			update_option( 'ewww_image_optimizer_bulk_attachments', $attachments, 'no' );
		}
		return $attachments;
	}


	// scan a folder for images and return them as an array
	private function scan_folder( $dir ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		global $wpdb;
		$images = array();
		if ( ! is_dir( $dir ) ) {
			return $images;
		}
		$this->debug_message( "scanning folder for images: $dir" );
		$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir ), \RecursiveIteratorIterator::CHILD_FIRST, \RecursiveIteratorIterator::CATCH_GET_CHILD );
		$start = microtime( true );
		$query = "SELECT path,image_size FROM $wpdb->ewwwio_images";
		$already_optimized = $wpdb->get_results( $query, ARRAY_A );
		$optimized_list = array();
		if ( $this->is_iterable( $already_optimized ) ) {
			foreach( $already_optimized as $optimized ) {
				$optimized_path = $optimized['path'];
				$optimized_list[ $optimized_path ] = $optimized['image_size'];
			}
		}
		$file_counter = 0;
		if ( ewww_image_optimizer_stl_check() ) {
			set_time_limit( 0 );
		}
		foreach ( $iterator as $file ) {
			$file_counter++;
			$skip_optimized = false;
			if ( $file->isFile() ) {
				$path = $file->getPathname();
				if ( preg_match( '/(\/|\\\\)\./', $path ) ) {
					continue;
				}
				if ( ! $this->quick_mimetype( $path ) ) {
					continue;
				}
				if ( isset( $optimized_list[$path] ) ) {
					$image_size = $file->getSize();
					if ( $optimized_list[ $path ] == $image_size ) {
						$this->debug_message( "match found for $path" );
						$skip_optimized = true;
					} else {
						$this->debug_message( "mismatch found for $path, db says " . $optimized_list[ $path ] . " vs. current $image_size" );
					}
				}
				if ( empty( $skip_optimized ) || ! empty( $this->force ) ) {
					$this->debug_message( "queued $path" );
					$images[] = $path;
				}
			}
		}
		$end = microtime( true ) - $start;
	    $this->debug_message( "query time for $file_counter files (seconds): $end" );
		return $images;
	}

	private function bulk_process( $attachments ) {
		// update the 'aux resume' option to show that an operation is in progress
		update_option('ewww_image_optimizer_bulk_resume', 'true');
		// store the time and number of images for later display
		$count = count( $attachments );
		$current = 0;
		foreach ( $attachments as $attachment ) {
			sleep( $this->delay );
			// retrieve the time when the optimizer starts
			$started = microtime( true );
			// get the 'aux attachments' with a list of attachments remaining
			$attachments_left = get_option('ewww_image_optimizer_bulk_attachments');
			// do the optimization for the current image
			$results = $this->optimize( $attachment );
			// remove the first element fromt the $attachments array
			if ( ! empty( $attachments_left ) ) {
				array_shift( $attachments_left );
			}
			// store the updated list of attachments back in the 'bulk_attachments' option
			update_option( 'ewww_image_optimizer_bulk_attachments', $attachments_left );
			$current++;
			// output the path
			$this->line( "($current/$count) " . __( 'Optimized image:', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . ' ' . $attachment );
			// tell the user what the results were for the original image
			$this->line( html_entity_decode( $results[1] ) );
			// calculate how much time has elapsed since we started
			$elapsed = microtime(true) - $started;
			// output how much time has elapsed since we started
			$this->line( sprintf( __( 'Elapsed: %.3f seconds', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $elapsed) );
		} 
		// all done, so we can update the bulk options with empty values
		update_option( 'ewww_image_optimizer_bulk_resume', '' );
		update_option( 'ewww_image_optimizer_bulk_attachments', '' );
		// and let the user know we are done
		$this->success( __( 'Finished Optimization!', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
	}

	/**
	 * Process an image.
	 *
	 * Returns an array of the $file and the optimization $results.
	 *
	 * @param string $file Full absolute path to the image file
	 * @returns array
	 */
	public function optimize( $file ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$result = '';
		// check that the file exists
		if ( ! is_file( $file ) ) {
			// tell the user we couldn't find the file
			$msg = sprintf( __( 'Could not find %s', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $file );
			$this->debug_message( "file doesn't appear to exist: $file" );
			// send back the above message
			return array( false, $msg );
		}
		// check that the file is writable
		if ( ! is_writable( $file ) ) {
			// tell the user we can't write to the file
			$msg = sprintf( __( '%s is not writable', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $file );
			$this->debug_message( "couldn't write to the file $file" );
			// send back the above message
			return array( false, $msg );
		}
		$type = $this->mimetype( $file, 'i' );
		if ( ! $type ) {
			$this->debug_message( 'could not find mimetype' );
			//otherwise we store an error message since we couldn't get the mime-type
			return array( false, __( 'Unknown file type', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
		}
		if ( strpos( $type, 'image' ) === false && strpos( $type, 'pdf' ) === false ) {
			$this->debug_message( "unsupported mimetype: $type" );
			return array( false, __( 'Unsupported file type', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . ": $type" );
		}
		$nice = '';
		if ( PHP_OS !== 'WINNT' && ! ewwwio()->cloud_mode && ewwwio()->local->exec_check() ) {
			// Check to see if 'nice' exists.
			$nice = ewwwio()->local->find_nix_binary( 'nice' );
		}
		$tools = array();
		$keep_metadata = false;
		$skip_lossy    = false;
		// If the max_execution_time is not 0, but less than 90, and configurable, set it to 0.
		if ( ini_get( 'max_execution_time' ) && ini_get( 'max_execution_time' ) < 90 && ewww_image_optimizer_stl_check() ) {
			set_time_limit( 0 );
		}
		// get the original image size
		$orig_size = ewwwio()->filesize( $file );
		ewwwio_debug_message( "original filesize: $orig_size" );
		if ( $orig_size < ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_size' ) ) {
			// tell the user optimization was skipped
			ewwwio_debug_message( "optimization bypassed due to filesize: $file" );
			return array( false, __( 'Optimization skipped', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
		}
		if ( 'image/png' === $type && ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_png_size' ) && $orig_size > ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_png_size' ) ) {
			// tell the user optimization was skipped
			ewwwio_debug_message( "optimization bypassed due to filesize: $file" );
			return array( false, __( 'Optimization skipped', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
		}
		$new_size = $orig_size;
		// set the optimization process to OFF
		$optimize = false;
		// run the appropriate optimization/conversion for the mime-type
		switch ( $type ) {
			case 'image/jpeg':
				// check for previous optimization, so long as the force flag isn't on
				if ( empty( $this->force ) ) {
					$results_msg = ewww_image_optimizer_check_table( $file, $orig_size );
					if ( $results_msg ) {
						return array( $file, $results_msg );
					}
				}
				if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) > 10 ) {
					list( $file, $result, $new_size ) = ewww_image_optimizer_cloud_optimizer( $file, $type );
					$webp_result = $this->webp_create( $file, $new_size, $type, null, $orig_size != $new_size );
					break;
				}

				$tools['jpegtran'] = ewwwio()->local->get_path( 'jpegtran' );
				$tools['cwebp']    = ewwwio()->local->get_path( 'cwebp' );

				// if jpegtran optimization is disabled
				if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) ) {
					// store an appropriate message in $result
					$result = __( 'JPG optimization is disabled', EWWW_IMAGE_OPTIMIZER_DOMAIN );
				// otherwise, if we aren't skipping the utility verification and jpegtran doesn't exist
				} elseif ( empty( $tools['jpegtran'] ) ) {
					// store an appropriate message in $result
					$result = sprintf( __( '%s is missing', EWWW_IMAGE_OPTIMIZER_DOMAIN ), 'jpegtran' );
				// otherwise, things should be good, so...
				} else {
					// set the optimization process to ON
					$optimize = true;
				}
				// if optimization is turned ON
				if ( $optimize ) {
					ewwwio_debug_message( 'attempting to optimize JPG...' );
					// generate temporary file-name:
					$progfile = $file . ".prog"; // progressive jpeg
					// check to see if we are supposed to remove meta
					if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_metadata_remove' ) ) {
						// don't copy metadata
						$copy_opt = 'none';
					} else {
						// copy all the metadata
						$copy_opt = 'all';
					}
					if ( $orig_size > 10240 ) {
						$progressive = '-progressive';
					} else {
						$progressive = '';
					}
					// run jpegtran - progressive
					exec( "$nice " . $tools['jpegtran'] . " -copy $copy_opt -optimize $progressive -outfile " . ewwwio()->escapeshellarg( $progfile ) . " " . ewwwio()->escapeshellarg( $file ) );
					// check the filesize of the progressive JPG
					$new_size = ewwwio()->filesize( $progfile );
					ewwwio_debug_message( "optimized JPG size: $new_size" );
					// if the best-optimized is smaller than the original JPG, and we didn't create an empty JPG
					if ( $new_size && $orig_size > $new_size && ewwwio()->mimetype( $progfile, 'i' ) === $type ) {
						// replace the original with the optimized file
						rename( $progfile, $file );
						// store the results of the optimization
						$result = "$orig_size vs. $new_size";
					// if the optimization didn't produce a smaller JPG
					} else {
						if ( is_file( $progfile ) ) {
							// delete the optimized file
							unlink($progfile);
						}
						// store the results
						$result   = 'unchanged';
						$new_size = $orig_size;
					}
				}
				$webp_result = $this->webp_create( $file, $new_size, $type, $tools['cwebp'], $orig_size !== $new_size );
				break;
			case 'image/png':
				// check for previous optimization, so long as the force flag isn't on
				if ( empty( $this->force ) ) {
					$results_msg = ewww_image_optimizer_check_table( $file, $orig_size );
					if ( $results_msg ) {
						return array( $file, $results_msg );
					}
				}
				if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) >= 20 && ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) {
					list( $file, $result, $new_size ) = ewww_image_optimizer_cloud_optimizer( $file, $type );
					$webp_result = $this->webp_create( $file, $new_size, $type, null, $orig_size != $new_size );
					break;
				}

				$apng = ewww_image_optimizer_is_animated_png( $file );

				$tools['optipng']  = ewwwio()->local->get_path( 'optipng' );
				$tools['pngout']   = ewwwio()->local->get_path( 'pngout' );
				$tools['pngquant'] = ewwwio()->local->get_path( 'pngquant' );
				$tools['cwebp']    = ewwwio()->local->get_path( 'cwebp' );

				// if pngout and optipng are disabled
				if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) ) {
					// tell the user all PNG tools are disabled
					$result = __( 'PNG optimization is disabled', EWWW_IMAGE_OPTIMIZER_DOMAIN );
				// if the utility checking is on, optipng is enabled, but optipng cannot be found
				} elseif ( empty( $tools['optipng'] ) ) {
					// tell the user optipng is missing
					$result = sprintf( __( '%s is missing', EWWW_IMAGE_OPTIMIZER_DOMAIN ), 'optipng' );
				} else {
					// turn optimization on if we made it through all the checks
					$optimize = true;
				}
				// if optimization is turned on
				if ( $optimize ) {
					// if lossy optimization is ON and full-size exclusion is not active
					if ( 40 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) && $tools['pngquant'] && ! $apng ) {
						ewwwio_debug_message( 'attempting lossy reduction' );
						exec( "$nice " . $tools['pngquant'] . " " . ewwwio()->escapeshellarg( $file ) );
						$quantfile = preg_replace( '/\.\w+$/', '-fs8.png', $file );
						$quantsize = ewwwio()->filesize( $quantfile );
						if ( $quantsize && $orig_size > $quantsize && $type === ewwwio()->mimetype( $quantfile, 'i' ) ) {
							ewwwio_debug_message( "lossy reduction is better: original - " . filesize( $file ) . " vs. lossy - " . filesize( $quantfile ) );
							rename( $quantfile, $file );
						} elseif ( is_file( $quantfile ) ) {
							ewwwio_debug_message( "lossy reduction is worse: original - $orig_size vs. lossy - $quantsize" );
							unlink( $quantfile );
						} else {
							ewwwio_debug_message( 'pngquant did not produce any output' );
						}
					}

					$tempfile = $file . '.tmp.png';
					copy( $file, $tempfile );

					// retrieve the optimization level for optipng
					$optipng_level = (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_optipng_level' );
					if (
						ewww_image_optimizer_get_option( 'ewww_image_optimizer_metadata_remove' ) &&
						preg_match( '/0.7/', ewwwio()->local->test_binary( $tools['optipng'], 'optipng' ) ) &&
						! $apng
					) {
						$strip = '-strip all ';
					} else {
						$strip = '';
					}
					// run optipng on the PNG file
					exec( "$nice " . $tools['optipng'] . " -o$optipng_level -quiet $strip " . ewwwio()->escapeshellarg( $tempfile ) );

					// retrieve the filesize of the temporary PNG
					$new_size = ewwwio()->filesize( $tempfile );
					// if the new PNG is smaller
					if ( $new_size && $orig_size > $new_size && ewwwio()->mimetype( $tempfile, 'i' ) === $type ) {
						// replace the original with the optimized file
						rename( $tempfile, $file );
						// store the results of the optimization
						$result = "$orig_size vs. $new_size";
					// if the optimization didn't produce a smaller PNG
					} else {
						if ( is_file( $tempfile ) ) {
							// delete the optimized file
							unlink( $tempfile );
						}
						// store the results
						$result   = 'unchanged';
						$new_size = $orig_size;
					}
				}
				$webp_result = $this->webp_create( $file, $new_size, $type, $tools['cwebp'], $orig_size !== $new_size );
				break;
			case 'image/gif':
				// check for previous optimization, so long as the force flag isn't on
				if ( empty( $this->force ) ) {
					$results_msg = ewww_image_optimizer_check_table( $file, $orig_size );
					if ( $results_msg ) {
						return array( $file, $results_msg );
					}
				}
				if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_level' ) ) {
					list( $file, $result, $new_size ) = ewww_image_optimizer_cloud_optimizer( $file, $type );
					$webp_result = $this->webp_create( $file, $new_size, $type, null, $orig_size !== $new_size );
					break;
				}

				$tools['gifsicle'] = ewwwio()->local->get_path( 'gifsicle' );

				// if gifsicle is disabled
				if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_level' ) ) {
					// return an appropriate message
					$result = __( 'GIF optimization is disabled', EWWW_IMAGE_OPTIMIZER_DOMAIN );
				// if utility checking is on, and gifsicle is not installed
				} elseif ( empty( $tools['gifsicle'] ) ) {
					// return an appropriate message
					$result = sprintf(__('%s is missing', EWWW_IMAGE_OPTIMIZER_DOMAIN), 'gifsicle');
				} else {
					// otherwise, turn optimization ON
					$optimize = true;
				}
				// if optimization is turned ON
				if ( $optimize ) {
					$tempfile = $file . '.tmp'; //temporary GIF output
					// run gifsicle on the GIF
					exec( "$nice " . $tools['gifsicle'] . ' -O3 --careful -o ' . ewwwio()->escapeshellarg( $tempfile ) . ' ' . ewwwio()->escapeshellarg( $file ) );
					// retrieve the filesize of the temporary GIF
					$new_size = ewwwio()->filesize( $tempfile );
					// if the new GIF is smaller
					if ( $new_size && $orig_size > $new_size && ewwwio()->mimetype( $tempfile, 'i' ) === $type ) {
						// replace the original with the optimized file
						rename( $tempfile, $file );
						// store the results of the optimization
						$result = "$orig_size vs. $new_size";
					// if the optimization didn't produce a smaller GIF
					} else {
						if ( is_file( $tempfile ) ) {
							// delete the optimized file
							unlink( $tempfile );
						}
						// store the results
						$result   = 'unchanged';
						$new_size = $orig_size;
					}
				}
				break;
			case 'application/pdf':
				if ( empty( $this->force ) ) {
					$results_msg = ewww_image_optimizer_check_table( $file, $orig_size );
					if ( $results_msg ) {
						return array( $file, $results_msg );
					}
				}
				if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' ) > 0 ) {
					list( $file, $result, $new_size ) = ewww_image_optimizer_cloud_optimizer( $file, $type );
				}
				break;
			default:
				// if not a JPG, PNG, GIF, or PDF: tell the user we don't work with strangers
				return array( false, __( 'Unsupported file type', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . ": $type" );
		}
		// if their cloud api license limit has been exceeded
		if ( 'exceeded' === $result ) {
			return array( false, __( 'License exceeded', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
		} elseif ( 'exceeded quota' === $result ) {
			return array( false, __( 'Soft Quota Reached', 'ewww-image-optimizer' ) );
		}
		if ( ! empty( $new_size ) ) {
			// Set correct file permissions
			$stat  = stat( dirname( $file ) );
			$perms = $stat['mode'] & 0000666; //same permissions as parent folder, strip off the executable bits
			@chmod( $file, $perms );

			$results_msg = ewww_image_optimizer_update_table( $file, $new_size, $orig_size );
			if ( ! empty( $webp_result ) ) {
				$results_msg .= "\n" . $webp_result;
			}
			return array( $file, $results_msg );
		}
		if ( ! empty( $webp_result ) && empty( $optimize ) ) {
			$result = $webp_result;
			return array( true, $result );
		}
		// otherwise, send back the filename and the results (some sort of error message)
		return array( false, $result );
	}

	// creates webp images alongside JPG and PNG files
	// needs a filename, the filesize, mimetype, and the path to the cwebp binary
	public function webp_create( $file, $orig_size, $type, $tool, $recreate = false ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$orig_size = $this->filesize( $file );
		$webpfile  = $file . '.webp';
		if ( ! get_option( 'ewww_image_optimizer_webp' ) ) { 
			return '';
		} elseif ( ! is_file( $file ) ) {
			ewwwio_debug_message( 'original file not found' );
			return __( 'Could not find file.', 'ewww-image-optimizer' );
		} elseif ( ! is_writable( $file ) ) {
			ewwwio_debug_message( 'original file not writable' );
			return __( 'File is not writable.', 'ewww-image-optimizer' );
		} elseif ( is_file( $webpfile ) && empty( $this->force ) && ! $recreate ) {
			ewwwio_debug_message( 'webp file exists, not forcing or recreating' );
			return __( 'WebP image already exists.', 'ewww-image-optimizer' );
		} elseif ( 'image/png' === $type && ewww_image_optimizer_is_animated_png( $file ) ) {
			ewwwio_debug_message( 'APNG found, WebP not possible' );
			return __( 'APNG cannot be converted to WebP.', 'ewww-image-optimizer' );
		}
		list( $width, $height ) = @getimagesize( $file );
		if ( ! $width || ! $height || $width > 16383 || $height > 16383 ) {
			return __( 'Image dimensions too large for WebP conversion.', 'ewww-image-optimizer' );
		}
		if ( empty( $tool ) || 'image/gif' === $type ) {
			if ( get_option( 'ewww_image_optimizer_cloud_key' ) ) {
				ewww_image_optimizer_cloud_optimizer( $file, $type, false, $webpfile, 'image/webp' );
			} elseif ( $this->imagick_supports_webp() ) {
				$this->imagick_create_webp( $file, $type, $webpfile );
			} elseif ( $this->gd_supports_webp() ) {
				$this->gd_create_webp( $file, $type, $webpfile );
			}
		} else {
			$nice = '';
			if ( PHP_OS !== 'WINNT' && ! ewwwio()->cloud_mode && ewwwio()->local->exec_check() ) {
				// Check to see if 'nice' exists.
				$nice = ewwwio()->local->find_nix_binary( 'nice' );
			}
			// Check to see if we are supposed to strip metadata.
			$copy_opt = get_option( 'ewww_image_optimizer_metadata_remove' ) ? 'icc' : 'all';
			$quality  = (int) get_option( 'ewww_image_optimizer_webp_quality' );
			if ( $quality < 50 || $quality > 100 ) {
				$quality = 75;
			}
			$sharp_yuv = get_option( 'ewww_image_optimizer_sharpen' ) ? '-sharp_yuv' : '';
			$lossless = '-lossless';
			if ( get_option( 'ewww_image_optimizer_lossy_png2webp' ) ) {
				$lossless = "-q $quality $sharp_yuv";
			}
			switch( $type ) {
				case 'image/jpeg':
					exec( "$nice " . $tool . " -q $quality $sharp_yuv -metadata $copy_opt -quiet " . $this->escapeshellarg( $file ) . " -o " . $this->escapeshellarg( $webpfile ) . ' 2>&1', $cli_output );
					if ( ! is_file( $webpfile ) && $this->imagick_supports_webp() && ewww_image_optimizer_is_cmyk( $file ) ) {
						$this->debug_message( 'cmyk image skipped, trying imagick' );
						$this->imagick_create_webp( $file, $type, $webpfile );
					} elseif ( is_file( $webpfile ) && 'image/webp' !== $this->mimetype( $webpfile, 'i' ) ) {
						$this->debug_message( 'non-webp file produced' );
					}
					break;
				case 'image/png':
					exec( "$nice " . $tool . " $lossless -metadata $copy_opt -quiet " . $this->escapeshellarg( $file ) . " -o " . $this->escapeshellarg( $webpfile ) . ' 2>&1', $cli_output );
					break;
			}
		}
		$webp_size = $this->filesize( $webpfile );
		$this->debug_message( "webp is $webp_size vs. $type is $orig_size" );
		if ( is_file( $webpfile ) && $orig_size < $webp_size ) {
			$this->debug_message( 'webp file was too big, deleting' );
			unlink( $webpfile );
			return __( 'WebP image was larger than original.', 'ewww-image-optimizer' );
		} elseif ( is_file( $webpfile ) && 'image/webp' === $this->mimetype( $webpfile, 'i' ) ) {
			// Set correct file permissions
			$stat = stat( dirname( $webpfile ) );
			$perms = $stat['mode'] & 0000666; //same permissions as parent folder, strip off the executable bits
			@chmod( $webpfile, $perms );
			return 'WebP: ' . ewww_image_optimizer_image_results( $orig_size, $webp_size );
		} elseif ( is_file( $webpfile ) ) {
			$this->debug_message( 'webp file mimetype did not validate, deleting' );
			unlink( $webpfile );
			return __( 'WebP conversion error.', 'ewww-image-optimizer' );
		}
		return __( 'Image could not be converted to WebP.', 'ewww-image-optimizer' );
	}

	/**
	 * Use GD to convert an image to WebP.
	 *
	 * @param string $file The original source image path.
	 * @param string $type The mime-type of the original image.
	 * @param string $webpfile The location to store the new WebP image.
	 */
	public function gd_create_webp( $file, $type, $webpfile ) {
		ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		$quality = (int) get_option( 'ewww_image_optimizer_webp_quality' );
		if ( $quality < 50 || $quality > 100 ) {
			$quality = 75;
		}
		switch ( $type ) {
			case 'image/jpeg':
				$image = imagecreatefromjpeg( $file );
				if ( false === $image ) {
					return;
				}
				break;
			case 'image/png':
				$image = imagecreatefrompng( $file );
				if ( false === $image ) {
					return;
				}
				if ( ! imageistruecolor( $image ) ) {
					$this->debug_message( 'converting to true color' );
					imagepalettetotruecolor( $image );
				}
				if ( ewww_image_optimizer_png_alpha( $file ) ) {
					$this->debug_message( 'saving alpha and disabling alpha blending' );
					imagealphablending( $image, false );
					imagesavealpha( $image, true );
				}
				if ( ! get_option( 'ewww_image_optimizer_lossy_png2webp' ) ) {
					$quality = 100;
				}
				break;
			default:
				return;
		}
		ewwwio_debug_message( "creating $webpfile with quality $quality" );
		$result = imagewebp( $image, $webpfile, $quality );
		// Make sure to cleanup--if $webpfile is borked, that will be handled elsewhere.
		imagedestroy( $image );
	}

	/**
	 * Use IMagick to convert an image to WebP.
	 *
	 * @param string $file The original source image path.
	 * @param string $type The mime-type of the original image.
	 * @param string $webpfile The location to store the new WebP image.
	 */
	public function imagick_create_webp( $file, $type, $webpfile ) {
		ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		$sharp_yuv = ewww_image_optimizer_get_option( 'ewww_image_optimizer_sharpen' );
		$quality = (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_quality' );
		if ( $quality < 50 || $quality > 100 ) {
			$quality = 75;
		}
		$profiles = array();
		switch ( $type ) {
			case 'image/jpeg':
				$image = new Imagick( $file );
				if ( false === $image ) {
					return;
				}
				if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_metadata_remove' ) ) {
					// Getting possible color profiles.
					$profiles = $image->getImageProfiles( 'icc', true );
				}
				$color = $image->getImageColorspace();
				ewwwio_debug_message( "color space is $color" );
				if ( Imagick::COLORSPACE_CMYK === $color ) {
					ewwwio_debug_message( 'found CMYK image' );
					if ( is_file( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'vendor/icc/sRGB2014.icc' ) ) {
						ewwwio_debug_message( 'adding icc profile' );
						$icc_profile = file_get_contents( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'vendor/icc/sRGB2014.icc' );
						$image->profileImage( 'icc', $icc_profile );
					}
					ewwwio_debug_message( 'attempting SRGB transform' );
					$image->transformImageColorspace( Imagick::COLORSPACE_SRGB );
					ewwwio_debug_message( 'removing icc profile' );
					$image->setImageProfile( '*', null );
					$profiles = array();
				}
				$image->setImageFormat( 'WEBP' );
				if ( $sharp_yuv ) {
					ewwwio_debug_message( 'enabling sharp_yuv' );
					$image->setOption( 'webp:use-sharp-yuv', 'true' );
				}
				ewwwio_debug_message( "setting quality to $quality" );
				$image->setImageCompressionQuality( $quality );
				break;
			case 'image/png':
				$image = new Imagick( $file );
				if ( false === $image ) {
					return;
				}
				$image->setImageFormat( 'WEBP' );
				if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_lossy_png2webp' ) ) {
					ewwwio_debug_message( 'doing lossy conversion' );
					if ( $sharp_yuv ) {
						ewwwio_debug_message( 'enabling sharp_yuv' );
						$image->setOption( 'webp:use-sharp-yuv', 'true' );
					}
					ewwwio_debug_message( "setting quality to $quality" );
					$image->setImageCompressionQuality( $quality );
				} else {
					ewwwio_debug_message( 'sticking to lossless' );
					$image->setOption( 'webp:lossless', true );
					$image->setOption( 'webp:alpha-quality', 100 );
				}
				break;
			default:
				return;
		}
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_metadata_remove' ) ) {
			ewwwio_debug_message( 'removing meta' );
			$image->stripImage();
			if ( ! empty( $profiles ) ) {
				ewwwio_debug_message( 'adding color profile to WebP' );
				$image->profileImage( 'icc', $profiles['icc'] );
			}
		}
		ewwwio_debug_message( 'getting blob' );
		$image_blob = $image->getImageBlob();
		ewwwio_debug_message( 'writing file' );
		file_put_contents( $webpfile, $image_blob );
	}
}
