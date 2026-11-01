/* Rogue Product — extra.js: класс по длине названия товара для уменьшения шрифта. */
(function () {
    'use strict';
    var el = document.querySelector('.rogue-product-page .product_title.entry-title');
    if (!el) { return; }
    var len = el.textContent.trim().length;
    if (len > 60)      { el.classList.add('rj-title--xlong'); }
    else if (len > 35) { el.classList.add('rj-title--long'); }
})();
