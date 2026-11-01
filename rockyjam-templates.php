<?php
/**
 * Plugin Name: RockyJam Templates
 * Plugin URI:  https://github.com/AlxDmk/RockyJam-Templates
 * Description: Custom page templates for WooCommerce product and category pages.
 * Version:     0.3.0
 * Author:      AlxDmk
 * Author URI:  https://github.com/AlxDmk
 * Text Domain: rockyjam-templates
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RJT_VERSION',  '0.3.0' );
define( 'RJT_FILE',     __FILE__ );
define( 'RJT_PATH',     plugin_dir_path( __FILE__ ) );
define( 'RJT_URL',      plugin_dir_url( __FILE__ ) );
define( 'RJT_BASENAME', plugin_basename( __FILE__ ) );
define( 'RJT_CPT',      'rj_template' );

// Load class files (no side-effects, just definitions).
require_once RJT_PATH . 'includes/Core/TemplateManager.php';
require_once RJT_PATH . 'includes/Core/HooksConfig.php';
require_once RJT_PATH . 'includes/Core/AddonsRegistry.php';
require_once RJT_PATH . 'includes/Core/Plugin.php';
require_once RJT_PATH . 'includes/Admin/AdminPage.php';
require_once RJT_PATH . 'includes/Admin/HooksPage.php';
require_once RJT_PATH . 'includes/Admin/OverridesPage.php';
require_once RJT_PATH . 'includes/Admin/ProductMeta.php';

/**
 * Returns the main plugin instance.
 *
 * @return \RockyJamTemplates\Core\Plugin
 */
function rockyjam_templates(): \RockyJamTemplates\Core\Plugin {
	return \RockyJamTemplates\Core\Plugin::instance();
}

// Boot after all plugins are loaded so WooCommerce is available.
add_action( 'plugins_loaded', function () {
	rockyjam_templates()->boot();
} );

/**
 * Optional per-template extra assets.
 * If templates/{slug}/assets/extra.css and/or extra.js exist, they are enqueued
 * on the product page AFTER the template's style.css / script.js.
 */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}

	$slug = ( new \RockyJamTemplates\Core\TemplateManager() )->resolve_for_product( (int) get_queried_object_id() );
	if ( ! $slug ) {
		return;
	}

	$base_path = RJT_PATH . 'templates/' . $slug . '/assets/';
	$base_url  = RJT_URL . 'templates/' . $slug . '/assets/';

	if ( file_exists( $base_path . 'extra.css' ) ) {
		wp_enqueue_style( 'rjt-tpl-' . $slug . '-extra', $base_url . 'extra.css', [ 'rjt-tpl-' . $slug ], (string) filemtime( $base_path . 'extra.css' ) );
	}
	if ( file_exists( $base_path . 'extra.js' ) ) {
		wp_enqueue_script( 'rjt-tpl-' . $slug . '-extra', $base_url . 'extra.js', [], (string) filemtime( $base_path . 'extra.js' ), true );
	}
}, 20 );

register_activation_hook( __FILE__, function () {
	\RockyJamTemplates\Core\TemplateManager::register_cpt();
	flush_rewrite_rules();
	// Create default template folder on disk.
	( new \RockyJamTemplates\Core\TemplateManager() )->maybe_create_default_template();
} );

register_deactivation_hook( __FILE__, function () {
	flush_rewrite_rules();
} );
