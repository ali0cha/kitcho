<?= hero() ?>
<section class="k-card k-card--pad auth-card">
    <h1>Connexion</h1>
    <?php if ($invite): ?>
        <div class="k-banner" role="status">
            <?= icon('info') ?><div class="k-banner__body">Vous êtes invité·e à rejoindre le foyer <b><?= e($invite['household_name']) ?></b>. Connectez-vous ou <a href="<?= e(url('/register')) ?>">créez un compte</a> pour accepter.</div>
        </div>
    <?php endif; ?>
    <form method="post" class="stack">
        <?= csrf_field() ?>
        <div class="k-field"><label class="k-label" for="email">E-mail</label>
            <input class="k-input" id="email" type="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="username"></div>
        <div class="k-field"><label class="k-label" for="password">Mot de passe</label>
            <input class="k-input" id="password" type="password" name="password" required autocomplete="current-password"></div>
        <button class="k-btn k-btn--primary k-btn--block">Se connecter</button>
    </form>
    <?php if ($invite || config('app.allow_registration', true)): ?>
        <p class="k-help">Pas encore de compte ? <a href="<?= e(url('/register')) ?>">Créer un compte</a></p>
    <?php endif; ?>
</section>
