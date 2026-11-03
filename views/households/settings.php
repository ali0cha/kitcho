<?php $isOwner = $hh['role'] === 'owner'; $me = (int) current_user()['id']; ?>
<h1>Foyer « <?= e($hh['name']) ?> »</h1>

<div class="grid-2">
<section class="card">
    <h2>Membres</h2>
    <ul class="plain list-rows">
        <?php foreach ($members as $m): ?>
            <li>
                <span>
                    <strong><?= e($m['name']) ?></strong><?= (int) $m['id'] === $me ? ' <span class="muted">(vous)</span>' : '' ?>
                    <span class="badge"><?= $m['role'] === 'owner' ? 'administrateur' : 'membre' ?></span><br>
                    <span class="muted small"><?= e($m['email']) ?></span>
                </span>
                <?php if ($isOwner && (int) $m['id'] !== $me): ?>
                    <span class="actions">
                        <form method="post" action="<?= e(url('/household/member/role')) ?>" class="inline">
                            <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $m['id'] ?>">
                            <input type="hidden" name="role" value="<?= $m['role'] === 'owner' ? 'member' : 'owner' ?>">
                            <button class="btn btn-small"><?= $m['role'] === 'owner' ? 'Retirer admin' : 'Nommer admin' ?></button>
                        </form>
                        <form method="post" action="<?= e(url('/household/member/remove')) ?>" class="inline" data-confirm="Retirer <?= e($m['name']) ?> du foyer ?">
                            <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $m['id'] ?>">
                            <button class="btn btn-small btn-danger">Retirer</button>
                        </form>
                    </span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<?php if ($isOwner): ?>
<section class="card">
    <h2>Paramètres</h2>
    <form method="post" action="<?= e(url('/household/update')) ?>" class="stack">
        <?= csrf_field() ?>
        <label>Nom du foyer <input name="name" value="<?= e($hh['name']) ?>" required maxlength="100"></label>
        <label>Nombre de portions par défaut
            <input type="number" name="default_servings" value="<?= (int) $hh['default_servings'] ?>" min="1" max="30">
        </label>
        <p class="muted small">Les quantités de la liste de courses sont adaptées à ce nombre de portions (modifiable repas par repas).</p>
        <button class="btn">Enregistrer</button>
    </form>
</section>
<?php endif; ?>
</div>

<?php if ($isOwner): ?>
<section class="card">
    <h2>Inviter quelqu'un</h2>
    <form method="post" action="<?= e(url('/household/invite')) ?>" class="row-form">
        <?= csrf_field() ?>
        <label>Validité
            <select name="days">
                <option value="1">1 jour</option>
                <option value="7" selected>7 jours</option>
                <option value="30">30 jours</option>
                <option value="0">Sans limite</option>
            </select>
        </label>
        <label class="check"><input type="checkbox" name="single_use" value="1"> Usage unique</label>
        <button class="btn btn-primary">Créer un lien d'invitation</button>
    </form>

    <?php if ($invites): ?>
        <h3>Liens d'invitation</h3>
        <ul class="plain list-rows">
            <?php foreach ($invites as $inv): ?>
                <li class="<?= $inv['state'] !== 'active' ? 'dim' : '' ?>">
                    <span class="grow">
                        <input class="copy-input" readonly value="<?= e($inv['link']) ?>" aria-label="Lien d'invitation">
                        <span class="muted small">
                            <?= ['active' => 'Actif', 'expired' => 'Expiré', 'used' => 'Utilisé'][$inv['state']] ?? '' ?>
                            · <?= $inv['expires_at'] ? 'expire le ' . e(date('d/m/Y H:i', strtotime($inv['expires_at']))) : 'sans expiration' ?>
                            · <?= (int) $inv['uses'] ?> <?= plural((int) $inv['uses'], 'utilisation', 'utilisations') ?><?= $inv['max_uses'] ? ' / ' . (int) $inv['max_uses'] : '' ?>
                        </span>
                    </span>
                    <span class="actions">
                        <?php if ($inv['state'] === 'active'): ?>
                            <button type="button" class="btn btn-small" data-copy="<?= e($inv['link']) ?>">Copier</button>
                        <?php endif; ?>
                        <form method="post" action="<?= e(url('/household/invite/revoke')) ?>" class="inline">
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $inv['id'] ?>">
                            <button class="btn btn-small btn-danger">Désactiver</button>
                        </form>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <p class="muted small">La personne invitée ouvre le lien, crée son compte (ou se connecte) puis rejoint le foyer et partage recettes, menus et courses.</p>
</section>
<?php endif; ?>

<section class="card">
    <h2>Zone sensible</h2>
    <form method="post" action="<?= e(url('/household/leave')) ?>" class="inline" data-confirm="Quitter ce foyer ?">
        <?= csrf_field() ?>
        <button class="btn btn-danger">Quitter le foyer</button>
    </form>
    <?php if ($isOwner): ?>
        <form method="post" action="<?= e(url('/household/delete')) ?>" class="row-form" data-confirm="Supprimer définitivement le foyer, ses recettes et ses menus ?">
            <?= csrf_field() ?>
            <label>Supprimer le foyer : saisissez son nom pour confirmer
                <input name="confirm_name" placeholder="<?= e($hh['name']) ?>" required>
            </label>
            <button class="btn btn-danger">Supprimer le foyer</button>
        </form>
    <?php endif; ?>
</section>
