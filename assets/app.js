(function () {
    'use strict';

    // Confirmation avant action destructrice : <form data-confirm="…">
    document.addEventListener('submit', function (e) {
        var msg = e.target.getAttribute && e.target.getAttribute('data-confirm');
        if (msg && !window.confirm(msg)) {
            e.preventDefault();
        }
    });

    // Soumission automatique : <select data-autosubmit>
    document.addEventListener('change', function (e) {
        if (e.target.hasAttribute && e.target.hasAttribute('data-autosubmit') && e.target.form) {
            e.target.form.submit();
        }
    });

    function copy(text, btn) {
        var done = function () {
            if (!btn) return;
            var old = btn.textContent;
            btn.textContent = 'Copié ✓';
            setTimeout(function () { btn.textContent = old; }, 1500);
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done);
        } else {
            var ta = document.createElement('textarea');
            ta.value = text;
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); done(); } catch (err) { /* ignore */ }
            document.body.removeChild(ta);
        }
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest('[data-copy],[data-copy-text]');
        if (btn) {
            e.preventDefault();
            copy(btn.getAttribute('data-copy') || btn.getAttribute('data-copy-text'), btn);
            return;
        }
        if (e.target.classList && e.target.classList.contains('copy-input')) {
            e.target.select();
        }
    });

    // ------------------------------------------------------------------ recettes : filtrage pendant la frappe
    var filterForm = document.querySelector('[data-live-filter]');
    if (filterForm) {
        var input = filterForm.querySelector('[data-filter-input]');
        var catSel = filterForm.querySelector('[data-filter-cat]');
        var rows = Array.prototype.slice.call(document.querySelectorAll('[data-filter-row]'));
        var countEl = document.querySelector('[data-filter-count]');
        var emptyEl = document.querySelector('[data-filter-empty]');
        var submitBtn = filterForm.querySelector('[data-filter-submit]');
        var fold = function (s) {
            return s.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/\s+/g, ' ').trim();
        };
        var applyFilter = function () {
            var words = fold(input.value).split(' ').filter(Boolean);
            var cat = catSel.value;
            var shown = 0;
            rows.forEach(function (tr) {
                var name = tr.getAttribute('data-name');
                var ok = (!cat || tr.getAttribute('data-cat') === cat) && words.every(function (w) { return name.indexOf(w) !== -1; });
                tr.hidden = !ok;
                if (ok) shown++;
            });
            if (countEl) {
                var total = countEl.getAttribute('data-total');
                countEl.textContent = (words.length || cat) ? '(' + shown + ' / ' + total + ')' : '(' + total + ')';
            }
            if (emptyEl) emptyEl.hidden = shown > 0 || !rows.length;
            if (window.history && history.replaceState) {
                var u = new URL(window.location.href);
                if (input.value.trim()) u.searchParams.set('q', input.value.trim()); else u.searchParams.delete('q');
                if (cat) u.searchParams.set('cat', cat); else u.searchParams.delete('cat');
                history.replaceState(null, '', u);
            }
        };
        if (rows.length) {
            input.addEventListener('input', applyFilter);
            catSel.addEventListener('change', applyFilter);
            // Plus besoin d'envoyer le formulaire pour filtrer ; Entrée ne recharge pas la page.
            filterForm.addEventListener('submit', function (e) { e.preventDefault(); });
            if (submitBtn) submitBtn.hidden = true;
            applyFilter();
        }
    }

    // ------------------------------------------------------------------ liste de courses
    var shop = document.querySelector('.shop');
    if (!shop) return;

    var week = shop.getAttribute('data-week');
    var csrf = document.body.getAttribute('data-csrf');
    var storeKey = 'menu-pending-' + week;
    var pending = {};   // clé article -> état voulu ('1' / '0'), en attente d'envoi
    var syncing = false;

    function load(k, d) { try { return JSON.parse(localStorage.getItem(k)) || d; } catch (e) { return d; } }
    function save(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) { /* ignore */ } }
    pending = load(storeKey, {});

    var el = {
        done: shop.querySelector('[data-done]'),
        total: shop.querySelector('[data-total]'),
        fill: shop.querySelector('[data-progress-fill]'),
        offline: shop.querySelector('[data-offline]'),
        changed: shop.querySelector('[data-changed]'),
        allDone: shop.querySelector('[data-alldone]')
    };

    function rowOf(key) { return shop.querySelector('li[data-key="' + key + '"]'); }

    // Applique un état à une ligne, sans passer par le réseau.
    function paint(li, checked) {
        var form = li.querySelector('.toggle');
        li.classList.toggle('done', checked);
        form.querySelector('.box').textContent = checked ? '✓' : '';
        form.querySelector('.row-btn').setAttribute('aria-pressed', checked ? 'true' : 'false');
        form.querySelector('[name=checked]').value = checked ? '0' : '1';
    }

    // Non cochés d'abord (ordre alphabétique conservé), cochés en bas du rayon.
    function sortAisle(aisle) {
        var ul = aisle.querySelector('.checklist');
        var items = Array.prototype.slice.call(ul.children);
        items.sort(function (a, b) {
            var da = a.classList.contains('done') ? 1 : 0, db = b.classList.contains('done') ? 1 : 0;
            if (da !== db) return da - db;
            return a.getAttribute('data-name') < b.getAttribute('data-name') ? -1 : 1;
        });
        items.forEach(function (li) { ul.appendChild(li); });
    }

    function recount() {
        var done = 0, total = 0;
        shop.querySelectorAll('[data-aisle]').forEach(function (aisle) {
            var lis = aisle.querySelectorAll('.checklist li');
            var d = aisle.querySelectorAll('.checklist li.done').length;
            aisle.querySelector('[data-aisle-done]').textContent = d;
            aisle.querySelector('[data-aisle-total]').textContent = lis.length;
            aisle.classList.toggle('complete', lis.length > 0 && d === lis.length);
            done += d; total += lis.length;
        });
        el.done.textContent = done;
        el.total.textContent = total;
        var pct = total ? Math.round(done * 100 / total) : 0;
        el.fill.style.width = pct + '%';
        el.fill.parentNode.setAttribute('aria-valuenow', pct);
        if (el.allDone) el.allDone.hidden = !(total > 0 && done === total);
    }

    function setOffline(on) { if (el.offline) el.offline.hidden = !on; }

    function send(key, value) {
        var body = new URLSearchParams({ _csrf: csrf, d: week, key: key, checked: value });
        return fetch(shop.querySelector('.toggle').action, {
            method: 'POST', body: body, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' }
        }).then(function (r) { if (!r.ok) throw new Error('http ' + r.status); return r; });
    }

    // Envoie les coches en attente ; en cas d'échec réseau on garde tout et on réessaiera.
    function flush() {
        var keys = Object.keys(pending);
        if (syncing || !keys.length) { if (!keys.length) setOffline(false); return; }
        syncing = true;
        var key = keys[0], value = pending[key];
        send(key, value).then(function () {
            if (pending[key] === value) delete pending[key];
            save(storeKey, pending);
            syncing = false;
            flush();
        }).catch(function () {
            syncing = false;
            setOffline(true);
        });
    }

    shop.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form.classList.contains('toggle')) return;
        e.preventDefault();
        var li = form.closest('li');
        var key = li.getAttribute('data-key');
        var value = form.querySelector('[name=checked]').value;      // état voulu
        paint(li, value === '1');
        sortAisle(li.closest('[data-aisle]'));
        recount();
        if (navigator.vibrate) navigator.vibrate(value === '1' ? 15 : 8);
        pending[key] = value;
        save(storeKey, pending);
        flush();
    });

    window.addEventListener('online', flush);
    setInterval(function () { if (Object.keys(pending).length) flush(); }, 10000);

    // Synchronisation avec les autres membres du foyer qui font les courses en même temps.
    function poll() {
        if (document.hidden || Object.keys(pending).length || syncing) return;
        fetch(shop.getAttribute('data-state-url'), { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
            .then(function (r) { if (!r.ok) throw new Error(); return r.json(); })
            .then(function (state) {
                if (Object.keys(pending).length) return;
                var known = shop.querySelectorAll('li[data-key]').length;
                var serverKeys = Object.keys(state.items);
                var touched = {};
                serverKeys.forEach(function (key) {
                    var li = rowOf(key);
                    if (!li) { if (el.changed) el.changed.hidden = false; return; }
                    if (li.classList.contains('done') !== state.items[key]) {
                        paint(li, state.items[key]);
                        touched[li.closest('[data-aisle]').querySelector('h2').textContent] = li.closest('[data-aisle]');
                    }
                });
                if (serverKeys.length !== known && el.changed) el.changed.hidden = false;
                Object.keys(touched).forEach(function (k) { sortAisle(touched[k]); });
                if (Object.keys(touched).length) recount();
                setOffline(false);
            })
            .catch(function () { /* hors connexion : on réessaiera */ });
    }
    setInterval(poll, 12000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });

    // Masquer les articles cochés (préférence mémorisée).
    var hideBtn = shop.querySelector('[data-toggle-done]');
    function setHide(on) {
        shop.classList.toggle('hide-done', on);
        hideBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
        hideBtn.textContent = on ? 'Afficher les cochés' : 'Masquer les cochés';
        save('menu-hide-done', on);
    }
    hideBtn.addEventListener('click', function () { setHide(!shop.classList.contains('hide-done')); });
    setHide(load('menu-hide-done', false) === true);

    // Garder l'écran allumé pendant les courses.
    var lockBtn = shop.querySelector('[data-wakelock]');
    var lock = null;
    function acquire() {
        navigator.wakeLock.request('screen').then(function (l) {
            lock = l;
            l.addEventListener('release', function () { lock = null; });
        }).catch(function () { /* refusé : tant pis */ });
    }
    if (lockBtn && navigator.wakeLock) {
        lockBtn.hidden = false;
        var want = load('menu-wakelock', false) === true;
        var apply = function (on) {
            want = on;
            lockBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
            save('menu-wakelock', on);
            if (on && !lock) acquire();
            if (!on && lock) { lock.release(); lock = null; }
        };
        lockBtn.addEventListener('click', function () { apply(!want); });
        document.addEventListener('visibilitychange', function () { if (!document.hidden && want && !lock) acquire(); });
        lockBtn.setAttribute('aria-pressed', want ? 'true' : 'false');
        if (want) acquire();
    }

    // Coches non envoyées lors d'une visite précédente (réseau coupé) : on les réaffiche puis on les renvoie.
    Object.keys(pending).forEach(function (key) {
        var li = rowOf(key);
        if (li) { paint(li, pending[key] === '1'); sortAisle(li.closest('[data-aisle]')); }
        else delete pending[key];
    });
    recount();
    flush();
})();
