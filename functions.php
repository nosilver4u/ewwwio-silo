<?php
// Remaining functions that haven't been migrated to OOP, and replacements for WP functionality.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// function to check if set_time_limit() is disabled
function ewww_image_optimizer_stl_check() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( defined( 'EWWW_IMAGE_OPTIMIZER_DISABLE_STL' ) && EWWW_IMAGE_OPTIMIZER_DISABLE_STL ) {
        ewwwio_debug_message( 'stl disabled by user' );
        return false;
    }
	$disabled = ini_get('disable_functions');
	ewwwio_debug_message( "disable_functions = $disabled" );
	if ( preg_match( '/set_time_limit/', $disabled ) ) {
		return false;
	} elseif ( function_exists( 'set_time_limit' ) ) {
		return true;
	} else {
		ewwwio_debug_message( 'set_time_limit does not exist' );
        return false;
    }
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
	}
	if ( empty( $api_key ) ) {
		ewwwio_debug_message( 'no api key' );
		if ( get_option( 'ewww_image_optimizer_jpg_level' ) > 10 ) {
			update_option( 'ewww_image_optimizer_jpg_level', 10 );
		}
		if ( get_option( 'ewww_image_optimizer_png_level' ) > 10 && (int) get_option( 'ewww_image_optimizer_png_level' ) !== 40 ) {
			update_option( 'ewww_image_optimizer_png_level', 10 );
		}
		if ( get_option( 'ewww_image_optimizer_pdf_level' ) > 0 ) {
			update_option( 'ewww_image_optimizer_pdf_level', 0 );
		}
		return false;
	}
	$verified = false;
	$result   = ewww_image_optimizer_cloud_post_key( $api_key );
	if ( $result->success && ! empty( $result->body ) && preg_match( '/(great|exceeded)/', $result->body ) ) {
		$verified = $result->body;
		ewwwio_debug_message( "verification success" );
	} else {
		ewwwio_debug_message( "verification failed" );
		ewwwio_debug_message( print_r( $result, true ) );
	}
	if ( $verified ) {
		set_transient( 'ewww_image_optimizer_cloud_status', $verified, 3600 ); 
		ewwwio_debug_message( "verification body contents: " . $result->body );
	}
	return $verified;
}

function ewww_image_optimizer_cloud_post_key( $key ) {
	$useragent = ewww_image_optimizer_cloud_useragent();
	$url       = 'https://optimize.exactlywww.com/verify/';
	try {
		$result = WpOrg\Requests\Requests::post( $url, array(), array( 'api_key' => $key ), array( 'timeout' => 5, 'useragent' => $useragent ) );
	} catch ( WpOrg\Requests\Exception $e ) {
		$error_message = $e->getMessage();
		ewwwio()->warning( "verification request failed: $error_message" );
	}
	return $result;
}

// checks the provided api key for quota information
function ewww_image_optimizer_cloud_quota() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$api_key = get_option( 'ewww_image_optimizer_cloud_key' );
	$url     = 'https://optimize.exactlywww.com/quota/v2/';
	$useragent = ewww_image_optimizer_cloud_useragent();
	try {
		$result = WpOrg\Requests\Requests::post(
			$url,
			array(),
			array(
				'api_key' => $api_key,
			),
			array(
				'timeout'   => 5,
				'useragent' => $useragent
			)
		);
	} catch ( WpOrg\Requests\Exception $e ) {
		$error_message = $e->getMessage();
		ewwwio()->warning( "quota request failed: $error_message" );
		return '';
	}
	if ( $result->success && ! empty( $result->body ) ) {
		ewwwio_debug_message( "quota data retrieved: " . $result->body );
		$quota = json_decode( $result->body, true );
		if ( ! is_array( $quota ) ) {
			return '';
		}
		if ( ! empty( $quota['status'] ) && 'expired' === $quota['status'] ) {
			return '';
		}
		if ( ! empty( $quota['unlimited'] ) && $quota['consumed'] >= 0 && isset( $quota['soft_cap'] ) ) {
			$consumed  = (int) $quota['consumed'];
			$soft_cap  = (int) $quota['soft_cap'];
			return sprintf(
				/* translators: 1: Number of images optimized, 2: image quota */
				__( 'optimized %1$d (of %2$s) images.', 'ewww-image-optimizer' ),
				$consumed,
				$soft_cap
			);
		} elseif ( ! $quota['licensed'] && $quota['consumed'] > 0 ) {
			return sprintf(
				/* translators: 1: Number of images 2: Number of days until renewal */
				_n( 'optimized %1$d images, renewal is in %2$d day.', 'optimized %1$d images, renewal is in %2$d days.', $quota['days'], 'ewww-image-optimizer' ),
				$quota['consumed'],
				$quota['days']
			);
		} elseif ( ! $quota['licensed'] && $quota['consumed'] < 0 ) {
			return sprintf(
				/* translators: 1: Number of image credits for the compression API */
				_n( '%1$d image credit remaining.', '%1$d image credits remaining.', abs( $quota['consumed'] ), 'ewww-image-optimizer' ),
				abs( $quota['consumed'] )
			);
		} elseif ( $quota['licensed'] > 0 && $quota['consumed'] <= 0 ) {
			$real_quota = (int) $quota['licensed'] - (int) $quota['consumed'];
			return sprintf(
				/* translators: 1: Number of image credits for the compression API */
				_n( '%1$d image credit remaining.', '%1$d image credits remaining.', $real_quota, 'ewww-image-optimizer' ),
				$real_quota
			);
		} elseif ( ! $quota['licensed'] && ! $quota['consumed'] && ! $quota['days'] && ! $quota['metered'] ) {
			return __( 'no credits remaining, please purchase more.', 'ewww-image-optimizer' );
		} else {
			return sprintf(
				/* translators: 1: Number of image credits used 2: Number of image credits available 3: days until subscription renewal */
				_n( 'used %1$d of %2$d, usage will reset in %3$d day.', 'used %1$d of %2$d, usage will reset in %3$d days.', $quota['days'], 'ewww-image-optimizer' ),
				$quota['consumed'],
				$quota['licensed'],
				$quota['days']
			);
		}
	}
}

/* submits an image to the cloud optimizer and saves the optimized image to disk
 *
 * Returns an array of the $file, $results, $converted to tell us if an image changes formats, and the $original file if it did.
 *
 * @param string  $file		Full absolute path to the image file
 * @param string  $type		mimetype of $file
 * @param boolean $convert		true says we want to attempt conversion of $file
 * @param string  $newfile		filename of new converted image
 * @param string  $newtype Mimetype of $newfile
 * @param boolean $fullsize Is this the full-size original?
 * @param string  $jpg_fill Optional. Fill color for PNG to JPG conversion in hex format.
 * @param int     $jpg_quality Optional. JPG quality level. Default null. Accepts 1-100.
 * @returns array
*/
function ewww_image_optimizer_cloud_optimizer( $file, $type, $convert = 0, $newfile = null, $newtype = null, $fullsize = false, $jpg_fill = '', $jpg_quality = 82 ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );

	$ewww_status = get_transient( 'ewww_image_optimizer_cloud_status' );
	$started = microtime( true );
	if ( preg_match( '/exceeded/', $ewww_status ) ) {
		if ( ! ewww_image_optimizer_cloud_verify() ) { 
			return array( $file, 'key verification failed', 0 );
		}
	}

	$api_quota = ewww_image_optimizer_cloud_quota();
	if ( $api_quota ) {
		ewwwio()->line( __( 'Image credits available:', 'ewww-image-optimizer' ) . ' ' . $api_quota );
	}

	// calculate how much time has elapsed since we started
	$elapsed = microtime( true ) - $started;
	// output how much time has elapsed since we started
	ewwwio_debug_message( sprintf( 'Cloud verify took %.3f seconds', $elapsed ) );
	$ewww_status = get_transient( 'ewww_image_optimizer_cloud_status' );
	if ( ! empty ( $ewww_status ) && preg_match( '/exceeded/', $ewww_status ) ) {
		ewwwio_debug_message( 'license exceeded, image not processed' );
		return array( $file, 'exceeded', 0 );
	}
	$metadata = 1;
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_metadata_remove' ) ) {
    	// don't copy metadata
        $metadata = 0;
    }
	$lossy_fast = 0;
	$lossy = 0;
	if ( $type === 'image/png' && ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) >= 40 ) {
		$lossy = 1;
		if ( 40 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) ) {
			$lossy_fast = 1;
		}
	} elseif ( $type === 'image/jpeg' && ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) >= 30 ) {
		$lossy = 1;
		if ( 30 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) ) {
			$lossy_fast = 1;
		}
	} elseif ( $type === 'application/pdf' && 20 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' ) ) {
		$lossy = 1;
	} else {
		$lossy = 0;
	}
	$sharp_yuv = get_option( 'ewww_image_optimizer_sharpen' ) ? 1 : 0;
	if ( 'image/webp' === $newtype ) {
		$webp        = 1;
		$jpg_quality = get_option( 'ewww_image_optimizer_webp_quality' );
		if ( 'image/png' === $type && ! get_option( 'ewww_image_optimizer_lossy_png2webp' ) ) {
			$lossy = 0;
		}
	} else {
		$webp = 0;
	}
	if ( $jpg_quality < 50 ) {
		$jpg_quality = 75;
	}
	$png_compress = 0;
	ewwwio_debug_message( "file: $file " );
	ewwwio_debug_message( "type: $type" );
	ewwwio_debug_message( "convert: $convert" );
	ewwwio_debug_message( "newfile: $newfile" );
	ewwwio_debug_message( "newtype: $newtype" );
	ewwwio_debug_message( "webp: $webp" );
	ewwwio_debug_message( "sharp_yuv: $sharp_yuv" );
	ewwwio_debug_message( "jpg_fill: $jpg_fill" );
	ewwwio_debug_message( "jpg quality: $jpg_quality" );
	$api_key = get_option( 'ewww_image_optimizer_cloud_key' );
	$url = "https://optimize.exactlywww.com/v2/";
	$boundary = generate_password( 24 );

	$useragent = ewww_image_optimizer_cloud_useragent();
	$headers = array(
        	'content-type' => 'multipart/form-data; boundary=' . $boundary,
	);
	$post_fields = array(
		'filename'   => $file,
		'convert'    => $convert, 
		'metadata'   => $metadata, 
		'api_key'    => $api_key,
		'jpg_fill'   => $jpg_fill,
		'quality'    => $jpg_quality,
		'compress'   => $png_compress,
		'lossy'      => $lossy,
		'lossy_fast' => $lossy_fast,
		'webp'       => $webp,
		'sharp_yuv'  => $sharp_yuv,
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
	$payload .= 'Content-Disposition: form-data; name="file"; filename="' . basename( $file ) . '"' . "\r\n";
	$payload .= 'Content-Type: ' . $type . "\r\n";
	$payload .= "\r\n";
	$payload .= file_get_contents($file);
	$payload .= "\r\n";
	$payload .= '--' . $boundary;
	$payload .= 'Content-Disposition: form-data; name="submitHandler"' . "\r\n";
	$payload .= "\r\n";
	$payload .= "Upload\r\n";
	$payload .= '--' . $boundary . '--';

	try {
		$response = WpOrg\Requests\Requests::post(
			$url,
			$headers,
			$payload,
			array(
				'timeout' => 300,
				'useragent' => $useragent,
			)
		);
	} catch ( WpOrg\Requests\Exception $e ) {
		$error_message = $e->getMessage();
		ewwwio()->warning( "API optimization failed: $error_message" );
		return array( $file, 'cloud optimize failed', 0 );
	}

	if ( ! $response->success ) {
		ewwwio()->warning( 'Unknown API error, contact support' );
		return array( $file, 'cloud optimize failed', 0 );
	} elseif ( empty( $response->body ) ) {
		ewwwio_debug_message( 'cloud results: no savings' );
		return array( $file, '', filesize( $file ) );
	}

	// If we got this far, we've got an API response to work with.
	$tempfile = $file . ".tmp";
	file_put_contents( $tempfile, $response->body );
	$orig_size = filesize( $file );
	$newsize   = $orig_size;
	$converted = false;
	$msg       = '';
	if ( 100 > strlen( $response->body ) && strpos( $response->body, 'invalid' ) ) {
		ewwwio_debug_message( 'License invalid' );
		$msg = 'Invalid API Key';
	} elseif ( 100 > strlen( $response->body ) && strpos( $response->body, 'exceeded quota' ) ) {
		ewwwio_debug_message( 'flex quota Exceeded' );
		set_transient( 'ewww_image_optimizer_cloud_status', 'exceeded', 3600 );
		$msg = 'Quota Exceeded';
	} elseif ( 100 > strlen( $response->body ) && strpos( $response->body, 'exceeded' ) ) {
		ewwwio_debug_message( 'quota exceeded' );
		set_transient( 'ewww_image_optimizer_cloud_status', 'exceeded', 3600 );
		$msg = 'Quota Exceeded';
	} elseif ( ewww_image_optimizer_mimetype( $tempfile, 'i' ) === $type ) {
		$newsize = filesize( $tempfile );
		ewwwio_debug_message( "cloud results: $newsize (new) vs. $orig_size (original)" );
		rename( $tempfile, $file );
	} elseif ( ewww_image_optimizer_mimetype( $tempfile, 'i' ) === 'image/webp' ) {
		$newsize = filesize( $tempfile );
		ewwwio_debug_message( "cloud results: $newsize (new) vs. $orig_size (original)" );
		rename( $tempfile, $newfile );
	} elseif ( ! is_null( $newtype) && ! is_null( $newfile ) && ewww_image_optimizer_mimetype( $tempfile, 'i' ) === $newtype ) {
		ewwwio_debug_message( "renaming file from $tempfile to $newfile" );
		if ( rename( $tempfile, $newfile ) ) {
			$converted = true;
			$newsize   = filesize( $tempfile );
			$file      = $newfile;
			ewwwio_debug_message( "cloud results: $newsize (new) vs. $orig_size (original)" );
		}
	}
	clearstatcache();
	if ( is_file( $tempfile ) ) {
		unlink( $tempfile );
	}
	return array( $file, $msg, $newsize );
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
		return $already_optimized;
	}
}

// receives a path, optimized size, and an original size to insert into ewwwwio_images table
function ewww_image_optimizer_update_table( $attachment, $opt_size, $orig_size ) {
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
	$results_msg = ewww_image_optimizer_image_results( $orig_size, $opt_size, $prev_string );

	if ( empty( $already_optimized ) || ! is_array( $already_optimized ) ) {
		ewwwio_debug_message( "creating new record, path: $attachment, size: $opt_size" );
		// store info on the current image for future reference
		$wpdb->insert( $wpdb->ewwwio_images, array(
			'path'       => $attachment,
			'image_size' => $opt_size,
			'orig_size'  => $orig_size,
			'results'    => $results_msg,
			'updated'    => date( 'Y-m-d H:i:s' ),
			'updates'    => 1,
		) );
	} else {
		ewwwio_debug_message( "updating existing record ({$already_optimized['id']}), path: $attachment, size: $opt_size" );
		// store info on the current image for future reference
		$wpdb->update( $wpdb->ewwwio_images,
			array(
				'image_size' => $opt_size,
				'results'    => $results_msg,
				'updates'    => $already_optimized['updates'] + 1,
			),
			array(
				'id' => $already_optimized['id'],
			)
		);
	}
	$wpdb->flush();
	return $results_msg;
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

/**
 * Creates a human-readable message based on the original and optimized sizes.
 *
 * @param int    $orig_size The original size of the image.
 * @param int    $opt_size The new size of the image.
 * @param string $prev_string Optional. A message to append for previously optimized images.
 * @return string A message with the percentage and size savings.
 */
function ewww_image_optimizer_image_results( $orig_size, $opt_size, $prev_string = '' ) {
	if ( $opt_size >= $orig_size ) {
		ewwwio_debug_message( 'original and new file are same size (or something weird made the new one larger), no savings' );
		$results_msg = __( 'No savings', 'ewww-image-optimizer' );
	} else {
		// Calculate how much space was saved.
		$savings     = intval( $orig_size ) - intval( $opt_size );
		$savings_str = size_format( $savings );
		// Determine the percentage savings.
		$percent = number_format_i18n( 100 - ( 100 * ( $opt_size / $orig_size ) ), 1 ) . '%';
		// Use the percentage and the savings size to output a nice message to the user.
		$results_msg = sprintf(
			/* translators: 1: Size of savings in bytes, kb, mb 2: Percentage savings */
			__( 'Reduced by %1$s (%2$s)', 'ewww-image-optimizer' ),
			$percent,
			$savings_str
		) . $prev_string;
		ewwwio_debug_message( "original and new file are different size: $results_msg" );
	}
	return $results_msg;
}

/**
 * Check the submitted PNG to see if it has transparency
 */
function ewww_image_optimizer_png_alpha( $filename ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ! is_file( $filename ) ) {
		return false;
	}
	$dimensions = @getimagesize( $filename );
	if ( empty( $dimensions[0] ) || empty( $dimensions[1] ) ) {
		return false;
	}
	$width  = $dimensions[0];
	$height = $dimensions[1];
	ewwwio_debug_message( "image dimensions: $width x $height" );
	if ( ! ewwwio()->gd_support() || ! ewwwio()->check_memory_available( ( $width * $height ) * 4.8 ) ) { // 4.8 = 24-bit or 3 bytes per pixel multiplied by a factor of 1.6 for extra wiggle room.
		$file_contents = file_get_contents( $filename );
		// Determine what color type is stored in the file.
		$color_type = ord( substr( $file_contents, 25, 1 ) );
		unset( $file_contents );
		ewwwio_debug_message( "color type: $color_type" );
		if ( 4 === $color_type || 6 === $color_type ) {
			ewwwio_debug_message( 'transparency found' );
			return true;
		}
	} elseif ( ewwwio()->gd_support() ) {
		$image = imagecreatefrompng( $filename );
		if ( ! $image ) {
			ewwwio_debug_message( 'could not load image' );
			return false;
		}
		if ( imagecolortransparent( $image ) >= 0 ) {
			ewwwio_debug_message( 'transparency found' );
			return true;
		}
		ewwwio_debug_message( 'preparing to scan image' );
		for ( $y = 0; $y < $height; $y++ ) {
			for ( $x = 0; $x < $width; $x++ ) {
				$color = imagecolorat( $image, $x, $y );
				$rgb   = imagecolorsforindex( $image, $color );
				if ( $rgb['alpha'] > 0 ) {
					ewwwio_debug_message( 'transparency found' );
					return true;
				}
			}
		}
	}
	ewwwio_debug_message( 'no transparency' );
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
	return $count > 1;
}

/**
 * Check the submitted PNG to see if it is animated. Thanks @GregOriol!
 *
 * @param string $filename Name of the PNG to test for animation.
 * @return bool True if animation found.
 */
function ewww_image_optimizer_is_animated_png( $filename ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$apng = false;
	if ( ! is_file( $filename ) ) {
		return false;
	}
	// If we can't open the file in read-only buffered mode.
	$fh = fopen( $filename, 'rb' );
	if ( ! $fh ) {
		return false;
	}
	$previousdata = '';
	// We read through the file til we reach the end of the file, or we've found an acTL or IDAT chunk.
	while ( ! feof( $fh ) ) {
		$data = fread( $fh, 1024 ); // Read 1kb at a time.
		if ( false !== strpos( $data, 'acTL' ) ) {
			ewwwio_debug_message( 'found acTL chunk (animated) in PNG' );
			$apng = true;
			break;
		} elseif ( false !== strpos( $previousdata . $data, 'acTL' ) ) {
			ewwwio_debug_message( 'found acTL chunk (animated) in PNG' );
			$apng = true;
			break;
		} elseif ( false !== strpos( $data, 'IDAT' ) ) {
			ewwwio_debug_message( 'found IDAT, but no acTL (animated) chunk in PNG' );
			break;
		} elseif ( false !== strpos( $previousdata . $data, 'IDAT' ) ) {
			ewwwio_debug_message( 'found IDAT, but no acTL (animated) chunk in PNG' );
			break;
		}
		$previousdata = $data;
	}
	fclose( $fh );
	return $apng;
}

/**
 * Check a JPG to see if it uses the CMYK color space.
 *
 * @param string $filename Name of the JPG to test.
 * @return bool True if CMYK, false otherwise.
 */
function ewww_image_optimizer_is_cmyk( $filename ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ewwwio()->imagick_support() ) {
		$image = new Imagick( $filename );
		$color = $image->getImageColorspace();
		ewwwio_debug_message( "color space is $color" );
		$image->destroy();
		if ( Imagick::COLORSPACE_CMYK === $color ) {
			return true;
		}
	} elseif ( ewwwio()->gd_support() ) {
		$info = getimagesize( $filename );
		if ( ! empty( $info['channels'] ) ) {
			ewwwio_debug_message( "channel count is {$info['channels']}" );
			if ( 4 === (int) $info['channels'] ) {
				return true;
			}
		}
	}
	return false;
}

function ewww_image_optimizer_savings() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	ewwwio_debug_message( 'querying savings for single site' );
	$total_savings = 0;
	$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'ewwwio_images WHERE image_size > orig_size' );
	$total_query = "SELECT SUM(orig_size-image_size) FROM $wpdb->ewwwio_images";
	ewwwio_debug_message( "query to be performed: $total_query" );
	$total_savings = $wpdb->get_var($total_query);
	ewwwio_debug_message( "savings found: $total_savings" );
	return $total_savings;
}

/*** Temporary wrapper functions. ***/

// test mimetype based on file extension instead of file contents
// only use for places where speed outweighs accuracy
function ewww_image_optimizer_quick_mimetype( $path ) {
	return ewwwio()->quick_mimetype( $path, $category );
}

/**
 * Check the mimetype of the given file with magic mime strings/patterns.
 *
 * @param string $path The absolute path to the file.
 * @param string $category The type of file we are checking. Accepts 'i' for
 *                     images/pdfs or 'b' for binary.
 * @return bool|string A valid mime-type or false.
 */
function ewww_image_optimizer_mimetype( $path, $category ) {
	return ewwwio()->mimetype( $path, $category );
}

// make sure an array/object can be parsed by a foreach()
function ewww_image_optimizer_iterable( $var ) {
	return ewwwio()->is_iterable( $var );
}

// retrieve an option: use config.php setting if set, otherwise use db setting
function ewww_image_optimizer_get_option( $option_name ) {
	$option_value = get_option( $option_name );
	return $option_value;
}

function ewwwio_debug_message( $message ) {
	ewwwio()->debug_message( $message );
}

/*** EWWW replacements (for WP functionality) ***/

// Based on wp_mkdir_p().
function ewwwio_mkdir( $target ) {
        clearstatcache();
        // From php.net/mkdir user contributed notes.
        $target = str_replace( '//', '/', $target );
        /*
         * Safe mode fails with a trailing slash under certain PHP versions.
         * Use rtrim() instead of untrailingslashit to avoid formatting.php dependency.
         */
        $target = rtrim( $target, '/' );
        if ( empty( $target ) ) {
                return true;
        }

        if ( file_exists( $target ) ) {
                return is_dir( $target );
        }

        // Do not allow path traversals.
        if ( false !== strpos( $target, '../' ) || false !== strpos( $target, '..' . DIRECTORY_SEPARATOR ) ) {
                return false;
        }

        // We need to find the permissions of the parent folder that exists and inherit that.
        $target_parent = dirname( $target );
        while ( '.' !== $target_parent && ! is_dir( $target_parent ) && dirname( $target_parent ) !== $target_parent ) {
                $target_parent = dirname( $target_parent );
        }

        // Get the permission bits.
        $stat = stat( $target_parent );
        if ( $stat ) {
                $dir_perms = $stat['mode'] & 0007777;
        } else {
                $dir_perms = 0777;
        }

        if ( @mkdir( $target, $dir_perms, true ) ) { // Errors silenced for race conditions.

                /*
                 * If a umask is set that modifies $dir_perms, we'll have to re-set
                 * the $dir_perms correctly with chmod()
                 */
                if ( ( $dir_perms & ~umask() ) != $dir_perms ) {
                        $folder_parts = explode( '/', substr( $target, strlen( $target_parent ) + 1 ) );
                        for ( $i = 1, $c = count( $folder_parts ); $i <= $c; $i++ ) {
                                chmod( $target_parent . '/' . implode( '/', array_slice( $folder_parts, 0, $i ) ), $dir_perms );
                        }
                }
                clearstatcache();
                return true;
        }
        return false;
}

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
    $formatted = number_format( $number, absint( $decimals ) );
    return $formatted;
}

function absint( $maybeint ) {
	return abs( intval( $maybeint ) );
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
	if ( 1 === (int) $count ) {
		return $string1;
	} else {
		return $string2;
	}
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
	} else {
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1", $option ) );
		// Has to be get_row instead of get_var because of funkiness with 0, false, null values
		if ( is_array( $row ) ) {
			$value = $row['option_value'];
			$alloptions[$option] = $value;
		} else { // option does not exist, so we must cache its non-existence
			return $default;
		}
	}

	return maybe_unserialize( $value );
}

function load_overrides() {
	global $alloptions;
	global $ewwwio_settings; // allow overrides from config.php

	if ( isset( $alloptions ) ) {
		if ( isset( $ewwwio_settings ) && is_iterable( $ewwwio_settings ) ) {
			foreach ( $ewwwio_settings as $name => $value ) {
				$alloptions[ $name ] = $value;
			}
		}
	}
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
