<section class="card narrow">
    <h1>Créer un compte</h1>
    <?php if ($invite): ?>
        <p class="notice">Après l'inscription, vous rejoindrez automatiquement le foyer <strong><?= e($invite['household_name']) ?></strong>.</p>
    <?php endif; ?>
    <form method="post" class="stack">
        <?= csrf_field() ?>
        <label>Prénom / nom
            <input name="name" value="<?= e($old['name']) ?>" required maxlength="100" autofocus autocomplete="name">
        </label>
        <label>E-mail
            <input type="email" name="email" value="<?= e($old['email']) ?>" required maxlength="190" autocomplete="username">
        </label>
        <label>Mot de passe <span class="muted small">(8 caractères minimum)</span>
            <input type="password" name="password" required minlength="8" autocomplete="new-password">
        </label>
        <label>Confirmer le mot de passe
            <input type="password" name="password2" required minlength="8" autocomplete="new-password">
        </label>
        <?php if (!$invite): ?>
            <label>Nom de votre foyer <span class="muted small">(facultatif — vous pourrez inviter d'autres personnes ensuite)</span>
                <input name="household" value="<?= e($old['household']) ?>" maxlength="100" placeholder="Ex. : La famille Dupont">
            </label>
        <?php endif; ?>
        <button class="btn btn-primary">Créer mon compte</button>
    </form>
    <p class="muted small">Déjà inscrit·e ? <a href="<?= e(url('/login')) ?>">Se connecter</a></p>
</section>
