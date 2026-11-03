<a class="k-btn k-btn--ghost k-btn--sm back-link" href="<?= e(url('/week')) ?>"><?= icon('left', 'sm') ?>Retour</a>
<h1 class="page-title">Mon profil</h1>
<div class="grid-2">
    <section class="k-card k-card--pad">
        <h2>Informations</h2>
        <form method="post" class="stack">
            <?= csrf_field() ?><input type="hidden" name="action" value="name">
            <div class="k-field"><label class="k-label" for="p-email">E-mail</label><input class="k-input" id="p-email" value="<?= e($profile['email']) ?>" disabled></div>
            <div class="k-field"><label class="k-label" for="p-name">Nom</label><input class="k-input" id="p-name" name="name" value="<?= e($profile['name']) ?>" required maxlength="100"></div>
            <button class="k-btn k-btn--secondary">Enregistrer</button>
        </form>
    </section>
    <section class="k-card k-card--pad">
        <h2>Mot de passe</h2>
        <form method="post" class="stack">
            <?= csrf_field() ?><input type="hidden" name="action" value="password">
            <div class="k-field"><label class="k-label" for="p-cur">Mot de passe actuel</label><input class="k-input" id="p-cur" type="password" name="current_password" required autocomplete="current-password"></div>
            <div class="k-field"><label class="k-label" for="p-new">Nouveau mot de passe</label><input class="k-input" id="p-new" type="password" name="new_password" required minlength="8" autocomplete="new-password"></div>
            <div class="k-field"><label class="k-label" for="p-new2">Confirmer</label><input class="k-input" id="p-new2" type="password" name="new_password2" required minlength="8" autocomplete="new-password"></div>
            <button class="k-btn k-btn--secondary">Changer le mot de passe</button>
        </form>
    </section>
</div>
<section class="k-card k-card--pad" data-pwa-card>
    <h2>Application</h2>
    <p class="k-muted">Installez Kitcho sur votre téléphone ou votre ordinateur : icône sur l'écran d'accueil, plein écran, et dernières pages disponibles même sans réseau.</p>
    <div class="k-banner k-banner--ok" data-pwa-status role="status" hidden><?= icon('check') ?><div class="k-banner__body"><b>Application installée</b>Vous utilisez déjà Kitcho en plein écran.</div></div>
    <button type="button" class="k-btn k-btn--primary" data-pwa-install hidden><?= icon('plus') ?>Installer l'application</button>
    <div data-pwa-howto>
        <h3>Comment faire</h3>
        <ul class="plain">
            <li><b>Android (Chrome)</b> : menu ⋮ → <i>Installer l'application</i> (ou le bouton ci-dessus).</li>
            <li><b>iPhone / iPad (Safari)</b> : bouton Partager → <i>Sur l'écran d'accueil</i>.</li>
            <li><b>Ordinateur (Chrome, Edge)</b> : icône d'installation dans la barre d'adresse.</li>
        </ul>
    </div>
</section>
<section class="k-card k-card--pad">
    <h2>Mes foyers</h2>
    <ul class="plain list-rows">
        <?php foreach ($households as $h): ?>
            <li><span><b><?= e($h['name']) ?></b>
                <span class="k-meta">· <?= (int) $h['members'] ?> <?= plural((int) $h['members'], 'membre', 'membres') ?></span></span>
                <span class="k-badge <?= $h['role'] === 'owner' ? 'k-badge--admin' : 'k-badge--member' ?>"><?= $h['role'] === 'owner' ? 'administrateur' : 'membre' ?></span></li>
        <?php endforeach; ?>
    </ul>
    <p><a class="k-btn k-btn--ghost" href="<?= e(url('/households')) ?>">Gérer mes foyers</a></p>
</section>
