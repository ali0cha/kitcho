<a class="k-btn k-btn--ghost k-btn--sm back-link" href="<?= e(url($id ? '/recipes/' . $id : '/recipes')) ?>"><?= icon('left', 'sm') ?><?= $id ? 'Retour à la recette' : 'Recettes' ?></a>
<h1 class="page-title"><?= e($title) ?></h1>
<?php if ($id === null && empty($notice)): ?>
    <p class="k-help">Vous avez le lien d'une recette ? <a href="<?= e(url('/recipes/import-url')) ?>">Importez-la</a> au lieu de la saisir.</p>
<?php elseif ($id !== null && empty($notice) && safe_url($recipe['url'] ?? '')): ?>
    <form method="post" action="<?= e(url('/recipes/' . (int) $id . '/refetch')) ?>" class="inline">
        <?= csrf_field() ?><button class="k-btn k-btn--secondary k-btn--sm" data-busy="Lecture de la page…"><?= icon('down', 'sm') ?>Récupérer les étapes et ingrédients depuis le lien</button>
    </form>
<?php endif; ?>
<?php if (!empty($notice)): ?>
    <div class="k-banner <?= e($notice['class']) ?>" role="status">
        <?= icon($notice['icon']) ?>
        <div class="k-banner__body"><b><?= e($notice['title']) ?></b><?= e($notice['text']) ?>
            <?php if (!empty($notice['items'])): ?><ul class="plain import-notes"><?php foreach ($notice['items'] as $it): ?><li><?= e($it) ?></li><?php endforeach; ?></ul><?php endif; ?></div>
    </div>
<?php endif; ?>
<form method="post" action="<?= e($action ?? '') ?>" class="k-card k-card--pad stack">
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
    <div class="grid-time">
        <div class="k-field"><label class="k-label" for="r-prep">Préparation</label>
            <input class="k-input" id="r-prep" type="number" name="prep_minutes" min="1" max="4320" value="<?= e((string) ($recipe['prep_minutes'] ?? '')) ?>" inputmode="numeric"></div>
        <div class="k-field"><label class="k-label" for="r-cook">Cuisson</label>
            <input class="k-input" id="r-cook" type="number" name="cook_minutes" min="1" max="4320" value="<?= e((string) ($recipe['cook_minutes'] ?? '')) ?>" inputmode="numeric"></div>
        <div class="k-field"><label class="k-label" for="r-total">Total</label>
            <input class="k-input" id="r-total" type="number" name="total_minutes" min="1" max="4320" value="<?= e((string) ($recipe['total_minutes'] ?? '')) ?>" inputmode="numeric" placeholder="auto"></div>
    </div>
    <p class="k-help time-help">Durées en minutes. « Total » est calculé tout seul (préparation + cuisson) si vous le laissez vide.</p>
    <div class="k-field"><label class="k-label" for="r-ing">Ingrédients</label>
        <textarea class="k-textarea" id="r-ing" name="ingredients" rows="9" placeholder="400 g tomates pelées&#10;1 oignon&#10;2 gousses d'ail&#10;250 g pâtes"><?= e($ingText) ?></textarea>
        <p class="k-help">Un ingrédient par ligne : « 200 g pâtes », « 2 oignons », « 1/2 c. à café de sel », « sel ; Épicerie ». Le rayon est deviné ; ajoutez « ; Crèmerie » en fin de ligne pour le forcer. Rayons : <?= e(implode(', ', AISLES)) ?>.</p></div>
    <div class="k-field"><label class="k-label" for="r-steps">Préparation (étapes)</label>
        <textarea class="k-textarea" id="r-steps" name="steps" rows="10" placeholder="Préchauffez le four à 180 °C.&#10;&#10;Mélangez la farine et le sucre dans un saladier.&#10;&#10;Enfournez 35 minutes."><?= e($stepsText ?? '') ?></textarea>
        <p class="k-help">Une étape par paragraphe, séparés par une ligne vide. Les numéros sont ajoutés tout seuls. Une ligne commençant par « # » devient un intertitre (« # Pour la pâte »).</p></div>
    <div class="k-field"><label class="k-label" for="r-notes">Notes</label>
        <textarea class="k-textarea" id="r-notes" name="notes" rows="3"><?= e($recipe['notes'] ?? '') ?></textarea></div>
    <label class="k-check"><input type="checkbox" name="favorite" value="1" <?= $recipe['favorite'] ? 'checked' : '' ?>>Favori (proposé plus souvent)</label>
    <label class="k-check"><input type="checkbox" name="auto_ok" value="1" <?= $recipe['auto_ok'] ? 'checked' : '' ?>>Proposer dans la génération automatique</label>
    <div class="row-form">
        <button class="k-btn k-btn--primary">Enregistrer</button>
        <a class="k-btn k-btn--secondary" href="<?= e(url($id ? '/recipes/' . $id : '/recipes')) ?>">Annuler</a>
    </div>
</form>
