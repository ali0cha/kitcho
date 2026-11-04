<?php
$prev = $monday->modify('-7 days');
$next = $monday->modify('+7 days');
$isThisWeek = ymd($monday) === ymd(monday_of(today()));
$todayStr = ymd(today());
?>
<div class="page-head">
    <div>
        <h1 class="page-title">Semaine du <?= e($monday->format('j')) ?> <?= e(month_name($monday)) ?></h1>
        <p class="k-muted"><?= e(fmt_week_range($monday)) ?> · <?= (int) $meals ?> <?= plural((int) $meals, 'repas planifié', 'repas planifiés') ?></p>
    </div>
    <div class="page-head__actions">
        <a class="k-iconbtn k-iconbtn--tint" href="<?= e(url('/week', ['d' => ymd($prev)])) ?>" aria-label="Semaine précédente"><?= icon('left') ?></a>
        <a class="k-btn k-btn--secondary k-btn--sm<?= $isThisWeek ? ' is-disabled' : '' ?>" href="<?= e(url('/week')) ?>">Aujourd'hui</a>
        <a class="k-iconbtn k-iconbtn--tint" href="<?= e(url('/week', ['d' => ymd($next)])) ?>" aria-label="Semaine suivante"><?= icon('right') ?></a>
        <a class="k-btn k-btn--primary k-btn--sm" href="<?= e(url('/shopping', ['d' => ymd($monday)])) ?>"><?= icon('basket', 'sm') ?>Courses</a>
    </div>
</div>

<?php if (!empty($onboarding)) { require ROOT . '/views/partials/onboarding.php'; } ?>

<nav class="week-strip k-noprint" aria-label="Aller à un jour">
    <?php foreach ($days as $d): $ds = ymd($d); ?>
        <a href="#d-<?= e($ds) ?>" class="<?= $ds === $todayStr ? 'is-today' : (($isThisWeek && $ds < $todayStr) ? 'is-past' : '') ?>">
            <span><?= e(ucfirst(mb_substr(day_name($d), 0, 3))) ?></span><b class="k-num"><?= e($d->format('j')) ?></b>
            <?php if (!empty($byDate[$ds])): ?><i class="week-strip__dot" aria-hidden="true"></i><?php endif; ?>
        </a>
    <?php endforeach; ?>
</nav>

<details class="k-details gen-panel" id="generer" <?= $meals === 0 ? 'open' : '' ?>>
    <summary><?= icon('spark') ?>Générer le menu de la semaine<?= icon('down') ?></summary>
    <div class="k-details__body">
        <form method="post" action="<?= e(url('/week/generate')) ?>" class="stack">
            <?= csrf_field() ?><input type="hidden" name="d" value="<?= e(ymd($monday)) ?>">
            <fieldset class="k-fieldset">
                <legend class="k-label">Jours à remplir</legend>
                <div class="day-chips">
                    <?php foreach ($days as $i => $d): ?>
                        <label class="k-day" aria-label="<?= e(ucfirst(day_name($d))) ?>">
                            <input type="checkbox" class="sr-only" name="days[]" value="<?= $i ?>" <?= $i < 5 ? 'checked' : '' ?>>
                            <?= e(ucfirst(mb_substr(day_name($d), 0, 3))) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <div class="row-form">
                <div class="k-field"><label class="k-label" for="g-slot">Repas</label>
                    <span class="k-selectwrap"><select class="k-select" id="g-slot" name="slot"><?php foreach (SLOTS as $k => $l): ?><option value="<?= $k ?>" <?= $k === 'dinner' ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select><?= icon('down', 'sm') ?></span></div>
                <div class="k-field grow"><label class="k-label" for="g-avoid">Éviter les plats faits ces dernières…</label>
                    <span class="k-selectwrap"><select class="k-select" id="g-avoid" name="avoid_weeks">
                        <?php foreach ([0 => 'Aucune restriction', 1 => '1 semaine', 2 => '2 semaines', 3 => '3 semaines', 4 => '4 semaines', 6 => '6 semaines'] as $v => $l): ?>
                            <option value="<?= $v ?>" <?= $v === 3 ? 'selected' : '' ?>><?= e($l) ?></option>
                        <?php endforeach; ?></select><?= icon('down', 'sm') ?></span></div>
                <div class="k-field"><label class="k-label" for="g-veg">Végétariens minimum</label>
                    <input class="k-input" id="g-veg" type="number" name="min_veg" value="2" min="0" max="7"></div>
            </div>
            <label class="k-check"><input type="checkbox" name="replace" value="1">Remplacer les repas déjà planifiés sur ces créneaux</label>
            <div><button class="k-btn k-btn--primary"><?= icon('spark') ?>Générer</button></div>
            <p class="k-help">Variété garantie : les plats récents sont écartés, les favoris sont privilégiés et une même catégorie n'est pas répétée à l'excès. Changez un plat avec ↻.</p>
        </form>
    </div>
</details>

<datalist id="recipe-list">
    <?php foreach ($recipes as $r): ?><option value="<?= e($r['name']) ?>"></option><?php endforeach; ?>
</datalist>

<?php
// Semaine en cours : les jours déjà écoulés sont regroupés dans un volet replié, pour que aujourd'hui reste en haut de l'écran.
$isCurrentWeek = ymd($monday) === ymd(monday_of(today()));
$pastDays = $isCurrentWeek ? array_values(array_filter($days, fn($x) => ymd($x) < $todayStr)) : [];
$upcoming = array_values(array_filter($days, fn($x) => !$pastDays || ymd($x) >= $todayStr));
?>
<div class="week-grid">
<?php if ($pastDays):
    $pastMeals = array_sum(array_map(fn($x) => count($byDate[ymd($x)] ?? []), $pastDays));
?>
    <details class="k-details past-days" id="passes">
        <summary><?= icon('week') ?>Jours écoulés
            <span class="k-meta"><?= count($pastDays) ?> <?= plural(count($pastDays), 'jour', 'jours') ?> · <?= (int) $pastMeals ?> <?= plural((int) $pastMeals, 'repas', 'repas') ?></span><?= icon('down') ?></summary>
        <div class="k-details__body">
            <div class="week-grid week-grid--inner">
                <?php foreach ($pastDays as $d) { require ROOT . '/views/partials/day_card.php'; } ?>
            </div>
        </div>
    </details>
<?php endif; ?>
<?php foreach ($upcoming as $d) { require ROOT . '/views/partials/day_card.php'; } ?>
</div>

<section class="k-card k-card--pad week-tools k-noprint">
    <h2>Outils de la semaine</h2>
    <div class="row-form">
        <form method="post" action="<?= e(url('/week/copy')) ?>" class="inline">
            <?= csrf_field() ?><input type="hidden" name="d" value="<?= e(ymd($monday)) ?>">
            <button class="k-btn k-btn--secondary"><?= icon('copy') ?>Copier la semaine précédente</button>
        </form>
        <form method="post" action="<?= e(url('/week/clear')) ?>" class="inline" data-confirm="Supprimer tous les repas de cette semaine ?">
            <?= csrf_field() ?><input type="hidden" name="d" value="<?= e(ymd($monday)) ?>">
            <button class="k-btn k-btn--danger-o"><?= icon('trash') ?>Vider la semaine</button>
        </form>
    </div>
    <h3>Partager en lecture seule</h3>
    <?php if ($share): ?>
        <p class="k-help">Toute personne disposant de ce lien voit le menu et la liste de courses de la semaine, sans pouvoir modifier.</p>
        <div class="row-form">
            <input class="k-input copy-input grow" readonly value="<?= e($share['link']) ?>" aria-label="Lien de partage">
            <button type="button" class="k-btn k-btn--secondary" data-copy="<?= e($share['link']) ?>"><?= icon('link') ?>Copier</button>
            <form method="post" action="<?= e(url('/share/revoke')) ?>" class="inline">
                <?= csrf_field() ?><input type="hidden" name="d" value="<?= e(ymd($monday)) ?>">
                <button class="k-btn k-btn--danger-o">Supprimer le lien</button>
            </form>
        </div>
    <?php else: ?>
        <form method="post" action="<?= e(url('/share/create')) ?>" class="inline">
            <?= csrf_field() ?><input type="hidden" name="d" value="<?= e(ymd($monday)) ?>">
            <button class="k-btn k-btn--secondary"><?= icon('share') ?>Créer un lien de partage</button>
        </form>
        <p class="k-help">Pour partager avec quelqu'un hors du foyer. Les membres du foyer voient déjà tout automatiquement.</p>
    <?php endif; ?>
</section>
