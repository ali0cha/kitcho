<?php
declare(strict_types=1);

// Installeur : crée config.php et les tables. À supprimer une fois l'application installée.
$root = __DIR__;
$installed = is_file($root . '/config.php') || (string) getenv('DB_HOST') !== '';
$errors = [];
$done = false;
$configText = null;
$v = ['host' => 'localhost', 'name' => '', 'user' => '', 'registration' => '1'];

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

if (!$installed && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $v = [
        'host' => trim((string) ($_POST['host'] ?? '')),
        'name' => trim((string) ($_POST['name'] ?? '')),
        'user' => trim((string) ($_POST['user'] ?? '')),
        'registration' => isset($_POST['registration']) ? '1' : '0',
    ];
    $pass = (string) ($_POST['pass'] ?? '');
    if (!extension_loaded('pdo_mysql')) {
        $errors[] = "L'extension PHP pdo_mysql est requise.";
    }
    if (PHP_VERSION_ID < 80100) {
        $errors[] = 'PHP 8.1 ou supérieur est requis (version actuelle : ' . PHP_VERSION . ').';
    }
    if (!$errors) {
        try {
            $pdo = new PDO("mysql:host={$v['host']};dbname={$v['name']};charset=utf8mb4", $v['user'], $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $sql = preg_replace('/^\s*--.*$/m', '', (string) file_get_contents($root . '/sql/schema.sql'));
            foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $stmt) {
                $pdo->exec($stmt);
            }
            $config = [
                'db' => ['host' => $v['host'], 'name' => $v['name'], 'user' => $v['user'], 'pass' => $pass, 'charset' => 'utf8mb4'],
                'app' => ['name' => 'Kitcho', 'allow_registration' => $v['registration'] === '1', 'debug' => false, 'timezone' => 'Europe/Brussels'],
            ];
            $configText = "<?php\nreturn " . var_export($config, true) . ";\n";
            if (@file_put_contents($root . '/config.php', $configText, LOCK_EX) !== false) {
                @chmod($root . '/config.php', 0640);
                $configText = null;
            }
            $done = true;
        } catch (Throwable $e) {
            $errors[] = 'Connexion ou création des tables impossible : ' . $e->getMessage();
        }
    }
}
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
?><!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex"><title>Installation · Kitcho</title>
<link rel="stylesheet" href="<?= h($base) ?>/assets/style.css"></head>
<body><main class="container"><section class="card narrow">
<h1>Installation</h1>
<?php if ($installed && !$done): ?>
    <p class="notice">L'application est déjà installée (config.php existe). Pour sécurité, supprimez <code>install.php</code> du serveur.</p>
    <p><a class="btn btn-primary" href="<?= h($base) ?>/">Ouvrir l'application</a></p>
<?php elseif ($done): ?>
    <p class="flash flash-success">Tables créées.</p>
    <?php if ($configText !== null): ?>
        <p class="flash flash-error">Impossible d'écrire <code>config.php</code>. Créez-le à la main avec ce contenu :</p>
        <textarea rows="14" readonly><?= h($configText) ?></textarea>
    <?php endif; ?>
    <ol>
        <li>Supprimez <code>install.php</code> du serveur.</li>
        <li><a href="<?= h($base) ?>/register">Créez votre compte</a> : le premier foyer est créé avec.</li>
    </ol>
<?php else: ?>
    <?php foreach ($errors as $e): ?><p class="flash flash-error"><?= h($e) ?></p><?php endforeach; ?>
    <p class="muted">Créez d'abord une base MySQL vide (ex. depuis le manager OVH), puis renseignez ses identifiants.</p>
    <form method="post" class="stack">
        <label>Serveur MySQL <input name="host" value="<?= h($v['host']) ?>" required></label>
        <label>Nom de la base <input name="name" value="<?= h($v['name']) ?>" required></label>
        <label>Utilisateur <input name="user" value="<?= h($v['user']) ?>" required></label>
        <label>Mot de passe <input type="password" name="pass" autocomplete="off"></label>
        <label class="check"><input type="checkbox" name="registration" value="1" <?= $v['registration'] === '1' ? 'checked' : '' ?>> Inscriptions ouvertes à tous (sinon : sur invitation uniquement)</label>
        <button class="btn btn-primary">Installer</button>
    </form>
<?php endif; ?>
</section></main></body></html>
