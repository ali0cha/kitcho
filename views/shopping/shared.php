<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title) ?></title>
    <link rel="stylesheet" href="<?= e(asset('style.css')) ?>">
</head>
<body>
<main class="container">
    <h1><?= e($s['household_name']) ?> — semaine du <?= e($monday->format('j')) ?> <?= e(month_name($monday)) ?></h1>
    <p class="muted"><?= e(fmt_week_range($monday)) ?> · consultation seule</p>

    <div class="grid-2">
        <section class="card">
            <h2>Menu</h2>
            <?php $any = false; foreach ($days as $d): $ds = ymd($d); if (empty($byDate[$ds])) continue; $any = true; ?>
                <h3><?= e(fmt_day($d)) ?></h3>
                <ul class="plain">
                    <?php foreach ($byDate[$ds] as $m): ?>
                        <li><span class="slot slot-<?= e($m['slot']) ?>"><?= e(SLOTS[$m['slot']]) ?></span>
                            <?php if ($u = safe_url($m['url'])): ?><a href="<?= e($u) ?>" target="_blank" rel="noopener noreferrer"><?= e($m['name']) ?></a><?php else: ?><?= e($m['name']) ?><?php endif; ?>
                            <?= $m['notes'] ? '<span class="muted small">· ' . e($m['notes']) . '</span>' : '' ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; if (!$any): ?><p class="muted">Rien de prévu cette semaine.</p><?php endif; ?>
        </section>
        <section class="card">
            <h2>Courses</h2>
            <?php foreach ($list['groups'] as $aisle => $items): ?>
                <h3><?= e($aisle) ?></h3>
                <ul class="plain">
                    <?php foreach ($items as $it): $q = item_quantity_text($it); ?>
                        <li class="<?= $it['checked'] ? 'done' : '' ?>"><?= $it['checked'] ? '✓' : '·' ?>
                            <?php if ($q !== ''): ?><strong><?= e($q) ?></strong> <?php endif; ?><?= e($it['name']) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; if (!$list['total']): ?><p class="muted">Aucun article.</p><?php endif; ?>
        </section>
    </div>
</main>
</body>
</html>
