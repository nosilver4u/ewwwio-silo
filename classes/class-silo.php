<?php

namespace EWWW;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sets up SILO, defines, requires, etc.
 */
final class SILO extends Base {
	/* Singleton */

	/**
	 * The one and only true EWWW\SILO
	 *
	 * @var object|EWWW\SILO $instance
	 */
	private static $instance;

	/**
	 * EWWW\Commands object.
	 *
	 * @var object|EWWW\Commands $commands
	 */
	public $commands;

	/**
	 * EWWW\Local object for handling local optimization tools/functions.
	 *
	 * @var object|EWWW\Local $local
	 */
	public $local;

	/**
	 * Whether the plugin is using the API or local tools.
	 *
	 * @var bool $cloud_mode
	 */
	public $cloud_mode = false;

	/**
	 * Did we already run tool_init()?
	 *
	 * @var bool $tools_initialized
	 */
	public $tools_initialized = false;

	/**
	 * Main EWWW\SILO instance.
	 *
	 * Ensures that only one instance of EWWW\SILO exists in memory at any given time.
	 *
	 * @static
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof SILO ) ) {

			self::setup_constants();
			self::$instance = new SILO();

			// For classes we need everywhere, front-end and back-end. Others are only included on admin_init (below).
			self::$instance->requires();
			self::$instance->db_init();

			self::$instance->debug_message( '<b>' . __METHOD__ . '()</b>' );
			// Setup additional classes that we need.
			self::$instance->load_children();
			// Check for an API key, set appropriate default compression levels, and init local binaries if needed.
			self::$instance->init();
			// init() should call cloud_init(), then exec_init() which calls tool_init, which then calls notice_utils()--though the latter two could likely be merged.

		}

		return self::$instance;
	}

	public static function setup_constants() {
		define( 'EWWW_IMAGE_OPTIMIZER_VERSION', 1000 );
		// For textdomain functions, should be removed.
		define( 'EWWW_IMAGE_OPTIMIZER_DOMAIN', 'ewww-image-optimizer' );
		// this is the path of the plugin file relative to the plugins/ folder
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
	}

	/**
	 * Include required files.
	 *
	 * @access private
	 */
	private function requires() {
		if ( defined( 'DB_NAME' ) && DB_NAME ) {
			require ABSPATH . 'mysql-db.php';
		} else {
			require ABSPATH . 'sqlite3-db.php';
		}
		require ABSPATH . 'classes/class-local.php';
		require ABSPATH . 'classes/class-commands.php';
	}

	/**
	 * Setup database.
	 */
	private function db_init() {
		// Create SQLite3 table
		if ( ! file_exists( ABSPATH . 'ewwwio.db' ) && ! defined( 'DB_NAME' ) ) {
			ewww_image_optimizer_install_sqlite_table();
		}

		// setup custom $wpdb attribute for our database
		global $wpdb;

		if ( ! isset( $wpdb ) && ! defined( 'DB_NAME' ) ) {
			$wpdb = new \wpdb( ABSPATH . 'ewwwio.db' );
		} elseif ( ! isset( $wpdb ) && defined( 'DB_NAME' ) && DB_NAME ) {
			$wpdb = new \wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		}

		// Create MySQL tables
		if ( defined( 'DB_NAME' ) && DB_NAME ) {
			ewww_image_optimizer_install_mysql_table();
		}

		if ( ! isset( $wpdb->ewwwio_images ) ) {
			$wpdb->ewwwio_images = $wpdb->prefix . "ewwwio_images";
		}
	}

	/**
	 * Setup additional/dependency classes.
	 */
	private function load_children() {
		self::$instance->local    = new Local();
		self::$instance->commands = new Commands();
	}

	// set some default option values
	private function set_defaults() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		add_option( 'ewww_image_optimizer_optipng_level', 2 );
		add_option( 'ewww_image_optimizer_metadata_remove', true );
		add_option( 'ewww_image_optimizer_jpg_level', '10' );
		add_option( 'ewww_image_optimizer_png_level', '10' );
		add_option( 'ewww_image_optimizer_gif_level', '10' );
		add_option( 'ewww_image_optimizer_pdf_level', '0' );
	}

	/**
	 * Settings initialization
	 */
	private function upgrade() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( get_option( 'ewww_image_optimizer_version' ) < EWWW_IMAGE_OPTIMIZER_VERSION ) { // This triggers load_alloptions().
			if ( get_option( 'ewww_image_optimizer_version' ) > 1 && get_option( 'ewww_image_optimizer_version' ) < 1000 ) {
				add_option( 'ewww_image_optimizer_metadata_remove', get_option( 'ewww_image_optimizer_remove_meta', true ) );
			}
			$this->set_defaults();
			update_option( 'ewww_image_optimizer_version', EWWW_IMAGE_OPTIMIZER_VERSION );
			load_overrides();
		}
	}

	/**
	 * Check to see if we are running in "cloud" mode. That is, using the API and no local tools.
	 */
	public function cloud_init() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$verified = ewww_image_optimizer_cloud_verify();
		if ( $verified && false !== strpos( $verified, 'exceeded' ) ) {
			$this->error( __( 'Your account is out of credits, upgrade at https://ewww.io/plans/ or purchase more at https://ewww.io/buy-credits/', 'ewww-image-optimizer' ) );
		}
		if ( $verified && false !== strpos( $verified, 'great' ) ) {
			if ( 10 === (int) get_option( 'ewww_image_optimizer_jpg_level' ) ) {
				update_option( 'ewww_image_optimizer_jpg_level', 30 );
			}
			if ( 10 === (int) get_option( 'ewww_image_optimizer_png_level' ) ) {
				update_option( 'ewww_image_optimizer_png_level', 20 );
			}
			if ( 0 === (int) get_option( 'ewww_image_optimizer_pdf_level' ) && ! isset( $ewwwio_settings['ewww_image_optimizer_pdf_level'] ) ) {
				update_option( 'ewww_image_optimizer_pdf_level', 10 );
			}
			if (
				$this->get_option( 'ewww_image_optimizer_jpg_level' ) > 10 &&
				$this->get_option( 'ewww_image_optimizer_png_level' ) > 10
			) {
				$this->debug_message( 'cloud mode enabled' );
				$this->cloud_mode = true;
			}
		} elseif ( $verified ) {
			$this->error( sprintf( __( 'Unexpected API verification response, contact support with this message: %s', 'ewww-image-optimizer' ), $verified ) );
		}
	}

	/**
	 * Initializes settings for the local tools, and runs the checks for tools on select pages.
	 */
	private function exec_init() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		// If cloud is fully enabled, we're going to skip all the checks related to the bundled tools.
		if ( $this->cloud_mode ) {
			$this->debug_message( 'cloud options enabled, shutting off binaries' );
			$this->local->skip_tools();
			return;
		}

		if ( ! $this->local->os_supported() ) {
			$this->notice_os();
			// Turn off all the tools.
			$this->debug_message( 'unsupported OS, disabling tools: ' . PHP_OS );
			$this->local->skip_tools();
			return;
		}
		$this->tool_init();
	}

	/**
	 * Check for binary installation and availability.
	 */
	private function tool_init() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$this->tools_initialized = true;
		if ( $this->cloud_mode ) {
			$this->debug_message( 'cloud options enabled, shutting off binaries' );
			$this->local->skip_tools();
			return;
		}
		// Make sure the bundled tools are installed.
		if ( ! $this->get_option( 'ewww_image_optimizer_skip_bundle' ) ) {
			$this->local->install_tools();
		}
		$this->notice_utils();
	}

	/**
	 * Checks for exec() and availability of local optimizers, then displays an error if needed.
	 */
	private function notice_utils() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		// Check if exec is disabled.
		if ( ! $this->local->exec_check() ) {
			// Display a warning if exec() is disabled, can't run local tools without it.
			$this->debug_message( 'exec disabled, alerting user' );
			$this->error(
				\printf(
					/* translators: %s: link to 'start your premium trial' */
					\__( 'Your server does not meet the requirements for free server-based compression with EWWW Image Optimizer. Enable exec, or get an API key at %s for 5x more compression, PNG/GIF/PDF compression, and more.', 'ewww-image-optimizer' ),
					'https://ewww.io/plans/'
				)
			);
		}

		$tools   = ewwwio()->local->check_all_tools();
		$missing = array();
		// Go through each of the required tools.
		foreach ( $tools as $tool => $info ) {
			// If a tool is needed, but wasn't found, add it to the $missing so we can display that info to the user.
			if ( $info['enabled'] && empty( $info['path'] ) ) {
				if ( 'cwebp' === $tool && ( $this->imagick_supports_webp() || $this->gd_supports_webp() ) ) {
					continue;
				}
				$missing[] = $tool;
			}
		}
		// If there is a message, display the warning.
		if ( ! empty( $missing ) ) {
			if ( ! \is_dir( $this->tool_dir ) ) {
				$this->tool_folder_notice();
			} elseif ( ! \is_writable( $this->tool_dir ) || ! is_readable( $this->tool_dir ) ) {
				$this->tool_folder_permissions_notice();
			} elseif ( ! \is_executable( $this->tool_dir ) && PHP_OS !== 'WINNT' ) {
				$this->tool_folder_permissions_notice();
			}
			// Expand the missing utilities list for use in the error message.
			$msg = \implode( ', ', $missing );
			$this->warning(
				\sprintf(
					/* translators: 1: comma-separated list of missing tools 2: Installation Instructions (link) */
					__( 'EWWW Image Optimizer uses open-source tools for free server-based compression, but your server is missing these: %1$s. Please install per %2$s.', 'ewww-image-optimizer' ),
					$msg,
					'https://docs.ewww.io/article/6-the-plugin-says-i-m-missing-something'
				)
			);
		}
	}

	/**
	 * Set initial defaults, load overrides, check for an API key and potentialy adjust default compression levels, then init local binaries if needed.
	 */
	private function init() {
		// Inits autoloaded options, sets defaults, and then loads overrides.
		$this->upgrade();
		// Check for a key, verify it, and then adjust compression levels if needed.
		$this->cloud_init();
		// Setup local binaries if we're not using the API for everything.
		$this->exec_init();
		if ( ! class_exists( 'SQLite3' ) && ! defined( 'DB_NAME' ) ) {
			// TODO: simulate this to test it out!
			//$this->cloud_init();
			//ewww_image_optimizer_cloud_verify(); //run it again to override defaults if necessary?
		}
	}

	/**
	 * Tells the user they are on an unsupported operating system.
	 */
	private function notice_os() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		// If they are using the API, let them know local mode won't do anything.
		if ( $this->get_option( 'ewww_image_optimizer_cloud_key' ) ) {
			$this->warning( __( 'Local server-based compression with EWWW Image Optimizer is only supported on Linux, FreeBSD, Mac OSX, and Windows. The Compress API will be used for the following formats:', 'ewww-image-optimizer' ) );
			if ( $this->get_option( 'ewww_image_optimizer_jpg_level' ) > 10 ) {
				$this->warning( 'JPG' );
			}
			if ( $this->get_option( 'ewww_image_optimizer_png_level' ) > 10 ) {
				$this->warning( 'PNG' );
			}
			if ( $this->get_option( 'ewww_image_optimizer_gif_level' ) ) {
				$this->warning( 'GIF' );
			}
			if ( $this->get_option( 'ewww_image_optimizer_pdf_level' ) ) {
				$this->warning( 'PDF' );
			}
			return;
		}
		$this->error( __( 'Local server-based compression with EWWW Image Optimizer is only supported on Linux, FreeBSD, Mac OSX, and Windows.', 'ewww-image-optimizer' ) );
	}

	/**
	 * Alert the user when the tool folder could not be created.
	 */
	private function tool_folder_notice() {
		$this->warning( __( 'EWWW Image Optimizer could not create the tool folder', 'ewww-image-optimizer' ) . ': ' . $this->tool_dir );
		$this->warning( __( 'Please adjust permissions or create the folder', 'ewww-image-optimizer' ) );
	}

	/**
	 * Alert the user when permissions on the tool folder are insufficient.
	 */
	private function tool_folder_permissions_notice() {
		$this->warning( \sprintf( __( 'EWWW Image Optimizer could not install tools in %s', 'ewww-image-optimizer' ), $this->tool_dir ) );
		$this->warning( __( 'Please adjust permissions on the folder. If you have installed the tools elsewhere, use the override to skip the bundled tools.', 'ewww-image-optimizer' ) );
		$this->warning( \sprintf( __( 'For more details, see %s.', 'ewww-image-optimizer' ), 'https://docs.ewww.io/article/6-the-plugin-says-i-m-missing-something' ) );
	}
}
