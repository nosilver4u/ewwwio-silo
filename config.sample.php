<?php

// While 10+ years of development have worked out just about every conceivable problem, make sure you have a good backup in place before proceeding. No amount of development can ensure bug-free code or avoid user errors in use/configuration. Sorry, just the way us humans are built!
global $ewwwio_settings;
$ewwwio_settings = array(

	// Uncomment (remove the slashes) to activate any of these settings and override the defaults.
	// API Key will be validated each time an image is optimized. Purchase an API key at https://ewww.io/plans/ (not required, but conserves resources and achieves must higher compression ratios)
	//'ewww_image_optimizer_cloud_key'       => '',

	// This will remove ALL metadata: EXIF and comments.
	//'ewww_image_optimizer_metadata_remove' =>	true,

	// Valid JPG optimization levels:
	// 0 = off
	// 10 = lossless
	// 20 = maximum lossless (requires API key)
	// 30 = lossy (requires API key--default when API key present)
	// 40 = maximum lossy (requires API key)
	//'ewww_image_optimizer_jpg_level'       => 10,

	// Valid PNG optimization levels:
	// 0 = off
	// 10 = lossless
	// 20 = better lossless (uses API--default when API key present)
	// 30 = deprecated/merged into level 20
	// 40 = lossy (can run via pngquant locally, but will use API for additional savings if key is entered)
	// 50 = maximum lossy (requires API key)
	//'ewww_image_optimizer_png_level'       => 10,

	// Valid GIF levels:
	// 0 = off
	// 10 = lossless
	//'ewww_image_optimizer_gif_level'		=> 10,

	// Valid PDF levels (API key required):
	// 0 = off
	// 10 = lossless (default when API key present)
	// 20 = lossy
	//'ewww_image_optimizer_pdf_level'		=>  0,

	// Choose how long to pause between images (in seconds)
	//'ewww_image_optimizer_delay'			=>  0,

	// Use full paths, not relative paths, uncomment any of the examples below or add your own folders. Be sure to enclose with single quotes, and finish with a comma (,) or you'll get a syntax error.
	//'ewww_image_optimizer_aux_paths'		=>	array(
	//		'/var/www/',
	//		'/home/username/public_html/',
	//),

	// skip images smaller than this (in bytes)
	//'ewww_image_optimizer_skip_size'		=>	0,

	// skip PNG images larger than this (in bytes)
	//'ewww_image_optimizer_skip_png_size'		=>	0,

	// If you have already installed the utilities in a system location, such as /usr/local/bin or /usr/bin, use this to force the plugin to use those versions and skip the auto-installers.
	//'ewww_image_optimizer_skip_bundle' 		=> 	false,

	// What quality level should we attempt with PNG to JPG conversion and/or JPG resizing (recommended to use something between 50 and 90)
	//'ewww_image_optimizer_jpg_quality'		=>	82,

	// JPG to WebP conversion is lossy, but quality loss is minimal. PNG to WebP conversion is lossless. This setting enables conversion for both formats. Originals are never deleted, and WebP images should only be served to supported browsers.
	//'ewww_image_optimizer_webp'			=>	false,

	// What quality level should we use for lossy WebP conversion (JPG typically). Note that because WebP is more efficient, this can usually be set lower than the JPG quality.
	//'ewww_image_optimizer_webp_quality'      => 75,

	// Enable sharper WebP images at the cost of more CPU and slower processing.
	//'ewww_image_optimizer_sharpen'      => false,

	// Enable lossy processing of PNG images for smaller filesizes.
	//'ewww_image_optimizer_lossy_png2webp' => false,
);

// By default, SILO uses sqlite for tracking image status. This may not be suitable in cases where overlapping processes need write access to the database. Uncomment (remove the leading slashes) and configure the settings below to enable MySQL support.
// NOTE: You will need to manually create an empty database, but SILO will create all the tables when it is first run.

/** MySQL database name */
//define('DB_NAME', 'ewww_silo');

/** MySQL database username */
//define('DB_USER', '');

/** MySQL database password */
//define('DB_PASSWORD', '');

/** MySQL hostname */
//define('DB_HOST', 'localhost');

/** Database Charset to use in creating database tables. */
//define('DB_CHARSET', 'utf8');

/** The Database Collate type. Don't change this if in doubt. */
//define('DB_COLLATE', '');

