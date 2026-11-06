<?php $isOwner = $hh['role'] === 'owner'; $me = (int) current_user()['id']; ?>
<h1 class="page-title">Foyer « <?= e($hh['name']) ?> »</h1>

<div class="grid-2">
<section class="k-card k-card--pad">
    <h2>Membres</h2>
    <ul class="plain list-rows">
        <?php foreach ($members as $m): ?>
            <li>
                <span>
                    <b><?= e($m['name']) ?></b><?= (int) $m['id'] === $me ? ' <span class="k-muted">(vous)</span>' : '' ?>
                    <span class="k-badge <?= $m['role'] === 'owner' ? 'k-badge--admin' : 'k-badge--member' ?>"><?= $m['role'] === 'owner' ? 'administrateur' : 'membre' ?></span><br>
                    <span class="k-meta"><?= icon('mail', 'sm') ?> <?= e($m['email']) ?></span>
                </span>
                <?php if ($isOwner && (int) $m['id'] !== $me): ?>
                    <span class="row-actions">
                        <form method="post" action="<?= e(url('/household/member/role')) ?>" class="inline">
                            <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $m['id'] ?>">
                            <input type="hidden" name="role" value="<?= $m['role'] === 'owner' ? 'member' : 'owner' ?>">
                            <button class="k-btn k-btn--secondary k-btn--sm"><?= $m['role'] === 'owner' ? 'Retirer admin' : 'Nommer admin' ?></button>
                        </form>
                        <form method="post" action="<?= e(url('/household/member/remove')) ?>" class="inline" data-confirm="Retirer <?= e($m['name']) ?> du foyer ?">
                            <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $m['id'] ?>">
                            <button class="k-btn k-btn--danger-o k-btn--sm">Retirer</button>
                        </form>
                    </span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<?php if ($isOwner): ?>
<section class="k-card k-card--pad">
    <h2>Réglages</h2>
    <form method="post" action="<?= e(url('/household/update')) ?>" class="stack">
        <?= csrf_field() ?>
        <div class="k-field"><label class="k-label" for="s-name">Nom du foyer</label>
            <input class="k-input" id="s-name" name="name" value="<?= e($hh['name']) ?>" required maxlength="100"></div>
        <div class="k-field"><label class="k-label" for="s-serv">Nombre de portions par défaut</label>
            <input class="k-input" id="s-serv" type="number" name="default_servings" value="<?= (int) $hh['default_servings'] ?>" min="1" max="30">
            <p class="k-help">Les quantités de la liste de courses sont adaptées à ce nombre de personnes (modifiable repas par repas).</p></div>
        <label class="k-check"><input type="checkbox" name="seasonal_pref" value="1" <?= !isset($hh['seasonal_pref']) || $hh['seasonal_pref'] ? 'checked' : '' ?>>Privilégier les recettes de saison dans les menus générés</label>
        <p class="k-help">Les recettes dont les fruits et légumes frais sont de saison (en Belgique) sont proposées plus souvent, celles qui en contiennent hors saison un peu moins. Vous pouvez aussi le décider à chaque génération.</p>
        <div><button class="k-btn k-btn--secondary">Enregistrer</button></div>
    </form>
</section>
<?php endif; ?>
</div>

<?php if ($isOwner): ?>
<section class="k-card k-card--pad">
    <h2>Inviter quelqu'un</h2>
    <form method="post" action="<?= e(url('/household/invite')) ?>" class="row-form">
        <?= csrf_field() ?>
        <div class="k-field"><label class="k-label" for="i-days">Validité</label>
            <span class="k-selectwrap"><select class="k-select" id="i-days" name="days">
                <option value="1">1 jour</option>
                <option value="7" selected>7 jours</option>
                <option value="30">30 jours</option>
                <option value="0">Sans limite</option>
            </select><?= icon('down', 'sm') ?></span></div>
        <label class="k-check"><input type="checkbox" name="single_use" value="1">Usage unique</label>
        <button class="k-btn k-btn--primary"><?= icon('link') ?>Créer un lien d'invitation</button>
    </form>

    <?php if ($invites): ?>
        <h3>Liens d'invitation</h3>
        <ul class="plain list-rows">
            <?php foreach ($invites as $inv): ?>
                <li class="<?= $inv['state'] !== 'active' ? 'is-dim' : '' ?>">
                    <span class="grow">
                        <input class="k-input copy-input" readonly value="<?= e($inv['link']) ?>" aria-label="Lien d'invitation">
                        <span class="k-meta">
                            <?= ['active' => 'Actif', 'expired' => 'Expiré', 'used' => 'Utilisé'][$inv['state']] ?? '' ?>
                            · <?= $inv['expires_at'] ? 'expire le ' . e(date('d/m/Y H:i', strtotime($inv['expires_at']))) : 'sans expiration' ?>
                            · <?= (int) $inv['uses'] ?> <?= plural((int) $inv['uses'], 'utilisation', 'utilisations') ?><?= $inv['max_uses'] ? ' / ' . (int) $inv['max_uses'] : '' ?>
                        </span>
                    </span>
                    <span class="row-actions">
                        <?php if ($inv['state'] === 'active'): ?>
                            <button type="button" class="k-btn k-btn--secondary k-btn--sm" data-copy="<?= e($inv['link']) ?>"><?= icon('copy', 'sm') ?>Copier</button>
                        <?php endif; ?>
                        <form method="post" action="<?= e(url('/household/invite/revoke')) ?>" class="inline">
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $inv['id'] ?>">
                            <button class="k-btn k-btn--danger-o k-btn--sm">Désactiver</button>
                        </form>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <p class="k-help">La personne invitée ouvre le lien, crée son compte (ou se connecte) puis rejoint le foyer et partage recettes, menus et courses.</p>
</section>
<?php endif; ?>

<section class="k-card k-card--pad danger-zone">
    <h2>Zone sensible</h2>
    <form method="post" action="<?= e(url('/household/leave')) ?>" class="inline" data-confirm="Quitter ce foyer ?">
        <?= csrf_field() ?>
        <button class="k-btn k-btn--danger-o"><?= icon('logout') ?>Quitter le foyer</button>
    </form>
    <?php if ($isOwner): ?>
        <form method="post" action="<?= e(url('/household/delete')) ?>" class="row-form" data-confirm="Supprimer définitivement le foyer, ses recettes et ses menus ?">
            <?= csrf_field() ?>
            <div class="k-field grow"><label class="k-label" for="del-name">Supprimer le foyer : saisissez son nom pour confirmer</label>
                <input class="k-input" id="del-name" name="confirm_name" placeholder="<?= e($hh['name']) ?>" required></div>
            <button class="k-btn k-btn--danger"><?= icon('trash') ?>Supprimer le foyer</button>
        </form>
    <?php endif; ?>
</section>
