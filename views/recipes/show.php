<div class="page-head">
    <h1><?= $r['favorite'] ? '<span class="star on">★</span> ' : '' ?><?= e($r['name']) ?></h1>
    <span class="actions">
        <a class="btn" href="<?= e(url('/recipes/' . (int) $r['id'] . '/edit')) ?>">Modifier</a>
        <form method="post" action="<?= e(url('/recipes/' . (int) $r['id'] . '/delete')) ?>" class="inline" data-confirm="Supprimer cette recette ?">
            <?= csrf_field() ?><button class="btn btn-danger">Supprimer</button>
        </form>
    </span>
</div>
<p>
    <span class="badge cat-<?= e($r['category']) ?>"><?= e(CATEGORIES[$r['category']] ?? $r['category']) ?></span>
    <span class="muted">· pour <?= (int) $r['servings'] ?> <?= plural((int) $r['servings'], 'portion', 'portions') ?></span>
    <?php if ($u = safe_url($r['url'])): ?>· <a href="<?= e($u) ?>" target="_blank" rel="noopener noreferrer">Voir la recette ↗</a><?php endif; ?>
</p>

<div class="grid-2">
<section class="card">
    <h2>Ingrédients</h2>
    <?php if ($ings): ?>
        <ul class="plain">
            <?php foreach ($ings as $i): $q = $i['quantity'] !== null ? (float) $i['quantity'] : null; ?>
                <li><?= $q !== null ? e(format_qty($q)) . ' ' : '' ?><?= e(unit_label($i['unit'], $q)) ?> <?= e($i['name']) ?>
                    <span class="muted small">· <?= e($i['aisle'] ?? '') ?></span></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="muted">Aucun ingrédient : ce plat n'apparaîtra pas dans la liste de courses. <a href="<?= e(url('/recipes/' . (int) $r['id'] . '/edit')) ?>">Ajouter des ingrédients</a></p>
    <?php endif; ?>
    <?php if ($r['notes']): ?><h3>Notes</h3><p class="pre"><?= e($r['notes']) ?></p><?php endif; ?>
</section>

<section class="card">
    <h2>Planifier</h2>
    <form method="post" action="<?= e(url('/meals/add')) ?>" class="stack">
        <?= csrf_field() ?><input type="hidden" name="recipe_id" value="<?= (int) $r['id'] ?>">
        <label>Date <input type="date" name="date" value="<?= e(date('Y-m-d')) ?>" required></label>
        <label>Repas
            <select name="slot"><?php foreach (SLOTS as $k => $l): ?><option value="<?= $k ?>" <?= $k === 'dinner' ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
        </label>
        <label>Portions <input type="number" name="servings" min="1" max="30" placeholder="<?= (int) $hh['default_servings'] ?>"></label>
        <button class="btn btn-primary">Ajouter au calendrier</button>
    </form>
    <?php if ($history): ?>
        <h3>Dernières fois</h3>
        <ul class="plain small">
            <?php foreach ($history as $h): ?>
                <li><a href="<?= e(week_url($h['meal_date'])) ?>"><?= e(fmt_day(parse_date($h['meal_date']))) ?></a> · <?= e(SLOTS[$h['slot']]) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
</div>
