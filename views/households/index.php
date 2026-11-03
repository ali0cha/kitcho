<h1 class="page-title">Mes foyers</h1>
<?php if (!$households): ?>
    <div class="k-banner" role="status"><?= icon('info') ?><div class="k-banner__body">Vous ne faites partie d'aucun foyer. Créez le vôtre, ou ouvrez un lien d'invitation que l'on vous a envoyé.</div></div>
<?php endif; ?>
<div class="grid-2">
    <section class="k-card k-card--pad">
        <h2>Foyers</h2>
        <?php if ($households): ?>
        <ul class="plain list-rows">
            <?php foreach ($households as $h): ?>
                <li>
                    <span><b><?= e($h['name']) ?></b>
                        <span class="k-badge <?= $h['role'] === 'owner' ? 'k-badge--admin' : 'k-badge--member' ?>"><?= $h['role'] === 'owner' ? 'administrateur' : 'membre' ?></span><br>
                        <span class="k-meta"><?= (int) $h['members'] ?> <?= plural((int) $h['members'], 'membre', 'membres') ?></span></span>
                    <?php if ($current && (int) $current['id'] === (int) $h['id']): ?>
                        <span class="k-badge cat-veg"><?= icon('check', 'sm') ?>actif</span>
                    <?php else: ?>
                        <form method="post" action="<?= e(url('/households/switch')) ?>" class="inline">
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $h['id'] ?>">
                            <button class="k-btn k-btn--secondary k-btn--sm">Ouvrir</button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php else: ?><p class="k-muted">Aucun foyer pour l'instant.</p><?php endif; ?>
    </section>
    <section class="k-card k-card--pad">
        <h2>Créer un foyer</h2>
        <form method="post" action="<?= e(url('/households/create')) ?>" class="stack">
            <?= csrf_field() ?>
            <div class="k-field"><label class="k-label" for="hh-name">Nom du foyer</label>
                <input class="k-input" id="hh-name" name="name" required maxlength="100" placeholder="Ex. : La famille Dupont"></div>
            <button class="k-btn k-btn--primary"><?= icon('plus') ?>Créer</button>
        </form>
    </section>
</div>
