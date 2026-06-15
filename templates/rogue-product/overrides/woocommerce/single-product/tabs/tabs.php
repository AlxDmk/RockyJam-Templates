<?php
/**
 * Rogue Product Template — override of WooCommerce tabs.php
 *
 * Tab «reviews»:
 *   — есть отзывы  → список .star-rating + форма
 *   — отзывов нет  → <span> (некликабельный) + пустой div
 *
 * Отзывы рендерятся через get_comments(post_id) + wp_list_comments(woocommerce_comments).
 * Это обходит ограничение wp_list_comments() вне comments_template() контекста:
 * без явной передачи $comments список пуст, даже если отзывы в БД есть.
 */

defined( 'ABSPATH' ) || exit;

$product_tabs = apply_filters( 'woocommerce_product_tabs', array() );

if ( ! empty( $product_tabs ) ) :

    global $product, $post;
    $review_count = ( $product instanceof WC_Product ) ? $product->get_review_count() : 0;

    if ( ! isset( $product_tabs['reviews'] ) ) {
        $product_tabs['reviews'] = array(
            'title'    => __( 'Отзывы', 'woocommerce' ),
            'priority' => 30,
            'callback' => null,
        );
    } else {
        $product_tabs['reviews']['callback'] = null;
    }

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
                <?php if ( $is_reviews ) : ?>
                    <?php if ( $is_disabled ) : ?>
                        <p class="rj-no-reviews"><?php esc_html_e( 'Отзывов пока нет.', 'woocommerce' ); ?></p>
                    <?php else : ?>
                        <div id="reviews" class="woocommerce-Reviews">
                            <div id="comments">
                                <?php
                                /**
                                 * Явно получаем отзывы из БД по post_id.
                                 * wp_list_comments() вне comments_template() не имеет
                                 * инициализированного $wp_query->comments, поэтому передаём
                                 * $comments вторым аргументом напрямую.
                                 */
                                $post_id       = $post->ID;
                                $product_reviews = get_comments( array(
                                    'post_id' => $post_id,
                                    'status'  => 'approve',
                                    'type'    => 'review',
                                    'orderby' => 'comment_date_gmt',
                                    'order'   => 'ASC',
                                ) );

                                if ( $product_reviews ) : ?>
                                    <ol class="commentlist">
                                        <?php
                                        wp_list_comments(
                                            apply_filters(
                                                'woocommerce_product_review_list_args',
                                                array( 'callback' => 'woocommerce_comments' )
                                            ),
                                            $product_reviews
                                        );
                                        ?>
                                    </ol>
                                <?php endif; ?>
                            </div>

                            <div id="review_form_wrapper">
                                <div id="review_form">
                                    <?php
                                    $commenter    = wp_get_current_commenter();
                                    $comment_form = array(
                                        'title_reply'         => $product_reviews
                                            ? esc_html__( 'Add a review', 'woocommerce' )
                                            : sprintf( esc_html__( 'Be the first to review &ldquo;%s&rdquo;', 'woocommerce' ), get_the_title() ),
                                        'title_reply_to'      => esc_html__( 'Leave a Reply to %s', 'woocommerce' ),
                                        'title_reply_before'  => '<span id="reply-title" class="comment-reply-title">',
                                        'title_reply_after'   => '</span>',
                                        'comment_notes_after' => '',
                                        'label_submit'        => esc_html__( 'Submit', 'woocommerce' ),
                                        'logged_in_as'        => '',
                                        'comment_field'       => '',
                                    );

                                    $name_email_required = (bool) get_option( 'require_name_email', 1 );
                                    $fields              = array(
                                        'author' => array(
                                            'label'    => __( 'Name', 'woocommerce' ),
                                            'type'     => 'text',
                                            'value'    => $commenter['comment_author'],
                                            'required' => $name_email_required,
                                        ),
                                        'email'  => array(
                                            'label'    => __( 'Email', 'woocommerce' ),
                                            'type'     => 'email',
                                            'value'    => $commenter['comment_author_email'],
                                            'required' => $name_email_required,
                                        ),
                                    );

                                    $comment_form['fields'] = array();
                                    foreach ( $fields as $key_f => $field ) {
                                        $comment_form['fields'][ $key_f ] = '<p class="comment-form-' . esc_attr( $key_f ) . '"><label for="' . esc_attr( $key_f ) . '">' . esc_html( $field['label'] ) . ( $field['required'] ? '&nbsp;<span class="required">*</span>' : '' ) . '</label><input id="' . esc_attr( $key_f ) . '" name="' . esc_attr( $key_f ) . '" type="' . esc_attr( $field['type'] ) . '" value="' . esc_attr( $field['value'] ) . '" size="30" ' . ( $field['required'] ? 'required' : '' ) . ' /></p>';
                                    }

                                    if ( wc_review_ratings_enabled() ) {
                                        $comment_form['comment_field']  = '<div class="comment-form-rating"><label for="rating">' . esc_html__( 'Your rating', 'woocommerce' ) . ( wc_review_ratings_required() ? '&nbsp;<span class="required">*</span>' : '' ) . '</label>';
                                        $comment_form['comment_field'] .= '<select name="rating" id="rating" required>';
                                        $comment_form['comment_field'] .= '<option value="">' . esc_html__( 'Rate&hellip;', 'woocommerce' ) . '</option>';
                                        $comment_form['comment_field'] .= '<option value="5">' . esc_html__( 'Perfect', 'woocommerce' ) . '</option>';
                                        $comment_form['comment_field'] .= '<option value="4">' . esc_html__( 'Good', 'woocommerce' ) . '</option>';
                                        $comment_form['comment_field'] .= '<option value="3">' . esc_html__( 'Average', 'woocommerce' ) . '</option>';
                                        $comment_form['comment_field'] .= '<option value="2">' . esc_html__( 'Not that bad', 'woocommerce' ) . '</option>';
                                        $comment_form['comment_field'] .= '<option value="1">' . esc_html__( 'Very poor', 'woocommerce' ) . '</option>';
                                        $comment_form['comment_field'] .= '</select></div>';
                                        $comment_form['comment_field'] .= '<p class="comment-form-comment"><label for="comment">' . esc_html__( 'Your review', 'woocommerce' ) . '&nbsp;<span class="required">*</span></label><textarea id="comment" name="comment" cols="45" rows="8" required></textarea></p>';
                                    }

                                    comment_form( apply_filters( 'woocommerce_product_review_comment_form_args', $comment_form ) );
                                    ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php elseif ( isset( $product_tab['callback'] ) && is_callable( $product_tab['callback'] ) ) : ?>
                    <?php call_user_func( $product_tab['callback'], $key, $product_tab ); ?>
                <?php endif; ?>
            </div>
        <?php $i++; endforeach; ?>
    </div>

</section>

<?php endif; ?>
