<?php
declare(strict_types=1);

/** Manifeste d'application web : plein écran, icône, raccourcis. Généré pour s'adapter au sous-dossier d'installation. */
function pwa_manifest(): void
{
    $base = base_path();
    $scope = $base === '' ? '/' : $base . '/';
    $name = (string) config('app.name', 'Kitcho');
    $manifest = [
        'id'               => $scope,
        'name'             => $name,
        'short_name'       => $name,
        'description'      => 'Planifiez vos repas, partagez vos courses.',
        'lang'             => 'fr',
        'dir'              => 'ltr',
        'start_url'        => url('/week', ['source' => 'pwa']),
        'scope'            => $scope,
        'display'          => 'fullscreen',
        'display_override' => ['fullscreen', 'standalone'],
        'background_color' => '#FBF5EA',
        'theme_color'      => '#FBF5EA',
        'categories'       => ['food', 'lifestyle'],
        'icons'            => [
            ['src' => url('/assets/icons/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => url('/assets/icons/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => url('/assets/icons/icon-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
        ],
        'shortcuts'        => [
            ['name' => 'Courses', 'url' => url('/shopping', ['source' => 'shortcut']), 'icons' => [['src' => url('/assets/icons/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png']]],
            ['name' => 'Semaine', 'url' => url('/week', ['source' => 'shortcut']), 'icons' => [['src' => url('/assets/icons/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png']]],
            ['name' => 'Recettes', 'url' => url('/recipes', ['source' => 'shortcut']), 'icons' => [['src' => url('/assets/icons/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png']]],
        ],
    ];
    header('Content-Type: application/manifest+json; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    echo json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Service worker :
 *  - fichiers statiques (CSS, JS, icônes) : en cache, servis instantanément ;
 *  - pages : le réseau d'abord (3,5 s max), sinon la dernière version vue, sinon la page « hors connexion » ;
 *  - jamais de cache pour la connexion, les données de fond, les exports ni les pages avec message flash.
 * Servi par PHP (et non comme fichier statique) pour que sa portée suive le dossier d'installation.
 */
function pwa_sw(): void
{
    $base = base_path();
    $scope = $base === '' ? '/' : $base . '/';
    $static = array_map('asset', ['kitcho.css', 'style.css', 'app.js', 'favicon.svg', 'icons/icon-192.png', 'icons/icon-512.png', 'icons/apple-touch-icon.png']);
    $offline = url('/offline');
    $version = substr(sha1(implode('|', $static) . '|v1'), 0, 10);
    $cfg = json_encode([
        'version' => $version, 'scope' => $scope, 'offline' => $offline, 'precache' => array_merge($static, [$offline]),
    ], JSON_UNESCAPED_SLASHES);

    header('Content-Type: application/javascript; charset=utf-8');
    header('Cache-Control: no-cache');
    echo "const CFG = $cfg;\n";
    echo <<<'JS'
const STATIC = 'kitcho-static-' + CFG.version;
const PAGES = 'kitcho-pages';
const MAX_PAGES = 60;
const NEVER_CACHE = ['/login', '/register', '/logout', '/join/', '/s/', '/offline'];
const BYPASS = ['/shopping/state', '/recipes/export', '/sw.js', '/manifest.webmanifest', '/version'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(STATIC).then((c) => c.addAll(CFG.precache)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        for (const key of await caches.keys()) {
            if (key.startsWith('kitcho-static-') && key !== STATIC) await caches.delete(key);
        }
        await self.clients.claim();
    })());
});

// Déconnexion : on efface les pages mises en cache (elles contiennent des données du foyer).
self.addEventListener('message', (event) => {
    if (event.data === 'clear-pages') event.waitUntil(caches.delete(PAGES));
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (url.origin !== self.location.origin || !url.pathname.startsWith(CFG.scope)) return;
    const rel = url.pathname.slice(CFG.scope.length - 1);       // « /week »

    if (rel.startsWith('/assets/')) {
        event.respondWith(caches.match(req).then((hit) => hit || fetch(req).then((res) => {
            if (res.ok) { const copy = res.clone(); caches.open(STATIC).then((c) => c.put(req, copy)); }
            return res;
        })));
        return;
    }
    if (BYPASS.includes(rel) || rel.startsWith('/cal/')) return;
    if (req.mode === 'navigate') event.respondWith(pageStrategy(req, rel));
});

function cacheable(rel, res) {
    return res.ok && !res.redirected && res.type === 'basic' && rel !== '/'
        && !res.headers.has('X-Kitcho-Flash')
        && (res.headers.get('Content-Type') || '').includes('text/html')
        && !NEVER_CACHE.some((p) => rel === p || rel.startsWith(p));
}

async function pageStrategy(req, rel) {
    const cache = await caches.open(PAGES);
    const network = fetch(req).then(async (res) => {
        if (cacheable(rel, res)) {
            await cache.put(req, res.clone());
            const keys = await cache.keys();
            if (keys.length > MAX_PAGES) await cache.delete(keys[0]);
        }
        return res;
    });
    try {
        return await Promise.race([network, new Promise((_, reject) => setTimeout(() => reject(new Error('timeout')), 3500))]);
    } catch (err) {
        network.catch(() => {});                                  // la requête continue et met le cache à jour
        const hit = (await cache.match(req)) || (await cache.match(req, { ignoreSearch: true }));
        if (hit) return hit;
        return (await caches.match(CFG.offline)) || Response.error();
    }
}
JS;
    exit;
}

/** Page affichée quand le réseau manque et qu'aucune version de la page demandée n'est en mémoire. */
function pwa_offline(): void
{
    header('Cache-Control: no-cache');
    render('offline', ['title' => 'Hors connexion'], false);
    exit;
}

/** Version déployée, pour vérifier que le NAS et le dépôt sont alignés (voir bin/check_deploy.sh). */
function app_version_json(): void
{
    $v = app_version();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');
    echo json_encode(['app' => config('app.name', 'Kitcho'), 'sha' => $v['sha'], 'short' => $v['short'], 'built' => $v['date']], JSON_UNESCAPED_UNICODE);
    exit;
}
