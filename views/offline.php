<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title) ?> · <?= e(config('app.name', 'Kitcho')) ?></title>
    <link rel="icon" href="<?= e(asset('favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset('kitcho.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('style.css')) ?>">
</head>
<body class="k-app">
<main class="container">
    <section class="err-page k-gingham">
        <span class="k-art"><?= icon('wifioff', 'xl') ?></span>
        <h1>Vous êtes hors connexion</h1>
        <p class="k-muted">Cette page n'a pas encore été ouverte sur cet appareil. Les pages déjà consultées, comme votre liste de courses, restent disponibles. Vos coches sont conservées et seront envoyées au retour du réseau.</p>
        <button class="k-btn k-btn--primary" onclick="location.reload()"><?= icon('refresh') ?>Réessayer</button>
    </section>
</main>
</body>
</html>
