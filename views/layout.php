<?php
/** @var string $content */
/** @var string|null $title */
$user = current_user();
$hh = $user ? current_household() : null;
$here = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$nav = [
    ['/week', 'Semaine', 'week'],
    ['/calendar', 'Calendrier', 'month'],
    ['/shopping', 'Courses', 'basket'],
    ['/recipes', 'Recettes', 'book'],
    ['/household', 'Foyer', 'home'],
];
$isActive = fn(string $p) => str_starts_with($here, url($p)) || ($p === '/week' && $here === url('/'));
$app = config('app.name', 'Kitcho');
?><!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#FBF5EA" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#17110E" media="(prefers-color-scheme: dark)">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e(($title ?? '') !== '' ? $title . ' · ' : '') ?><?= e($app) ?></title>
    <link rel="icon" href="<?= e(asset('favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset('kitcho.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('style.css')) ?>">
    <script src="<?= e(asset('app.js')) ?>" defer></script>
</head>
<body class="k-app<?= $user && $hh ? ' has-tabbar' : '' ?>" data-csrf="<?= e(csrf_token()) ?>">
<a class="skip-link" href="#main">Aller au contenu</a>

<header class="k-topbar app-header">
    <?= logo() ?>
    <?php if ($user && $hh): ?>
        <nav class="k-nav" aria-label="Navigation principale">
            <?php foreach ($nav as [$p, $label, $ic]): ?>
                <a href="<?= e(url($p)) ?>" <?= $isActive($p) ? 'aria-current="page"' : '' ?>><?= icon($ic, 'sm') ?><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>
    <div class="app-header__right">
        <?php if ($user): ?>
            <?php $all = user_households((int) $user['id']); ?>
            <?php if (count($all) > 1 && $hh): ?>
                <form method="post" action="<?= e(url('/households/switch')) ?>" class="inline">
                    <?= csrf_field() ?>
                    <span class="k-selectwrap hh-switch">
                        <select class="k-select" name="id" data-autosubmit aria-label="Foyer actif">
                            <?php foreach ($all as $h): ?>
                                <option value="<?= (int) $h['id'] ?>" <?= (int) $h['id'] === (int) $hh['id'] ? 'selected' : '' ?>><?= e($h['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= icon('down', 'sm') ?>
                    </span>
                </form>
            <?php elseif ($hh): ?>
                <span class="k-muted hide-sm"><?= e($hh['name']) ?></span>
            <?php endif; ?>
            <a class="user-link" href="<?= e(url('/profile')) ?>" aria-label="Mon profil">
                <?= icon('user') ?><span class="hide-sm"><?= e($user['name']) ?></span>
            </a>
            <form method="post" action="<?= e(url('/logout')) ?>" class="inline">
                <?= csrf_field() ?>
                <button class="k-iconbtn" aria-label="Déconnexion" title="Déconnexion"><?= icon('logout') ?></button>
            </form>
        <?php endif; ?>
    </div>
</header>

<main class="container" id="main">
    <?php foreach (flashes() as $f): [$cls, $ic] = banner_variant($f['type']); ?>
        <div class="k-banner <?= $cls ?> flash" role="<?= $f['type'] === 'error' ? 'alert' : 'status' ?>">
            <?= icon($ic) ?><div class="k-banner__body"><?= e($f['message']) ?></div>
        </div>
    <?php endforeach; ?>
    <?= $content ?>
</main>

<?php if ($user && $hh): ?>
    <nav class="k-tabbar" aria-label="Navigation">
        <?php foreach ($nav as [$p, $label, $ic]): ?>
            <a class="k-tab<?= $p === '/shopping' ? ' k-tab--cta' : '' ?>" href="<?= e(url($p)) ?>" <?= $isActive($p) ? 'aria-current="page"' : '' ?>>
                <span class="k-tab__ico"><?= icon($ic) ?></span><?= e($label) ?>
            </a>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>
</body>
</html>
