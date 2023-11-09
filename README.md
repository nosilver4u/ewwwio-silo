# EWWW Image Optimizer - SILO edition

License: GPLv3

This is a port of the EWWW Image Optimizer plugin for WordPress that reduces image sizes in standalone (SILO) mode using lossless/lossy methods and image format conversion. It is currently in Alpha status, and contains copious amount of the WP code still. Some of that is intential, like the port of the wpdb class to interface with SQLite3/MySQL, but some just needs cleaning.
SILO edition is a PHP application that will optimize your images from the command-line. It can re-compress your images, will eventually be able to convert your images automatically to the file format that will produce the smallest image size, and can currently apply lossy compression to achieve huge savings for PNG and JPG images.

To use, copy config.sample.php to config.php and edit to your needs, then run cli.php from the command line.

### Why use EWWW Image Optimizer?

1. **Your pages will load faster.** Smaller image sizes means faster page loads. This will make your visitors happy, and can increase revenue.
2. **Faster backups.** Smaller image sizes also means faster backups.
3. **Less bandwidth usage.** Optimizing your images can save you hundreds of KB per image, which means significantly less bandwidth usage.
4. **Super fast.** EWWW IO can run on your own server, so you don’t have to wait for a third party service to receive, process, and return your images. You can optimize hundreds of images in just a few minutes. PNG files take the longest, but you can adjust the settings for your situation.
5. **Best JPG optimization.** With TinyJPG integration, nothing else comes close (requires an API subscription).
6. **Best PNG optimization.** You can use optipng and pngquant together. And if that isn't enough, try the lossy PNG option powered by the Compress API.
7. **Root access not needed** Pre-compiled binaries are made available to install directly within the EWWW IO folder, and cloud-based optimization is provided for those who cannot run the binaries locally (or if you want better compression).

By default, EWWW Image Optimizer uses lossless optimization techniques, so your image quality will be exactly the same before and after the optimization. The only thing that will change is your file size. The one small exception to this is GIF animations. While the optimization is technically lossless, you will not be able to properly edit the animation again without performing an --unoptimize operation with gifsicle. The gif2png and jpg2png conversions are also lossless but the png2jpg process is not lossless. The lossy optimization for JPG and PNG files uses sophisticated algorithms to minimize perceptual quality loss, which is vastly different than setting a static quality/compression level.

EWWW Image Optimizer calls optimization utilities directly which is well suited to shared hosting situations where these utilities may already be installed. Pre-compiled binaries/executables are provided for optipng, gifsicle, pngquant, cwebp, and jpegtran. If local optimization doesn't work on your server, the [Compress API](https://ewww.io/plans/) will work for any site.

### Bulk Optimize

Scans entire folders (recursively) to optimize every image, with minimal hassle.

### Skips Previously Optimized Images

All optimized images are stored in an (optional) SQLite3 or MySQL database so that the application does not attempt to re-optimize them unless they are modified.

### WebP Images

Can generate WebP versions of your images (will not remove originals, since you'll need both versions to support all browsers), and enables you to serve even smaller images to supported browsers.

### PHP CLI

Lets you optimize entire folders, or single images. Run `php cli.php -h` for command-line syntax. Allows you to do things like run it in 'screen' or via cron.

## Pre-requisites

SILO requires at least PHP 7.x, and make sure you have php-cli and php-sqlite3 available. The SQLite3 or Mysqli extensions are optional, but will allow EWWW IO to keep track of which images have been compressed already, if you intend to run it regularly. There is a sample config file at config.sample.php which you can copy to config.php and customize to your liking. If the SQLite3 or Mysqli extensions are available, options may also be stored in the database, otherwise, they will be read from the config file, or use the defaults.

## Frequently Asked Questions

### Google Pagespeed says my images need compressing or resizing, but I already optimized all my images. What do I do?

Try this for starters: https://ewww.io/2014/12/05/pagespeed-says-my-images-need-more-work/

### It complains that I'm missing something, what do I do?

This article will walk you through installing the required tools (and the alternatives if installation does not work): https://ewww.io/2014/12/06/the-plugin-says-im-missing-something/

### Does EWWW IO replace existing images?

Yes, but only if the optimized version is smaller. The plugin should NEVER create a larger image.

### Can I resize my images with this plugin?

Not yet, but it's on the wishlist. [The WordPress plugin can though](https://ewww.io).

### Can I lower the compression setting for JPGs to save more space?

The lossy optimization using the EWWW IO Compress API service will determine the ideal quality setting and save even more space. You cannot manually set the quality with this plugin, if you want more compression, you should REALLY try EWWW IO premium at https://ewww.io/plans/.

### I want to know more about image optimization, and why you chose these options/tools.

That's not a question, but since I made it up, I'll answer it. See these resources:  
http://developer.yahoo.com/performance/rules.html#opt_images  
https://developers.google.com/speed/docs/best-practices/payload#CompressImages  
https://developers.google.com/speed/docs/insights/OptimizeImages

TinyJPG/TinyPNG, JPEGmini, and Pngquant were recommended by EWWW IO users. Pngout (usually) optimizes better than Optipng, and best when they are used together. TinyJPG is the best lossy compression tool that I have found for JPG images. Pngquant is an excellent lossy optimizer for PNGs, and is one of the tools used by TinyPNG.

## Changelog

### 0.60
* Updated binaries: jpegtran 9d, optipng 0.7.7, gifsicle 1.93, pngquant 2.17.0, and cwebp 1.2.0

### 0.50
* Updated Requests library
* Fixed API SSL issues
* Fixed API options initialization
* Updated to API v2 endpoint

### 0.40
* Fixed PHP 7/8 compatibility

### 0.30
* Added MySQL support, and fixed bug with apply_filters()

### 0.20
* API enabled, notices fixed, webp updated, config options working properly, and other goodness. still might eat your cat

### 0.10
* initial release, may eat your cat

## Contact and Credits

Written by [Shane Bishop](https://ewww.io). Based upon CW Image Optimizer, which was written by [Jacob Allred](http://www.jacoballred.com/) at [Corban Works, LLC](http://www.corbanworks.com/). CW Image Optimizer was based on WP Smush.it. Jpegtran is the work of the Independent JPEG Group.  
