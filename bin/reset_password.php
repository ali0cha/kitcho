<?php
declare(strict_types=1);

/**
 * Réinitialise le mot de passe d'un compte (oubli, pas de messagerie sur le serveur).
 *
 *   php bin/reset_password.php              liste les comptes
 *   php bin/reset_password.php email        demande le nouveau mot de passe (saisie masquée)
 *
 * Dans Docker : docker exec -it <conteneur-app> php bin/reset_password.php email
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/src/bootstrap.php';

$email = mb_strtolower(trim($argv[1] ?? ''));
if ($email === '') {
    fwrite(STDERR, "Usage : php bin/reset_password.php <email>\nComptes existants :\n");
    foreach (db_all('SELECT email, name FROM users ORDER BY id') as $u) {
        fwrite(STDERR, "  {$u['email']}  ({$u['name']})\n");
    }
    exit(1);
}
$user = db_one('SELECT id, name FROM users WHERE email = ?', [$email]);
if (!$user) {
    fwrite(STDERR, "Aucun compte avec l'e-mail « $email ».\n");
    exit(1);
}

function ask(string $prompt): string
{
    fwrite(STDERR, $prompt);
    $tty = function_exists('posix_isatty') ? posix_isatty(STDIN) : stream_isatty(STDIN);
    if ($tty) {
        system('stty -echo');
    }
    $line = rtrim((string) fgets(STDIN), "\r\n");
    if ($tty) {
        system('stty echo');
        fwrite(STDERR, "\n");
    }
    return $line;
}

$password = ask('Nouveau mot de passe : ');
if ($err = valid_password($password)) {
    fwrite(STDERR, "$err\n");
    exit(1);
}
if ($password !== ask('Confirmez : ')) {
    fwrite(STDERR, "Les deux saisies ne correspondent pas.\n");
    exit(1);
}
db_run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
db_run('DELETE FROM login_attempts WHERE email = ?', [$email]);   // lève un éventuel blocage anti brute-force
echo "Mot de passe de {$user['name']} ($email) modifié.\n";
