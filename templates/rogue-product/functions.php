<?php
/**
 * Rogue Product Template — functions.php
 * Disables WooCommerce Flexslider JS on single product pages
 * where this template is active.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Dequeue WooCommerce Flexslider scripts and styles on single product pages.
 * Our custom gallery (product-image.php override) handles everything.
 */
add_action( 'wp_enqueue_scripts', function() {
    if ( ! is_singular( 'product' ) ) return;

    // Remove WC Flexslider JS
    wp_dequeue_script( 'flexslider' );
    wp_deregister_script( 'flexslider' );

    // Remove WC PhotoSwipe (lightbox)
    wp_dequeue_script( 'photoswipe' );
    wp_dequeue_script( 'photoswipe-ui-default' );
    wp_dequeue_style( 'photoswipe' );
    wp_dequeue_style( 'photoswipe-skin' );

    // Remove WC single product JS that init Flexslider
    wp_dequeue_script( 'wc-single-product' );
}, 99 );
