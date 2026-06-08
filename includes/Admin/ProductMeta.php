<?php

namespace RockyJamTemplates\Admin;

use RockyJamTemplates\Core\TemplateManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds a "Template" metabox to the WooCommerce product editor.
 *
 * Supports both Classic Editor and the new Block Editor (Product Editor).
 *
 * @package RockyJamTemplates
 */
class ProductMeta {

	private TemplateManager $manager;

	public function __construct( TemplateManager $manager ) {
		$this->manager = $manager;
	}

	public function register(): void {
		add_action( 'add_meta_boxes', [ $this, 'add_metabox' ] );
		add_action( 'save_post_product', [ $this, 'save_meta' ], 10, 2 );
	}

	// ------------------------------------------------------------------
	// Metabox
	// ------------------------------------------------------------------

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

		$current_id = (int) get_post_meta( $post->ID, '_rj_template_id', true );
		$templates  = $this->manager->get_all_templates( 'product' );
		$default    = $this->manager->get_default_template( 'product' );

		$default_label = $default
			/* translators: %s: template name */
			? sprintf( __( 'Default (%s)', 'rockyjam-templates' ), $default['title'] )
			: __( 'Default', 'rockyjam-templates' );
		?>
		<div class="rjt-metabox">
			<p class="rjt-metabox__desc description">
				<?php esc_html_e( 'Choose a custom template for this product. Leave on "Default" to use the global default.', 'rockyjam-templates' ); ?>
			</p>

			<select name="rjt_template_id" id="rjt_template_id" class="widefat">
				<option value="0" <?php selected( $current_id, 0 ); ?>>
					— <?php echo esc_html( $default_label ); ?> —
				</option>
				<?php foreach ( $templates as $tpl ) : ?>
					<option value="<?php echo esc_attr( $tpl['id'] ); ?>" <?php selected( $current_id, $tpl['id'] ); ?>>
						<?php echo esc_html( $tpl['title'] ); ?>
						<?php if ( $tpl['is_default'] ) : ?>
							(<?php esc_html_e( 'default', 'rockyjam-templates' ); ?>)
						<?php endif; ?>
					</option>
				<?php endforeach; ?>
			</select>

			<?php if ( ! empty( $templates ) ) : ?>
			<p class="rjt-metabox__link">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=rjt-templates' ) ); ?>" target="_blank">
					<?php esc_html_e( 'Manage templates ↗', 'rockyjam-templates' ); ?>
				</a>
			</p>
			<?php endif; ?>
		</div>
		<?php
	}

	// ------------------------------------------------------------------
	// Save
	// ------------------------------------------------------------------

	public function save_meta( int $post_id, \WP_Post $post ): void {
		// Verify nonce.
		$nonce = sanitize_text_field( wp_unslash( $_POST['rjt_product_nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'rjt_product_meta' ) ) {
			return;
		}

		// Bail on autosave / revisions / no permission.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$template_id = (int) ( $_POST['rjt_template_id'] ?? 0 );

		if ( $template_id > 0 ) {
			// Validate the template actually exists and is a product template.
			$tpl = $this->manager->get_template_data( $template_id );
			if ( ! $tpl || 'product' !== $tpl['type'] ) {
				$template_id = 0;
			}
		}

		update_post_meta( $post_id, '_rj_template_id', $template_id );
	}
}
