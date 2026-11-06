<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';
foreach (['auth', 'households', 'recipes', 'planning', 'shopping', 'pwa', 'onboarding'] as $c) {
    require SRC . '/controllers/' . $c . '.php';
}

// [méthodes, regex du chemin, fonction]. Les groupes capturés sont passés en arguments.
$routes = [
    ['GET',      '#^/$#',                         'home_index'],
    ['GET',      '#^/manifest\.webmanifest$#',   'pwa_manifest'],
    ['GET',      '#^/sw\.js$#',                  'pwa_sw'],
    ['GET',      '#^/offline$#',                  'pwa_offline'],
    ['GET',      '#^/version$#',                  'app_version_json'],
    ['GET|POST', '#^/login$#',                    'auth_login'],
    ['GET|POST', '#^/register$#',                 'auth_register'],
    ['POST',     '#^/logout$#',                   'auth_logout'],
    ['GET|POST', '#^/profile$#',                  'auth_profile'],
    ['GET|POST', '#^/join/([0-9a-f]{40})$#',      'invite_join'],

    ['GET',  '#^/households$#',                   'hh_index'],
    ['POST', '#^/households/create$#',            'hh_create'],
    ['POST', '#^/households/switch$#',            'hh_switch'],
    ['GET',  '#^/household$#',                    'hh_settings'],
    ['POST', '#^/household/update$#',             'hh_update'],
    ['POST', '#^/household/invite$#',             'hh_invite'],
    ['POST', '#^/household/invite/revoke$#',      'hh_invite_revoke'],
    ['POST', '#^/household/member/remove$#',      'hh_member_remove'],
    ['POST', '#^/household/member/role$#',        'hh_member_role'],
    ['POST', '#^/household/leave$#',              'hh_leave'],
    ['POST', '#^/household/delete$#',             'hh_delete'],

    ['GET',      '#^/recipes$#',                  'recipes_index'],
    ['GET|POST', '#^/recipes/new$#',              'recipes_new'],
    ['POST',     '#^/recipes/import$#',           'recipes_import'],
    ['GET|POST', '#^/recipes/import-url$#',       'recipes_import_url'],
    ['POST',     '#^/recipes/(\d+)/refetch$#',    'recipes_refetch'],
    ['GET',      '#^/recipes/export$#',           'recipes_export'],
    ['POST',     '#^/recipes/import-file$#',      'recipes_import_file'],
    ['GET',      '#^/recipes/(\d+)$#',            'recipes_show'],
    ['GET|POST', '#^/recipes/(\d+)/edit$#',       'recipes_edit'],
    ['POST',     '#^/recipes/(\d+)/delete$#',     'recipes_delete'],
    ['POST',     '#^/recipes/(\d+)/favorite$#',   'recipes_favorite'],
    ['POST',     '#^/recipes/(\d+)/copy$#',       'recipes_copy'],

    ['GET',  '#^/week$#',                         'planning_week'],
    ['POST', '#^/onboarding/dismiss$#',           'onboarding_dismiss'],
    ['POST', '#^/onboarding/reset$#',             'onboarding_reset'],
    ['POST', '#^/onboarding/seen$#',              'onboarding_seen'],
    ['GET',  '#^/calendar$#',                     'planning_calendar'],
    ['GET|HEAD', '#^/cal/([0-9a-f]{40})\.ics$#',  'calendar_feed_ics'],
    ['POST', '#^/calendar/feed/create$#',         'calendar_feed_create'],
    ['POST', '#^/calendar/feed/revoke$#',         'calendar_feed_revoke'],
    ['POST', '#^/meals/add$#',                    'meals_add'],
    ['POST', '#^/meals/(\d+)/update$#',           'meals_update'],
    ['POST', '#^/meals/(\d+)/delete$#',           'meals_delete'],
    ['POST', '#^/meals/(\d+)/reroll$#',           'meals_reroll'],
    ['POST', '#^/days/swap$#',                    'days_swap'],
    ['POST', '#^/week/generate$#',                'week_generate'],
    ['POST', '#^/week/copy$#',                    'week_copy'],
    ['POST', '#^/week/clear$#',                   'week_clear'],

    ['GET',  '#^/shopping$#',                     'shopping_index'],
    ['GET',  '#^/shopping/state$#',               'shopping_state'],
    ['POST', '#^/shopping/toggle$#',              'shopping_toggle'],
    ['POST', '#^/shopping/extra$#',               'shopping_extra_add'],
    ['POST', '#^/shopping/extra/delete$#',        'shopping_extra_delete'],
    ['POST', '#^/shopping/reset$#',               'shopping_reset'],

    ['POST', '#^/share/create$#',                 'share_create'],
    ['POST', '#^/share/revoke$#',                 'share_revoke'],
    ['GET',  '#^/s/([0-9a-f]{40})$#',             'share_view'],
];

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$base = base_path();
if ($base !== '' && str_starts_with($path, $base)) {
    $path = substr($path, strlen($base)) ?: '/';
}
if ($path === '/index.php') {
    $path = '/';
}
$path = rtrim($path, '/') ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$pathMatched = false;
foreach ($routes as [$methods, $regex, $handler]) {
    if (!preg_match($regex, $path, $m)) {
        continue;
    }
    $pathMatched = true;
    if (!in_array($method, explode('|', $methods), true)) {
        continue;
    }
    array_shift($m);
    $args = array_map(fn($v) => ctype_digit($v) && strlen($v) < 10 ? (int) $v : $v, $m);
    $handler(...$args);
    exit;
}
abort($pathMatched ? 405 : 404);
