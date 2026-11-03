<h1>Mes foyers</h1>
<?php if (!$households): ?>
    <p class="notice">Vous ne faites partie d'aucun foyer. Créez le vôtre, ou ouvrez un lien d'invitation que l'on vous a envoyé.</p>
<?php endif; ?>
<div class="grid-2">
    <section class="card">
        <h2>Foyers</h2>
        <?php if ($households): ?>
        <ul class="plain list-rows">
            <?php foreach ($households as $h): ?>
                <li>
                    <span><strong><?= e($h['name']) ?></strong>
                        <span class="badge"><?= $h['role'] === 'owner' ? 'administrateur' : 'membre' ?></span>
                        <span class="muted small">· <?= (int) $h['members'] ?> <?= plural((int) $h['members'], 'membre', 'membres') ?></span></span>
                    <?php if ($current && (int) $current['id'] === (int) $h['id']): ?>
                        <span class="badge badge-ok">actif</span>
                    <?php else: ?>
                        <form method="post" action="<?= e(url('/households/switch')) ?>" class="inline">
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $h['id'] ?>">
                            <button class="btn btn-small">Ouvrir</button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php else: ?><p class="muted">Aucun foyer pour l'instant.</p><?php endif; ?>
    </section>
    <section class="card">
        <h2>Créer un foyer</h2>
        <form method="post" action="<?= e(url('/households/create')) ?>" class="stack">
            <?= csrf_field() ?>
            <label>Nom du foyer <input name="name" required maxlength="100" placeholder="Ex. : La famille Dupont"></label>
            <button class="btn btn-primary">Créer</button>
        </form>
    </section>
</div>
