(function () {
    'use strict';

    function load(k, d) { try { var v = JSON.parse(localStorage.getItem(k)); return v === null ? d : v; } catch (e) { return d; } }
    function save(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) { /* ignore */ } }
    function $(sel, root) { return (root || document).querySelector(sel); }
    function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

    // ------------------------------------------------------------------ général

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
            var old = btn.innerHTML;
            btn.textContent = 'Copié ✓';
            setTimeout(function () { btn.innerHTML = old; }, 1500);
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
        // Referme le menu « ⋯ » quand on clique ailleurs.
        $$('details.more[open]').forEach(function (d) { if (!d.contains(e.target)) d.removeAttribute('open'); });
    });

    // ------------------------------------------------------------------ application installable (PWA)

    var base = document.body.getAttribute('data-base') || '';
    if ('serviceWorker' in navigator && (window.isSecureContext || location.hostname === 'localhost')) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register(base + '/sw.js', { scope: base + '/' }).catch(function () { /* non bloquant */ });
        });
        // Sur une page publique (connexion…) : on efface les pages privées gardées pour le hors ligne.
        if (document.body.hasAttribute('data-guest')) {
            navigator.serviceWorker.ready.then(function (reg) { if (reg.active) reg.active.postMessage('clear-pages'); });
        }
    }

    // Premiers pas : on signale au serveur que l'application tourne installée (plein écran).
    try {
        var isInstalled = window.matchMedia('(display-mode: fullscreen), (display-mode: standalone)').matches || navigator.standalone === true;
        if (isInstalled && !document.body.hasAttribute('data-guest') && !sessionStorage.getItem('kitcho-install-seen')) {
            fetch(base + '/onboarding/seen', {
                method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' },
                body: new URLSearchParams({ _csrf: document.body.getAttribute('data-csrf'), step: 'install' })
            }).then(function (r) { if (r.ok) sessionStorage.setItem('kitcho-install-seen', '1'); }).catch(function () { /* hors ligne : tant pis */ });
        }
    } catch (err) { /* stockage indisponible */ }

    var installCard = $('[data-pwa-card]');
    if (installCard) {
        var installed = window.matchMedia('(display-mode: fullscreen), (display-mode: standalone)').matches || navigator.standalone === true;
        var installBtn = $('[data-pwa-install]', installCard);
        var deferred = null;
        $('[data-pwa-status]', installCard).hidden = !installed;
        if (installed) { $$('[data-pwa-howto]', installCard).forEach(function (n) { n.hidden = true; }); }
        window.addEventListener('beforeinstallprompt', function (e) {
            e.preventDefault();
            deferred = e;
            if (installBtn && !installed) installBtn.hidden = false;
        });
        if (installBtn) {
            installBtn.addEventListener('click', function () {
                if (!deferred) return;
                deferred.prompt();
                deferred.userChoice.then(function () { deferred = null; installBtn.hidden = true; });
            });
        }
        window.addEventListener('appinstalled', function () { if (installBtn) installBtn.hidden = true; $('[data-pwa-status]', installCard).hidden = false; });
    }

    // ------------------------------------------------------------------ recettes : filtrage pendant la frappe

    var filterForm = $('[data-live-filter]');
    if (filterForm) {
        var input = $('[data-filter-input]', filterForm);
        var catSel = $('[data-filter-cat]', filterForm);
        var rows = $$('[data-filter-row]');
        var countEl = $('[data-filter-count]');
        var emptyEl = $('[data-filter-empty]');
        var fold = function (s) {
            return s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/\s+/g, ' ').trim();
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
                countEl.textContent = (words.length || cat) ? shown + ' / ' + total : total;
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
            filterForm.addEventListener('submit', function (e) { e.preventDefault(); });
            applyFilter();
        }
    }

    // ------------------------------------------------------------------ calendrier : détail du jour sur mobile

    var cal = $('[data-cal]');
    if (cal) {
        var narrow = window.matchMedia('(max-width: 700px)');
        var selectDay = function (date) {
            $$('.cal-day', cal).forEach(function (a) { a.classList.toggle('is-selected', a.getAttribute('data-date') === date); });
            $$('[data-cal-detail]').forEach(function (el) { el.hidden = el.getAttribute('data-cal-detail') !== date; });
        };
        cal.addEventListener('click', function (e) {
            var a = e.target.closest('.cal-day');
            if (!a || !narrow.matches) return;
            e.preventDefault();
            selectDay(a.getAttribute('data-date'));
        });
        var start = $('.cal-day.is-today', cal) || $('.cal-day:not(.is-outside)', cal);
        if (start) selectDay(start.getAttribute('data-date'));
    }

    // ------------------------------------------------------------------ page d'accueil : maquette vivante

    var demo = $('[data-demo]');
    if (demo && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        var demoRows = $$('[data-demo-row]', demo);
        var demoInputs = demoRows.map(function (r) { return $('input', r); });
        var demoStart = demoInputs.map(function (i) { return i.checked; });
        var demoNum = $('[data-demo-done]', demo), demoFill = $('[data-demo-fill]', demo);
        var demoPaint = function () {
            var done = demoInputs.filter(function (i) { return i.checked; }).length;
            demoNum.textContent = done;
            demoFill.style.width = Math.round(done * 100 / demoInputs.length) + '%';
        };
        var demoWait = 0;
        setInterval(function () {
            if (document.hidden) return;
            if (demoWait > 0) { demoWait--; return; }
            var next = demoInputs.filter(function (i) { return !i.checked; })[0];
            if (next) { next.checked = true; demoPaint(); if (demoInputs.every(function (i) { return i.checked; })) demoWait = 2; }
            else { demoInputs.forEach(function (i, n) { i.checked = demoStart[n]; }); demoPaint(); }
        }, 1500);
    }

    // ------------------------------------------------------------------ liste de courses

    var shop = $('.shop');
    if (!shop) return;

    var week = shop.getAttribute('data-week');
    var csrf = document.body.getAttribute('data-csrf');
    var storeKey = 'kitcho-pending-' + week;
    var pending = load(storeKey, {});   // clé article -> état voulu ('1' / '0'), en attente d'envoi
    var syncing = false;

    var el = {
        done: $('[data-done]', shop),
        total: $('[data-total]', shop),
        fill: $('[data-progress-fill]', shop),
        offline: $('[data-offline]', shop),
        expired: $('[data-expired]', shop),
        changed: $('[data-changed]', shop),
        allDone: $('[data-alldone]', shop)
    };

    function allRows() { return $$('.k-row[data-key]', shop); }
    function rowOf(key) { return $('.k-row[data-key="' + key + '"]', shop); }
    function inputOf(row) { return $('.k-row__input', row); }
    function aisleOf(row) { return row.closest('[data-aisle]'); }

    // Non cochés d'abord (ordre alphabétique conservé), cochés en bas du rayon.
    function sortAisle(aisle) {
        var body = $('.k-aisle__body', aisle);
        var rows = $$('.k-row', body);
        rows.sort(function (a, b) {
            var da = inputOf(a).checked ? 1 : 0, db = inputOf(b).checked ? 1 : 0;
            if (da !== db) return da - db;
            return a.getAttribute('data-name') < b.getAttribute('data-name') ? -1 : 1;
        });
        rows.forEach(function (r) { body.appendChild(r); });
    }

    function recount() {
        var done = 0, total = 0;
        $$('[data-aisle]', shop).forEach(function (aisle) {
            var rows = $$('.k-row', aisle);
            var d = rows.filter(function (r) { return inputOf(r).checked; }).length;
            $('[data-aisle-done]', aisle).textContent = d;
            $('[data-aisle-total]', aisle).textContent = rows.length;
            var full = rows.length > 0 && d === rows.length;
            aisle.classList.toggle('is-done', full);
            $('.k-count', aisle).classList.toggle('k-count--done', full);
            done += d; total += rows.length;
        });
        el.done.textContent = done;
        el.total.textContent = total;
        var pct = total ? Math.round(done * 100 / total) : 0;
        el.fill.style.width = pct + '%';
        el.fill.parentNode.setAttribute('aria-valuenow', pct);
        if (el.allDone) el.allDone.hidden = !(total > 0 && done === total);
    }

    function setOffline(on) { if (el.offline) el.offline.hidden = !on; }
    function setExpired(on) { if (el.expired) el.expired.hidden = !on; }

    function send(key, value) {
        var body = new URLSearchParams({ _csrf: csrf, d: week, key: key, checked: value });
        return fetch(shop.getAttribute('data-toggle-url'), {
            method: 'POST', body: body, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' }
        }).then(function (r) {
            if (r.status === 401) { var e = new Error('auth'); e.auth = true; throw e; }
            if (!r.ok) throw new Error('http ' + r.status);
            return r;
        });
    }

    // Envoie les coches en attente ; en cas d'échec réseau on garde tout et on réessaiera.
    function flush() {
        var keys = Object.keys(pending);
        if (syncing || !keys.length) { if (!keys.length) setOffline(false); return; }
        syncing = true;
        var key = keys[0], value = pending[key];
        send(key, value).then(function () {
            setExpired(false);
            if (pending[key] === value) delete pending[key];
            save(storeKey, pending);
            syncing = false;
            flush();
        }).catch(function (err) {
            syncing = false;
            if (err && err.auth) { setOffline(false); setExpired(true); } else { setOffline(true); }
        });
    }

    var collapseTimers = new WeakMap();
    shop.addEventListener('change', function (e) {
        var input = e.target;
        if (!input.classList.contains('k-row__input')) return;
        var row = input.closest('.k-row');
        var aisle = aisleOf(row);
        var checked = input.checked;
        sortAisle(aisle);
        input.focus({ preventScroll: true });
        recount();
        if (navigator.vibrate) navigator.vibrate(12);
        pending[row.getAttribute('data-key')] = checked ? '1' : '0';
        save(storeKey, pending);
        flush();
        // Rayon terminé : on le replie après un court instant (annulé si on décoche).
        clearTimeout(collapseTimers.get(aisle));
        if (checked && aisle.classList.contains('is-done') && !shop.classList.contains('store-mode')) {
            collapseTimers.set(aisle, setTimeout(function () {
                if (aisle.classList.contains('is-done')) setClosed(aisle, true);
            }, 900));
        }
    });

    function setClosed(aisle, closed) {
        aisle.classList.toggle('is-closed', closed);
        $('.k-aisle__head', aisle).setAttribute('aria-expanded', closed ? 'false' : 'true');
    }

    shop.addEventListener('click', function (e) {
        var head = e.target.closest('.k-aisle__head');
        if (head && !shop.classList.contains('store-mode')) {
            var aisle = head.closest('[data-aisle]');
            setClosed(aisle, !aisle.classList.contains('is-closed'));
            return;
        }
        var del = e.target.closest('[data-extra-del]');
        if (del) {
            var row = del.closest('.k-row');
            var name = $('.k-row__line', row).textContent.trim();
            if (!window.confirm('Supprimer « ' + name + ' » de la liste ?')) return;
            fetch(shop.getAttribute('data-extra-del-url'), {
                method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' },
                body: new URLSearchParams({ _csrf: csrf, d: week, id: del.getAttribute('data-extra-del') })
            }).then(function (r) {
                if (!r.ok) throw new Error();
                var aisle = aisleOf(row);
                row.remove();
                if (!$$('.k-row', aisle).length) aisle.remove(); else recount();
                recount();
            }).catch(function () { window.location.reload(); });
        }
    });

    window.addEventListener('online', flush);
    setInterval(function () { if (Object.keys(pending).length) flush(); }, 10000);

    // Synchronisation avec les autres membres du foyer qui font les courses en même temps.
    function poll() {
        if (document.hidden || Object.keys(pending).length || syncing) return;
        fetch(shop.getAttribute('data-state-url'), { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
            .then(function (r) {
                if (r.status === 401) { var e = new Error('auth'); e.auth = true; throw e; }
                if (!r.ok) throw new Error();
                return r.json();
            })
            .then(function (state) {
                if (Object.keys(pending).length) return;
                var known = allRows().length;
                var serverKeys = Object.keys(state.items);
                var touched = [];
                serverKeys.forEach(function (key) {
                    var row = rowOf(key);
                    if (!row) { if (el.changed) el.changed.hidden = false; return; }
                    var input = inputOf(row);
                    if (input.checked !== state.items[key]) {
                        input.checked = state.items[key];
                        var a = aisleOf(row);
                        if (touched.indexOf(a) === -1) touched.push(a);
                    }
                });
                if (serverKeys.length !== known && el.changed) el.changed.hidden = false;
                touched.forEach(sortAisle);
                if (touched.length) recount();
                setOffline(false);
                setExpired(false);
            })
            .catch(function (err) { if (err && err.auth) setExpired(true); /* sinon hors connexion : on réessaiera */ });
    }
    setInterval(poll, 12000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });

    // Masquer les articles cochés (préférence mémorisée).
    var hideBtn = $('[data-toggle-done]', shop);
    function setHide(on) {
        shop.classList.toggle('hide-done', on);
        hideBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
        save('kitcho-hide-done', on);
    }
    hideBtn.addEventListener('click', function () { setHide(!shop.classList.contains('hide-done')); });
    setHide(load('kitcho-hide-done', false) === true);

    // Garder l'écran allumé pendant les courses.
    var lockBtn = $('[data-wakelock]', shop);
    var lock = null;
    function acquire() {
        navigator.wakeLock.request('screen').then(function (l) {
            lock = l;
            l.addEventListener('release', function () { lock = null; });
        }).catch(function () { /* refusé : tant pis */ });
    }
    if (lockBtn && navigator.wakeLock) {
        lockBtn.hidden = false;
        var want = load('kitcho-wakelock', false) === true;
        var apply = function (on) {
            want = on;
            lockBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
            save('kitcho-wakelock', on);
            if (on && !lock) acquire();
            if (!on && lock) { lock.release(); lock = null; }
        };
        lockBtn.addEventListener('click', function () { apply(!want); });
        document.addEventListener('visibilitychange', function () { if (!document.hidden && want && !lock) acquire(); });
        lockBtn.setAttribute('aria-pressed', want ? 'true' : 'false');
        if (want) acquire();
    }

    // Mode magasin : plein écran, un rayon à la fois, gros boutons.
    var storeBtn = $('[data-store-mode]', shop);
    var storeBar = $('.store-bar', shop);
    if (storeBtn && storeBar) {
        var cur = 0;
        var aisles = function () { return $$('[data-aisle]', shop); };
        var showAisle = function (i) {
            var list = aisles();
            cur = Math.max(0, Math.min(i, list.length - 1));
            list.forEach(function (a, idx) {
                a.classList.toggle('is-current', idx === cur);
                if (idx === cur) setClosed(a, false);
            });
            var last = cur === list.length - 1;
            $('[data-store-next-label]', storeBar).textContent = last ? 'Terminer' : 'Suivant · ' + $('.k-aisle__name', list[cur + 1]).textContent;
            $('[data-store-prev]', storeBar).disabled = cur === 0;
            window.scrollTo(0, 0);
        };
        var enter = function () {
            var list = aisles();
            if (!list.length) return;
            shop.classList.add('store-mode');
            document.body.classList.add('store-on');
            storeBar.hidden = false;
            var first = 0;
            for (var i = 0; i < list.length; i++) { if (!list[i].classList.contains('is-done')) { first = i; break; } }
            showAisle(first);
        };
        var leave = function () {
            shop.classList.remove('store-mode');
            document.body.classList.remove('store-on');
            storeBar.hidden = true;
            aisles().forEach(function (a) { a.classList.remove('is-current'); });
            window.scrollTo(0, 0);
        };
        storeBtn.addEventListener('click', enter);
        $('[data-store-exit]', storeBar).addEventListener('click', leave);
        $('[data-store-prev]', storeBar).addEventListener('click', function () { showAisle(cur - 1); });
        $('[data-store-next]', storeBar).addEventListener('click', function () {
            if (cur >= aisles().length - 1) leave(); else showAisle(cur + 1);
        });
    }

    // Coches non envoyées lors d'une visite précédente (réseau coupé) : on les réaffiche puis on les renvoie.
    Object.keys(pending).forEach(function (key) {
        var row = rowOf(key);
        if (row) { inputOf(row).checked = pending[key] === '1'; sortAisle(aisleOf(row)); }
        else delete pending[key];
    });
    recount();
    flush();
})();
