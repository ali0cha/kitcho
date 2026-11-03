<?php
$titles = [403 => 'Accès refusé', 404 => 'Cette page est introuvable', 405 => 'Action impossible', 419 => 'Votre session a expiré', 500 => 'Oups, quelque chose a brûlé'];
?>
<section class="err-page k-gingham">
    <p class="err-code k-display"><?= (int) $code ?></p>
    <h1><?= e($titles[(int) $code] ?? 'Une erreur est survenue') ?></h1>
    <p class="k-muted"><?= e($message) ?></p>
    <?php if (!empty($detail)): ?><pre class="err-detail"><?= e($detail) ?></pre><?php endif; ?>
    <a class="k-btn k-btn--primary" href="<?= e(url('/')) ?>">Retour à l'accueil</a>
</section>
