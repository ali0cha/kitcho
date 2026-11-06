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
define('SKIP_SCHEMA_ENSURE', true);   // la base peut ne pas être prête : on attend ci-dessous puis on migre explicitement
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

$n = schema_migrate();
@file_put_contents(schema_marker_file(), md5((string) file_get_contents(dirname(__DIR__) . '/sql/schema.sql') . serialize(SCHEMA_COLUMNS)), LOCK_EX);
echo "Schéma à jour ($n instructions).\n";
