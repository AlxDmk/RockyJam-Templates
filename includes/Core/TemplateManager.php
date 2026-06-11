<?php

namespace RockyJamTemplates\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages template folders on disk and their metadata in wp_options / wp_postmeta.
 *
 * Directory layout
 * ----------------
 * {plugin}/templates/{slug}/
 *   template.json   — metadata: name, type, is_default, version, description
 *   hooks.php       — remove_action / add_action / priority overrides
 *   content.php     — the actual page markup (has $product in scope)
 *   functions.php   — helper functions specific to this template
 *   assets/
 *     style.css
 *     script.js
 *
 * Template metadata is stored on disk only (template.json).
 * Which template is assigned to a product is stored in wp_postmeta (_rj_template_slug).
 * The active default slug is stored in wp_options (rjt_default_product_template).
 *
 * @package RockyJamTemplates
 */
class TemplateManager {

	/** Base directory for all templates. */
	public static function templates_dir(): string {
		return RJT_PATH . 'templates/';
	}

	// =========================================================================
	// CPT — still used to power the admin list screen, but content lives on disk.
	// We keep a lightweight post per template so WP handles revisions / caps.
	// =========================================================================

	public static function register_cpt(): void {
		register_post_type(
			RJT_CPT,
			[
				'label'              => __( 'Product Templates', 'rockyjam-templates' ),
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => false,
				'show_in_menu'       => false,
				'show_in_rest'       => false,
				'capability_type'    => 'post',
				'map_meta_cap'       => true,
				'hierarchical'       => false,
				'supports'           => [ 'title' ],
				'rewrite'            => false,
				'query_var'          => false,
			]
		);
	}

	// =========================================================================
	// Discovery — read templates from disk
	// =========================================================================

	/**
	 * Scan templates/ directory and return all valid template slugs.
	 *
	 * @return string[]
	 */
	public function get_available_slugs(): array {
		$dir  = self::templates_dir();
		$list = [];

		if ( ! is_dir( $dir ) ) {
			return $list;
		}

		foreach ( (array) glob( $dir . '*', GLOB_ONLYDIR ) as $folder ) {
			$slug = basename( $folder );
			if ( file_exists( $folder . '/template.json' ) ) {
				$list[] = $slug;
			}
		}

		return $list;
	}

	/**
	 * Read template.json for a given slug. Returns null if invalid.
	 *
	 * @param  string $slug
	 * @return array{slug:string,name:string,type:string,version:string,description:string,is_default:bool}|null
	 */
	public function get_meta( string $slug ): ?array {
		$file = self::templates_dir() . $slug . '/template.json';

		if ( ! file_exists( $file ) ) {
			return null;
		}

		$raw = json_decode( file_get_contents( $file ), true );

		if ( ! is_array( $raw ) ) {
			return null;
		}

		return [
			'slug'        => $slug,
			'name'        => $raw['name']        ?? $slug,
			'type'        => $raw['type']        ?? 'product',
			'version'     => $raw['version']     ?? '1.0.0',
			'description' => $raw['description'] ?? '',
			'author'      => $raw['author']       ?? '',
			'is_default'  => $this->get_default_slug( $raw['type'] ?? 'product' ) === $slug,
		];
	}

	/**
	 * Return metadata for all templates, optionally filtered by type.
	 *
	 * @param  string $type  'product' | 'category' | '' (all)
	 * @return array[]
	 */
	public function get_all( string $type = '' ): array {
		$list = [];

		foreach ( $this->get_available_slugs() as $slug ) {
			$meta = $this->get_meta( $slug );
			if ( ! $meta ) {
				continue;
			}
			if ( $type && $meta['type'] !== $type ) {
				continue;
			}
			$list[] = $meta;
		}

		// Sort: default first, then alphabetically.
		usort( $list, fn( $a, $b ) =>
			( $b['is_default'] <=> $a['is_default'] ) ?: strcmp( $a['name'], $b['name'] )
		);

		return $list;
	}

	// =========================================================================
	// Default template
	// =========================================================================

	/**
	 * Return the slug of the current default template for a type.
	 *
	 * @param  string $type  'product' | 'category'
	 * @return string
	 */
	public function get_default_slug( string $type = 'product' ): string {
		return (string) get_option( 'rjt_default_' . $type . '_template', '' );
	}

	/**
	 * Set the default template for a type.
	 *
	 * @param string $slug
	 * @param string $type
	 */
	public function set_default( string $slug, string $type = 'product' ): void {
		update_option( 'rjt_default_' . $type . '_template', $slug );
	}

	/**
	 * If no default has been set yet, pick the first available template.
	 * Called on 'init' priority 6 (after register_cpt).
	 */
	public function maybe_set_default(): void {
		foreach ( [ 'product', 'category' ] as $type ) {
			if ( '' !== $this->get_default_slug( $type ) ) {
				continue;
			}
			$all = $this->get_all( $type );
			if ( ! empty( $all ) ) {
				$this->set_default( $all[0]['slug'], $type );
			}
		}
	}

	/**
	 * Create the built-in default template on disk if it doesn't exist.
	 * Safe to call multiple times.
	 */
	public function maybe_create_default_template(): void {
		$this->maybe_set_default();

		$slug = 'default-product';
		$dir  = self::templates_dir() . $slug . '/';

		if ( is_dir( $dir ) && file_exists( $dir . 'template.json' ) ) {
			return;
		}

		$this->scaffold_template( $slug, 'Default Product Template', 'product', true );
	}

	// =========================================================================
	// Resolution — which template to use for a product
	// =========================================================================

	/**
	 * Resolve the template slug for a product ID.
	 *
	 * Priority: product-specific → global default → null
	 *
	 * @param  int $product_id
	 * @return string|null Template slug or null if nothing found.
	 */
	public function resolve_for_product( int $product_id ): ?string {
		// 1. Product-specific.
		$slug = (string) get_post_meta( $product_id, '_rj_template_slug', true );

		if ( $slug && $this->get_meta( $slug ) ) {
			return $slug;
		}

		// 2. Global default.
		$default = $this->get_default_slug( 'product' );

		if ( $default && $this->get_meta( $default ) ) {
			return $default;
		}

		return null;
	}

	/**
	 * Return the absolute path to a template file.
	 *
	 * @param  string $slug
	 * @param  string $file  e.g. 'content.php', 'hooks.php'
	 * @return string|null
	 */
	public function get_template_file( string $slug, string $file ): ?string {
		$path = self::templates_dir() . $slug . '/' . $file;
		return file_exists( $path ) ? $path : null;
	}

	// =========================================================================
	// Frontend hooks
	// =========================================================================

	public function register_hooks(): void {
		add_filter( 'woocommerce_locate_template', [ $this, 'locate_template' ], 10, 3 );
		add_action( 'woocommerce_before_single_product', [ $this, 'apply_product_hooks' ], 1 );
		// Enqueue template assets.
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_template_assets' ] );
	}

	/**
	 * Enqueue CSS/JS from the active template's assets/ directory.
	 */
	public function enqueue_template_assets(): void {
		if ( ! is_product() ) {
			return;
		}

		global $post;
		$slug = $this->resolve_for_product( (int) $post->ID );

		if ( ! $slug ) {
			return;
		}

		$base_path = self::templates_dir() . $slug . '/assets/';
		$base_url  = RJT_URL . 'templates/' . $slug . '/assets/';
		$meta      = $this->get_meta( $slug );
		$ver       = $meta ? $meta['version'] : RJT_VERSION;

		if ( file_exists( $base_path . 'style.css' ) ) {
			wp_enqueue_style( 'rjt-tpl-' . $slug, $base_url . 'style.css', [], $ver );
		}
		if ( file_exists( $base_path . 'script.js' ) ) {
			wp_enqueue_script( 'rjt-tpl-' . $slug, $base_url . 'script.js', [ 'jquery' ], $ver, true );
		}
	}

	/**
	 * Load the template's hooks.php to apply its WC hook modifications.
	 * Called on woocommerce_before_single_product (before any WC output).
	 */
	public function apply_product_hooks(): void {
		global $post;

		if ( ! $post ) {
			return;
		}

		$slug      = $this->resolve_for_product( (int) $post->ID );
		$hooks_php = $slug ? $this->get_template_file( $slug, 'hooks.php' ) : null;

		if ( $hooks_php ) {
			try {
				require_once $hooks_php;
			} catch ( \Throwable $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'RockyJam Templates: hooks.php error for template [' . $slug . ']: ' . $e->getMessage() );
				}
			}
		}
	}

	/**
	 * Override WC template files with ours from content.php.
	 * We only intercept single-product/content.php.
	 *
	 * @param  string $template      Located template file path.
	 * @param  string $template_name Template name (relative path).
	 * @param  string $template_path Template path (theme override dir).
	 * @return string
	 */
	public function locate_template( string $template, string $template_name, string $template_path ): string {
		if ( ! is_product() && ! is_product_category() ) {
			return $template;
		}

		global $post;
		$slug = $this->resolve_for_product( (int) ( $post->ID ?? 0 ) );

		if ( ! $slug ) {
			return $template;
		}

		// 1. Legacy: content.php directly in template root.
		if ( 'single-product/content.php' === $template_name ) {
			$content_php = $this->get_template_file( $slug, 'content.php' );
			if ( $content_php ) {
				return $content_php;
			}
		}

		// 2. Per-template WC overrides: templates/{slug}/overrides/woocommerce/{template_name}
		$override = self::templates_dir() . $slug . '/overrides/woocommerce/' . $template_name;
		if ( file_exists( $override ) ) {
			return $override;
		}

		return $template;
	}

	// =========================================================================
	// CRUD — create / update / delete on disk
	// =========================================================================

	/**
	 * Create or update a template folder on disk.
	 *
	 * @param  array{slug?:string,name:string,type:string,description?:string,author?:string,version?:string} $data
	 * @param  bool  $is_new   True when creating, false when updating metadata only.
	 * @return string|\WP_Error  Slug on success.
	 */
	public function save( array $data, bool $is_new = false ) {
		$slug = sanitize_title( $data['slug'] ?? '' );
		$name = sanitize_text_field( $data['name'] ?? '' );
		$type = in_array( $data['type'] ?? '', [ 'product', 'category' ], true )
			? $data['type'] : 'product';

		if ( empty( $name ) ) {
			return new \WP_Error( 'empty_name', __( 'Template name is required.', 'rockyjam-templates' ) );
		}

		if ( empty( $slug ) ) {
			return new \WP_Error( 'empty_slug', __( 'Template slug is required.', 'rockyjam-templates' ) );
		}

		$dir = self::templates_dir() . $slug . '/';

		if ( $is_new && is_dir( $dir ) ) {
			return new \WP_Error( 'exists', __( 'A template with this slug already exists.', 'rockyjam-templates' ) );
		}

		if ( $is_new ) {
			$result = $this->scaffold_template( $slug, $name, $type, false );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		// Always update template.json with latest metadata.
		$json = [
			'name'        => $name,
			'type'        => $type,
			'version'     => sanitize_text_field( $data['version'] ?? '1.0.0' ),
			'description' => sanitize_textarea_field( $data['description'] ?? '' ),
			'author'      => sanitize_text_field( $data['author'] ?? '' ),
		];

		file_put_contents( $dir . 'template.json', wp_json_encode( $json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );

		// Handle default flag.
		if ( ! empty( $data['is_default'] ) ) {
			$this->set_default( $slug, $type );
		}

		return $slug;
	}

	/**
	 * Delete a template directory. Refuses if it's the only template.
	 *
	 * @param  string $slug
	 * @return true|\WP_Error
	 */
	public function delete( string $slug ) {
		$slug = sanitize_title( $slug );
		$dir  = self::templates_dir() . $slug . '/';

		if ( ! is_dir( $dir ) ) {
			return new \WP_Error( 'not_found', __( 'Template not found.', 'rockyjam-templates' ) );
		}

		$meta = $this->get_meta( $slug );
		$type = $meta['type'] ?? 'product';

		// Refuse to delete if it's the last template of this type.
		$others = array_filter(
			$this->get_all( $type ),
			fn( $t ) => $t['slug'] !== $slug
		);

		if ( empty( $others ) ) {
			return new \WP_Error(
				'last_template',
				__( 'Cannot delete the only template. Create another template first.', 'rockyjam-templates' )
			);
		}

		$this->rmdir_recursive( $dir );

		// If this was the default, reassign to the next available.
		if ( $this->get_default_slug( $type ) === $slug ) {
			$next = array_values( $others )[0];
			$this->set_default( $next['slug'], $type );
		}

		// Remove assignment from products.
		$this->detach_from_products( $slug );

		return true;
	}

	// =========================================================================
	// Scaffold — create all files for a new template
	// =========================================================================

	/**
	 * Create the full directory structure for a new template.
	 *
	 * @param  string $slug
	 * @param  string $name
	 * @param  string $type  'product' | 'category'
	 * @param  bool   $is_default
	 * @return string|\WP_Error  Slug on success.
	 */
	private function scaffold_template( string $slug, string $name, string $type, bool $is_default ) {
		$dir = self::templates_dir() . $slug . '/';

		if ( ! wp_mkdir_p( $dir . 'assets/' ) ) {
			return new \WP_Error( 'mkdir', __( 'Could not create template directory.', 'rockyjam-templates' ) );
		}

		// ---- template.json ----
		$json = [
			'name'        => $name,
			'type'        => $type,
			'version'     => '1.0.0',
			'description' => '',
			'author'      => '',
		];
		file_put_contents( $dir . 'template.json', wp_json_encode( $json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );

		// ---- hooks.php ----
		$hooks = $this->generate_hooks_php( $name, $type );
		file_put_contents( $dir . 'hooks.php', $hooks );

		// ---- content.php ----
		$content = $this->generate_content_php( $name, $type );
		file_put_contents( $dir . 'content.php', $content );

		// ---- functions.php ----
		$functions = $this->generate_functions_php( $name, $slug );
		file_put_contents( $dir . 'functions.php', $functions );

		// ---- assets/style.css ----
		file_put_contents( $dir . 'assets/style.css', $this->generate_style_css( $name, $slug ) );

		// ---- assets/script.js ----
		file_put_contents( $dir . 'assets/script.js', $this->generate_script_js( $name, $slug ) );

		if ( $is_default ) {
			$this->set_default( $slug, $type );
		}

		return $slug;
	}

	// =========================================================================
	// File generators
	// =========================================================================

	private function generate_hooks_php( string $name, string $type ): string {
		$out  = '<?php' . "\n";
		$out .= '/**' . "\n";
		$out .= ' * ' . $name . ' — WooCommerce hook overrides.' . "\n";
		$out .= ' *' . "\n";
		$out .= ' * This file is loaded on woocommerce_before_single_product (priority 1).' . "\n";
		$out .= ' * Use remove_action() to strip default WC output, then add_action() to' . "\n";
		$out .= ' * insert your own callbacks at the desired priority.' . "\n";
		$out .= ' *' . "\n";
		$out .= ' * Reference: https://woocommerce.com/document/conditional-tags/' . "\n";
		$out .= ' * Hook list: https://woocommerce.com/document/hooks-and-filters/' . "\n";
		$out .= ' */' . "\n\n";
		$out .= "if ( ! defined( 'ABSPATH' ) ) {\n\texit;\n}\n\n";

		if ( 'product' === $type ) {
			$out .= '// -----------------------------------------------------------------------' . "\n";
			$out .= '// Load this template\'s helper functions.' . "\n";
			$out .= '// -----------------------------------------------------------------------' . "\n";
			$out .= "require_once __DIR__ . '/functions.php';\n\n";

			$out .= '// -----------------------------------------------------------------------' . "\n";
			$out .= '// REMOVE default WooCommerce hooks (uncomment what you want to disable).' . "\n";
			$out .= '// -----------------------------------------------------------------------' . "\n\n";
			$out .= "// remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_title',        5  );\n";
			$out .= "// remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating',       10 );\n";
			$out .= "// remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price',        10 );\n";
			$out .= "// remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt',      20 );\n";
			$out .= "// remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart',  30 );\n";
			$out .= "// remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta',         40 );\n";
			$out .= "// remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_sharing',      50 );\n";
			$out .= "// remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_images',   20 );\n";
			$out .= "// remove_action( 'woocommerce_after_single_product_summary',  'woocommerce_output_product_data_tabs', 10 );\n";
			$out .= "// remove_action( 'woocommerce_after_single_product_summary',  'woocommerce_upsell_display',         15 );\n";
			$out .= "// remove_action( 'woocommerce_after_single_product_summary',  'woocommerce_output_related_products', 20 );\n\n";

			$out .= '// -----------------------------------------------------------------------' . "\n";
			$out .= '// ADD / RE-PRIORITISE your own hooks.' . "\n";
			$out .= '// -----------------------------------------------------------------------' . "\n\n";
			$out .= "// add_action( 'woocommerce_single_product_summary', 'my_custom_callback', 15 );\n\n";

			$out .= '// -----------------------------------------------------------------------' . "\n";
			$out .= '// CHANGE WooCommerce filter output.' . "\n";
			$out .= '// -----------------------------------------------------------------------' . "\n\n";
			$out .= "// add_filter( 'woocommerce_product_tabs', function( \$tabs ) {\n";
			$out .= "//     unset( \$tabs['reviews'] ); // hide reviews tab\n";
			$out .= "//     return \$tabs;\n";
			$out .= "// } );\n";
		}

		return $out;
	}

	private function generate_content_php( string $name, string $type ): string {
		$out  = '<?php' . "\n";
		$out .= '/**' . "\n";
		$out .= ' * ' . $name . ' — product page markup.' . "\n";
		$out .= ' *' . "\n";
		$out .= ' * This file replaces woocommerce/templates/single-product/content.php.' . "\n";
		$out .= ' * Variables available: $product (WC_Product)' . "\n";
		$out .= ' */' . "\n\n";
		$out .= "if ( ! defined( 'ABSPATH' ) ) {\n\texit;\n}\n\n";
		$out .= "global \$product;\n\n";
		$out .= "do_action( 'woocommerce_before_single_product' );\n\n";
		$out .= "if ( post_password_required() ) {\n";
		$out .= "\techo get_the_password_form(); // WPCS: XSS ok.\n";
		$out .= "\treturn;\n}\n";
		$out .= '?>' . "\n";
		$out .= '<div id="product-<?php the_ID(); ?>" <?php wc_product_class( \'\', $product ); ?>>' . "\n\n";
		$out .= "\t" . '<?php do_action( \'woocommerce_before_single_product_summary\' ); ?>' . "\n\n";
		$out .= "\t" . '<div class="summary entry-summary">' . "\n";
		$out .= "\t\t" . '<?php do_action( \'woocommerce_single_product_summary\' ); ?>' . "\n";
		$out .= "\t" . '</div>' . "\n\n";
		$out .= "\t" . '<?php do_action( \'woocommerce_after_single_product_summary\' ); ?>' . "\n\n";
		$out .= '</div>' . "\n\n";
		$out .= '<?php do_action( \'woocommerce_after_single_product\' ); ?>' . "\n";

		return $out;
	}

	private function generate_functions_php( string $name, string $slug ): string {
		$prefix = 'rjt_' . str_replace( '-', '_', $slug );
		$out  = '<?php' . "\n";
		$out .= '/**' . "\n";
		$out .= ' * ' . $name . ' — helper functions.' . "\n";
		$out .= ' *' . "\n";
		$out .= ' * Loaded by hooks.php. Define template-specific helper functions here.' . "\n";
		$out .= ' */' . "\n\n";
		$out .= "if ( ! defined( 'ABSPATH' ) ) {\n\texit;\n}\n\n";
		$out .= '/**' . "\n";
		$out .= ' * Example helper for the ' . $name . ' template.' . "\n";
		$out .= ' * Call it from hooks.php callbacks or from content.php.' . "\n";
		$out .= ' *' . "\n";
		$out .= ' * @param  \\WC_Product $product' . "\n";
		$out .= ' * @return void' . "\n";
		$out .= ' */' . "\n";
		$out .= 'function ' . $prefix . '_render_badge( \\WC_Product $product ): void {' . "\n";
		$out .= "\tif ( \$product->is_on_sale() ) {\n";
		$out .= "\t\techo '<span class=\"rjt-badge rjt-badge--sale\">' . esc_html__( 'Sale', 'rockyjam-templates' ) . '</span>';\n";
		$out .= "\t}\n";
		$out .= "}\n";

		return $out;
	}

	private function generate_style_css( string $name, string $slug ): string {
		$out  = '/**' . "\n";
		$out .= ' * ' . $name . ' — frontend styles.' . "\n";
		$out .= ' */' . "\n\n";
		$out .= '.woocommerce div.product {' . "\n";
		$out .= "\t/* Add your product page styles here */" . "\n";
		$out .= "}\n\n";
		$out .= '.rjt-badge {' . "\n";
		$out .= "\tdisplay: inline-block;\n";
		$out .= "\tpadding: 2px 10px;\n";
		$out .= "\tborder-radius: 3px;\n";
		$out .= "\tfont-size: 12px;\n";
		$out .= "\tfont-weight: 600;\n";
		$out .= "}\n\n";
		$out .= '.rjt-badge--sale {' . "\n";
		$out .= "\tbackground: #e9143e;\n";
		$out .= "\tcolor: #fff;\n";
		$out .= "}\n";

		return $out;
	}

	private function generate_script_js( string $name, string $slug ): string {
		$out  = '/**' . "\n";
		$out .= ' * ' . $name . ' — frontend scripts.' . "\n";
		$out .= ' */' . "\n";
		$out .= '( function ( $ ) {' . "\n";
		$out .= "\t'use strict';\n\n";
		$out .= "\t\$( document ).ready( function () {\n";
		$out .= "\t\t// Add your product page scripts here.\n";
		$out .= "\t} );\n";
		$out .= '} )( jQuery );' . "\n";

		return $out;
	}


	// =========================================================================
	// Overrides — per-template WC template overrides
	// =========================================================================

	/**
	 * Return the directory for WC overrides of a given template slug.
	 */
	public static function overrides_dir( string $slug ): string {
		return self::templates_dir() . $slug . '/overrides/woocommerce/';
	}

	/**
	 * Load the WC templates registry.
	 *
	 * @return array[]  Each entry: {path, label, description, featured}
	 */
	public static function load_wc_templates_registry(): array {
		$file = RJT_PATH . 'data/wc-templates-registry.json';
		if ( ! file_exists( $file ) ) {
			return [];
		}
		$data = json_decode( file_get_contents( $file ), true );
		return $data['templates'] ?? [];
	}

	/**
	 * List overrides active for a template slug.
	 * Returns array of WC template paths (relative), e.g. ['single-product/price.php']
	 *
	 * @return string[]
	 */
	public function list_overrides( string $slug ): array {
		$dir  = self::overrides_dir( $slug );
		if ( ! is_dir( $dir ) ) {
			return [];
		}
		$result = [];
		$iter   = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $iter as $file ) {
			if ( $file->isFile() && str_ends_with( $file->getFilename(), '.php' ) ) {
				$rel = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $dir ) ) );
				$result[] = ltrim( $rel, '/' );
			}
		}
		sort( $result );
		return $result;
	}

	/**
	 * Get the content of an override file, or the original WC template content as default.
	 *
	 * @param  string $slug          Template slug.
	 * @param  string $wc_tpl_path   WC template relative path, e.g. 'single-product/price.php'
	 * @return array{content:string, exists:bool}
	 */
	public function get_override_content( string $slug, string $wc_tpl_path ): array {
		$override_file = self::overrides_dir( $slug ) . $wc_tpl_path;

		if ( file_exists( $override_file ) ) {
			return [ 'content' => file_get_contents( $override_file ), 'exists' => true ];
		}

		// Return WC default as starting point.
		$wc_default = WC()->plugin_path() . '/templates/' . $wc_tpl_path;
		$content    = file_exists( $wc_default )
			? file_get_contents( $wc_default )
			: "<?php\n// WC template: {$wc_tpl_path}\n// WooCommerce default not found — write your override here.\n";

		return [ 'content' => $content, 'exists' => false ];
	}

	/**
	 * Save (create or update) an override file.
	 *
	 * @param  string $slug
	 * @param  string $wc_tpl_path   e.g. 'single-product/price.php'
	 * @param  string $php_content   Raw PHP content (admin-only, manage_options required).
	 * @return true|\WP_Error
	 */
	public function save_override( string $slug, string $wc_tpl_path, string $php_content ) {
		// Validate path — only allow alphanumeric, hyphens, underscores, slashes, dots.
		if ( ! preg_match( '#^[a-z0-9/_\-]+\.php$#', $wc_tpl_path ) ) {
			return new \WP_Error( 'invalid_path', __( 'Invalid template path.', 'rockyjam-templates' ) );
		}

		$dir = self::overrides_dir( $slug );

		if ( ! wp_mkdir_p( dirname( $dir . $wc_tpl_path ) ) ) {
			return new \WP_Error( 'mkdir', __( 'Could not create override directory.', 'rockyjam-templates' ) );
		}

		if ( false === file_put_contents( $dir . $wc_tpl_path, $php_content ) ) {
			return new \WP_Error( 'write', __( 'Could not write override file.', 'rockyjam-templates' ) );
		}

		return true;
	}

	/**
	 * Delete an override file (revert to WC default).
	 *
	 * @param  string $slug
	 * @param  string $wc_tpl_path
	 * @return true|\WP_Error
	 */
	public function delete_override( string $slug, string $wc_tpl_path ) {
		if ( ! preg_match( '#^[a-z0-9/_\-]+\.php$#', $wc_tpl_path ) ) {
			return new \WP_Error( 'invalid_path', __( 'Invalid template path.', 'rockyjam-templates' ) );
		}

		$file = self::overrides_dir( $slug ) . $wc_tpl_path;

		if ( ! file_exists( $file ) ) {
			return new \WP_Error( 'not_found', __( 'Override not found.', 'rockyjam-templates' ) );
		}

		wp_delete_file( $file );

		// Remove empty parent directories (up to overrides/woocommerce/).
		$dir = dirname( $file );
		$base = rtrim( self::overrides_dir( $slug ), '/' );
		while ( $dir !== $base && is_dir( $dir ) && count( scandir( $dir ) ) === 2 ) {
			rmdir( $dir );
			$dir = dirname( $dir );
		}

		return true;
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	private function rmdir_recursive( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		foreach ( array_diff( (array) scandir( $dir ), [ '.', '..' ] ) as $item ) {
			$path = rtrim( $dir, '/' ) . '/' . $item;
			is_dir( $path ) ? $this->rmdir_recursive( $path . '/' ) : wp_delete_file( $path );
		}
		rmdir( $dir );
	}

	private function detach_from_products( string $slug ): void {
		global $wpdb;
		$wpdb->delete(
			$wpdb->postmeta,
			[ 'meta_key' => '_rj_template_slug', 'meta_value' => $slug ],
			[ '%s', '%s' ]
		);
	}
}
