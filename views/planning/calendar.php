<?php $todayStr = ymd(today()); ?>
<div class="page-head">
    <h1 class="page-title"><?= e(ucfirst(month_name($first))) ?> <?= e($first->format('Y')) ?></h1>
    <div class="page-head__actions">
        <a class="k-iconbtn k-iconbtn--tint" href="<?= e(url('/calendar', ['m' => $prev])) ?>" aria-label="Mois précédent"><?= icon('left') ?></a>
        <a class="k-btn k-btn--secondary k-btn--sm" href="<?= e(url('/calendar')) ?>">Aujourd'hui</a>
        <a class="k-iconbtn k-iconbtn--tint" href="<?= e(url('/calendar', ['m' => $next])) ?>" aria-label="Mois suivant"><?= icon('right') ?></a>
    </div>
</div>

<div class="cal" data-cal>
    <?php foreach (FR_DAYS as $dn): ?><div class="cal-head"><?= e(ucfirst(mb_substr($dn, 0, 3))) ?><span class="hide-sm">.</span></div><?php endforeach; ?>
    <?php for ($d = $gridStart; $d <= $gridEnd; $d = $d->modify('+1 day')):
        $ds = ymd($d);
        $outside = $d->format('Y-m') !== $first->format('Y-m');
        $dm = $byDate[$ds] ?? [];
    ?>
        <a class="cal-day<?= $outside ? ' is-outside' : '' ?><?= $ds === $todayStr ? ' is-today' : '' ?>" href="<?= e(week_url($d)) ?>" data-date="<?= e($ds) ?>"
           aria-label="<?= e(fmt_day($d)) ?>, <?= count($dm) ?> <?= plural(count($dm), 'repas', 'repas') ?>">
            <span class="cal-num k-num"><?= e($d->format('j')) ?></span>
            <span class="cal-meals">
                <?php foreach (array_slice($dm, 0, 3) as $m): ?>
                    <span class="cal-meal is-<?= e($m['slot']) ?>" title="<?= e(SLOTS[$m['slot']] . ' : ' . $m['name']) ?>"><?= icon($m['slot'] === 'lunch' ? 'sun' : 'moon', 'sm') ?><span><?= e($m['name']) ?></span></span>
                <?php endforeach; ?>
                <?php if (count($dm) > 3): ?><span class="k-meta">+<?= count($dm) - 3 ?></span><?php endif; ?>
            </span>
            <span class="cal-dots" aria-hidden="true">
                <?php foreach (array_slice($dm, 0, 4) as $m): ?><i class="cal-dot is-<?= e($m['slot']) ?>"></i><?php endforeach; ?>
            </span>
        </a>
    <?php endfor; ?>
</div>

<p class="cal-legend k-meta"><span><i class="cal-dot is-lunch"></i> Déjeuner</span> <span><i class="cal-dot is-dinner"></i> Dîner</span></p>

<?php for ($d = $gridStart; $d <= $gridEnd; $d = $d->modify('+1 day')): $ds = ymd($d); $dm = $byDate[$ds] ?? []; ?>
    <section class="cal-detail k-card k-card--pad" data-cal-detail="<?= e($ds) ?>" hidden>
        <h2><?= e(fmt_day($d)) ?></h2>
        <?php if ($dm): ?>
            <ul class="plain list-rows">
                <?php foreach ($dm as $m): ?>
                    <li><span class="k-meal__slot"><?= icon($m['slot'] === 'lunch' ? 'sun' : 'moon', 'sm') ?><?= e(SLOTS[$m['slot']]) ?></span> <b><?= e($m['name']) ?></b></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?><p class="k-muted">Rien de prévu ce jour-là.</p><?php endif; ?>
        <a class="k-btn k-btn--secondary k-btn--block" href="<?= e(week_url($d)) ?>">Ouvrir la semaine</a>
    </section>
<?php endfor; ?>

<p class="k-help cal-hint hide-lg">Touchez un jour pour voir son détail, puis ouvrez la semaine pour le modifier.</p>
