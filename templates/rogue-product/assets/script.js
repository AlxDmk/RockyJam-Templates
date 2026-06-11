/**
 * Rogue Product Template — assets/script.js
 * Handles: gallery thumbnails, qty controls, custom tabs, FAQ accordion.
 * Wrapped in DOMContentLoaded + jQuery-safe guard.
 */
(function() {
    'use strict';

    function rjInitProductPage() {

        // ========== GALLERY THUMBNAILS ==========
        // WooCommerce FlexSlider usually handles this, but we also wire the rj-specific
        // thumbnail grid clicks for the static prototype fallback.
        var mainGallery = document.querySelector('.rogue-product-page .woocommerce-product-gallery__image');

        document.querySelectorAll('.rogue-product-page .flex-control-thumbs li img').forEach(function(thumb) {
            thumb.addEventListener('click', function() {
                // FlexSlider will swap the main image; just update active border via class.
                document.querySelectorAll('.rogue-product-page .flex-control-thumbs li img').forEach(function(t) {
                    t.classList.remove('flex-active');
                });
                this.classList.add('flex-active');
            });
        });

        // ========== QUANTITY CONTROLS ==========
        // Works with WooCommerce quantity input (.qty) inside our custom wrapper.
        var qtySection = document.querySelector('.rogue-product-page .rj-qty-controls');
        if (qtySection) {
            var qtyInput = qtySection.querySelector('.qty, .rj-qty-value');
            var qtyMinus = qtySection.querySelector('.rj-qty-minus');
            var qtyPlus  = qtySection.querySelector('.rj-qty-plus');

            if (qtyMinus && qtyInput) {
                qtyMinus.addEventListener('click', function() {
                    var current = parseInt(qtyInput.value, 10) || 1;
                    var min = parseInt(qtyInput.getAttribute('min'), 10) || 1;
                    if (current > min) {
                        qtyInput.value = current - 1;
                        // Trigger WC event so cart fragments update
                        qtyInput.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });
            }

            if (qtyPlus && qtyInput) {
                qtyPlus.addEventListener('click', function() {
                    var current = parseInt(qtyInput.value, 10) || 1;
                    var max = parseInt(qtyInput.getAttribute('max'), 10);
                    if (!max || current < max) {
                        qtyInput.value = current + 1;
                        qtyInput.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });
            }
        }

        // ========== CUSTOM TABS ==========
        var tabLinks    = document.querySelectorAll('.rj-product-tabs .rj-tab-link');
        var tabContents = document.querySelectorAll('.rj-product-tabs .rj-tab-content');

        tabLinks.forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();

                // Remove active from all
                tabLinks.forEach(function(l) { l.classList.remove('active'); });
                tabContents.forEach(function(c) { c.classList.remove('active'); });

                // Activate clicked
                this.classList.add('active');
                var tabId = this.getAttribute('data-tab');
                var target = document.getElementById(tabId);
                if (target) {
                    target.classList.add('active');
                }
            });
        });

        // ========== FAQ ACCORDION ==========
        var faqItems = document.querySelectorAll('.rj-faq-item');

        faqItems.forEach(function(item) {
            var question = item.querySelector('.rj-faq-question');
            var answer   = item.querySelector('.rj-faq-answer');

            if (!question || !answer) return;

            question.addEventListener('click', function() {
                var isActive = item.classList.contains('active');

                // Close all
                faqItems.forEach(function(other) {
                    other.classList.remove('active');
                    var ans = other.querySelector('.rj-faq-answer');
                    if (ans) ans.style.maxHeight = null;
                });

                // Open clicked (if it was closed)
                if (!isActive) {
                    item.classList.add('active');
                    answer.style.maxHeight = answer.scrollHeight + 'px';
                }
            });
        });

        // ========== ADD TO CART FEEDBACK ==========
        // Visual feedback on the WC add-to-cart button (supplement WC's own AJAX handling).
        var addToCartBtn = document.querySelector('.rogue-product-page .single_add_to_cart_button');
        if (addToCartBtn) {
            // WooCommerce fires 'added_to_cart' on jQuery — listen for it.
            if (typeof jQuery !== 'undefined') {
                jQuery(document).on('added_to_cart', function() {
                    addToCartBtn.classList.add('added');
                    setTimeout(function() {
                        addToCartBtn.classList.remove('added');
                    }, 2000);
                });
            }
        }

    }

    // Run after DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', rjInitProductPage);
    } else {
        rjInitProductPage();
    }

})();
