<?php
/**
 * Review comments template
 *
 * This template is a WooCommerce override for the rogue-product template.
 * It adds star ratings to each individual review in the reviews tab.
 *
 * Based on WooCommerce default: templates/single-product/review.php
 *
 * ВАЖНО: <span> внутри .star-rating должен быть ПУСТЫМ.
 * Звёзды рисуются через CSS ::before псевдоэлементы с шрифтом "WooCommerce".
 * Если внутри <span> есть текстовый контент — псевдоэлементы перекрываются
 * и звёзды не отображаются. Screen-reader текст вынесен отдельно.
 *
 * @package RockyJam_Templates
 */

defined( 'ABSPATH' ) || exit;

$rating = intval( get_comment_meta( $comment->comment_ID, 'rating', true ) );
?>
<li <?php comment_class(); ?> id="li-comment-<?php comment_ID(); ?>">

	<div id="comment-<?php comment_ID(); ?>" class="comment_container">

		<?php echo get_avatar( $comment, apply_filters( 'woocommerce_review_gravatar_size', '60' ), '' ); ?>

		<div class="comment-text">

			<?php if ( ! empty( $rating ) && wc_review_ratings_enabled() ) : ?>
				<div class="star-rating" role="img" aria-label="<?php printf( esc_attr__( 'Rated %d out of 5', 'woocommerce' ), $rating ); ?>">
					<span style="width:<?php echo esc_attr( ( $rating / 5 ) * 100 ); ?>%"></span>
				</div>
			<?php endif; ?>

			<p class="meta">
				<strong class="woocommerce-review__author"><?php comment_author(); ?></strong>
				<?php if ( get_option( 'woocommerce_review_rating_verification_label' ) === 'yes' ) : ?>
					<?php if ( wc_customer_bought_product( $comment->comment_author_email, $comment->user_id, $comment->comment_post_ID ) ) : ?>
						<em class="woocommerce-review__verified verified"><?php esc_html_e( '(verified owner)', 'woocommerce' ); ?></em>
					<?php endif; ?>
				<?php endif; ?>
				<span class="woocommerce-review__dash"> &ndash; </span>
				<time class="woocommerce-review__published-date" datetime="<?php echo esc_attr( get_comment_date( 'c' ) ); ?>"><?php echo esc_html( get_comment_date( wc_date_format() ) ); ?></time>
			</p>

			<div class="description"><?php comment_text(); ?></div>

		</div>
	</div>
</li>
