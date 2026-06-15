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
                    thumbItems.forEach(function (t) { t.classList.remove('active'); });
                    item.classList.add('active');

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
        var lb, lbMainImg, lbImgWrap, lbThumbsRow, lbBtnClose, lbBtnPrev, lbBtnNext;
        var images  = [];
        var current = 0;

        // ---- Touch/swipe state ----
        var touchStartX  = 0;
        var touchStartY  = 0;
        var touchDeltaX  = 0;
        var touchDeltaY  = 0;
        var swipeDir     = null; // 'h' | 'v' | null — направление фиксируется после 10px движения
        var DIR_LOCK_PX  = 10;  // px до фиксации направления
        var SWIPE_H_PX   = 40;  // px минимум для горизонтального свайпа (навигация)
        var SWIPE_V_PX   = 60;  // px минимум для вертикального свайпа (закрытие)

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
            lbImgWrap   = lb.querySelector('.rj-lb-img-wrap');
            lbThumbsRow = document.getElementById('rj-lb-thumbs');
            lbBtnClose  = lb.querySelector('.rj-lb-close');
            lbBtnPrev   = lb.querySelector('.rj-lb-prev');
            lbBtnNext   = lb.querySelector('.rj-lb-next');

            lbBtnClose.addEventListener('click', close);
            lbBtnPrev.addEventListener('click', function () { goTo(current - 1); });
            lbBtnNext.addEventListener('click', function () { goTo(current + 1); });

            lb.addEventListener('click', function (e) {
                if (e.target === lb) close();
            });

            // ---- Свайп по главному изображению ----
            // passive:false для touchmove — чтобы блокировать скролл страницы во время гориз. свайпа
            lbImgWrap.addEventListener('touchstart', onTouchStart, { passive: true });
            lbImgWrap.addEventListener('touchmove',  onTouchMove,  { passive: false });
            lbImgWrap.addEventListener('touchend',   onTouchEnd,   { passive: true });
        }

        // ---- Touch handlers ----
        function onTouchStart(e) {
            var t    = e.touches[0];
            touchStartX = t.clientX;
            touchStartY = t.clientY;
            touchDeltaX = 0;
            touchDeltaY = 0;
            swipeDir    = null;
        }

        function onTouchMove(e) {
            var t = e.touches[0];
            touchDeltaX = t.clientX - touchStartX;
            touchDeltaY = t.clientY - touchStartY;

            // Фиксируем направление после первых DIR_LOCK_PX
            if (!swipeDir) {
                var absX = Math.abs(touchDeltaX);
                var absY = Math.abs(touchDeltaY);
                if (absX > DIR_LOCK_PX || absY > DIR_LOCK_PX) {
                    swipeDir = absX >= absY ? 'h' : 'v';
                }
            }

            // Блокируем стандартный скролл страницы только если направление фиксировано
            // (избегаем блокировки вертикального скролла страницы когда направление ещё не определено)
            if (swipeDir === 'h') {
                e.preventDefault();
            }

            // Визуальный отклик: при вертикальном свайпе сдвигаем изображение вниз
            if (swipeDir === 'v') {
                var opacity = Math.max(0.3, 1 - Math.abs(touchDeltaY) / 250);
                lbMainImg.style.transform = 'translateY(' + touchDeltaY + 'px)';
                lbMainImg.style.opacity   = opacity;
            }
        }

        function onTouchEnd() {
            var absX = Math.abs(touchDeltaX);
            var absY = Math.abs(touchDeltaY);

            if (swipeDir === 'h' && absX >= SWIPE_H_PX) {
                // Горизонтальный свайп — навигация
                goTo(touchDeltaX < 0 ? current + 1 : current - 1);
            } else if (swipeDir === 'v' && absY >= SWIPE_V_PX) {
                // Вертикальный свайп — закрыть лайтбокс
                close();
            } else {
                // Недостаточно далеко — возвращаем изображение на место
                resetImgTransform();
            }

            touchDeltaX = 0;
            touchDeltaY = 0;
            swipeDir    = null;
        }

        function resetImgTransform() {
            lbMainImg.style.transition = 'transform 0.25s ease, opacity 0.25s ease';
            lbMainImg.style.transform  = 'translateY(0)';
            lbMainImg.style.opacity    = '1';
            setTimeout(function () {
                lbMainImg.style.transition = '';
            }, 260);
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

            // Сбрасываем визуальный отклик от свайпа и меняем изображение
            lbMainImg.style.transition = '';
            lbMainImg.style.transform  = 'translateY(0)';
            lbMainImg.style.opacity    = '0.4';
            lbMainImg.src = images[idx].large;
            lbMainImg.alt = images[idx].alt;
            lbMainImg.onload = function () { lbMainImg.style.opacity = '1'; };
            if (lbMainImg.complete) { lbMainImg.style.opacity = '1'; }

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
            buildDOM();
            collectImages();
            buildThumbs();
            lb.hidden = false;
            document.body.style.overflow = 'hidden';
            goTo(startIdx || 0);
            lbBtnClose.focus();
        }

        function close() {
            // Анимация закрытия: изображение улетает вниз
            if (lb && !lb.hidden) {
                lbMainImg.style.transition = 'transform 0.22s ease, opacity 0.22s ease';
                lbMainImg.style.transform  = 'translateY(60px)';
                lbMainImg.style.opacity    = '0';
                setTimeout(function () {
                    lb.hidden = true;
                    lbMainImg.style.transition = '';
                    lbMainImg.style.transform  = 'translateY(0)';
                    lbMainImg.style.opacity    = '1';
                    document.body.style.overflow = '';
                }, 230);
            }
        }

        document.addEventListener('keydown', function (e) {
            if (!lb || lb.hidden) return;
            if (e.key === 'Escape')     close();
            if (e.key === 'ArrowLeft')  goTo(current - 1);
            if (e.key === 'ArrowRight') goTo(current + 1);
        });

        return { open: open, close: close };
    })();

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', rjInitProductPage);
    } else {
        rjInitProductPage();
    }

})();
