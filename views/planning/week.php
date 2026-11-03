<?php
$prev = $monday->modify('-7 days');
$next = $monday->modify('+7 days');
$isThisWeek = ymd($monday) === ymd(monday_of(today()));
$todayStr = ymd(today());
?>
<div class="page-head">
    <div>
        <h1>Semaine du <?= e($monday->format('j')) ?> <?= e(month_name($monday)) ?></h1>
        <p class="muted"><?= e(fmt_week_range($monday)) ?> · <?= (int) $meals ?> <?= plural((int) $meals, 'repas', 'repas') ?> planifié<?= $meals > 1 ? 's' : '' ?></p>
    </div>
    <div class="actions">
        <a class="btn" href="<?= e(url('/week', ['d' => ymd($prev)])) ?>" aria-label="Semaine précédente">‹</a>
        <a class="btn <?= $isThisWeek ? 'disabled' : '' ?>" href="<?= e(url('/week')) ?>">Aujourd'hui</a>
        <a class="btn" href="<?= e(url('/week', ['d' => ymd($next)])) ?>" aria-label="Semaine suivante">›</a>
        <a class="btn btn-primary" href="<?= e(url('/shopping', ['d' => ymd($monday)])) ?>">🛒 Liste de courses</a>
    </div>
</div>

<details class="card" <?= $meals === 0 ? 'open' : '' ?>>
    <summary><strong>✨ Générer le menu de la semaine</strong></summary>
    <form method="post" action="<?= e(url('/week/generate')) ?>" class="stack">
        <?= csrf_field() ?><input type="hidden" name="d" value="<?= e(ymd($monday)) ?>">
        <fieldset>
            <legend>Jours à remplir</legend>
            <div class="chips">
                <?php foreach ($days as $i => $d): ?>
                    <label class="chip"><input type="checkbox" name="days[]" value="<?= $i ?>" <?= $i < 5 ? 'checked' : '' ?>> <?= e(ucfirst(mb_substr(day_name($d), 0, 3))) ?>.</label>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <div class="row-form">
            <label>Repas
                <select name="slot"><?php foreach (SLOTS as $k => $l): ?><option value="<?= $k ?>" <?= $k === 'dinner' ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
            </label>
            <label>Éviter les plats faits ces dernières
                <select name="avoid_weeks">
                    <?php foreach ([0 => 'Aucune restriction', 1 => '1 semaine', 2 => '2 semaines', 3 => '3 semaines', 4 => '4 semaines', 6 => '6 semaines'] as $v => $l): ?>
                        <option value="<?= $v ?>" <?= $v === 3 ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Repas végétariens minimum
                <input type="number" name="min_veg" value="2" min="0" max="7">
            </label>
        </div>
        <label class="check"><input type="checkbox" name="replace" value="1"> Remplacer les repas déjà planifiés sur ces créneaux</label>
        <div><button class="btn btn-primary">Générer</button>
            <span class="muted small">Variété garantie : les plats récents sont écartés, les favoris sont privilégiés et une même catégorie n'est pas répétée à l'excès. Vous pouvez relancer ou changer un plat avec ↻.</span></div>
    </form>
</details>

<datalist id="recipe-list">
    <?php foreach ($recipes as $r): ?><option value="<?= e($r['name']) ?>"></option><?php endforeach; ?>
</datalist>

<div class="week-grid">
<?php foreach ($days as $d):
    $ds = ymd($d);
    $dayMeals = $byDate[$ds] ?? [];
?>
    <section class="day card <?= $ds === $todayStr ? 'today' : '' ?>">
        <header>
            <h2><?= e(ucfirst(day_name($d))) ?> <span class="muted"><?= e($d->format('j')) ?> <?= e(mb_substr(month_name($d), 0, 4)) ?>.</span></h2>
        </header>

        <?php foreach ($dayMeals as $m): $name = $m['recipe_name'] ?? $m['label']; ?>
            <article class="meal">
                <div class="meal-main">
                    <span class="slot slot-<?= e($m['slot']) ?>"><?= e(SLOTS[$m['slot']]) ?></span>
                    <?php if ($m['recipe_id']): ?>
                        <a href="<?= e(url('/recipes/' . (int) $m['recipe_id'])) ?>" class="meal-name"><?= e($name) ?></a>
                    <?php else: ?>
                        <span class="meal-name"><?= e($name) ?></span>
                    <?php endif; ?>
                    <span class="meal-meta muted small">
                        <?= $m['servings'] ? (int) $m['servings'] . ' pers.' : '' ?>
                        <?= $m['recipe_id'] && !$m['ingredient_count'] ? '<span class="warn" title="Sans ingrédients : absent de la liste de courses">⚠ sans ingrédients</span>' : '' ?>
                    </span>
                    <?php if ($m['notes']): ?><span class="meal-note small"><?= e($m['notes']) ?></span><?php endif; ?>
                </div>
                <div class="meal-actions">
                    <?php if ($m['recipe_id']): ?>
                        <form method="post" action="<?= e(url('/meals/' . (int) $m['id'] . '/reroll')) ?>" class="inline">
                            <?= csrf_field() ?><button class="icon-btn" title="Proposer un autre plat" aria-label="Changer de plat">↻</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('/meals/' . (int) $m['id'] . '/delete')) ?>" class="inline">
                        <?= csrf_field() ?><button class="icon-btn" title="Supprimer" aria-label="Supprimer ce repas">✕</button>
                    </form>
                </div>
                <details class="meal-edit">
                    <summary class="small muted">Modifier</summary>
                    <form method="post" action="<?= e(url('/meals/' . (int) $m['id'] . '/update')) ?>" class="stack small">
                        <?= csrf_field() ?>
                        <div class="row-form">
                            <label>Date <input type="date" name="date" value="<?= e($m['meal_date']) ?>"></label>
                            <label>Repas <select name="slot"><?php foreach (SLOTS as $k => $l): ?><option value="<?= $k ?>" <?= $m['slot'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
                            <label>Pers. <input type="number" name="servings" min="1" max="30" value="<?= e((string) $m['servings']) ?>" placeholder="<?= (int) $hh['default_servings'] ?>"></label>
                        </div>
                        <label>Note <input name="notes" maxlength="500" value="<?= e($m['notes']) ?>"></label>
                        <button class="btn btn-small">Enregistrer</button>
                    </form>
                </details>
            </article>
        <?php endforeach; ?>
        <?php if (!$dayMeals): ?><p class="muted small empty">Rien de prévu</p><?php endif; ?>

        <form method="post" action="<?= e(url('/meals/add')) ?>" class="add-meal">
            <?= csrf_field() ?><input type="hidden" name="date" value="<?= e($ds) ?>">
            <input type="text" name="label" list="recipe-list" maxlength="200" autocomplete="off" required
                   placeholder="+ Ajouter un repas…" aria-label="Repas du <?= e(day_name($d)) ?> <?= e($d->format('j')) ?>">
            <select name="slot" aria-label="Moment du repas">
                <?php foreach (SLOTS as $k => $l): ?><option value="<?= $k ?>" <?= $k === 'dinner' ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
            </select>
            <button class="btn btn-small btn-primary" aria-label="Ajouter">Ajouter</button>
        </form>
    </section>
<?php endforeach; ?>
</div>

<section class="card">
    <h2>Outils de la semaine</h2>
    <div class="row-form">
        <form method="post" action="<?= e(url('/week/copy')) ?>" class="inline">
            <?= csrf_field() ?><input type="hidden" name="d" value="<?= e(ymd($monday)) ?>">
            <button class="btn">Copier la semaine précédente</button>
        </form>
        <form method="post" action="<?= e(url('/week/clear')) ?>" class="inline" data-confirm="Supprimer tous les repas de cette semaine ?">
            <?= csrf_field() ?><input type="hidden" name="d" value="<?= e(ymd($monday)) ?>">
            <button class="btn btn-danger">Vider la semaine</button>
        </form>
    </div>
    <h3>Partager en lecture seule</h3>
    <?php if ($share): ?>
        <p class="muted small">Toute personne disposant de ce lien voit le menu et la liste de courses de la semaine, sans pouvoir modifier.</p>
        <div class="row-form">
            <input class="copy-input grow" readonly value="<?= e($share['link']) ?>" aria-label="Lien de partage">
            <button type="button" class="btn" data-copy="<?= e($share['link']) ?>">Copier</button>
            <form method="post" action="<?= e(url('/share/revoke')) ?>" class="inline">
                <?= csrf_field() ?><input type="hidden" name="d" value="<?= e(ymd($monday)) ?>">
                <button class="btn btn-danger">Supprimer le lien</button>
            </form>
        </div>
    <?php else: ?>
        <form method="post" action="<?= e(url('/share/create')) ?>" class="inline">
            <?= csrf_field() ?><input type="hidden" name="d" value="<?= e(ymd($monday)) ?>">
            <button class="btn">Créer un lien de partage</button>
        </form>
        <span class="muted small">Pour partager avec quelqu'un hors du foyer. Les membres du foyer voient déjà tout automatiquement.</span>
    <?php endif; ?>
</section>
