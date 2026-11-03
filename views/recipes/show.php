<div class="recipe-head">
    <?= thumb($r['category'], true) ?>
    <div class="grow">
        <h1 class="page-title"><?= e($r['name']) ?> <?php if ($r['favorite']): ?><span class="fav is-on" title="Favori"><?= icon('starfill') ?></span><?php endif; ?></h1>
        <p class="recipe-head__meta">
            <?= cat_badge($r['category']) ?>
            <span class="k-muted"><?= icon('user', 'sm') ?> pour <?= (int) $r['servings'] ?> <?= plural((int) $r['servings'], 'portion', 'portions') ?></span>
            <?php if ($u = safe_url($r['url'])): ?><a href="<?= e($u) ?>" target="_blank" rel="noopener noreferrer">Voir la recette <?= icon('external', 'sm') ?></a><?php endif; ?>
        </p>
    </div>
    <div class="page-head__actions">
        <a class="k-btn k-btn--secondary k-btn--sm" href="<?= e(url('/recipes/' . (int) $r['id'] . '/edit')) ?>"><?= icon('edit', 'sm') ?>Modifier</a>
        <form method="post" action="<?= e(url('/recipes/' . (int) $r['id'] . '/delete')) ?>" class="inline" data-confirm="Supprimer cette recette ?">
            <?= csrf_field() ?><button class="k-btn k-btn--danger-o k-btn--sm"><?= icon('trash', 'sm') ?>Supprimer</button>
        </form>
    </div>
</div>

<div class="grid-2">
<section class="k-card k-card--pad">
    <h2>Ingrédients</h2>
    <?php if ($ings): ?>
        <ul class="plain ing-list">
            <?php foreach ($ings as $i): $q = $i['quantity'] !== null ? (float) $i['quantity'] : null; ?>
                <li>
                    <span><?php if ($q !== null): ?><b class="k-qty"><?= e(trim(format_qty($q) . ' ' . unit_label($i['unit'], $q))) ?></b><?php endif; ?><?= e($i['name']) ?></span>
                    <span class="k-meta"><?= icon(aisle_icon((string) $i['aisle']), 'sm') ?> <?= e($i['aisle'] ?? '') ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <div class="k-banner k-banner--warn" role="status">
            <?= icon('warn') ?><div class="k-banner__body"><b>Aucun ingrédient</b>Ce plat n'apparaîtra pas dans la liste de courses.
                <a href="<?= e(url('/recipes/' . (int) $r['id'] . '/edit')) ?>">Ajouter des ingrédients</a></div>
        </div>
    <?php endif; ?>
    <?php if ($r['notes']): ?><h3>Notes</h3><p class="pre"><?= e($r['notes']) ?></p><?php endif; ?>
</section>

<section class="k-card k-card--pad">
    <h2>Planifier</h2>
    <form method="post" action="<?= e(url('/meals/add')) ?>" class="stack">
        <?= csrf_field() ?><input type="hidden" name="recipe_id" value="<?= (int) $r['id'] ?>">
        <div class="k-field"><label class="k-label" for="pl-date">Date</label><input class="k-input" id="pl-date" type="date" name="date" value="<?= e(date('Y-m-d')) ?>" required></div>
        <div class="row-form">
            <div class="k-field grow"><label class="k-label" for="pl-slot">Repas</label>
                <span class="k-selectwrap"><select class="k-select" id="pl-slot" name="slot"><?php foreach (SLOTS as $k => $l): ?><option value="<?= $k ?>" <?= $k === 'dinner' ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select><?= icon('down', 'sm') ?></span></div>
            <div class="k-field"><label class="k-label" for="pl-pers">Personnes</label><input class="k-input" id="pl-pers" type="number" name="servings" min="1" max="30" placeholder="<?= (int) $hh['default_servings'] ?>"></div>
        </div>
        <button class="k-btn k-btn--primary"><?= icon('plus') ?>Ajouter au calendrier</button>
    </form>
    <?php if ($history): ?>
        <h3>Dernières fois</h3>
        <ul class="plain history-list">
            <?php foreach ($history as $h): ?>
                <li><a href="<?= e(week_url($h['meal_date'])) ?>"><?= e(fmt_day(parse_date($h['meal_date']))) ?></a>
                    <span class="k-meal__slot"><?= icon($h['slot'] === 'lunch' ? 'sun' : 'moon', 'sm') ?><?= e(SLOTS[$h['slot']]) ?></span></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
</div>
