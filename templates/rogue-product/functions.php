<?php
/**
 * Rogue Product Template — functions.php
 * 1. Dequeues WC Flexslider/PhotoSwipe on single product pages.
 * 2. Adds stock badge immediately after the price block.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Dequeue WC Flexslider, PhotoSwipe and wc-single-product JS.
 */
add_action( 'wp_enqueue_scripts', function() {
    if ( ! is_singular( 'product' ) ) return;

    wp_dequeue_script( 'flexslider' );
    wp_deregister_script( 'flexslider' );
    wp_dequeue_script( 'photoswipe' );
    wp_dequeue_script( 'photoswipe-ui-default' );
    wp_dequeue_style( 'photoswipe' );
    wp_dequeue_style( 'photoswipe-skin' );
    wp_dequeue_script( 'wc-single-product' );
}, 99 );

/**
 * Output stock badge right after price (priority 11, price is 10).
 *
 * wc_get_stock_html() returns empty string when manage_stock=false and
 * stock_status='instock' — WooCommerce considers the badge optional in
 * that case. We render our own badge unconditionally based on stock_status.
 */
add_action( 'woocommerce_single_product_summary', function() {
    $product = wc_get_product( get_the_ID() );
    if ( ! $product ) return;

    $status = $product->get_stock_status(); // 'instock' | 'outofstock' | 'onbackorder'

    if ( 'instock' === $status ) {
        echo '<p class="stock in-stock">' . esc_html__( 'В наличии', 'woocommerce' ) . '</p>';
    } elseif ( 'outofstock' === $status ) {
        echo '<p class="stock out-of-stock">' . esc_html__( 'Нет в наличии', 'woocommerce' ) . '</p>';
    } elseif ( 'onbackorder' === $status ) {
        echo '<p class="stock available-on-backorder">' . esc_html__( 'Под заказ', 'woocommerce' ) . '</p>';
    }
}, 11 );
