<h1 class="page-title"><?= e($title) ?></h1>
<form method="post" class="k-card k-card--pad stack">
    <?= csrf_field() ?>
    <div class="grid-form">
        <div class="k-field span-2"><label class="k-label" for="r-name">Nom</label>
            <input class="k-input" id="r-name" name="name" value="<?= e($recipe['name']) ?>" required maxlength="200" autofocus></div>
        <div class="k-field"><label class="k-label" for="r-cat">Catégorie</label>
            <span class="k-selectwrap"><select class="k-select" id="r-cat" name="category">
                <?php foreach (CATEGORIES as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $recipe['category'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?></select><?= icon('down', 'sm') ?></span></div>
        <div class="k-field"><label class="k-label" for="r-serv">Portions de la recette</label>
            <input class="k-input" id="r-serv" type="number" name="servings" value="<?= (int) $recipe['servings'] ?>" min="1" max="30"></div>
        <div class="k-field span-2"><label class="k-label" for="r-url">Lien (site, vidéo…)</label>
            <input class="k-input" id="r-url" type="url" name="url" value="<?= e($recipe['url'] ?? '') ?>" maxlength="500" placeholder="https://…"></div>
    </div>
    <div class="k-field"><label class="k-label" for="r-ing">Ingrédients</label>
        <textarea class="k-textarea" id="r-ing" name="ingredients" rows="9" placeholder="400 g tomates pelées&#10;1 oignon&#10;2 gousses d'ail&#10;250 g pâtes"><?= e($ingText) ?></textarea>
        <p class="k-help">Un ingrédient par ligne : « 200 g pâtes », « 2 oignons », « 1/2 c. à café de sel », « sel ; Épicerie ». Le rayon est deviné ; ajoutez « ; Crèmerie » en fin de ligne pour le forcer. Rayons : <?= e(implode(', ', AISLES)) ?>.</p></div>
    <div class="k-field"><label class="k-label" for="r-notes">Notes</label>
        <textarea class="k-textarea" id="r-notes" name="notes" rows="3"><?= e($recipe['notes'] ?? '') ?></textarea></div>
    <label class="k-check"><input type="checkbox" name="favorite" value="1" <?= $recipe['favorite'] ? 'checked' : '' ?>>Favori (proposé plus souvent)</label>
    <label class="k-check"><input type="checkbox" name="auto_ok" value="1" <?= $recipe['auto_ok'] ? 'checked' : '' ?>>Proposer dans la génération automatique</label>
    <div class="row-form">
        <button class="k-btn k-btn--primary">Enregistrer</button>
        <a class="k-btn k-btn--secondary" href="<?= e(url($id ? '/recipes/' . $id : '/recipes')) ?>">Annuler</a>
    </div>
</form>
