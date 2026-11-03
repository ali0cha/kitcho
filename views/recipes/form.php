<h1><?= e($title) ?></h1>
<form method="post" class="card stack">
    <?= csrf_field() ?>
    <div class="grid-form">
        <label class="span-2">Nom
            <input name="name" value="<?= e($recipe['name']) ?>" required maxlength="200" autofocus>
        </label>
        <label>Catégorie
            <select name="category">
                <?php foreach (CATEGORIES as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $recipe['category'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Portions de la recette
            <input type="number" name="servings" value="<?= (int) $recipe['servings'] ?>" min="1" max="30">
        </label>
        <label class="span-2">Lien (site, vidéo…)
            <input type="url" name="url" value="<?= e($recipe['url'] ?? '') ?>" maxlength="500" placeholder="https://…">
        </label>
    </div>
    <label>Ingrédients <span class="muted small">— un par ligne : « 200 g pâtes », « 2 oignons », « 1/2 c. à café de sel », « sel ; Épicerie »</span>
        <textarea name="ingredients" rows="9" placeholder="400 g tomates pelées&#10;1 oignon&#10;2 gousses d'ail&#10;250 g pâtes"><?= e($ingText) ?></textarea>
    </label>
    <p class="muted small">Le rayon est deviné automatiquement ; ajoutez « ; Crèmerie » en fin de ligne pour le forcer. Rayons : <?= e(implode(', ', AISLES)) ?>.</p>
    <label>Notes <textarea name="notes" rows="3"><?= e($recipe['notes'] ?? '') ?></textarea></label>
    <div class="row-form">
        <label class="check"><input type="checkbox" name="favorite" value="1" <?= $recipe['favorite'] ? 'checked' : '' ?>> ★ Favori (proposé plus souvent)</label>
        <label class="check"><input type="checkbox" name="auto_ok" value="1" <?= $recipe['auto_ok'] ? 'checked' : '' ?>> Proposer dans la génération automatique</label>
    </div>
    <div class="row-form">
        <button class="btn btn-primary">Enregistrer</button>
        <a class="btn" href="<?= e(url($id ? '/recipes/' . $id : '/recipes')) ?>">Annuler</a>
    </div>
</form>
