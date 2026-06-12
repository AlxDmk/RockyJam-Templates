<?php
/**
 * Rogue Product Template — override of WooCommerce tabs.php
 * Custom tab navigation with green active indicator matching reference design.
 *
 * Tab «reviews»:
 *   — есть отзывы  → обычная кликабельная вкладка + контент WC
 *   — отзывов нет  → <span> (некликабельный, серый) + скрытый пустой div
 *
 * ВАЖНО: WooCommerce удаляет вкладку reviews из $product_tabs когда
 * отзывы отключены на товаре или глобально. Мы принудительно
 * добавляем её обратно, чтобы она всегда отображалась в nav.
 */

defined( 'ABSPATH' ) || exit;

$product_tabs = apply_filters( 'woocommerce_product_tabs', array() );

if ( ! empty( $product_tabs ) ) :

    global $product;
    $review_count = ( $product instanceof WC_Product ) ? $product->get_review_count() : 0;

    // Если WooCommerce убрал вкладку reviews — добавляем её принудительно.
    if ( ! isset( $product_tabs['reviews'] ) ) {
        $product_tabs['reviews'] = array(
            'title'    => __( 'Отзывы', 'woocommerce' ),
            'priority' => 30,
            'callback' => 'comments_template',
        );
    }

    // Сортируем по priority как это делает WC.
    uasort( $product_tabs, function( $a, $b ) {
        return ( $a['priority'] ?? 10 ) <=> ( $b['priority'] ?? 10 );
    } );

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
