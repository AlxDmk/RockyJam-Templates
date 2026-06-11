<?php
/**
 * Rogue Product Template — override of WooCommerce tabs.php
 * Custom tab navigation with green active indicator matching reference design.
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

if ( ! empty( $product_tabs ) ) : ?>

<section class="rj-product-tabs">

    <div class="rj-tabs-nav">
        <?php $i = 0; foreach ( $product_tabs as $key => $product_tab ) : ?>
            <button
                class="rj-tab-link<?php echo $i === 0 ? ' active' : ''; ?>"
                data-tab="tab-<?php echo esc_attr( $key ); ?>">
                <?php echo wp_kses_post( apply_filters( 'woocommerce_product_' . $key . '_tab_title', $product_tab['title'], $key ) ); ?>
            </button>
        <?php $i++; endforeach; ?>
    </div>

    <div class="rj-tabs-content">
        <?php $i = 0; foreach ( $product_tabs as $key => $product_tab ) : ?>
            <div class="rj-tab-content<?php echo $i === 0 ? ' active' : ''; ?>" id="tab-<?php echo esc_attr( $key ); ?>">
                <?php
                if ( isset( $product_tab['callback'] ) ) {
                    call_user_func( $product_tab['callback'], $key, $product_tab );
                }
                ?>
            </div>
        <?php $i++; endforeach; ?>
    </div>

</section>

<?php endif; ?>
