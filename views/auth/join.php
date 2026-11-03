<?= hero('Vous avez été invité·e à partager un foyer.') ?>
<section class="k-card k-card--pad auth-card center">
    <h1>Invitation</h1>
    <p>Rejoindre le foyer <b><?= e($inv['household_name']) ?></b>
       <span class="k-muted">(<?= (int) $inv['members'] ?> <?= plural((int) $inv['members'], 'membre', 'membres') ?>)</span></p>

    <?php if ($state !== 'active' && !$already): ?>
        <div class="k-banner k-banner--err" role="alert">
            <?= icon('warn') ?><div class="k-banner__body"><b><?= ['revoked' => 'Ce lien a été désactivé.', 'expired' => 'Ce lien a expiré.', 'used' => 'Ce lien a déjà été utilisé.'][$state] ?? 'Ce lien n\'est plus valide.' ?></b>
            Demandez un nouveau lien à un administrateur du foyer.</div>
        </div>
    <?php elseif ($already): ?>
        <p class="k-muted">Vous faites déjà partie de ce foyer.</p>
        <a class="k-btn k-btn--primary k-btn--block" href="<?= e(url('/week')) ?>">Ouvrir le planning</a>
    <?php elseif ($user): ?>
        <p class="k-muted">Connecté en tant que <?= e($user['name']) ?>.</p>
        <form method="post"><?= csrf_field() ?><button class="k-btn k-btn--primary k-btn--block">Rejoindre ce foyer</button></form>
    <?php else: ?>
        <p class="k-muted">Créez un compte ou connectez-vous pour rejoindre le foyer.</p>
        <div class="stack">
            <a class="k-btn k-btn--primary k-btn--block" href="<?= e(url('/register')) ?>">Créer un compte</a>
            <a class="k-btn k-btn--secondary k-btn--block" href="<?= e(url('/login')) ?>">J'ai déjà un compte</a>
        </div>
    <?php endif; ?>
</section>
