<?php
/**
 * Rogue Product Template — override of WooCommerce tabs.php
 * Custom tab navigation with green active indicator matching reference design.
 *
 * Tab «reviews»:
 *   — есть отзывы  → обычная кликабельная вкладка + контент
 *   — отзывов нет  → <span> (некликабельный, серый) + скрытый пустой div
 *                    чтобы JS всегда находил #tab-reviews в DOM
 */

defined( 'ABSPATH' ) || exit;

/**
 * Filter tabs and allow third parties to add their own.
 *
 * Each tab is an array with keys:
 *   title    (string)
 *   priority (int)
 *   callback (callable)
 */
$product_tabs = apply_filters( 'woocommerce_product_tabs', array() );

if ( ! empty( $product_tabs ) ) :

    global $product;
    $review_count = ( $product instanceof WC_Product ) ? $product->get_review_count() : 0;

?>

<section class="rj-product-tabs">

    <div class="rj-tabs-nav">
        <?php
        $i = 0;
        foreach ( $product_tabs as $key => $product_tab ) :

            $is_reviews  = ( 'reviews' === $key );
            $is_disabled = $is_reviews && ( $review_count < 1 );
            $tab_title   = wp_kses_post(
                apply_filters( 'woocommerce_product_' . $key . '_tab_title', $product_tab['title'], $key )
            );
        ?>

            <?php if ( $is_disabled ) : ?>
                <span
                    class="rj-tab-link rj-tab-disabled"
                    aria-disabled="true"
                    title="<?php esc_attr_e( 'Нет отзывов', 'woocommerce' ); ?>">
                    <?php echo $tab_title; ?>
                </span>
            <?php else : ?>
                <button
                    class="rj-tab-link<?php echo $i === 0 ? ' active' : ''; ?>"
                    data-tab="tab-<?php echo esc_attr( $key ); ?>">
                    <?php echo $tab_title; ?>
                </button>
            <?php endif; ?>

        <?php $i++; endforeach; ?>
    </div>

    <div class="rj-tabs-content">
        <?php
        $i = 0;
        foreach ( $product_tabs as $key => $product_tab ) :
            $is_reviews  = ( 'reviews' === $key );
            $is_disabled = $is_reviews && ( $review_count < 1 );
        ?>
            <div
                class="rj-tab-content<?php echo $i === 0 ? ' active' : ''; ?>"
                id="tab-<?php echo esc_attr( $key ); ?>"
                <?php echo $is_disabled ? 'aria-hidden="true"' : ''; ?>
            >
                <?php if ( $is_disabled ) : ?>
                    <p class="rj-no-reviews"><?php esc_html_e( 'Отзывов пока нет.', 'woocommerce' ); ?></p>
                <?php elseif ( isset( $product_tab['callback'] ) ) : ?>
                    <?php call_user_func( $product_tab['callback'], $key, $product_tab ); ?>
                <?php endif; ?>
            </div>
        <?php $i++; endforeach; ?>
    </div>

</section>

<?php endif; ?>
