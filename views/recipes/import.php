<a class="k-btn k-btn--ghost k-btn--sm back-link" href="<?= e(url('/recipes')) ?>"><?= icon('left', 'sm') ?>Recettes</a>
<h1 class="page-title">Importer une recette depuis un lien</h1>

<section class="k-card k-card--pad">
    <form method="post" action="<?= e(url('/recipes/import-url')) ?>" class="stack">
        <?= csrf_field() ?>
        <div class="k-field">
            <label class="k-label" for="imp-url">Adresse de la recette</label>
            <input class="k-input" id="imp-url" type="url" name="url" value="<?= e($url ?? '') ?>" required autofocus maxlength="2000"
                   placeholder="https://www.marmiton.org/recettes/…" inputmode="url" autocomplete="off" autocapitalize="off" spellcheck="false">
            <p class="k-help">Collez le lien d'une recette : Kitcho lit la page et reconnaît le titre, les ingrédients, les étapes de préparation, les durées et le nombre de portions. Vous pouvez tout vérifier et corriger avant d'enregistrer.</p>
        </div>
        <div><button class="k-btn k-btn--primary" data-busy="Lecture de la page…"><?= icon('down') ?>Importer la recette</button></div>
    </form>
</section>

<section class="k-card k-card--pad">
    <h2>Comment ça marche</h2>
    <ul class="plain pwa-steps">
        <li>La plupart des sites de cuisine (Marmiton, 750g, CuisineAZ, Ricardo, HelloFresh, blogs…) publient leurs recettes dans un format standard (<i>schema.org</i>) que Kitcho sait lire.</li>
        <li>Les quantités sont converties pour la liste de courses (« 2 c. à soupe d'huile », « 1 ½ verre de lait », « une pincée de sel »…), et un rayon est deviné pour chaque ingrédient.</li>
        <li>Les photos ne sont pas copiées : le lien d'origine reste enregistré dans la recette.</li>
        <li>Certains sites bloquent la lecture automatique ou n'ont pas de recette structurée : dans ce cas Kitcho vous le dit, et vous pouvez la saisir à la main.</li>
    </ul>
</section>
