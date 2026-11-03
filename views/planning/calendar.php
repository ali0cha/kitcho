<?php $todayStr = ymd(today()); ?>
<div class="page-head">
    <h1><?= e(ucfirst(month_name($first))) ?> <?= e($first->format('Y')) ?></h1>
    <div class="actions">
        <a class="btn" href="<?= e(url('/calendar', ['m' => $prev])) ?>" aria-label="Mois précédent">‹</a>
        <a class="btn" href="<?= e(url('/calendar')) ?>">Aujourd'hui</a>
        <a class="btn" href="<?= e(url('/calendar', ['m' => $next])) ?>" aria-label="Mois suivant">›</a>
    </div>
</div>
<div class="cal">
    <?php foreach (FR_DAYS as $dn): ?><div class="cal-head"><?= e(ucfirst(mb_substr($dn, 0, 3))) ?>.</div><?php endforeach; ?>
    <?php for ($d = $gridStart; $d <= $gridEnd; $d = $d->modify('+1 day')):
        $ds = ymd($d);
        $outside = $d->format('Y-m') !== $first->format('Y-m');
        $meals = $byDate[$ds] ?? [];
    ?>
        <a class="cal-day <?= $outside ? 'outside' : '' ?> <?= $ds === $todayStr ? 'today' : '' ?>" href="<?= e(week_url($d)) ?>">
            <span class="cal-num"><?= e($d->format('j')) ?></span>
            <?php foreach (array_slice($meals, 0, 3) as $m): ?>
                <span class="cal-meal slot-<?= e($m['slot']) ?>" title="<?= e(SLOTS[$m['slot']] . ' : ' . $m['name']) ?>"><?= e($m['name']) ?></span>
            <?php endforeach; ?>
            <?php if (count($meals) > 3): ?><span class="muted small">+<?= count($meals) - 3 ?></span><?php endif; ?>
        </a>
    <?php endfor; ?>
</div>
<p class="muted small">Cliquez sur un jour pour ouvrir la semaine et modifier le menu.</p>
