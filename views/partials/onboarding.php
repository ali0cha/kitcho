<?php /** @var array $onboarding */ $ob = $onboarding; $pct = (int) round($ob['done'] * 100 / max(1, $ob['total'])); ?>
<?php $compact = !$ob['fresh']; $next = $ob['next']; ?>
<<?= $compact ? 'details' : 'section' ?> class="k-card onboarding<?= $compact ? ' onboarding--compact' : ' k-card--pad' ?><?= $ob['complete'] ? ' is-complete' : '' ?> k-noprint" <?= $compact ? '' : 'aria-labelledby="ob-title"' ?> data-onboarding>
<?php if ($compact): ?>
    <summary class="ob-line">
        <span class="ob-line__main">
            <span class="k-eyebrow"><?= $ob['complete'] ? 'Premiers pas terminés' : 'Premiers pas' ?></span>
            <span class="ob-line__next"><?= $ob['complete'] ? 'Tout est prêt !' : e($next['title']) ?></span>
        </span>
        <span class="k-count<?= $ob['complete'] ? ' k-count--done' : '' ?>"><?= (int) $ob['done'] ?>/<?= (int) $ob['total'] ?></span>
        <?= icon('down', 'sm') ?>
    </summary>
    <div class="ob-body">
<?php endif; ?>
    <header class="ob-head">
        <div>
            <p class="k-eyebrow">Premiers pas</p>
            <h2 id="ob-title"><?= $ob['complete'] ? 'Tout est prêt !' : ($ob['fresh'] ? 'Bienvenue dans ' . e(config('app.name', 'Kitcho')) : 'Continuons la mise en route') ?></h2>
        </div>
        <form method="post" action="<?= e(url('/onboarding/dismiss')) ?>" class="inline">
            <?= csrf_field() ?>
            <button class="k-iconbtn" aria-label="Masquer les premiers pas" title="Masquer"><?= icon('x') ?></button>
        </form>
    </header>

    <?php if ($ob['fresh']): ?>
        <p class="k-muted">Trois temps suffisent : vos recettes, le menu de la semaine, puis la liste de courses qui se remplit toute seule.</p>
        <ol class="ob-flow plain" aria-label="Le principe">
            <li><span class="k-aisle__icon"><?= icon('book') ?></span>Recettes</li>
            <li class="ob-arrow" aria-hidden="true"><?= icon('right', 'sm') ?></li>
            <li><span class="k-aisle__icon"><?= icon('week') ?></span>Menu</li>
            <li class="ob-arrow" aria-hidden="true"><?= icon('right', 'sm') ?></li>
            <li><span class="k-aisle__icon"><?= icon('basket') ?></span>Courses</li>
        </ol>
    <?php endif; ?>

    <div class="k-progress">
        <div class="k-progress__top">
            <span class="k-progress__num"><?= (int) $ob['done'] ?><small>/ <?= (int) $ob['total'] ?></small></span>
            <span class="k-meta">étapes terminées</span>
        </div>
        <div class="k-progress__track" role="progressbar" aria-label="Avancement des premiers pas" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $pct ?>">
            <div class="k-progress__fill" style="width: <?= $pct ?>%"></div>
        </div>
    </div>

    <?php if ($ob['next']): $n = $ob['next']; ?>
        <div class="ob-next">
            <span class="ob-next__icon"><?= icon($n['icon'], 'lg') ?></span>
            <div class="grow">
                <p class="k-eyebrow">Prochaine étape</p>
                <h3><?= e($n['title']) ?></h3>
                <p class="k-muted"><?= e($n['text']) ?></p>
                <a class="k-btn k-btn--primary" href="<?= e($n['url']) ?>"><?= e($n['cta']) ?><?= icon('right', 'sm') ?></a>
            </div>
        </div>
    <?php else: ?>
        <div class="k-banner k-banner--ok" role="status"><?= icon('check') ?><div class="k-banner__body"><b>Bravo, la mise en route est terminée.</b>Vous pouvez masquer cette carte ; elle reste disponible depuis votre profil.</div></div>
    <?php endif; ?>

    <details class="ob-all">
        <summary>Toutes les étapes<?= icon('down', 'sm') ?></summary>
        <ol class="plain ob-steps">
            <?php foreach ($ob['steps'] as $s): ?>
                <li class="<?= $s['done'] ? 'is-done' : '' ?>">
                    <span class="k-box<?= $s['done'] ? ' k-box--sm is-on' : ' k-box--sm' ?>" aria-hidden="true"><?= icon('check') ?></span>
                    <span class="grow">
                        <?php if ($s['done']): ?><b><?= e($s['title']) ?></b> <span class="sr-only">(terminé)</span><?php else: ?><a href="<?= e($s['url']) ?>"><b><?= e($s['title']) ?></b></a><?php endif; ?>
                        <span class="k-meta"><?= e($s['text']) ?></span>
                    </span>
                </li>
            <?php endforeach; ?>
        </ol>
    </details>
<?php if ($compact): ?>
    </div>
<?php endif; ?>
</<?= $compact ? 'details' : 'section' ?>>
