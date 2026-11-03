<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#FBF5EA" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#17110E" media="(prefers-color-scheme: dark)">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title) ?></title>
    <link rel="icon" href="<?= e(asset('favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset('kitcho.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('style.css')) ?>">
</head>
<body class="k-app">
<div class="k-hero k-gingham share-hero">
    <span class="k-logo"><?= icon('pot', 'lg', 'k-logo__mark') ?><span><?= e(config('app.name', 'Kitcho')) ?></span></span>
    <p class="k-eyebrow">Menu partagé par <?= e($s['household_name']) ?></p>
</div>
<main class="container">
    <h1 class="page-title">Semaine du <?= e($monday->format('j')) ?> <?= e(month_name($monday)) ?></h1>
    <p class="k-muted"><?= e(fmt_week_range($monday)) ?> · consultation seule</p>

    <div class="share-cols">
        <section class="k-card k-card--pad">
            <h2>Menu</h2>
            <?php $any = false; foreach ($days as $d): $ds = ymd($d); if (empty($byDate[$ds])) continue; $any = true; ?>
                <h3 class="share-day"><?= e(fmt_day($d)) ?></h3>
                <ul class="plain list-rows">
                    <?php foreach ($byDate[$ds] as $m): ?>
                        <li><span><span class="k-meal__slot"><?= icon($m['slot'] === 'lunch' ? 'sun' : 'moon', 'sm') ?><?= e(SLOTS[$m['slot']]) ?></span><br>
                            <?php if ($u = safe_url($m['url'])): ?><a class="k-meal__name" href="<?= e($u) ?>" target="_blank" rel="noopener noreferrer"><?= e($m['name']) ?></a><?php else: ?><b><?= e($m['name']) ?></b><?php endif; ?>
                            <?= $m['notes'] ? '<span class="k-meta">· ' . e($m['notes']) . '</span>' : '' ?></span></li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; if (!$any): ?><p class="k-muted">Rien de prévu cette semaine.</p><?php endif; ?>
        </section>
        <section class="k-card k-card--pad">
            <h2>Courses</h2>
            <?php foreach ($list['groups'] as $aisle => $items): ?>
                <h3 class="share-day"><?= icon(aisle_icon($aisle), 'sm') ?> <?= e($aisle) ?></h3>
                <ul class="plain share-list">
                    <?php foreach ($items as $it): $q = item_quantity_text($it); ?>
                        <li class="<?= $it['checked'] ? 'is-checked' : '' ?>"><span class="k-box k-box--sm<?= $it['checked'] ? ' is-on' : '' ?>"><?= icon('check') ?></span>
                            <span><?php if ($q !== ''): ?><b class="k-qty"><?= e($q) ?></b><?php endif; ?><?= e($it['name']) ?></span></li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; if (!$list['total']): ?><p class="k-muted">Aucun article.</p><?php endif; ?>
        </section>
    </div>
    <p class="k-meta center share-foot">Fait avec <?= e(config('app.name', 'Kitcho')) ?></p>
</main>
</body>
</html>
