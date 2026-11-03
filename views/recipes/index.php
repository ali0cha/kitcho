<div class="page-head">
    <h1>Recettes <span class="muted small" data-filter-count data-total="<?= (int) $total ?>">(<?= (int) $total ?>)</span></h1>
    <a class="btn btn-primary" href="<?= e(url('/recipes/new')) ?>">+ Nouvelle recette</a>
</div>

<form method="get" class="filters" data-live-filter>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Rechercher une recette…" aria-label="Rechercher" autocomplete="off" autofocus data-filter-input>
    <select name="cat" aria-label="Catégorie" data-filter-cat>
        <option value="">Toutes catégories</option>
        <?php foreach (CATEGORIES as $k => $label): ?>
            <option value="<?= e($k) ?>" <?= $cat === $k ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="sort" aria-label="Tri" data-autosubmit>
        <?php foreach (['name' => 'Nom', 'times' => 'Les plus planifiées', 'last' => 'Dernière fois', 'recent' => 'Ajoutées récemment'] as $k => $label): ?>
            <option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
    </select>
</form>

<?php if (!$recipes): ?>
    <p class="notice"><?= $total ? 'Aucune recette ne correspond.' : 'Aucune recette pour le moment : ajoutez-en une ou collez une liste de plats ci-dessous.' ?></p>
<?php else: ?>
<div class="card flush">
<table class="table">
    <thead><tr><th></th><th>Recette</th><th>Catégorie</th><th class="hide-sm">Ingrédients</th><th class="hide-sm">Planifiée</th><th class="hide-sm">Dernière fois</th></tr></thead>
    <tbody data-filter-body>
    <?php foreach ($recipes as $r): ?>
        <tr data-filter-row data-name="<?= e(norm_key($r['name'])) ?>" data-cat="<?= e($r['category']) ?>">
            <td class="narrow-col">
                <form method="post" action="<?= e(url('/recipes/' . (int) $r['id'] . '/favorite')) ?>" class="inline">
                    <?= csrf_field() ?><input type="hidden" name="back" value="<?= e(request_app_path()) ?>">
                    <button class="star <?= $r['favorite'] ? 'on' : '' ?>" title="Favori" aria-label="Basculer favori">★</button>
                </form>
            </td>
            <td>
                <a href="<?= e(url('/recipes/' . (int) $r['id'])) ?>"><?= e($r['name']) ?></a>
                <?php if (!$r['auto_ok']): ?><span class="badge" title="Exclue de la génération automatique">manuelle</span><?php endif; ?>
            </td>
            <td><span class="badge cat-<?= e($r['category']) ?>"><?= e(CATEGORIES[$r['category']] ?? $r['category']) ?></span></td>
            <td class="hide-sm"><?= $r['ingredient_count'] ? (int) $r['ingredient_count'] : '<span class="muted">—</span>' ?></td>
            <td class="hide-sm"><?= (int) $r['times_planned'] ?>×</td>
            <td class="hide-sm"><?= $r['last_planned'] ? e(date('d/m/Y', strtotime($r['last_planned']))) : '<span class="muted">—</span>' ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="muted center" data-filter-empty hidden>Aucune recette ne correspond.</p>
</div>
<?php endif; ?>

<details class="card">
    <summary><strong>Importer une liste de plats</strong></summary>
    <form method="post" action="<?= e(url('/recipes/import')) ?>" class="stack">
        <?= csrf_field() ?>
        <label>Un plat par ligne (la catégorie est devinée, les doublons ignorés)
            <textarea name="names" rows="6" placeholder="Pâtes carbonara&#10;Soupe aux chicons&#10;Quiche saumon épinards"></textarea>
        </label>
        <button class="btn">Importer</button>
    </form>
</details>
