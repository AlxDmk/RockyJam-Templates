/**
 * Rogue Product Template — assets/script.js
 * Handles: custom gallery (no Flexslider), qty controls, custom tabs, FAQ accordion.
 */
(function () {
    'use strict';

    function rjInitProductPage() {

        // ========== CUSTOM GALLERY ==========
        var mainImg   = document.getElementById('rj-main-img');
        var thumbItems = document.querySelectorAll('.rj-thumbs .rj-thumb-item');

        if (mainImg && thumbItems.length) {
            thumbItems.forEach(function (item) {
                var thumb = item.querySelector('.rj-thumb');
                if (!thumb) return;

                thumb.addEventListener('click', function () {
                    // Active border
                    thumbItems.forEach(function (t) { t.classList.remove('active'); });
                    item.classList.add('active');

                    // Fade-swap main image
                    var fullSrc = thumb.getAttribute('data-full');
                    var fullAlt = thumb.getAttribute('data-alt') || '';

                    mainImg.style.opacity = '0.5';
                    setTimeout(function () {
                        mainImg.src = fullSrc;
                        mainImg.alt = fullAlt;
                        mainImg.style.opacity = '1';
                    }, 180);
                });
            });
        }

        // ========== QUANTITY CONTROLS ==========
        var qtySection = document.querySelector('.rogue-product-page .rj-qty-controls');
        if (qtySection) {
            var qtyInput = qtySection.querySelector('.qty, .rj-qty-value');
            var qtyMinus = qtySection.querySelector('.rj-qty-minus');
            var qtyPlus  = qtySection.querySelector('.rj-qty-plus');

            if (qtyMinus && qtyInput) {
                qtyMinus.addEventListener('click', function () {
                    var current = parseInt(qtyInput.value, 10) || 1;
                    var min = parseInt(qtyInput.getAttribute('min'), 10) || 1;
                    if (current > min) {
                        qtyInput.value = current - 1;
                        qtyInput.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });
            }

            if (qtyPlus && qtyInput) {
                qtyPlus.addEventListener('click', function () {
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

        tabLinks.forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                tabLinks.forEach(function (l) { l.classList.remove('active'); });
                tabContents.forEach(function (c) { c.classList.remove('active'); });
                this.classList.add('active');
                var target = document.getElementById(this.getAttribute('data-tab'));
                if (target) target.classList.add('active');
            });
        });

        // ========== FAQ ACCORDION ==========
        var faqItems = document.querySelectorAll('.rj-faq-item');

        faqItems.forEach(function (item) {
            var question = item.querySelector('.rj-faq-question');
            var answer   = item.querySelector('.rj-faq-answer');
            if (!question || !answer) return;

            question.addEventListener('click', function () {
                var isActive = item.classList.contains('active');
                faqItems.forEach(function (other) {
                    other.classList.remove('active');
                    var ans = other.querySelector('.rj-faq-answer');
                    if (ans) ans.style.maxHeight = null;
                });
                if (!isActive) {
                    item.classList.add('active');
                    answer.style.maxHeight = answer.scrollHeight + 'px';
                }
            });
        });

        // ========== ADD TO CART FEEDBACK ==========
        var addToCartBtn = document.querySelector('.rogue-product-page .single_add_to_cart_button');
        if (addToCartBtn && typeof jQuery !== 'undefined') {
            jQuery(document).on('added_to_cart', function () {
                addToCartBtn.classList.add('added');
                setTimeout(function () {
                    addToCartBtn.classList.remove('added');
                }, 2000);
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', rjInitProductPage);
    } else {
        rjInitProductPage();
    }

})();
