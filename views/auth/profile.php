<h1>Mon profil</h1>
<div class="grid-2">
    <section class="card">
        <h2>Informations</h2>
        <form method="post" class="stack">
            <?= csrf_field() ?><input type="hidden" name="action" value="name">
            <label>E-mail <input value="<?= e($profile['email']) ?>" disabled></label>
            <label>Nom <input name="name" value="<?= e($profile['name']) ?>" required maxlength="100"></label>
            <button class="btn">Enregistrer</button>
        </form>
    </section>
    <section class="card">
        <h2>Mot de passe</h2>
        <form method="post" class="stack">
            <?= csrf_field() ?><input type="hidden" name="action" value="password">
            <label>Mot de passe actuel <input type="password" name="current_password" required autocomplete="current-password"></label>
            <label>Nouveau mot de passe <input type="password" name="new_password" required minlength="8" autocomplete="new-password"></label>
            <label>Confirmer <input type="password" name="new_password2" required minlength="8" autocomplete="new-password"></label>
            <button class="btn">Changer le mot de passe</button>
        </form>
    </section>
</div>
<section class="card">
    <h2>Mes foyers</h2>
    <ul class="plain">
        <?php foreach ($households as $h): ?>
            <li><?= e($h['name']) ?> <span class="badge"><?= $h['role'] === 'owner' ? 'administrateur' : 'membre' ?></span>
                <span class="muted small">· <?= (int) $h['members'] ?> <?= plural((int) $h['members'], 'membre', 'membres') ?></span></li>
        <?php endforeach; ?>
    </ul>
    <p><a href="<?= e(url('/households')) ?>">Gérer mes foyers</a></p>
</section>
