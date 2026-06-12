<?php
/**
 * Related Products — Rogue Product Template override.
 *
 * Replaces the default WooCommerce related.php with custom markup
 * that uses .rj-related-* classes, fully controlled by our CSS.
 * Avoids all float/width conflicts from the parent theme.
 *
 * @see woocommerce/templates/single-product/related.php
 */

defined( 'ABSPATH' ) || exit;

if ( ! $related_products ) {
	return;
}

$heading = apply_filters( 'woocommerce_product_related_products_heading', __( 'Related products', 'woocommerce' ) );
?>

<section class="rj-related">
	<?php if ( $heading ) : ?>
		<h2 class="rj-related__title"><?php echo esc_html( $heading ); ?></h2>
	<?php endif; ?>

	<ul class="rj-related__grid">
		<?php foreach ( $related_products as $related_product ) : ?>
			<?php
			$post_object = get_post( $related_product->get_id() );
			setup_postdata( $GLOBALS['post'] = $post_object ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$product = wc_get_product( $related_product->get_id() );
			if ( ! $product ) { continue; }

			$permalink   = get_permalink( $product->get_id() );
			$image_id    = $product->get_image_id();
			$image_url   = $image_id
				? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' )
				: wc_placeholder_img_src( 'woocommerce_thumbnail' );
			$image_alt   = $image_id ? get_post_meta( $image_id, '_wp_attachment_image_alt', true ) : '';
			$title       = $product->get_name();
			$price_html  = $product->get_price_html();
			?>
			<li class="rj-related__card">
				<a href="<?php echo esc_url( $permalink ); ?>" class="rj-related__card-link" aria-label="<?php echo esc_attr( $title ); ?>">
					<div class="rj-related__img-wrap">
						<img
							src="<?php echo esc_url( $image_url ); ?>"
							alt="<?php echo esc_attr( $image_alt ?: $title ); ?>"
							class="rj-related__img"
							loading="lazy"
							decoding="async"
						>
					</div>
				</a>

				<div class="rj-related__body">
					<h3 class="rj-related__name">
						<a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a>
					</h3>

					<?php if ( $price_html ) : ?>
						<div class="rj-related__price"><?php echo $price_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
					<?php endif; ?>
				</div>

				<a href="<?php echo esc_url( $permalink ); ?>" class="rj-related__btn">
					<?php esc_html_e( 'В корзину', 'woocommerce' ); ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</section>

<?php wp_reset_postdata();
