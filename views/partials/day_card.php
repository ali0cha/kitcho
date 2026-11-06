<?php
/** Carte d'un jour de la semaine. Attend : $d (DateTimeImmutable), $byDate, $todayStr, $hh, $monday ; $swap (jour de départ d'un échange) et $swapped (jours à faire ressortir). */
$ds = ymd($d);
$dayMeals = $byDate[$ds] ?? [];
$isToday = $ds === $todayStr;
$swapping = !empty($swap);
$isSource = $swapping && $ds === $swap;
$isTarget = $swapping && !$isSource;
$justSwapped = in_array($ds, $swapped ?? [], true);
?>
    <section class="k-daycard<?= $isToday ? ' is-today' : '' ?><?= $isSource ? ' is-swap-source' : '' ?><?= $isTarget ? ' is-swap-target' : '' ?><?= $justSwapped ? ' just-swapped' : '' ?>" id="d-<?= e($ds) ?>" data-day="<?= e($ds) ?>">
        <header class="k-daycard__head" data-day-handle>
            <h2 class="k-daycard__name"><?= e(ucfirst(day_name($d))) ?> <span class="k-muted"><?= e($d->format('j')) ?> <?= e(month_abbr($d)) ?></span></h2>
            <?php if ($isToday): ?><span class="k-badge k-badge--today">Aujourd'hui</span><?php endif; ?>
            <?php if (!$swapping): ?>
                <a class="swap-btn k-noprint" href="<?= e(url('/week', ['d' => ymd($monday), 'swap' => $ds]) . '#d-' . $ds) ?>" title="Intervertir les repas de ce jour avec ceux d'un autre jour"><?= icon('swap', 'sm') ?><span>Échanger</span></a>
            <?php endif; ?>
        </header>
        <?php if ($isTarget): ?>
            <form method="post" action="<?= e(url('/days/swap')) ?>" class="swap-target k-noprint">
                <?= csrf_field() ?><input type="hidden" name="d" value="<?= e(ymd($monday)) ?>">
                <input type="hidden" name="a" value="<?= e($swap) ?>"><input type="hidden" name="b" value="<?= e($ds) ?>">
                <button class="swap-target__btn"><?= icon('swap') ?>
                    <span>Échanger avec <b><?= e(fmt_day(parse_date($swap))) ?></b></span>
                    <small><?= count($dayMeals) ? count($dayMeals) . ' ' . plural(count($dayMeals), 'repas', 'repas') . ' ici' : 'jour vide' ?> · <?= count($byDate[$swap] ?? []) ?> <?= plural(count($byDate[$swap] ?? []), 'repas', 'repas') ?> là-bas</small>
                </button>
            </form>
        <?php endif; ?>

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
                            <div class="row-form">
                                <button class="k-btn k-btn--secondary k-btn--sm">Enregistrer</button>
                                <button class="k-btn k-btn--ghost k-btn--sm" name="postpone" value="1" title="Déplacer ce repas au jour suivant (les repas déjà prévus au même moment sont décalés d'un jour)"><?= icon('right', 'sm') ?>Reporter au lendemain</button>
                            </div>
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
