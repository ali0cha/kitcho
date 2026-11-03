<section class="card narrow center">
    <h1><?= (int) $code ?></h1>
    <p><?= e($message) ?></p>
    <?php if (!empty($detail)): ?><pre class="small"><?= e($detail) ?></pre><?php endif; ?>
    <p><a class="btn" href="<?= e(url('/')) ?>">Retour à l'accueil</a></p>
</section>
