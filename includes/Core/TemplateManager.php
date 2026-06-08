<?php

namespace RockyJamTemplates\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the rj_template Custom Post Type and template resolution.
 *
 * Template types
 * --------------
 * product  — single product page (woocommerce/single-product.php)
 * category — product category archive (woocommerce/taxonomy-product_cat.php)
 *
 * Meta fields on rj_template posts
 * ----------------------------------
 * _rj_template_type     : 'product' | 'category'
 * _rj_template_content  : raw HTML / shortcodes / template tags
 * _rj_is_default        : '1' if this is the site-wide default for its type
 *
 * Meta field on wc_product posts
 * --------------------------------
 * _rj_template_id : post ID of the chosen rj_template (0 = use default)
 *
 * @package RockyJamTemplates
 */
class TemplateManager {

	// ------------------------------------------------------------------
	// CPT registration
	// ------------------------------------------------------------------

	public static function register_cpt(): void {
		register_post_type(
			RJT_CPT,
			[
				'label'               => __( 'Product Templates', 'rockyjam-templates' ),
				'labels'              => [
					'name'          => __( 'Product Templates', 'rockyjam-templates' ),
					'singular_name' => __( 'Product Template', 'rockyjam-templates' ),
					'add_new_item'  => __( 'Add New Template', 'rockyjam-templates' ),
					'edit_item'     => __( 'Edit Template', 'rockyjam-templates' ),
				],
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => false,   // We have our own UI.
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'hierarchical'        => false,
				'supports'            => [ 'title' ],
				'rewrite'             => false,
				'query_var'           => false,
			]
		);
	}

	// ------------------------------------------------------------------
	// Default template
	// ------------------------------------------------------------------

	/**
	 * Create the built-in "Default" template on first activation (or if deleted).
	 */
	public function maybe_create_default_template(): void {
		// Check if a default product template already exists.
		$existing = get_posts( [
			'post_type'      => RJT_CPT,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'meta_query'     => [
				[
					'key'   => '_rj_is_default',
					'value' => '1',
				],
				[
					'key'   => '_rj_template_type',
					'value' => 'product',
				],
			],
			'fields' => 'ids',
		] );

		if ( ! empty( $existing ) ) {
			return;
		}

		$default_content = $this->get_default_template_content();

		$post_id = wp_insert_post( [
			'post_title'   => __( 'Default Product Template', 'rockyjam-templates' ),
			'post_type'    => RJT_CPT,
			'post_status'  => 'publish',
			'post_content' => '',
		] );

		if ( $post_id && ! is_wp_error( $post_id ) ) {
			update_post_meta( $post_id, '_rj_template_type',    'product' );
			update_post_meta( $post_id, '_rj_template_content', $default_content );
			update_post_meta( $post_id, '_rj_is_default',       '1' );
		}
	}

	// ------------------------------------------------------------------
	// Frontend hooks
	// ------------------------------------------------------------------

	public function register_hooks(): void {
		// Override WooCommerce single product template.
		add_filter( 'wc_get_template', [ $this, 'filter_product_template' ], 10, 2 );
	}

	/**
	 * Swap WooCommerce's single-product/content.php with our custom template
	 * when the current product has one assigned (or a global default exists).
	 *
	 * @param string $template Full path to the template file WC is about to load.
	 * @param string $template_name Template slug (e.g. "single-product/content.php").
	 * @return string
	 */
	public function filter_product_template( string $template, string $template_name ): string {
		if ( 'single-product/content.php' !== $template_name ) {
			return $template;
		}

		global $product;

		if ( ! $product instanceof \WC_Product ) {
			return $template;
		}

		$tpl = $this->resolve_product_template( $product->get_id() );

		if ( ! $tpl ) {
			return $template;
		}

		// Write content to a temp file and return its path.
		// (Cleaner alternative: use output buffer in a WC action.)
		return $this->render_to_temp_file( $tpl, $product );
	}

	// ------------------------------------------------------------------
	// Resolution
	// ------------------------------------------------------------------

	/**
	 * Find the template to use for a product.
	 *
	 * Priority: product-specific → global default → null
	 *
	 * @param  int $product_id
	 * @return array|null Array with keys: id, title, content. Null if nothing found.
	 */
	public function resolve_product_template( int $product_id ): ?array {
		// 1. Product-specific template.
		$template_id = (int) get_post_meta( $product_id, '_rj_template_id', true );

		if ( $template_id > 0 ) {
			$data = $this->get_template_data( $template_id );
			if ( $data ) {
				return $data;
			}
		}

		// 2. Global default for product type.
		return $this->get_default_template( 'product' );
	}

	/**
	 * @param  int $template_id Post ID of rj_template.
	 * @return array|null
	 */
	public function get_template_data( int $template_id ): ?array {
		$post = get_post( $template_id );

		if ( ! $post || RJT_CPT !== $post->post_type || 'publish' !== $post->post_status ) {
			return null;
		}

		return [
			'id'         => $post->ID,
			'title'      => $post->post_title,
			'content'    => get_post_meta( $post->ID, '_rj_template_content', true ),
			'type'       => get_post_meta( $post->ID, '_rj_template_type', true ),
			'is_default' => (bool) get_post_meta( $post->ID, '_rj_is_default', true ),
		];
	}

	/**
	 * @param  string $type 'product' or 'category'.
	 * @return array|null
	 */
	public function get_default_template( string $type ): ?array {
		$posts = get_posts( [
			'post_type'      => RJT_CPT,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'meta_query'     => [
				'relation' => 'AND',
				[
					'key'   => '_rj_is_default',
					'value' => '1',
				],
				[
					'key'   => '_rj_template_type',
					'value' => $type,
				],
			],
		] );

		if ( empty( $posts ) ) {
			return null;
		}

		return $this->get_template_data( $posts[0]->ID );
	}

	/**
	 * Return all templates of a given type.
	 *
	 * @param  string $type 'product' | 'category' | '' (all)
	 * @return array[]
	 */
	public function get_all_templates( string $type = '' ): array {
		$args = [
			'post_type'      => RJT_CPT,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		];

		if ( $type ) {
			$args['meta_query'] = [ [
				'key'   => '_rj_template_type',
				'value' => $type,
			] ];
		}

		$posts = get_posts( $args );
		$list  = [];

		foreach ( $posts as $post ) {
			$list[] = [
				'id'         => $post->ID,
				'title'      => $post->post_title,
				'content'    => get_post_meta( $post->ID, '_rj_template_content', true ),
				'type'       => get_post_meta( $post->ID, '_rj_template_type', true ),
				'is_default' => (bool) get_post_meta( $post->ID, '_rj_is_default', true ),
			];
		}

		return $list;
	}

	// ------------------------------------------------------------------
	// CRUD
	// ------------------------------------------------------------------

	/**
	 * Create or update a template.
	 *
	 * @param  array $data Keys: title, type, content, is_default. Add 'id' to update.
	 * @return int|\WP_Error Post ID on success.
	 */
	public function save_template( array $data ) {
		$title      = sanitize_text_field( $data['title'] ?? '' );
		$type       = in_array( $data['type'] ?? '', [ 'product', 'category' ], true )
			? $data['type']
			: 'product';
		$content    = wp_kses_post( $data['content'] ?? '' );
		$is_default = ! empty( $data['is_default'] );
		$id         = isset( $data['id'] ) ? (int) $data['id'] : 0;

		if ( empty( $title ) ) {
			return new \WP_Error( 'empty_title', __( 'Template title is required.', 'rockyjam-templates' ) );
		}

		$post_data = [
			'post_title'   => $title,
			'post_type'    => RJT_CPT,
			'post_status'  => 'publish',
			'post_content' => '',
		];

		if ( $id > 0 ) {
			$post_data['ID'] = $id;
			$post_id = wp_update_post( $post_data, true );
		} else {
			$post_id = wp_insert_post( $post_data, true );
		}

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, '_rj_template_type',    $type );
		update_post_meta( $post_id, '_rj_template_content', $content );
		update_post_meta( $post_id, '_rj_is_default',       $is_default ? '1' : '' );

		// If this is set as default — unset all others of the same type.
		if ( $is_default ) {
			$this->clear_other_defaults( $post_id, $type );
		}

		return $post_id;
	}

	/**
	 * Delete a template. Refuses to delete the last default template.
	 *
	 * @param  int $template_id
	 * @return true|\WP_Error
	 */
	public function delete_template( int $template_id ) {
		$post = get_post( $template_id );

		if ( ! $post || RJT_CPT !== $post->post_type ) {
			return new \WP_Error( 'not_found', __( 'Template not found.', 'rockyjam-templates' ) );
		}

		$is_default = (bool) get_post_meta( $template_id, '_rj_is_default', true );
		$type       = get_post_meta( $template_id, '_rj_template_type', true );

		// Don't allow deleting the only default.
		if ( $is_default ) {
			$others = get_posts( [
				'post_type'      => RJT_CPT,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'post__not_in'   => [ $template_id ],
				'meta_query'     => [
					[ 'key' => '_rj_template_type', 'value' => $type ],
				],
				'fields' => 'ids',
			] );

			if ( empty( $others ) ) {
				return new \WP_Error(
					'last_default',
					__( 'Cannot delete the only template. Create another template first.', 'rockyjam-templates' )
				);
			}

			// Auto-assign default to the next available template.
			$next_id = $others[0];
			update_post_meta( $next_id, '_rj_is_default', '1' );
		}

		// Remove template assignment from all products that used this template.
		$this->detach_from_products( $template_id );

		wp_delete_post( $template_id, true );

		return true;
	}

	/**
	 * Set the default template for a type (unsets all others).
	 *
	 * @param  int    $template_id
	 * @param  string $type
	 */
	public function set_as_default( int $template_id, string $type ): void {
		update_post_meta( $template_id, '_rj_is_default', '1' );
		$this->clear_other_defaults( $template_id, $type );
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	/**
	 * Unset _rj_is_default on all templates of $type except $except_id.
	 */
	private function clear_other_defaults( int $except_id, string $type ): void {
		$others = get_posts( [
			'post_type'      => RJT_CPT,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'post__not_in'   => [ $except_id ],
			'meta_query'     => [
				[ 'key' => '_rj_template_type', 'value' => $type ],
				[ 'key' => '_rj_is_default', 'value' => '1' ],
			],
			'fields' => 'ids',
		] );

		foreach ( $others as $id ) {
			update_post_meta( $id, '_rj_is_default', '' );
		}
	}

	/**
	 * Remove _rj_template_id from all products that reference the deleted template.
	 */
	private function detach_from_products( int $template_id ): void {
		global $wpdb;
		$wpdb->delete(
			$wpdb->postmeta,
			[
				'meta_key'   => '_rj_template_id',
				'meta_value' => $template_id,
			],
			[ '%s', '%d' ]
		);
	}

	/**
	 * Render template content to a temporary PHP file and return its path.
	 * The temp file sets up $product in scope before echoing the content.
	 *
	 * @param  array       $tpl     Template data array.
	 * @param  \WC_Product $product Current WooCommerce product.
	 * @return string       Path to temp file.
	 */
	private function render_to_temp_file( array $tpl, \WC_Product $product ): string {
		$upload_dir = wp_upload_dir();
		$tmp_dir    = trailingslashit( $upload_dir['basedir'] ) . 'rjt-cache/';

		if ( ! is_dir( $tmp_dir ) ) {
			wp_mkdir_p( $tmp_dir );
			// Protect the directory.
			file_put_contents( $tmp_dir . 'index.php', '<?php // Silence' );
		}

		$file = $tmp_dir . 'tpl-' . $tpl['id'] . '.php';

		// Regenerate if missing.
		if ( ! file_exists( $file ) ) {
			$php  = "<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>\n";
			$php .= do_shortcode( $tpl['content'] );
			file_put_contents( $file, $php );
		}

		return $file;
	}

	/**
	 * Default template HTML scaffold (shown in editor as starting point).
	 */
	private function get_default_template_content(): string {
		return '<div class="rjt-product">' . "\n"
			. "\t" . '<div class="rjt-product__gallery">' . "\n"
			. "\t\t" . '[rjt_product_gallery]' . "\n"
			. "\t" . '</div>' . "\n"
			. "\t" . '<div class="rjt-product__summary">' . "\n"
			. "\t\t" . '<h1 class="product_title">[rjt_product_title]</h1>' . "\n"
			. "\t\t" . '[rjt_product_price]' . "\n"
			. "\t\t" . '[rjt_product_description]' . "\n"
			. "\t\t" . '[rjt_add_to_cart]' . "\n"
			. "\t" . '</div>' . "\n"
			. '</div>';
	}
}
