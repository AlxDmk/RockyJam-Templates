<?php
/**
 * Rogue Product Template — functions.php
 * 1. Dequeues WC Flexslider/PhotoSwipe on single product pages.
 * 2. Adds stock badge immediately after the price block.
 * 3. Wraps long product titles (smaller font) instead of cutting them with an ellipsis.
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

/**
 * Product title: allow wrapping and reduce font size for long names.
 * Overrides `white-space: nowrap; text-overflow: ellipsis` from assets/style.css.
 * Printed late in <head> (priority 99) so it wins over the template stylesheet.
 */
add_action( 'wp_head', function() {
    if ( ! is_singular( 'product' ) ) return;
    ?>
    <style id="rj-rogue-title-wrap">
    body .rogue-product-page .product_title.entry-title {
        white-space: normal !important;
        overflow: visible !important;
        text-overflow: clip !important;
        overflow-wrap: anywhere;
        hyphens: auto;
        text-wrap: balance;
        line-height: 1.2 !important;
    }
    body .rogue-product-page .product_title.entry-title.rj-title--long {
        font-size: clamp(16px, 1.4vw, 20px) !important;
    }
    body .rogue-product-page .product_title.entry-title.rj-title--xlong {
        font-size: clamp(14px, 1.2vw, 17px) !important;
        line-height: 1.25 !important;
    }
    </style>
    <?php
}, 99 );

add_action( 'wp_footer', function() {
    if ( ! is_singular( 'product' ) ) return;
    ?>
    <script>
    (function () {
        var el = document.querySelector('.rogue-product-page .product_title.entry-title');
        if (!el) return;
        var len = el.textContent.trim().length;
        if (len > 60)      el.classList.add('rj-title--xlong');
        else if (len > 35) el.classList.add('rj-title--long');
    })();
    </script>
    <?php
}, 30 );
