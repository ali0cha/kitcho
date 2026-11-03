<section class="card narrow center">
    <h1>Invitation</h1>
    <p>Vous êtes invité·e à rejoindre le foyer <strong><?= e($inv['household_name']) ?></strong>
       (<?= (int) $inv['members'] ?> <?= plural((int) $inv['members'], 'membre', 'membres') ?>).</p>

    <?php if ($state !== 'active' && !$already): ?>
        <p class="flash flash-error">
            <?= ['revoked' => 'Ce lien a été désactivé.', 'expired' => 'Ce lien a expiré.', 'used' => 'Ce lien a déjà été utilisé.'][$state] ?? 'Ce lien n\'est plus valide.' ?>
            Demandez un nouveau lien à un administrateur du foyer.
        </p>
    <?php elseif ($already): ?>
        <p>Vous faites déjà partie de ce foyer.</p>
        <p><a class="btn btn-primary" href="<?= e(url('/week')) ?>">Ouvrir le planning</a></p>
    <?php elseif ($user): ?>
        <p class="muted">Connecté en tant que <?= e($user['name']) ?>.</p>
        <form method="post">
            <?= csrf_field() ?>
            <button class="btn btn-primary">Rejoindre ce foyer</button>
        </form>
    <?php else: ?>
        <p>Créez un compte ou connectez-vous pour rejoindre le foyer.</p>
        <p>
            <a class="btn btn-primary" href="<?= e(url('/register')) ?>">Créer un compte</a>
            <a class="btn" href="<?= e(url('/login')) ?>">J'ai déjà un compte</a>
        </p>
    <?php endif; ?>
</section>
