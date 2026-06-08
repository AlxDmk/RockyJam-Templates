<?php

namespace RockyJamTemplates\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin singleton.
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
		add_action( 'init', [ $this, 'load_textdomain' ], 1 );
		add_action( 'init', [ TemplateManager::class, 'register_cpt' ], 5 );
		add_action( 'init', [ $this->template_manager, 'maybe_create_default_template' ], 6 );

		// Apply template on the frontend.
		add_action( 'init', [ $this->template_manager, 'register_hooks' ], 10 );

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
}
