/**
 * Adapte les quantités d'une recette au nombre de personnes.
 * Mêmes règles que scale_quantity() dans src/ingredients.php (testées pour donner des résultats identiques).
 * Fonctionne aussi dans Node (module.exports) pour les tests.
 */
(function (root, factory) {
    if (typeof module === 'object' && module.exports) {
        module.exports = factory();
    } else {
        root.KitchoScale = factory();
    }
})(typeof self !== 'undefined' ? self : this, function () {
    'use strict';

    var PLURAL_UNITS = ['pincée', 'gousse', 'tranche', 'boîte', 'sachet', 'pot', 'botte', 'paquet', 'brin', 'feuille',
        'verre', 'tasse', 'bouquet', 'barquette', 'poignée', 'tige', 'branche', 'filet', 'cube', 'bâton'];

    // Arrondi « moitié vers le haut » stable (126,49999999999999 → 127), identique à round_stable() en PHP.
    function rnd(x, places) {
        var m = Math.pow(10, places || 0);
        return Math.round(parseFloat((x * m).toPrecision(15))) / m;
    }

    function unitLabel(unit, shown) {
        if (!unit) return '';
        if (unit === 'morceau') return shown > 1 ? 'morceaux' : unit;
        return shown > 1 && PLURAL_UNITS.indexOf(unit) !== -1 ? unit + 's' : unit;
    }

    // [texte, valeur affichée] : « ½ », « 1 ¼ », « ⅓ », « 12 »
    function fractionText(q) {
        if (q >= 10) {
            var n = rnd(q);
            return [String(n), n];
        }
        var whole = Math.floor(q);
        var frac = q - whole;
        var cands = [[0, ''], [1 / 4, '¼'], [1 / 3, '⅓'], [1 / 2, '½'], [2 / 3, '⅔'], [3 / 4, '¾'], [1, '']];
        if (whole === 0) cands.splice(1, 0, [1 / 8, '⅛']);
        var best = cands[0];
        cands.forEach(function (c) {
            if (Math.abs(frac - c[0]) < Math.abs(frac - best[0]) - 1e-9) best = c;
        });
        var value = best[0], glyph = best[1];
        if (value >= 1) { whole += 1; value = 0; glyph = ''; }
        if (whole === 0 && glyph === '') { value = 1 / 8; glyph = '⅛'; }
        var text = (whole > 0 ? String(whole) : '') + (glyph !== '' ? (whole > 0 ? ' ' : '') + glyph : '');
        return [text, whole + value];
    }

    // 1,5 · 250 · 0,25 (virgule française, sans zéros inutiles)
    function decimalText(n) {
        n = rnd(n, 2);
        if (Math.abs(n - Math.round(n)) < 0.005) return String(Math.round(n));
        return n.toFixed(2).replace('.', ',').replace(/0+$/, '').replace(/,$/, '');
    }

    function scaleQuantity(q, unit) {
        if (unit === 'kg' && q < 1) { q *= 1000; unit = 'g'; }
        else if (unit === 'l' && q < 1) { q *= 1000; unit = 'ml'; }
        if (unit === 'g' && q >= 1000) { q /= 1000; unit = 'kg'; }
        else if (unit === 'ml' && q >= 1000) { q /= 1000; unit = 'l'; }
        else if (unit === 'cl' && q >= 100) { q /= 100; unit = 'l'; }
        else if (unit === 'dl' && q >= 10) { q /= 10; unit = 'l'; }
        var text, shown;
        if (unit === 'g' || unit === 'ml') {
            text = decimalText(q >= 10 ? rnd(q) : rnd(q, 1));
            shown = parseFloat(text.replace(',', '.'));
        } else if (['kg', 'l', 'cl', 'dl'].indexOf(unit) !== -1) {
            text = decimalText(q >= 10 ? rnd(q) : rnd(q, 2));
            shown = parseFloat(text.replace(',', '.'));
        } else {
            var f = fractionText(q);
            text = f[0];
            shown = f[1];
        }
        var label = unitLabel(unit, shown);
        return label !== '' ? text + ' ' + label : text;
    }

    // ------------------------------------------------------------------ page recette (navigateur)

    function init() {
        var box = document.querySelector('[data-scaler]');
        if (!box) return;
        var base = parseInt(box.getAttribute('data-base'), 10) || 1;
        var home = parseInt(box.getAttribute('data-default'), 10) || base;
        var input = box.querySelector('[data-scale-input]');
        var minus = box.querySelector('[data-scale-minus]');
        var plus = box.querySelector('[data-scale-plus]');
        var reset = box.querySelector('[data-scale-reset]');
        var word = box.querySelector('[data-scale-word]');
        var live = box.querySelector('[data-scale-live]');
        var planner = document.getElementById('pl-pers');
        var items = Array.prototype.slice.call(document.querySelectorAll('[data-scaler-scope] [data-qty]'));
        var current = parseInt(box.getAttribute('data-current'), 10) || home;

        function href(n) {
            return window.location.pathname + (n === home ? '' : '?p=' + n);
        }

        function apply(n, fromInput) {
            n = Math.max(1, Math.min(30, n || current));
            current = n;
            var factor = n / base;
            items.forEach(function (li) {
                var q = parseFloat(li.getAttribute('data-qty'));
                var out = li.querySelector('[data-qty-text]');
                if (out && !isNaN(q)) out.textContent = scaleQuantity(q * factor, li.getAttribute('data-unit') || null);
            });
            if (!fromInput) input.value = n;
            minus.setAttribute('href', href(Math.max(1, n - 1)));
            plus.setAttribute('href', href(Math.min(30, n + 1)));
            minus.classList.toggle('is-disabled', n <= 1);
            plus.classList.toggle('is-disabled', n >= 30);
            if (word) word.textContent = n > 1 ? 'personnes' : 'personne';
            if (reset) reset.hidden = n === base;
            if (live) live.textContent = 'Quantités pour ' + n + (n > 1 ? ' personnes' : ' personne');
            if (planner) planner.value = n === home ? '' : String(n);   // « Planifier » reprend le nombre choisi
            if (window.history && history.replaceState) history.replaceState(null, '', href(n) + window.location.hash);
        }

        [minus, plus].forEach(function (a) {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                if (a.classList.contains('is-disabled')) return;
                apply(current + (a === plus ? 1 : -1));
            });
        });
        input.addEventListener('input', function () {
            var n = parseInt(input.value, 10);
            if (n >= 1 && n <= 30) apply(n, true);
        });
        input.addEventListener('change', function () { apply(parseInt(input.value, 10) || current); });
        if (reset) {
            reset.addEventListener('click', function (e) { e.preventDefault(); apply(base); });
        }
        apply(current);
    }

    if (typeof document !== 'undefined') {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    }

    return { scaleQuantity: scaleQuantity, fractionText: fractionText, decimalText: decimalText, unitLabel: unitLabel };
});
