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

<nav class="week-strip k-noprint" aria-label="Aller à un jour">
    <?php foreach ($days as $d): $ds = ymd($d); ?>
        <a href="#d-<?= e($ds) ?>" class="<?= $ds === $todayStr ? 'is-today' : '' ?>">
            <span><?= e(ucfirst(mb_substr(day_name($d), 0, 3))) ?></span><b class="k-num"><?= e($d->format('j')) ?></b>
            <?php if (!empty($byDate[$ds])): ?><i class="week-strip__dot" aria-hidden="true"></i><?php endif; ?>
        </a>
    <?php endforeach; ?>
</nav>

<details class="k-details gen-panel" <?= $meals === 0 ? 'open' : '' ?>>
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

<div class="week-grid">
<?php foreach ($days as $d):
    $ds = ymd($d);
    $dayMeals = $byDate[$ds] ?? [];
    $isToday = $ds === $todayStr;
?>
    <section class="k-daycard<?= $isToday ? ' is-today' : '' ?>" id="d-<?= e($ds) ?>">
        <header class="k-daycard__head">
            <h2 class="k-daycard__name"><?= e(ucfirst(day_name($d))) ?> <span class="k-muted"><?= e($d->format('j')) ?> <?= e(month_abbr($d)) ?></span></h2>
            <?php if ($isToday): ?><span class="k-badge k-badge--today">Aujourd'hui</span><?php endif; ?>
        </header>

        <?php foreach ($dayMeals as $m): $name = $m['recipe_name'] ?? $m['label']; ?>
            <article class="k-meal">
                <?php if ($m['recipe_id']): ?><?= thumb($m['category'] ?: 'autre') ?><?php else: ?><span class="k-thumb cat-other"><?= icon('plate') ?></span><?php endif; ?>
                <div class="meal-main">
                    <span class="k-meal__slot"><?= icon($m['slot'] === 'lunch' ? 'sun' : 'moon', 'sm') ?><?= e(SLOTS[$m['slot']]) ?></span>
                    <?php if ($m['recipe_id']): ?>
                        <a href="<?= e(url('/recipes/' . (int) $m['recipe_id'])) ?>" class="k-meal__name"><?= e($name) ?></a>
                    <?php else: ?>
                        <span class="k-meal__name"><?= e($name) ?></span>
                    <?php endif; ?>
                    <span class="k-meal__sub">
                        <?php if ($m['servings']): ?><span><?= icon('user', 'sm') ?> <?= (int) $m['servings'] ?> pers.</span><?php endif; ?>
                        <?php if ($m['recipe_id'] && !$m['ingredient_count']): ?><span class="meal-warn" title="Sans ingrédients : absent de la liste de courses"><?= icon('warn', 'sm') ?>sans ingrédients</span><?php endif; ?>
                        <?php if ($m['notes']): ?><span class="meal-note"><?= e($m['notes']) ?></span><?php endif; ?>
                    </span>
                    <details class="meal-edit">
                        <summary><?= icon('edit', 'sm') ?>Modifier</summary>
                        <form method="post" action="<?= e(url('/meals/' . (int) $m['id'] . '/update')) ?>" class="stack">
                            <?= csrf_field() ?>
                            <div class="row-form">
                                <div class="k-field"><label class="k-label" for="m<?= (int) $m['id'] ?>-d">Date</label><input class="k-input" id="m<?= (int) $m['id'] ?>-d" type="date" name="date" value="<?= e($m['meal_date']) ?>"></div>
                                <div class="k-field"><label class="k-label" for="m<?= (int) $m['id'] ?>-s">Repas</label>
                                    <span class="k-selectwrap"><select class="k-select" id="m<?= (int) $m['id'] ?>-s" name="slot"><?php foreach (SLOTS as $k => $l): ?><option value="<?= $k ?>" <?= $m['slot'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select><?= icon('down', 'sm') ?></span></div>
                                <div class="k-field"><label class="k-label" for="m<?= (int) $m['id'] ?>-p">Personnes</label><input class="k-input" id="m<?= (int) $m['id'] ?>-p" type="number" name="servings" min="1" max="30" value="<?= e((string) $m['servings']) ?>" placeholder="<?= (int) $hh['default_servings'] ?>"></div>
                            </div>
                            <div class="k-field"><label class="k-label" for="m<?= (int) $m['id'] ?>-n">Note</label><input class="k-input" id="m<?= (int) $m['id'] ?>-n" name="notes" maxlength="500" value="<?= e($m['notes']) ?>"></div>
                            <button class="k-btn k-btn--secondary k-btn--sm">Enregistrer</button>
                        </form>
                    </details>
                </div>
                <div class="meal-actions">
                    <?php if ($m['recipe_id']): ?>
                        <form method="post" action="<?= e(url('/meals/' . (int) $m['id'] . '/reroll')) ?>" class="inline">
                            <?= csrf_field() ?><button class="k-iconbtn" title="Proposer un autre plat" aria-label="Changer de plat"><?= icon('refresh') ?></button>
                        </form>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('/meals/' . (int) $m['id'] . '/delete')) ?>" class="inline">
                        <?= csrf_field() ?><button class="k-iconbtn" title="Supprimer" aria-label="Supprimer ce repas"><?= icon('x') ?></button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if (!$dayMeals): ?><p class="k-meta day-empty">Rien de prévu</p><?php endif; ?>

        <form method="post" action="<?= e(url('/meals/add')) ?>" class="k-add">
            <?= csrf_field() ?><input type="hidden" name="date" value="<?= e($ds) ?>">
            <input class="k-input" type="text" name="label" list="recipe-list" maxlength="200" autocomplete="off" required
                   placeholder="Ajouter un repas…" aria-label="Repas du <?= e(day_name($d)) ?> <?= e($d->format('j')) ?>">
            <span class="k-selectwrap add-slot"><select class="k-select" name="slot" aria-label="Moment du repas">
                <?php foreach (SLOTS as $k => $l): ?><option value="<?= $k ?>" <?= $k === 'dinner' ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
            </select><?= icon('down', 'sm') ?></span>
            <button class="k-iconbtn k-iconbtn--tint add-btn" aria-label="Ajouter"><?= icon('plus') ?></button>
        </form>
    </section>
<?php endforeach; ?>
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
