<?= hero() ?>
<section class="k-card k-card--pad auth-card">
    <h1>Créer un compte</h1>
    <?php if ($invite): ?>
        <div class="k-banner k-banner--ok" role="status">
            <?= icon('home') ?><div class="k-banner__body">Après l'inscription, vous rejoindrez automatiquement le foyer <b><?= e($invite['household_name']) ?></b>.</div>
        </div>
    <?php endif; ?>
    <form method="post" class="stack">
        <?= csrf_field() ?>
        <div class="k-field"><label class="k-label" for="name">Prénom / nom</label>
            <input class="k-input" id="name" name="name" value="<?= e($old['name']) ?>" required maxlength="100" autofocus autocomplete="name"></div>
        <div class="k-field"><label class="k-label" for="email">E-mail</label>
            <input class="k-input" id="email" type="email" name="email" value="<?= e($old['email']) ?>" required maxlength="190" autocomplete="username"></div>
        <div class="k-field"><label class="k-label" for="password">Mot de passe</label>
            <input class="k-input" id="password" type="password" name="password" required minlength="8" autocomplete="new-password">
            <p class="k-help">8 caractères minimum.</p></div>
        <div class="k-field"><label class="k-label" for="password2">Confirmer le mot de passe</label>
            <input class="k-input" id="password2" type="password" name="password2" required minlength="8" autocomplete="new-password"></div>
        <?php if (!$invite): ?>
            <div class="k-field"><label class="k-label" for="household">Nom de votre foyer</label>
                <input class="k-input" id="household" name="household" value="<?= e($old['household']) ?>" maxlength="100" placeholder="Ex. : La famille Dupont">
                <p class="k-help">Facultatif. Vous pourrez inviter d'autres personnes ensuite.</p></div>
        <?php endif; ?>
        <button class="k-btn k-btn--primary k-btn--block">Créer mon compte</button>
    </form>
    <p class="k-help">Déjà inscrit·e ? <a href="<?= e(url('/login')) ?>">Se connecter</a></p>
</section>
