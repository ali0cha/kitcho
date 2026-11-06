<a class="k-btn k-btn--ghost k-btn--sm back-link" href="<?= e(url('/recipes')) ?>"><?= icon('left', 'sm') ?>Recettes</a>
<div class="recipe-head">
    <?= thumb($r['category'], true) ?>
    <div class="grow">
        <h1 class="page-title"><?= e($r['name']) ?> <?php if ($r['favorite']): ?><span class="fav is-on" title="Favori"><?= icon('starfill') ?></span><?php endif; ?></h1>
        <p class="recipe-head__meta">
            <?= cat_badge($r['category']) ?>
            <span class="k-muted"><?= icon('user', 'sm') ?> pour <?= (int) $r['servings'] ?> <?= plural((int) $r['servings'], 'portion', 'portions') ?></span>
            <?php $total = recipe_total_minutes($r); ?>
            <?php if (!empty($r['prep_minutes'])): ?><span class="k-muted">Préparation <b><?= e(format_minutes((int) $r['prep_minutes'])) ?></b></span><?php endif; ?>
            <?php if (!empty($r['cook_minutes'])): ?><span class="k-muted">Cuisson <b><?= e(format_minutes((int) $r['cook_minutes'])) ?></b></span><?php endif; ?>
            <?php $given = array_filter([(int) ($r['prep_minutes'] ?? 0), (int) ($r['cook_minutes'] ?? 0)]); ?>
            <?php if ($total && (count($given) !== 1 || $total !== (int) reset($given))): ?><span class="k-muted">Total <b><?= e(format_minutes($total)) ?></b></span><?php endif; ?>
            <?php if ($u = safe_url($r['url'])): ?><a href="<?= e($u) ?>" target="_blank" rel="noopener noreferrer">Voir la recette <?= icon('external', 'sm') ?></a><?php endif; ?>
        </p>
    </div>
    <div class="page-head__actions">
        <?php if ($own): ?>
        <a class="k-btn k-btn--secondary k-btn--sm" href="<?= e(url('/recipes/' . (int) $r['id'] . '/edit')) ?>"><?= icon('edit', 'sm') ?>Modifier</a>
        <form method="post" action="<?= e(url('/recipes/' . (int) $r['id'] . '/delete')) ?>" class="inline" data-confirm="Supprimer cette recette ?">
            <?= csrf_field() ?><button class="k-btn k-btn--danger-o k-btn--sm"><?= icon('trash', 'sm') ?>Supprimer</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php if (!$own): ?>
    <div class="k-banner public-banner" role="status">
        <?= icon('share') ?>
        <div class="k-banner__body"><b>Recette publique partagée par <?= e($r['owner_name']) ?></b>
            <?php if ($copyId): ?>
                Elle est déjà dans vos recettes. <a href="<?= e(url('/recipes/' . (int) $copyId)) ?>">Ouvrir ma copie</a>
            <?php else: ?>
                Vous la consultez en lecture seule. Ajoutez-la à vos recettes pour la modifier, la planifier et l'inclure dans votre liste de courses.
            <?php endif; ?>
        </div>
        <?php if (!$copyId): ?>
            <form method="post" action="<?= e(url('/recipes/' . (int) $r['id'] . '/copy')) ?>" class="inline">
                <?= csrf_field() ?><button class="k-btn k-btn--primary k-btn--sm"><?= icon('plus', 'sm') ?>Ajouter à mes recettes</button>
            </form>
        <?php endif; ?>
    </div>
<?php elseif (!empty($r['is_public'])): ?>
    <p class="k-help public-note"><?= icon('share', 'sm') ?> Recette publique : visible par les autres foyers (sans vos notes). Décochez l'option dans « Modifier » pour la retirer.</p>
<?php endif; ?>

<div class="recipe-cols">
<div class="recipe-main">
<section class="k-card k-card--pad">
    <h2>Ingrédients</h2>
    <?php if ($ings):
        $homeServings = (int) $hh['default_servings'];
        $factor = $people / $baseServings;
        $scaleHref = fn(int $n) => url('/recipes/' . (int) $r['id'], $n === $homeServings ? [] : ['p' => $n]);
    ?>
        <div class="scaler" data-scaler data-base="<?= $baseServings ?>" data-default="<?= $homeServings ?>" data-current="<?= $people ?>">
            <span class="k-label" id="scaler-label">Pour</span>
            <a class="k-iconbtn k-iconbtn--tint scaler__btn<?= $people <= 1 ? ' is-disabled' : '' ?>" data-scale-minus href="<?= e($scaleHref(max(1, $people - 1))) ?>" aria-label="Une personne de moins">−</a>
            <input class="k-input scaler__input" type="number" inputmode="numeric" min="1" max="30" value="<?= $people ?>" data-scale-input aria-labelledby="scaler-label" aria-describedby="scaler-live">
            <a class="k-iconbtn k-iconbtn--tint scaler__btn<?= $people >= 30 ? ' is-disabled' : '' ?>" data-scale-plus href="<?= e($scaleHref(min(30, $people + 1))) ?>" aria-label="Une personne de plus"><?= icon('plus') ?></a>
            <span class="k-label" data-scale-word><?= $people > 1 ? 'personnes' : 'personne' ?></span>
            <a class="scaler__reset" data-scale-reset href="<?= e($scaleHref($baseServings)) ?>" <?= $people === $baseServings ? 'hidden' : '' ?>>Recette d'origine : <?= $baseServings ?></a>
            <span class="sr-only" id="scaler-live" role="status" aria-live="polite" data-scale-live></span>
        </div>
        <ul class="plain ing-list" data-scaler-scope>
            <?php foreach ($ings as $i): $q = $i['quantity'] !== null ? (float) $i['quantity'] : null; ?>
                <li<?= $q !== null ? ' data-qty="' . e((string) $q) . '" data-unit="' . e((string) ($i['unit'] ?? '')) . '"' : '' ?>>
                    <span><?php if ($q !== null): ?><b class="k-qty" data-qty-text><?= e(scale_quantity($q * $factor, $i['unit'] ?: null)) ?></b><?php endif; ?><?= e($i['name']) ?></span>
                    <span class="k-meta"><?= icon(aisle_icon((string) $i['aisle']), 'sm') ?> <?= e($i['aisle'] ?? '') ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <script src="<?= e(asset('scale.js')) ?>" defer></script>
    <?php else: ?>
        <div class="k-banner k-banner--warn" role="status">
            <?= icon('warn') ?><div class="k-banner__body"><b>Aucun ingrédient</b>Ce plat n'apparaîtra pas dans la liste de courses.
                <?php if ($own): ?><a href="<?= e(url('/recipes/' . (int) $r['id'] . '/edit')) ?>">Ajouter des ingrédients</a><?php endif; ?></div>
        </div>
    <?php endif; ?>
</section>

<section class="k-card k-card--pad recipe-steps" id="preparation">
    <h2>Préparation</h2>
    <?php if ($steps): $n = 0; ?>
        <ol class="plain steps-list">
            <?php foreach ($steps as $st): ?>
                <?php if ($st['kind'] === 'heading'): ?>
                    <li class="steps-heading"><h3><?= e($st['text']) ?></h3></li>
                <?php else: $n++; ?>
                    <li class="steps-item"><span class="steps-n" aria-hidden="true"><?= $n ?></span><p class="pre"><?= e($st['text']) ?></p></li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ol>
    <?php else: ?>
        <div class="k-muted steps-empty">Aucune étape pour le moment.
            <?php if ($own && safe_url($r['url'])): ?>
                <form method="post" action="<?= e(url('/recipes/' . (int) $r['id'] . '/refetch')) ?>" class="inline">
                    <?= csrf_field() ?><button class="k-btn k-btn--secondary k-btn--sm"><?= icon('down', 'sm') ?>Récupérer depuis le lien</button>
                </form>
            <?php endif; ?>
            <?php if ($own): ?><a href="<?= e(url('/recipes/' . (int) $r['id'] . '/edit') . '#r-steps') ?>">Écrire les étapes</a><?php endif; ?></div>
    <?php endif; ?>
    <?php if ($r['notes']): ?><h3>Notes</h3><p class="pre"><?= e($r['notes']) ?></p><?php endif; ?>
</section>
</div>

<?php if ($own): ?>
<section class="k-card k-card--pad">
    <h2>Planifier</h2>
    <form method="post" action="<?= e(url('/meals/add')) ?>" class="stack">
        <?= csrf_field() ?><input type="hidden" name="recipe_id" value="<?= (int) $r['id'] ?>">
        <div class="k-field"><label class="k-label" for="pl-date">Date</label><input class="k-input" id="pl-date" type="date" name="date" value="<?= e(date('Y-m-d')) ?>" required></div>
        <div class="row-form">
            <div class="k-field grow"><label class="k-label" for="pl-slot">Repas</label>
                <span class="k-selectwrap"><select class="k-select" id="pl-slot" name="slot"><?php foreach (SLOTS as $k => $l): ?><option value="<?= $k ?>" <?= $k === 'dinner' ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select><?= icon('down', 'sm') ?></span></div>
            <div class="k-field"><label class="k-label" for="pl-pers">Personnes</label><input class="k-input" id="pl-pers" type="number" name="servings" min="1" max="30" value="<?= isset($people) && $people !== (int) $hh['default_servings'] ? (int) $people : '' ?>" placeholder="<?= (int) $hh['default_servings'] ?>"></div>
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
<?php endif; ?>
</div>
