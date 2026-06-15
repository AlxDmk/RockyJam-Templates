/**
 * Rogue Product Template — assets/script.js
 * Handles: custom gallery (no Flexslider), qty controls, custom tabs, FAQ accordion, lightbox.
 */
(function () {
    'use strict';

    function rjInitProductPage() {

        // ========== CUSTOM GALLERY ==========
        var mainImgWrap = document.querySelector('.rj-main-image-wrap');
        var mainImg     = document.getElementById('rj-main-img');
        var thumbItems  = document.querySelectorAll('.rj-thumbs .rj-thumb-item');

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

            // Open lightbox on main image click
            if (mainImgWrap) {
                mainImgWrap.style.cursor = 'zoom-in';
                mainImgWrap.addEventListener('click', function () {
                    var activeIndex = 0;
                    thumbItems.forEach(function (item, idx) {
                        if (item.classList.contains('active')) activeIndex = idx;
                    });
                    rjLightbox.open(activeIndex);
                });
            }
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

    // ========== LIGHTBOX ==========
    var rjLightbox = (function () {
        var lb, lbMainImg, lbThumbsRow, lbBtnClose, lbBtnPrev, lbBtnNext;
        var images  = [];
        var current = 0;
        var isMobile = false;

        function checkMobile() {
            isMobile = window.innerWidth <= 768;
        }

        function collectImages() {
            images = [];
            var thumbItems = document.querySelectorAll('.rj-thumbs .rj-thumb-item');
            thumbItems.forEach(function (item) {
                var thumb = item.querySelector('.rj-thumb');
                if (!thumb) return;
                images.push({
                    large : thumb.getAttribute('data-full') || thumb.src,
                    thumb : thumb.src,
                    alt   : thumb.getAttribute('data-alt') || thumb.alt || ''
                });
            });
            // Fallback: если нет миниатюр — берём только главное изображение
            if (!images.length) {
                var mainImg = document.getElementById('rj-main-img');
                if (mainImg) {
                    images.push({ large: mainImg.src, thumb: mainImg.src, alt: mainImg.alt || '' });
                }
            }
        }

        function buildDOM() {
            if (document.getElementById('rj-lightbox')) return;
            lb = document.createElement('div');
            lb.id = 'rj-lightbox';
            lb.className = 'rj-lightbox';
            lb.setAttribute('role', 'dialog');
            lb.setAttribute('aria-modal', 'true');
            lb.setAttribute('aria-label', 'Просмотр изображений');
            lb.hidden = true;

            lb.innerHTML =
                '<button class="rj-lb-close" aria-label="Закрыть">&#x2715;</button>' +
                '<div class="rj-lb-main">' +
                    '<button class="rj-lb-arrow rj-lb-prev" aria-label="Предыдущее">&#8249;</button>' +
                    '<div class="rj-lb-img-wrap"><img src="" alt="" class="rj-lb-img" id="rj-lb-main-img"></div>' +
                    '<button class="rj-lb-arrow rj-lb-next" aria-label="Следующее">&#8250;</button>' +
                '</div>' +
                '<div class="rj-lb-thumbs" id="rj-lb-thumbs"></div>';

            document.body.appendChild(lb);

            lbMainImg   = document.getElementById('rj-lb-main-img');
            lbThumbsRow = document.getElementById('rj-lb-thumbs');
            lbBtnClose  = lb.querySelector('.rj-lb-close');
            lbBtnPrev   = lb.querySelector('.rj-lb-prev');
            lbBtnNext   = lb.querySelector('.rj-lb-next');

            lbBtnClose.addEventListener('click', close);
            lbBtnPrev.addEventListener('click', function () { goTo(current - 1); });
            lbBtnNext.addEventListener('click', function () { goTo(current + 1); });

            // Закрыть по клику на фон
            lb.addEventListener('click', function (e) {
                if (e.target === lb) close();
            });
        }

        function buildThumbs() {
            lbThumbsRow.innerHTML = '';
            images.forEach(function (item, idx) {
                var t = document.createElement('img');
                t.src       = item.thumb;
                t.alt       = item.alt;
                t.className = 'rj-lb-thumb';
                t.setAttribute('loading', 'lazy');
                t.addEventListener('click', function () { goTo(idx); });
                lbThumbsRow.appendChild(t);
            });
        }

        function goTo(idx) {
            if (!images.length) return;
            idx = Math.max(0, Math.min(idx, images.length - 1));
            current = idx;

            lbMainImg.src = images[idx].large;
            lbMainImg.alt = images[idx].alt;

            var thumbEls = lbThumbsRow.querySelectorAll('.rj-lb-thumb');
            thumbEls.forEach(function (t, i) {
                t.classList.toggle('is-active', i === idx);
            });

            if (thumbEls[idx]) {
                thumbEls[idx].scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
            }

            lbBtnPrev.disabled = (idx === 0);
            lbBtnNext.disabled = (idx === images.length - 1);
        }

        function open(startIdx) {
            checkMobile();
            buildDOM();
            collectImages();
            buildThumbs();
            lb.hidden = false;
            document.body.style.overflow = 'hidden';
            goTo(startIdx || 0);
            lbBtnClose.focus();
        }

        function close() {
            if (lb) lb.hidden = true;
            document.body.style.overflow = '';
        }

        // Клавиатура
        document.addEventListener('keydown', function (e) {
            if (!lb || lb.hidden) return;
            if (e.key === 'Escape')     close();
            if (e.key === 'ArrowLeft')  goTo(current - 1);
            if (e.key === 'ArrowRight') goTo(current + 1);
        });

        window.addEventListener('resize', checkMobile);

        return { open: open, close: close };
    })();

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', rjInitProductPage);
    } else {
        rjInitProductPage();
    }

})();
