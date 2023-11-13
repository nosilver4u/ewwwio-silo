<?php
/**
 * Implements basic and common utility functions for all sub-classes.
 *
 * @link https://ewww.io
 * @package EIO
 */

namespace EWWW;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Common utility functions for child classes.
 */
class Base {

	/**
	 * Tool directory (path) for the plugin to use.
	 *
	 * @access protected
	 * @var string $content_dir
	 */
	protected $tool_dir = '';

	/**
	 * GD support information.
	 *
	 * @access protected
	 * @var string $gd_info
	 */
	protected $gd_info = '';

	/**
	 * GD support status.
	 *
	 * @access protected
	 * @var string|bool $gd_support
	 */
	protected $gd_support = false;

	/**
	 * GD WebP support status.
	 *
	 * @access protected
	 * @var string|bool $gd_supports_webp
	 */
	protected $gd_supports_webp = false;

	/**
	 * Gmagick support information.
	 *
	 * @access protected
	 * @var string $gmagick_info
	 */
	protected $gmagick_info = '';

	/**
	 * Gmagick support status.
	 *
	 * @access protected
	 * @var string|bool $gmagick_support
	 */
	protected $gmagick_support = false;

	/**
	 * Imagick support information.
	 *
	 * @access protected
	 * @var string $imagick_info
	 */
	protected $imagick_info = '';

	/**
	 * Imagick support status.
	 *
	 * @access protected
	 * @var string|bool $imagick_support
	 */
	protected $imagick_support = false;

	/**
	 * Imagick WebP support status.
	 *
	 * @access protected
	 * @var string|bool $imagick_supports_webp
	 */
	protected $imagick_supports_webp = false;

	/**
	 * Plugin version for the plugin.
	 *
	 * @access protected
	 * @var float $version
	 */
	protected $version = 10;

	/**
	 * Prefix to be used by plugin in option and hook names.
	 *
	 * @access protected
	 * @var string $prefix
	 */
	protected $prefix = 'ewww_image_optimizer_';

	/**
	 * EWWW\Colors object.
	 *
	 * @var object|EWWW\Colors $colors
	 */
	protected $colors;

	/**
	 * Tells the optimizer to optimize regardless of previous optimizations.
	 *
	 * @var bool $force
	 */
	public $force = false;

	/**
	 * The number of seconds to sleep in between optimizations.
	 *
	 * @var int $delay
	 */
	public $delay = 0;

	/**
	 * Displays usage information.
	 *
	 * @var bool $help
	 */
	public $help = false;

	/**
	 * By default we prompt the user to confirm, but this allows automation where they can run without user interaction.
	 *
	 * @var bool $noprompt
	 */
	public $noprompt = false;

	/**
	 * Whether the optimizer should rescan instead of continuing where we left off.
	 *
	 * @var bool $reset
	 */
	public $reset = false;

	/**
	 * Enables debugging output.
	 *
	 * @var bool $force
	 */
	public $debug = false;

	/**
	 * A file or folder (of files) to be optimized.
	 *
	 * @var string $file
	 */
	public $file = '';

	/**
	 * Set class properties for children.
	 */
	public function __construct() {
		$this->version  = EWWW_IMAGE_OPTIMIZER_VERSION;
		$this->tool_dir = EWWW_IMAGE_OPTIMIZER_TOOL_PATH;
		$this->colors   = new Colors();

		// Load up the CLI args.
		$args = $this->parse_args();
		if ( isset( $args['debug'] ) ) {
			$this->debug = true;
		}
		if ( ! empty( $args['delay'] ) ) {
			$this->delay = (int) $args['delay'];
		}
		if ( isset( $args['force'] ) ) {
			$this->force = true;
		}
		if ( isset( $args['help'] ) || isset( $args['h'] ) ) {
			$this->help = true;
		}
		if ( isset( $args['noprompt'] ) ) {
			$this->noprompt = true;
		}
		if ( isset( $args['reset'] ) ) {
			$this->reset = true;
		}
		if ( ! empty( $args['f'] ) ) {
			$this->file = $args['f'];
		}
	}

	public function parse_args() {
		$user_args = getopt( 'f:h', array(
			'delay:',
			'force',
			'help',
			'noprompt',
			'reset',
			'single',
			'debug',
		) );
		return $user_args;
	}

	/**
	 * Adds information to the in-memory debug log.
	 *
	 * @param string $message Debug information to add to the log.
	 */
	public function debug_message( $message ) {
		if ( ! \is_string( $message ) && ! \is_int( $message ) && ! \is_float( $message ) ) {
			return;
		}
		if ( $this->debug ) {
			$this->debug( $message );
		}
	}

	public function success( $message ) {
		if ( ! defined( 'EWWW_CLI' ) || ! EWWW_CLI ) {
			return;
		}
		echo $this->colors->getColoredString( $message, 'green' ) . "\n";
	}

	public function debug( $message ) {
		if ( ! defined( 'EWWW_CLI' ) || ! EWWW_CLI ) {
			return;
		}
		echo $this->colors->getColoredString( $message, 'cyan' ) . "\n";
	}

	public function warning( $message ) {
		if ( ! defined( 'EWWW_CLI' ) || ! EWWW_CLI ) {
			return;
		}
		echo $this->colors->getColoredString( $message, 'red' ) . "\n";
	}

	public function error( $message ) {
		if ( ! defined( 'EWWW_CLI' ) || ! EWWW_CLI ) {
			return;
		}
		echo $this->colors->getColoredString( $message, 'red' ) . "\n";
		exit;
	}

	public function line( $message ) {
		if ( ! defined( 'EWWW_CLI' ) || ! EWWW_CLI ) {
			return;
		}
		echo $message . "\n";
	}

	public function confirm( $question ) {
		if ( ! defined( 'EWWW_CLI' ) || ! EWWW_CLI ) {
			return;
		}
		echo $question . " [y/n] ";
		$answer = strtolower( trim( fgets( STDIN ) ) );
		if ( 'y' !== $answer )
			exit;
	}

	/**
	 * Escape any spaces in the filename.
	 *
	 * @param string $path The path to a binary file.
	 * @return string The path with spaces escaped.
	 */
	public function escapeshellcmd( $path ) {
		return ( \preg_replace( '/ /', '\ ', $path ) );
	}

	/**
	 * Replacement for escapeshellarg() that won't kill non-ASCII characters.
	 *
	 * @param string $arg A value to sanitize/escape for commmand-line usage.
	 * @return string The value after being escaped.
	 */
	public function escapeshellarg( $arg ) {
		if ( PHP_OS === 'WINNT' ) {
			$safe_arg = \str_replace( '%', ' ', $arg );
			$safe_arg = \str_replace( '!', ' ', $safe_arg );
			$safe_arg = \str_replace( '"', ' ', $safe_arg );
			return '"' . $safe_arg . '"';
		}
		$safe_arg = "'" . \str_replace( "'", "'\\''", $arg ) . "'";
		return $safe_arg;
	}

	/**
	 * Checks if a function is disabled or does not exist.
	 *
	 * @param string $function_name The name of a function to test.
	 * @param bool   $debug Whether to output debugging.
	 * @return bool True if the function is available, False if not.
	 */
	public function function_exists( $function_name, $debug = false ) {
		if ( \function_exists( '\ini_get' ) ) {
			$disabled = @\ini_get( 'disable_functions' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( $debug ) {
				$this->debug_message( "disable_functions: $disabled" );
			}
		}
		if ( \extension_loaded( 'suhosin' ) && \function_exists( '\ini_get' ) ) {
			$suhosin_disabled = @\ini_get( 'suhosin.executor.func.blacklist' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( $debug ) {
				$this->debug_message( "suhosin_blacklist: $suhosin_disabled" );
			}
			if ( ! empty( $suhosin_disabled ) ) {
				$suhosin_disabled = \explode( ',', $suhosin_disabled );
				$suhosin_disabled = \array_map( 'trim', $suhosin_disabled );
				$suhosin_disabled = \array_map( 'strtolower', $suhosin_disabled );
				if ( \function_exists( $function_name ) && ! \in_array( \trim( $function_name, '\\' ), $suhosin_disabled, true ) ) {
					return true;
				}
				return false;
			}
		}
		return \function_exists( $function_name );
	}

	/**
	 * Check for GD support of both PNG and JPG.
	 *
	 * @return string|bool The version of GD if full support is detected, false otherwise.
	 */
	public function gd_support() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( ! $this->gd_info && $this->function_exists( '\gd_info' ) ) {
			$this->gd_info = \gd_info();
			$this->debug_message( 'GD found, supports:' );
			if ( $this->is_iterable( $this->gd_info ) ) {
				foreach ( $this->gd_info as $supports => $supported ) {
					$this->debug_message( "$supports: $supported" );
				}
				if ( ( ! empty( $this->gd_info['JPEG Support'] ) || ! empty( $this->gd_info['JPG Support'] ) ) && ! empty( $this->gd_info['PNG Support'] ) ) {
					$this->gd_support = ! empty( $this->gd_info['GD Version'] ) ? $this->gd_info['GD Version'] : '1';
				}
			}
		}
		return $this->gd_support;
	}

	/**
	 * Check for GMagick support of both PNG and JPG.
	 *
	 * @return bool True if full Gmagick support is detected.
	 */
	public function gmagick_support() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( ! $this->gmagick_info && \extension_loaded( 'gmagick' ) && \class_exists( '\Gmagick' ) ) {
			$gmagick            = new \Gmagick();
			$this->gmagick_info = $gmagick->queryFormats();
			$this->debug_message( implode( ',', $this->gmagick_info ) );
			if ( \in_array( 'PNG', $this->gmagick_info, true ) && \in_array( 'JPG', $this->gmagick_info, true ) ) {
				$this->gmagick_support = true;
			} else {
				$this->debug_message( 'gmagick found, but PNG or JPG not supported' );
			}
		}
		return $this->gmagick_support;
	}

	/**
	 * Check for IMagick support of both PNG and JPG.
	 *
	 * @return bool True if full Imagick support is detected.
	 */
	public function imagick_support() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( ! $this->imagick_info && \extension_loaded( 'imagick' ) && \class_exists( '\Imagick' ) ) {
			$imagick            = new \Imagick();
			$this->imagick_info = $imagick->queryFormats();
			$this->debug_message( \implode( ',', $this->imagick_info ) );
			if ( \in_array( 'PNG', $this->imagick_info, true ) && \in_array( 'JPG', $this->imagick_info, true ) ) {
				$this->imagick_support = true;
			} else {
				$this->debug_message( 'imagick found, but PNG or JPG not supported' );
			}
		}
		return $this->imagick_support;
	}

	/**
	 * Check for GD support of WebP format.
	 *
	 * @return bool True if proper WebP support is detected.
	 */
	public function gd_supports_webp() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( ! $this->gd_supports_webp ) {
			$gd_version = $this->gd_support();
			if ( $gd_version ) {
				if (
					\function_exists( '\imagewebp' ) &&
					\function_exists( '\imagepalettetotruecolor' ) &&
					\function_exists( '\imageistruecolor' ) &&
					\function_exists( '\imagealphablending' ) &&
					\function_exists( '\imagesavealpha' )
				) {
					if ( \version_compare( $gd_version, '2.2.5', '>=' ) ) {
						$this->debug_message( 'yes it does' );
						$this->gd_supports_webp = true;
					}
				}
			}

			if ( ! $this->gd_supports_webp ) {
				if ( ! \function_exists( '\imagewebp' ) ) {
					$this->debug_message( 'imagewebp() missing' );
				} elseif ( ! \function_exists( '\imagepalettetotruecolor' ) ) {
					$this->debug_message( 'imagepalettetotruecolor() missing' );
				} elseif ( \function_exists( '\imageistruecolor' ) ) {
					$this->debug_message( 'imageistruecolor() missing' );
				} elseif ( \function_exists( '\imagealphablending' ) ) {
					$this->debug_message( 'imagealphablending() missing' );
				} elseif ( \function_exists( '\imagesavealpha' ) ) {
					$this->debug_message( 'imagesavealpha() missing' );
				} elseif ( $gd_version ) {
					$this->debug_message( "version: $gd_version" );
				}
				$this->debug_message( 'sorry nope' );
			}
		}
		return $this->gd_supports_webp;
	}

	/**
	 * Check for Imagick support of WebP.
	 *
	 * @return bool True if WebP support is detected.
	 */
	public function imagick_supports_webp() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( ! $this->imagick_supports_webp ) {
			if ( $this->imagick_support() ) {
				if ( \in_array( 'WEBP', $this->imagick_info, true ) ) {
					$this->debug_message( 'yes it does' );
					$this->imagick_supports_webp = true;
				}
			}
			if ( ! $this->imagick_supports_webp ) {
				$this->debug_message( 'sorry nope' );
			}
		}
		return $this->imagick_supports_webp;
	}

	/**
	 * Wrapper for get_option(). TODO: might make this the real thing later.
	 *
	 * @param string $option_name The name of the option to retrieve.
	 * @return mixed The value of the option.
	 */
	public function get_option( $option_name ) {
		$option_value = \get_option( $option_name );
		return $option_value;
	}

	/**
	 * Wrapper for update_option(). TODO: like $this->get_option, we might make this the real deal later.
	 *
	 * @param string $option_name The name of the option to save.
	 * @param mixed  $option_value The value to save for the option.
	 * @return bool True if the operation was successful.
	 */
	public function set_option( $option_name, $option_value ) {
		$success = \update_option( $option_name, $option_value );
		return $success;
	}

	/**
	 * Implode a multi-dimensional array without throwing errors. Arguments can be reverse order, same as implode().
	 *
	 * @param string $delimiter The character to put between the array items (the glue).
	 * @param array  $data The array to output with the glue.
	 * @return string The array values, separated by the delimiter.
	 */
	public function implode( $delimiter, $data = '' ) {
		if ( \is_array( $delimiter ) ) {
			$temp_data = $delimiter;
			$delimiter = $data;
			$data      = $temp_data;
		}
		if ( \is_array( $delimiter ) ) {
			return '';
		}
		$output = '';
		foreach ( $data as $value ) {
			if ( \is_string( $value ) || \is_numeric( $value ) ) {
				$output .= $value . $delimiter;
			} elseif ( \is_bool( $value ) ) {
				$output .= ( $value ? 'true' : 'false' ) . $delimiter;
			} elseif ( \is_array( $value ) ) {
				$output .= 'Array,';
			}
		}
		return \rtrim( $output, ',' );
	}

	/**
	 * Make sure an array/object can be parsed by a foreach().
	 *
	 * @param mixed $value A variable to test for iteration ability.
	 * @return bool True if the variable is iterable and not empty.
	 */
	public function is_iterable( $value ) {
		return ! empty( $value ) && is_iterable( $value );
	}

	/**
	 * Check if file exists, and that it is local rather than using a protocol like http:// or phar://
	 *
	 * @param string $file The path of the file to check.
	 * @return bool True if the file exists and is local, false otherwise.
	 */
	public function is_file( $file ) {
		if ( false !== \strpos( $file, '://' ) ) {
			return false;
		}
		if ( false !== \strpos( $file, 'phar://' ) ) {
			return false;
		}
		$file = \realpath( $file );
		return \is_file( $file );
	}

	/**
	 * Check if a file/directory is readable.
	 *
	 * @param string $file The path to check.
	 * @return bool True if it is, false if it ain't.
	 */
	public function is_readable( $file ) {
		if ( ! $this->is_file( $file ) ) {
			return false;
		}
		return \is_readable( $file );
	}

	/**
	 * Check filesize, and prevent errors by ensuring file exists, and that the cache has been cleared.
	 *
	 * @param string $file The name of the file.
	 * @return int The size of the file or zero.
	 */
	public function filesize( $file ) {
		$file = \realpath( $file );
		if ( $this->is_file( $file ) ) {
			// Flush the cache for filesize.
			\clearstatcache();
			// Find out the size of the new PNG file.
			return \filesize( $file );
		} else {
			return 0;
		}
	}

	/**
	 * Check the mimetype of the given file with magic mime strings/patterns.
	 *
	 * @param string $path The absolute path to the file.
	 * @param string $category The type of file we are checking. Accepts 'i' for
	 *                     images/pdfs or 'b' for binary.
	 * @return bool|string A valid mime-type or false.
	 */
	public function mimetype( $path, $category ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$this->debug_message( "testing mimetype: $path" );
		$type = false;
		// For S3 images/files, don't attempt to read the file, just use the quick (filename) mime check.
		if ( 'i' === $category && $this->stream_wrapped( $path ) ) {
			return $this->quick_mimetype( $path );
		}
		$path = \realpath( $path );
		if ( ! $this->is_file( $path ) ) {
			$this->debug_message( "$path is not a file, or out of bounds" );
			return $type;
		}
		if ( ! \is_readable( $path ) ) {
			$this->debug_message( "$path is not readable" );
			return $type;
		}
		if ( 'i' === $category ) {
			$file_handle   = \fopen( $path, 'rb' );
			$file_contents = \fread( $file_handle, 4096 );
			if ( $file_contents ) {
				// Read first 12 bytes, which equates to 24 hex characters.
				$magic = \bin2hex( \substr( $file_contents, 0, 12 ) );
				$this->debug_message( $magic );
				if ( 0 === \strpos( $magic, '52494646' ) && 16 === \strpos( $magic, '57454250' ) ) {
					$type = 'image/webp';
					$this->debug_message( "ewwwio type: $type" );
					return $type;
				}
				if ( 'ffd8ff' === \substr( $magic, 0, 6 ) ) {
					$type = 'image/jpeg';
					$this->debug_message( "ewwwio type: $type" );
					return $type;
				}
				if ( '89504e470d0a1a0a' === \substr( $magic, 0, 16 ) ) {
					$type = 'image/png';
					$this->debug_message( "ewwwio type: $type" );
					return $type;
				}
				if ( '474946383761' === \substr( $magic, 0, 12 ) || '474946383961' === \substr( $magic, 0, 12 ) ) {
					$type = 'image/gif';
					$this->debug_message( "ewwwio type: $type" );
					return $type;
				}
				if ( '25504446' === \substr( $magic, 0, 8 ) ) {
					$type = 'application/pdf';
					$this->debug_message( "ewwwio type: $type" );
					return $type;
				}
				if ( \preg_match( '/<svg/', $file_contents ) ) {
					$type = 'image/svg+xml';
					$this->debug_message( "ewwwio type: $type" );
					return $type;
				}
				$this->debug_message( "match not found for image: $magic" );
			} else {
				$this->debug_message( 'could not open for reading' );
			}
		}
		if ( 'b' === $category ) {
			$file_handle   = fopen( $path, 'rb' );
			$file_contents = fread( $file_handle, 12 );
			if ( $file_contents ) {
				// Read first 4 bytes, which equates to 8 hex characters.
				$magic = \bin2hex( \substr( $file_contents, 0, 4 ) );
				$this->debug_message( $magic );
				// Mac (Mach-O) binary.
				if ( 'cffaedfe' === $magic || 'feedface' === $magic || 'feedfacf' === $magic || 'cefaedfe' === $magic || 'cafebabe' === $magic ) {
					$type = 'application/x-executable';
					$this->debug_message( "ewwwio type: $type" );
					return $type;
				}
				// ELF (Linux or BSD) binary.
				if ( '7f454c46' === $magic ) {
					$type = 'application/x-executable';
					$this->debug_message( "ewwwio type: $type" );
					return $type;
				}
				// MS (DOS) binary.
				if ( '4d5a9000' === $magic ) {
					$type = 'application/x-executable';
					$this->debug_message( "ewwwio type: $type" );
					return $type;
				}
				$this->debug_message( "match not found for binary: $magic" );
			} else {
				$this->debug_message( 'could not open for reading' );
			}
		}
		return false;
	}

	/**
	 * Get mimetype based on file extension instead of file contents when speed outweighs accuracy.
	 *
	 * @param string $path The name of the file.
	 * @return string|bool The mime type based on the extension or false.
	 */
	public function quick_mimetype( $path ) {
		$pathextension = \strtolower( \pathinfo( $path, PATHINFO_EXTENSION ) );
		switch ( $pathextension ) {
			case 'jpg':
			case 'jpeg':
			case 'jpe':
				return 'image/jpeg';
			case 'png':
				return 'image/png';
			case 'gif':
				return 'image/gif';
			case 'webp':
				return 'image/webp';
			case 'pdf':
				return 'application/pdf';
			case 'svg':
				return 'image/svg+xml';
			default:
				if ( empty( $pathextension ) && ! $this->stream_wrapped( $path ) && $this->is_file( $path ) ) {
					return $this->mimetype( $path, 'i' );
				}
				return false;
		}
	}

	/**
	 * Checks if there is enough memory still available.
	 *
	 * Looks to see if the current usage + padding will fit within the memory_limit defined by PHP.
	 *
	 * @param int $padding Optional. The amount of memory needed to continue. Default 1050000.
	 * @return True to proceed, false if there is not enough memory.
	 */
	public function check_memory_available( $padding = 1050000 ) {
		$memory_limit = $this->memory_limit();

		$current_memory = \memory_get_usage( true ) + $padding;
		if ( $current_memory >= $memory_limit ) {
			$this->debug_message( "detected memory limit is not enough: $memory_limit" );
			return false;
		}
		$this->debug_message( "detected memory limit is: $memory_limit" );
		return true;
	}

	/**
	 * Finds the current PHP memory limit or a reasonable default.
	 *
	 * @return int The memory limit in bytes.
	 */
	public function memory_limit() {
		if ( \defined( 'EIO_MEMORY_LIMIT' ) && EIO_MEMORY_LIMIT ) {
			$memory_limit = EIO_MEMORY_LIMIT;
		} elseif ( \function_exists( 'ini_get' ) ) {
			$memory_limit = \ini_get( 'memory_limit' );
		} else {
			if ( ! \defined( 'EIO_MEMORY_LIMIT' ) ) {
				// Conservative default, current usage + 16M.
				$current_memory = \memory_get_usage( true );
				$memory_limit   = \round( $current_memory / ( 1024 * 1024 ) ) + 16;
				define( 'EIO_MEMORY_LIMIT', $memory_limit );
			}
		}
		if ( ! $memory_limit || -1 === \intval( $memory_limit ) ) {
			// Unlimited, set to 32GB.
			$memory_limit = '32000M';
		}
		if ( \stripos( $memory_limit, 'g' ) ) {
			$memory_limit = \intval( $memory_limit ) * 1024 * 1024 * 1024;
		} else {
			$memory_limit = \intval( $memory_limit ) * 1024 * 1024;
		}
		return $memory_limit;
	}

	/**
	 * Performs a case-sensitive check indicating if
	 * the haystack ends with needle.
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle   The substring to search for in the `$haystack`.
	 * @return bool True if `$haystack` ends with `$needle`, otherwise false.
	 */
	public function str_ends_with( $haystack, $needle ) {
		if ( '' === $haystack && '' !== $needle ) {
			return false;
		}

		$len = \strlen( $needle );

		return 0 === \substr_compare( $haystack, $needle, -$len, $len );
	}

	/**
	 * Checks the filename for an S3 or GCS stream wrapper.
	 *
	 * @param string $filename The filename to be searched.
	 * @return bool True if a stream wrapper is found, false otherwise.
	 */
	public function stream_wrapped( $filename ) {
		if ( false !== \strpos( $filename, '://' ) ) {
			if ( \strpos( $filename, 's3' ) === 0 ) {
				return true;
			}
			if ( \strpos( $filename, 'gs' ) === 0 ) {
				return true;
			}
		}
		return false;
	}
}
