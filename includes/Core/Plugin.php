<?php

namespace RockyJamTemplates\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin singleton.
 *
 * boot() is called on 'plugins_loaded' from the main plugin file,
 * so WooCommerce and all other plugins are already loaded when we run.
 *
 * @package RockyJamTemplates
 */
final class Plugin {

	private static ?Plugin $instance = null;
	private TemplateManager $template_manager;

	private function __construct() {
		$this->template_manager = new TemplateManager();
	}

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot(): void {
		// Textdomain first.
		add_action( 'init', [ $this, 'load_textdomain' ], 1 );

		// Register CPT on init.
		add_action( 'init', [ TemplateManager::class, 'register_cpt' ], 5 );

		// Create default template if missing (runs after CPT is registered).
		add_action( 'init', [ $this->template_manager, 'maybe_create_default_template' ], 6 );

		// Register frontend hooks only when WooCommerce is active.
		if ( $this->is_woocommerce_active() ) {
			add_action( 'init', [ $this->template_manager, 'register_hooks' ], 10 );
		}

		// Admin-only classes are already loaded via require_once in the main file.
		// We only register their hooks here.
		if ( is_admin() ) {
			( new \RockyJamTemplates\Admin\AdminPage( $this->template_manager ) )->register();
			( new \RockyJamTemplates\Admin\ProductMeta( $this->template_manager ) )->register();
		}
	}

	public function load_textdomain(): void {
		load_plugin_textdomain(
			'rockyjam-templates',
			false,
			dirname( RJT_BASENAME ) . '/languages'
		);
	}

	public function template_manager(): TemplateManager {
		return $this->template_manager;
	}

	/**
	 * Check if WooCommerce is active without depending on WC classes.
	 */
	private function is_woocommerce_active(): bool {
		return class_exists( 'WooCommerce' );
	}
}
