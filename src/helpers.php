<?php
declare(strict_types=1);

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function config(string $path, mixed $default = null): mixed
{
    $v = $GLOBALS['config'];
    foreach (explode('.', $path) as $k) {
        if (!is_array($v) || !array_key_exists($k, $v)) {
            return $default;
        }
        $v = $v[$k];
    }
    return $v;
}

/** Dossier d'installation relatif à la racine du site, sans slash final ("" ou "/menu"). */
function base_path(): string
{
    return rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
}

function url(string $path = '/', array $query = []): string
{
    $u = base_path() . $path;
    if ($query) {
        $u .= '?' . http_build_query($query);
    }
    return $u === '' ? '/' : $u;
}

function abs_url(string $path = '/'): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url($path);
}

function asset(string $file): string
{
    $f = ROOT . '/assets/' . $file;
    return url('/assets/' . $file) . (is_file($f) ? '?v=' . filemtime($f) : '');
}

function redirect(string $path, array $query = []): never
{
    header('Location: ' . url($path, $query));
    exit;
}

/** Chemin interne de l'appli (ex. "/recipes?q=x") issu d'un champ de formulaire, sinon $fallback. */
function app_path(string $p, string $fallback): string
{
    return preg_match('#^/(?![/\\\\])[^\r\n]*$#', $p) ? $p : $fallback;
}

/** Chemin + query de la requête courante, relatif à l'appli (ex. "/recipes?q=x"). */
function request_app_path(): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $base = base_path();
    return $base !== '' && str_starts_with($uri, $base) ? (substr($uri, strlen($base)) ?: '/') : $uri;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function input(string $key, mixed $default = null): mixed
{
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

function input_str(string $key, string $default = ''): string
{
    $v = input($key, $default);
    return is_string($v) ? trim($v) : $default;
}

function input_int(string $key, ?int $default = null): ?int
{
    $v = input($key);
    return is_string($v) && preg_match('/^-?\d+$/', $v) ? (int) $v : $default;
}

// ---------------------------------------------------------------- flash / csrf

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        abort(419, 'Session expirée ou requête invalide. Rechargez la page et réessayez.');
    }
}

/** À appeler en tête de tout handler POST. */
function require_post(): void
{
    if (!is_post()) {
        abort(405, 'Méthode non autorisée.');
    }
    csrf_verify();
}

function abort(int $code, string $message = ''): never
{
    http_response_code($code);
    $defaults = [403 => 'Accès refusé.', 404 => 'Page introuvable.', 405 => 'Méthode non autorisée.', 419 => 'Session expirée.'];
    render('error', ['code' => $code, 'message' => $message ?: ($defaults[$code] ?? 'Erreur.'), 'detail' => null]);
    exit;
}

// ---------------------------------------------------------------- vues

function render(string $view, array $data = [], bool $layout = true): void
{
    extract($data, EXTR_SKIP);
    ob_start();
    require ROOT . '/views/' . $view . '.php';
    $content = ob_get_clean();
    if ($layout) {
        require ROOT . '/views/layout.php';
    } else {
        echo $content;
    }
}

function json_response(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function wants_json(): bool
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
}

function new_token(): string
{
    return bin2hex(random_bytes(20));
}

/** Retourne l'URL si elle est http(s), sinon null (évite javascript: dans les liens). */
function safe_url(?string $u): ?string
{
    $u = trim((string) $u);
    return preg_match('#^https?://#i', $u) ? $u : null;
}

function plural(int|float $n, string $one, string $many): string
{
    return $n > 1 ? $many : $one;
}

// ---------------------------------------------------------------- design system (kitcho.css)

/** Pictogramme (masque CSS) : <i class="k-i i-check">. $size : '' | 'sm' | 'lg' | 'xl'. */
function icon(string $name, string $size = '', string $extra = ''): string
{
    return '<i class="k-i' . ($size !== '' ? ' k-i--' . $size : '') . ' i-' . $name . ($extra !== '' ? ' ' . $extra : '') . '" aria-hidden="true"></i>';
}

const CATEGORY_STYLE = [
    'vegetarien' => ['cat-veg', 'leaf'],
    'poisson'    => ['cat-fish', 'fish'],
    'viande'     => ['cat-meat', 'meat'],
    'soupe'      => ['cat-soup', 'soup'],
    'salade'     => ['cat-salad', 'salad'],
    'autre'      => ['cat-other', 'plate'],
];

const AISLE_ICONS = [
    'Fruits & légumes' => 'apple', 'Boulangerie' => 'bread', 'Boucherie & poissonnerie' => 'meat', 'Crèmerie' => 'milk',
    'Épicerie' => 'jar', 'Surgelés' => 'snow', 'Boissons' => 'glass', 'Autre' => 'bag',
];

function cat_class(string $category): string
{
    return (CATEGORY_STYLE[$category] ?? CATEGORY_STYLE['autre'])[0];
}

function cat_icon(string $category): string
{
    return (CATEGORY_STYLE[$category] ?? CATEGORY_STYLE['autre'])[1];
}

function cat_badge(string $category): string
{
    return '<span class="k-badge ' . cat_class($category) . '">' . icon(cat_icon($category)) . e(CATEGORIES[$category] ?? 'Autre') . '</span>';
}

/** Vignette de recette sans photo : couleur + motif + pictogramme de la catégorie. */
function thumb(string $category, bool $large = false): string
{
    return '<span class="k-thumb ' . cat_class($category) . ($large ? ' k-thumb--lg' : '') . '">' . icon(cat_icon($category)) . '</span>';
}

function aisle_icon(string $aisle): string
{
    return AISLE_ICONS[$aisle] ?? 'bag';
}

/** Type de message flash → variante de bandeau et pictogramme. */
function banner_variant(string $type): array
{
    return match ($type) {
        'success' => ['k-banner--ok', 'check'],
        'error'   => ['k-banner--err', 'warn'],
        default   => ['', 'info'],
    };
}

/** Logo : cocotte cochée + mot-symbole. */
function logo(bool $withText = true): string
{
    return '<a class="k-logo" href="' . e(url('/')) . '" aria-label="' . e(config('app.name', 'Kitcho')) . ' — accueil">'
        . icon('pot', 'lg', 'k-logo__mark') . ($withText ? '<span>' . e(config('app.name', 'Kitcho')) . '</span>' : '') . '</a>';
}

/** État vide : cocotte (sans coche) sur un rond vichy. */
function empty_art(): string
{
    return '<span class="k-art k-gingham" aria-hidden="true">' . icon('pot0', 'xl') . '</span>';
}

/** Bandeau vichy d'accueil (connexion, inscription, invitation). */
function hero(string $tagline = 'Planifiez vos repas, partagez vos courses.'): string
{
    return '<div class="k-hero k-gingham"><span class="k-logo k-logo--xl">' . icon('pot', 'xl', 'k-logo__mark')
        . '<span>' . e(config('app.name', 'Kitcho')) . '</span></span><p class="k-muted">' . e($tagline) . '</p></div>';
}

/** Version construite (fichier VERSION écrit au build) : commit Git complet, court, date. « dev » hors image. */
function app_version(): array
{
    static $v = null;
    if ($v === null) {
        $lines = is_file(ROOT . '/VERSION') ? array_map('trim', file(ROOT . '/VERSION')) : [];
        $sha = preg_match('/^[0-9a-f]{40}$/', $lines[0] ?? '') ? $lines[0] : null;
        $v = [
            'sha'   => $sha,
            'short' => $sha ? substr($sha, 0, 7) : (($lines[0] ?? '') ?: 'dev'),
            'date'  => ($lines[1] ?? '') !== '' ? $lines[1] : null,
        ];
    }
    return $v;
}
