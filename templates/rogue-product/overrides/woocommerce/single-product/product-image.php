<?php
/**
 * Rogue Product Template — override of WooCommerce single-product/product-image.php
 * Replaces WooCommerce Flexslider with a custom lightweight gallery.
 *
 * Main image: .rj-main-image (square, 100% width of parent)
 * Thumbnails: .rj-thumb (6 per row, square, click to swap main image)
 */

defined( 'ABSPATH' ) || exit;

global $product;

$post_thumbnail_id  = $product->get_image_id();
$attachment_ids     = $product->get_gallery_image_ids();
$all_ids            = array_merge( [ $post_thumbnail_id ], $attachment_ids );

$main_src = $post_thumbnail_id
    ? wp_get_attachment_image_src( $post_thumbnail_id, 'woocommerce_single' )
    : [];
$main_url  = ! empty( $main_src[0] ) ? esc_url( $main_src[0] ) : wc_placeholder_img_src( 'woocommerce_single' );
$main_alt  = $post_thumbnail_id ? esc_attr( get_post_meta( $post_thumbnail_id, '_wp_attachment_image_alt', true ) ) : esc_attr( get_the_title() );
?>
<div class="rj-gallery-wrap" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>">

    <!-- ===== MAIN IMAGE ===== -->
    <div class="rj-main-image-wrap">
        <img
            id="rj-main-img"
            class="rj-main-img"
            src="<?php echo $main_url; ?>"
            alt="<?php echo $main_alt; ?>"
        />
        <?php woocommerce_show_product_sale_flash(); ?>
    </div>

    <!-- ===== THUMBNAILS ===== -->
    <?php if ( count( $all_ids ) > 1 ) : ?>
    <ul class="rj-thumbs">
        <?php foreach ( $all_ids as $index => $att_id ) :
            $thumb_src = wp_get_attachment_image_src( $att_id, 'woocommerce_thumbnail' );
            $full_src  = wp_get_attachment_image_src( $att_id, 'woocommerce_single' );
            if ( ! $thumb_src || ! $full_src ) continue;
            $alt = esc_attr( get_post_meta( $att_id, '_wp_attachment_image_alt', true ) );
        ?>
        <li class="rj-thumb-item<?php echo $index === 0 ? ' active' : ''; ?>">
            <img
                class="rj-thumb"
                src="<?php echo esc_url( $thumb_src[0] ); ?>"
                data-full="<?php echo esc_url( $full_src[0] ); ?>"
                data-alt="<?php echo $alt; ?>"
                alt="<?php echo $alt; ?>"
            />
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>

</div><!-- .rj-gallery-wrap -->
