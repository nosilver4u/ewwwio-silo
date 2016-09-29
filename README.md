#EWWW Image Optimizer - SILO edition
[Donate](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=MKMQKCBFFG3WW)

License: GPLv3

This is a port of the EWWW Image Optimizer plugin for WordPress that reduces image sizes in standalone (SILO) mode using lossless/lossy methods and image format conversion. It is currently in Alpha status, and contains copious amount of the WP code still. Some of that is intential, like the port of the wpdb class to interface with SQLite3, but some just needs cleaning.
SILO edition is a PHP application that will optimize your images from the command-line (and soon with a web-interface). It can re-compress your images, will eventually be able to convert your images automatically to the file format that will produce the smallest image size, and can currently apply lossy compression to achieve huge savings for PNG and JPG images.

###Why use EWWW Image Optimizer?

1. **Your pages will load faster.** Smaller image sizes means faster page loads. This will make your visitors happy, and can increase revenue.
2. **Faster backups.** Smaller image sizes also means faster backups.
3. **Less bandwidth usage.** Optimizing your images can save you hundreds of KB per image, which means significantly less bandwidth usage.
4. **Super fast.** EWWW IO can run on your own server, so you don’t have to wait for a third party service to receive, process, and return your images. You can optimize hundreds of images in just a few minutes. PNG files take the longest, but you can adjust the settings for your situation.
5. **Best JPG optimization.** With TinyJPG integration, nothing else comes close (requires an API subscription).
6. **Best PNG optimization.** You can use pngout, optipng, and pngquant in conjunction. And if that isn't enough, try the lossy PNG option powered by TinyPNG.
7. **Root access not needed** Pre-compiled binaries are made available to install directly within the EWWW IO folder, and cloud optimization is provided for those who cannot run the binaries locally (or if you want better compression).

By default, EWWW Image Optimizer uses lossless optimization techniques, so your image quality will be exactly the same before and after the optimization. The only thing that will change is your file size. The one small exception to this is GIF animations. While the optimization is technically lossless, you will not be able to properly edit the animation again without performing an --unoptimize operation with gifsicle. The gif2png and jpg2png conversions are also lossless but the png2jpg process is not lossless. The lossy optimization for JPG and PNG files uses sophisticated algorithms to minimize perceptual quality loss, which is vastly different than setting a static quality/compression level.

The tools used for optimization are [jpegtran](http://jpegclub.org/jpegtran/), [TinyJPG](http://www.tinyjpg.com), [JPEGmini](http://www.jpegmini.com), [optipng](http://optipng.sourceforge.net/), [pngout](http://advsys.net/ken/utils.htm), [pngquant](http://pngquant.org/), [TinyPNG](http://www.tinypng.com), and [gifsicle](http://www.lcdf.org/gifsicle/). Most of these are freely available except TinyJPG/TinyPNG and JPEGmini. Images are converted using the above tools and one of the following: GMagick, IMagick, GD or 'convert' (ImageMagick).

EWWW Image Optimizer calls optimization utilities directly which is well suited to shared hosting situations where these utilities may already be installed. Pre-compiled binaries/executables are provided for optipng, gifsicle, pngquant, cwebp, and jpegtran. Pngout can be installed with one-click from the settings page. If none of that works, there is a cloud option that will work for any site.

### Bulk Optimize

Scans entire folders (recursively) to optimize every image, with minimal hassle.

###Skips Previously Optimized Images

All optimized images are stored in an (optional) SQLite3 database so that the plugin does not attempt to re-optimize them unless they are modified.

###WebP Images

Can generate WebP versions of your images (will not remove originals, since you'll need both versions to support all browsers), and enables you to serve even smaller images to supported browsers.

###PHP CLI

Lets you optimize entire folders, or single images. Run `php cli.php -h` for command-line syntax. Allows you to do things like run it in 'screen' or via cron. 

###CDN Support (in the future)

Planning to add the ability to upload to Amazon S3, Azure Storage, Cloudinary, and DreamSpeed CDN.

## Pre-requisites

Make sure you have php-cli, and php-sqlite3 available. The SQLite3 extension is optional, but will allow EWWW IO to keep track of which images have been compressed already, if you intend to run it regularly. There is a sample config file at config.sample.php which you can copy to config.php and customize to your liking. If the SQLite3 extension is available, options may also be stored in the database, otherwise, they will be read from the config file, or use the defaults.

###Installing pngout

Pngout is not enabled by default because it is resource intensive and not redistributable. Optipng is the preferred PNG optimizer if you have resource (CPU) constraints. Pngout is also not open-source for those who care about such things, but the command-line version is free. You can download the appropriate version from http://advsys.net/ken/utils.htm and install it in the ewwwio-silo/tools/ folder.

##Frequently Asked Questions

###Google Pagespeed says my images need compressing or resizing, but I already optimized all my images. What do I do?

Try this for starters: https://ewww.io/2014/12/05/pagespeed-says-my-images-need-more-work/

###It complains that I'm missing something, what do I do?

This article will walk you through installing the required tools (and the alternatives if installation does not work): https://ewww.io/2014/12/06/the-plugin-says-im-missing-something/

###Does EWWW IO replace existing images?

Yes, but only if the optimized version is smaller. The plugin should NEVER create a larger image.

###Can I resize my images with this plugin?

Not yet, but it's on my radar. [The WordPress plugin can though](https://ewww.io).

###Can I lower the compression setting for JPGs to save more space?

The lossy optimization using the EWWW IO Cloud service will determine the ideal quality setting and save even more space. You cannot manually set the quality with this plugin, if you want more compression, you should REALLY try EWWW IO Cloud at https://ewww.io/plans/.

###I want to know more about image optimization, and why you chose these options/tools.

That's not a question, but since I made it up, I'll answer it. See these resources:  
http://developer.yahoo.com/performance/rules.html#opt_images  
https://developers.google.com/speed/docs/best-practices/payload#CompressImages  
https://developers.google.com/speed/docs/insights/OptimizeImages

Pngout, TinyJPG/TinyPNG, JPEGmini, and Pngquant were recommended by EWWW IO users. Pngout (usually) optimizes better than Optipng, and best when they are used together. TinyJPG is the best lossy compression tool that I have found for JPG images. Pngquant is an excellent lossy optimizer for PNGs, and is one of the tools used by TinyPNG.

##Changelog

###0.20
* API enabled, notices fixed, webp updated, config options working properly, and other goodness. still might eat your cat

###0.10
* initial release, may eat your cat

##Contact and Credits

Written by [Shane Bishop](https://ewww.io). Based upon CW Image Optimizer, which was written by [Jacob Allred](http://www.jacoballred.com/) at [Corban Works, LLC](http://www.corbanworks.com/). CW Image Optimizer was based on WP Smush.it. Jpegtran is the work of the Independent JPEG Group.  
