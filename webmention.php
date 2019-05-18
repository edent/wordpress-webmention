<?php
/**
 * Plugin Name: Webmention
 * Plugin URI: https://github.com/pfefferle/wordpress-webmention
 * Description: Webmention support for WordPress posts
 * Author: Matthias Pfefferle
 * Author URI: https://notiz.blog/
 * Version: 3.8.8
 * License: MIT
 * License URI: http://opensource.org/licenses/MIT
 * Text Domain: webmention
 * Domain Path: /languages
 */

defined( 'WEBMENTION_COMMENT_APPROVE' ) || define( 'WEBMENTION_COMMENT_APPROVE', 0 );
defined( 'WEBMENTION_COMMENT_TYPE' ) || define( 'WEBMENTION_COMMENT_TYPE', 'webmention' );

define( 'WEBMENTION_PROCESS_TYPE_ASYNC', 'async' );
define( 'WEBMENTION_PROCESS_TYPE_SYNC', 'sync' );

defined( 'WEBMENTION_PROCESS_TYPE' ) || define( 'WEBMENTION_PROCESS_TYPE', WEBMENTION_PROCESS_TYPE_SYNC );

defined( 'WEBMENTION_VOUCH' ) || define( 'WEBMENTION_VOUCH', false );

// initialize admin settings
require_once dirname( __FILE__ ) . '/includes/class-webmention-admin.php';
add_action( 'admin_init', array( 'Webmention_Admin', 'init' ) );
add_action( 'admin_menu', array( 'Webmention_Admin', 'admin_menu' ) );

/**
 * Initialize Webmention Plugin
 */
function webmention_init() {
	// Add a new feature type to posts for webmentions
	add_post_type_support( 'post', 'webmentions' );
	if ( 1 === (int) get_option( 'webmention_support_pages' ) ) {
		add_post_type_support( 'page', 'webmentions' );
	}

	// list of various public helper functions
	require_once dirname( __FILE__ ) . '/includes/functions.php';

	$files = array(
		'debug.php',
		'functions.php',
		'class-webmention-receiver.php',
		'class-webmention-sender.php',
		'class-webmention-vouch.php',
		'class-webmention-admin.php',
		'class-webmention-410.php',
	);

	$path = plugin_dir_path( __FILE__ ) . 'includes/';
	foreach ( $files as $filename ) {
		if ( file_exists( $path . $filename ) ) {
			require_once $path . $filename;
			$filename = str_replace( '.php', '', $filename );
			if ( strncmp( $filename, 'class', strlen( 'class' ) ) === 0 ) {
				$filename = str_replace( 'class-', '', $filename );
				$filename = ucwords( str_replace( '-', '_', $filename ), '_' );
				if ( method_exists( $filename, 'init' ) ) {
					add_action( 'init', array( $filename, 'init' ) );
				}
			}
		}
	}

	// Default Comment Status
	add_filter( 'get_default_comment_status', 'webmention_get_default_comment_status', 11, 3 );
	add_filter( 'pings_open', 'webmention_pings_open', 10, 2 );

	// Load language files
	webmention_plugin_textdomain();

	add_action( 'comment_form_after', 'webmention_comment_form', 11 );

	add_filter( 'nodeinfo_data', 'webmention_nodeinfo', 10, 2 );
	add_filter( 'nodeinfo2_data', 'webmention_nodeinfo2', 10 );

	// remove old Webmention code
	remove_action( 'init', array( 'WebMentionFormPlugin', 'init' ) );
	remove_action( 'init', array( 'WebMentionForCommentsPlugin', 'init' ) );
}
add_action( 'plugins_loaded', 'webmention_init' );

function webmention_get_default_comment_status( $status, $post_type, $comment_type ) {
	if ( 'webmention' === $comment_type ) {
		return post_type_supports( $post_type, 'webmentions' ) ? 'open' : 'closed';
	}
	// Since support for the pingback comment type is used to keep pings open...
	if ( ( 'pingback' === $comment_type ) ) {
		return ( post_type_supports( $post_type, 'webmentions' ) ? 'open' : $status );
	}

	return $status;
}

/**
 * render the comment form
 */
function webmention_comment_form() {
	$template = apply_filters( 'webmention_comment_form', plugin_dir_path( __FILE__ ) . 'templates/webmention-comment-form.php' );

	if ( 1 === (int) get_option( 'webmention_show_comment_form', 1 ) ) {
		load_template( $template );
	}
}

/**
 * Return true if page is enabled for Homepage Webmentions
 *
 * @param bool $open    Whether the current post is open for pings.
 * @param int  $post_id The post ID.
 *
 * @return boolean if pings are open
 */
function webmention_pings_open( $open, $post_id ) {
	if ( get_option( 'webmention_home_mentions' ) === $post_id ) {
		return true;
	}

	return $open;
}

/**
 * Load language files
 */
function webmention_plugin_textdomain() {
	load_plugin_textdomain( 'webmention', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

/**
 * Extend NodeInfo data
 *
 * @param array $nodeinfo NodeInfo data
 * @param array $version  updated data
 */
function webmention_nodeinfo( $nodeinfo, $version ) {
	if ( '2.0' === $version ) {
		$nodeinfo['protocols'][] = 'webmention';
	} else {
		$nodeinfo['protocols']['inbound'][]  = 'webmention';
		$nodeinfo['protocols']['outbound'][] = 'webmention';
	}

	return $nodeinfo;
}

/**
 * Extend NodeInfo2 data
 *
 * @param array $nodeinfo NodeInfo2 data
 * @param array $version  updated data
 */
function webmention_nodeinfo2( $nodeinfo ) {
	$nodeinfo['protocols'][] = 'webmention';

	return $nodeinfo;
}
