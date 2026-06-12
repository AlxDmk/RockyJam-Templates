<?php
/**
 * Rogue Product Template — override of simple.php add-to-cart form.
 * Stock badge is rendered by functions.php hook after price — NOT here.
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! $product->is_purchasable() ) {
    return;
}

if ( $product->is_in_stock() ) : ?>

    <?php do_action( 'woocommerce_before_add_to_cart_form' ); ?>

    <form class="cart rj-cart-form" action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>" method="post" enctype='multipart/form-data'>

        <?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>

        <div class="rj-qty-section">
            <label class="rj-qty-label" for="quantity_<?php echo esc_attr( $product->get_id() ); ?>"><?php esc_html_e( 'Quantity:', 'woocommerce' ); ?></label>
            <div class="rj-qty-controls">
                <button type="button" class="rj-qty-minus" aria-label="<?php esc_attr_e( 'Decrease quantity', 'woocommerce' ); ?>">&#8722;</button>
                <?php
                woocommerce_quantity_input(
                    array(
                        'min_value'   => apply_filters( 'woocommerce_quantity_input_min', $product->get_min_purchase_quantity(), $product ),
                        'max_value'   => apply_filters( 'woocommerce_quantity_input_max', $product->get_max_purchase_quantity(), $product ),
                        'input_value' => isset( $_POST['quantity'] ) ? wc_stock_amount( wp_unslash( $_POST['quantity'] ) ) : $product->get_min_purchase_quantity(),
                        'classes'     => array( 'qty', 'rj-qty-value' ),
                    )
                );
                ?>
                <button type="button" class="rj-qty-plus" aria-label="<?php esc_attr_e( 'Increase quantity', 'woocommerce' ); ?>">+</button>
            </div>
        </div>

        <button type="submit"
            name="add-to-cart"
            value="<?php echo esc_attr( $product->get_id() ); ?>"
            class="single_add_to_cart_button rj-add-to-cart button alt wp-element-button">
            <?php echo esc_html( $product->single_add_to_cart_text() ); ?>
        </button>

        <?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>

    </form>

    <?php do_action( 'woocommerce_after_add_to_cart_form' ); ?>

<?php endif; ?>
