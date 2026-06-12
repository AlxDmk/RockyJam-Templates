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
 * Use wc_get_product( get_the_ID() ) — global $product may not be set
 * inside an anonymous function hooked early in the summary.
 */
add_action( 'woocommerce_single_product_summary', function() {
    $product = wc_get_product( get_the_ID() );
    if ( ! $product ) return;
    echo wc_get_stock_html( $product );
}, 11 );
