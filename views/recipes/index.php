<div class="page-head">
    <h1 class="page-title">Recettes <span class="k-count" data-filter-count data-total="<?= (int) $total ?>"><?= (int) $total ?></span></h1>
    <div class="page-head__actions">
        <a class="k-btn k-btn--secondary" href="<?= e(url('/recipes/import-url')) ?>"><?= icon('down') ?>Importer un lien</a>
    <a class="k-btn k-btn--primary" href="<?= e(url('/recipes/new')) ?>"><?= icon('plus') ?>Nouvelle recette</a>
    </div>
</div>

<form method="get" class="filters" data-live-filter role="search">
    <div class="k-searchbox filters__search">
        <?= icon('search') ?>
        <input class="k-input" type="search" name="q" value="<?= e($q) ?>" placeholder="Rechercher une recette…" aria-label="Rechercher une recette" autocomplete="off" autofocus data-filter-input>
    </div>
    <span class="k-selectwrap">
        <select class="k-select" name="cat" aria-label="Catégorie" data-filter-cat>
            <option value="">Catégories</option>
            <?php foreach (CATEGORIES as $k => $label): ?>
                <option value="<?= e($k) ?>" <?= $cat === $k ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select><?= icon('down', 'sm') ?>
    </span>
    <span class="k-selectwrap">
        <select class="k-select" name="sort" aria-label="Tri" data-autosubmit>
            <?php foreach (['name' => 'Nom', 'times' => 'Les plus planifiées', 'last' => 'Dernière fois', 'recent' => 'Ajoutées récemment'] as $k => $label): ?>
                <option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select><?= icon('down', 'sm') ?>
    </span>
    <?php if ($publicCount || $pub): ?>
        <label class="k-check filters__pub"><input type="checkbox" name="pub" value="1" data-autosubmit <?= $pub ? 'checked' : '' ?>>Afficher les recettes publiques<?= $publicCount ? ' (' . (int) $publicCount . ')' : '' ?></label>
    <?php endif; ?>
</form>

<?php if (!$recipes && !$public): ?>
    <section class="k-card k-empty">
        <?= empty_art() ?>
        <h2>Aucune recette pour le moment</h2>
        <p>Ajoutez votre première recette, ou collez une liste de plats ci-dessous.</p>
        <a class="k-btn k-btn--primary" href="<?= e(url('/recipes/new')) ?>">Nouvelle recette</a>
    </section>
<?php else: ?>
<div class="k-card flush">
    <?php if (!$recipes): ?><p class="k-muted center rec-none">Vous n'avez pas encore de recette à vous.</p><?php endif; ?>
    <ul class="plain rec-list">
    <?php foreach ($recipes as $r): ?>
        <li class="rec-row" data-filter-row data-name="<?= e(norm_key($r['name'])) ?>" data-cat="<?= e($r['category']) ?>">
            <form method="post" action="<?= e(url('/recipes/' . (int) $r['id'] . '/favorite')) ?>" class="inline">
                <?= csrf_field() ?><input type="hidden" name="back" value="<?= e(request_app_path()) ?>">
                <button class="k-iconbtn fav<?= $r['favorite'] ? ' is-on' : '' ?>" aria-pressed="<?= $r['favorite'] ? 'true' : 'false' ?>"
                        aria-label="<?= $r['favorite'] ? 'Retirer des favoris' : 'Ajouter aux favoris' ?> : <?= e($r['name']) ?>"><?= icon($r['favorite'] ? 'starfill' : 'star') ?></button>
            </form>
            <?= thumb($r['category']) ?>
            <a class="rec-main" href="<?= e(url('/recipes/' . (int) $r['id'])) ?>">
                <b><?= e($r['name']) ?></b>
                <span class="k-meta">
                    <?= $r['ingredient_count'] ? (int) $r['ingredient_count'] . ' ' . plural((int) $r['ingredient_count'], 'ingrédient', 'ingrédients') : 'sans ingrédients' ?>
                    · planifiée <?= (int) $r['times_planned'] ?>×<?= $r['last_planned'] ? ' · dernière fois le ' . e(date('d/m/Y', strtotime($r['last_planned']))) : '' ?>
                </span>
            </a>
            <span class="rec-badges">
                <?= cat_badge($r['category']) ?>
                <?php if (!empty($r['is_public'])): ?><span class="k-badge k-badge--public" title="Visible par les autres foyers"><?= icon('share', 'sm') ?>publique</span><?php endif; ?>
                <?php if (!$r['auto_ok']): ?><span class="k-badge k-badge--manual" title="Exclue de la génération automatique">manuelle</span><?php endif; ?>
            </span>
        </li>
    <?php endforeach; ?>
    </ul>
    <?php if ($public): ?>
        <h2 class="rec-public-title"><?= icon('share') ?>Recettes publiques d'autres foyers <span class="k-meta">lecture seule · ajoutez-les à vos recettes pour les modifier et les planifier</span></h2>
        <ul class="plain rec-list">
        <?php foreach ($public as $r): ?>
            <li class="rec-row rec-row--public" data-filter-row data-name="<?= e(norm_key($r['name'])) ?>" data-cat="<?= e($r['category']) ?>">
                <?= thumb($r['category']) ?>
                <a class="rec-main" href="<?= e(url('/recipes/' . (int) $r['id'])) ?>">
                    <b><?= e($r['name']) ?></b>
                    <span class="k-meta">Partagée par <?= e($r['owner_name']) ?> · <?= $r['ingredient_count'] ? (int) $r['ingredient_count'] . ' ' . plural((int) $r['ingredient_count'], 'ingrédient', 'ingrédients') : 'sans ingrédients' ?></span>
                </a>
                <span class="rec-badges"><?= cat_badge($r['category']) ?><span class="k-badge k-badge--public"><?= icon('share', 'sm') ?>publique</span></span>
            </li>
        <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <p class="k-muted center filter-empty" data-filter-empty hidden>Aucune recette ne correspond.</p>
</div>
<?php endif; ?>

<details class="k-details">
    <summary><?= icon('plus') ?>Importer une liste de plats<?= icon('down') ?></summary>
    <div class="k-details__body">
        <form method="post" action="<?= e(url('/recipes/import')) ?>" class="stack">
            <?= csrf_field() ?>
            <div class="k-field"><label class="k-label" for="names">Un plat par ligne</label>
                <textarea class="k-textarea" id="names" name="names" rows="6" placeholder="Pâtes carbonara&#10;Soupe aux chicons&#10;Quiche saumon épinards"></textarea>
                <p class="k-help">La catégorie est devinée, les doublons sont ignorés.</p></div>
            <div><button class="k-btn k-btn--secondary">Importer</button></div>
        </form>
    </div>
</details>

<details class="k-details">
    <summary><?= icon('copy') ?>Exporter / importer un fichier<?= icon('down') ?></summary>
    <div class="k-details__body stack">
        <p class="k-help">Pour transférer vos recettes <em>avec leurs ingrédients</em> vers une autre installation de Kitcho (sauvegarde, nouveau serveur…). À l'import, les recettes déjà présentes (même nom) ne sont pas modifiées ; si l'une n'a aucun ingrédient, ceux du fichier lui sont ajoutés.</p>
        <p><a class="k-btn k-btn--secondary" href="<?= e(url('/recipes/export')) ?>" download><?= icon('down') ?>Exporter mes recettes</a></p>
        <form method="post" action="<?= e(url('/recipes/import-file')) ?>" enctype="multipart/form-data" class="row-form">
            <?= csrf_field() ?>
            <div class="k-field grow"><label class="k-label" for="file">Fichier d'export (.json)</label>
                <input class="k-input" id="file" type="file" name="file" accept=".json,application/json" required></div>
            <button class="k-btn k-btn--primary">Importer</button>
        </form>
    </div>
</details>
