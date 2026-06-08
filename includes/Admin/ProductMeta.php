<?php

namespace RockyJamTemplates\Admin;

use RockyJamTemplates\Core\TemplateManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Metabox for selecting a template in the product editor.
 *
 * @package RockyJamTemplates
 */
class ProductMeta {

	private TemplateManager $manager;

	public function __construct( TemplateManager $manager ) {
		$this->manager = $manager;
	}

	public function register(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		add_action( 'add_meta_boxes',      [ $this, 'add_metabox' ] );
		add_action( 'save_post_product',   [ $this, 'save_meta' ], 10, 2 );
	}

	public function add_metabox(): void {
		add_meta_box(
			'rjt_product_template',
			__( 'Page Template (RockyJam)', 'rockyjam-templates' ),
			[ $this, 'render_metabox' ],
			'product',
			'side',
			'default'
		);
	}

	public function render_metabox( \WP_Post $post ): void {
		wp_nonce_field( 'rjt_product_meta', 'rjt_product_nonce' );

		$current_slug = (string) get_post_meta( $post->ID, '_rj_template_slug', true );
		$templates    = $this->manager->get_all( 'product' );
		$default_slug = $this->manager->get_default_slug( 'product' );

		$default_name = '';
		foreach ( $templates as $t ) {
			if ( $t['slug'] === $default_slug ) {
				$default_name = $t['name'];
				break;
			}
		}

		$default_label = $default_name
			/* translators: %s: template name */
			? sprintf( __( 'Default (%s)', 'rockyjam-templates' ), $default_name )
			: __( 'Default', 'rockyjam-templates' );
		?>
		<div class="rjt-metabox">
			<p class="description" style="margin-bottom:8px;">
				<?php esc_html_e( 'Choose a custom template for this product.', 'rockyjam-templates' ); ?>
			</p>
			<select name="rjt_template_slug" id="rjt_template_slug" class="widefat">
				<option value="" <?php selected( $current_slug, '' ); ?>>
					— <?php echo esc_html( $default_label ); ?> —
				</option>
				<?php foreach ( $templates as $tpl ) : ?>
					<option value="<?php echo esc_attr( $tpl['slug'] ); ?>" <?php selected( $current_slug, $tpl['slug'] ); ?>>
						<?php echo esc_html( $tpl['name'] ); ?>
						<?php if ( $tpl['is_default'] ) : ?>
							(<?php esc_html_e( 'default', 'rockyjam-templates' ); ?>)
						<?php endif; ?>
					</option>
				<?php endforeach; ?>
			</select>
			<p style="margin-top:8px;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=rjt-templates' ) ); ?>" target="_blank" style="font-size:12px;">
					<?php esc_html_e( 'Manage templates ↗', 'rockyjam-templates' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	public function save_meta( int $post_id, \WP_Post $post ): void {
		$nonce = sanitize_text_field( wp_unslash( $_POST['rjt_product_nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'rjt_product_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$slug = sanitize_title( $_POST['rjt_template_slug'] ?? '' );

		// Validate the slug actually exists on disk.
		if ( $slug && ! $this->manager->get_meta( $slug ) ) {
			$slug = '';
		}

		update_post_meta( $post_id, '_rj_template_slug', $slug );
	}
}
