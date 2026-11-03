<section class="card narrow">
    <h1>Connexion</h1>
    <?php if ($invite): ?>
        <p class="notice">Vous êtes invité·e à rejoindre le foyer <strong><?= e($invite['household_name']) ?></strong>. Connectez-vous ou <a href="<?= e(url('/register')) ?>">créez un compte</a> pour accepter.</p>
    <?php endif; ?>
    <form method="post" class="stack">
        <?= csrf_field() ?>
        <label>E-mail
            <input type="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="username">
        </label>
        <label>Mot de passe
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button class="btn btn-primary">Se connecter</button>
    </form>
    <?php if ($invite || config('app.allow_registration', true)): ?>
        <p class="muted small">Pas encore de compte ? <a href="<?= e(url('/register')) ?>">Créer un compte</a></p>
    <?php endif; ?>
</section>
