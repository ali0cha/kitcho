<?php
/** @var string $content */
/** @var string|null $title */
$user = current_user();
$hh = $user ? current_household() : null;
$here = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$nav = [
    ['/week', 'Semaine'],
    ['/calendar', 'Calendrier'],
    ['/shopping', 'Courses'],
    ['/recipes', 'Recettes'],
    ['/household', 'Foyer'],
];
$isActive = fn(string $p) => str_starts_with($here, url($p)) || ($p === '/week' && $here === url('/'));
?><!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#2f7d4f">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e(($title ?? '') !== '' ? $title . ' · ' : '') ?><?= e(config('app.name', 'Kitcho')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('style.css')) ?>">
    <script src="<?= e(asset('app.js')) ?>" defer></script>
</head>
<body data-csrf="<?= e(csrf_token()) ?>">
<header class="topbar">
    <a class="brand" href="<?= e(url('/')) ?>"><?= e(config('app.name', 'Kitcho')) ?></a>
    <?php if ($user && $hh): ?>
        <nav class="nav" aria-label="Navigation principale">
            <?php foreach ($nav as [$p, $label]): ?>
                <a href="<?= e(url($p)) ?>" <?= $isActive($p) ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>
    <div class="topbar-right">
        <?php if ($user): ?>
            <?php $all = user_households((int) $user['id']); ?>
            <?php if (count($all) > 1 && $hh): ?>
                <form method="post" action="<?= e(url('/households/switch')) ?>" class="inline">
                    <?= csrf_field() ?>
                    <select name="id" data-autosubmit aria-label="Foyer actif">
                        <?php foreach ($all as $h): ?>
                            <option value="<?= (int) $h['id'] ?>" <?= (int) $h['id'] === (int) $hh['id'] ? 'selected' : '' ?>><?= e($h['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            <?php elseif ($hh): ?>
                <span class="muted small"><?= e($hh['name']) ?></span>
            <?php endif; ?>
            <a href="<?= e(url('/profile')) ?>" class="small"><?= e($user['name']) ?></a>
            <form method="post" action="<?= e(url('/logout')) ?>" class="inline">
                <?= csrf_field() ?>
                <button class="btn btn-link small">Déconnexion</button>
            </form>
        <?php endif; ?>
    </div>
</header>

<main class="container">
    <?php foreach (flashes() as $f): ?>
        <div class="flash flash-<?= e($f['type']) ?>" role="status"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
</main>
<footer class="footer muted small"><?= e(config('app.name', 'Kitcho')) ?> · planifiez vos repas, partagez vos courses</footer>
</body>
</html>
