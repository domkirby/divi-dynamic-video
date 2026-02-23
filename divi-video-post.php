<?php
/**
 * Plugin Name: Divi Dynamic Video
 * Plugin URI:  https://example.com/divi-dynamic-video
 * Description: Registers a Video Post custom post type and a Divi Builder Video Embed module with dynamic and manual URL modes.
 * Version:     1.0.0
 * Author:      Your Name
 * Text Domain: divi-video-post
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

defined( 'ABSPATH' ) || exit;

define( 'DVP_VERSION', '1.0.0' );
define( 'DVP_PLUGIN_FILE', __FILE__ );
define( 'DVP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DVP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load CPT registration.
require_once DVP_PLUGIN_DIR . 'includes/class-video-post-cpt.php';
new DVP_Video_Post_CPT();

// Load Divi Extension when Divi is ready.
add_action( 'divi_extensions_init', function () {
	require_once DVP_PLUGIN_DIR . 'includes/class-divi-extension.php';
} );
