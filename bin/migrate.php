<?php
declare(strict_types=1);

/**
 * Crée / met à jour les tables (sql/schema.sql, idempotent : CREATE TABLE IF NOT EXISTS).
 * Lancé automatiquement au démarrage du conteneur ; attend que la base soit joignable.
 *
 *   php bin/migrate.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/src/bootstrap.php';

$deadline = time() + 120;
while (true) {
    try {
        db();
        break;
    } catch (PDOException $e) {
        if (time() > $deadline) {
            fwrite(STDERR, "Base de données injoignable : " . $e->getMessage() . "\n");
            exit(1);
        }
        echo "En attente de la base de données…\n";
        sleep(2);
    }
}

$sql = preg_replace('/^\s*--.*$/m', '', (string) file_get_contents(dirname(__DIR__) . '/sql/schema.sql'));
$n = 0;
foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $statement) {
    db()->exec($statement);
    $n++;
}
echo "Schéma à jour ($n instructions).\n";
