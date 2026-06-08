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
require_once RJT_PATH . 'includes/Core/Plugin.php';
require_once RJT_PATH . 'includes/Admin/AdminPage.php';
require_once RJT_PATH . 'includes/Admin/HooksPage.php';
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

register_activation_hook( __FILE__, function () {
	\RockyJamTemplates\Core\TemplateManager::register_cpt();
	flush_rewrite_rules();
	// Create default template folder on disk.
	( new \RockyJamTemplates\Core\TemplateManager() )->maybe_create_default_template();
} );

register_deactivation_hook( __FILE__, function () {
	flush_rewrite_rules();
} );
