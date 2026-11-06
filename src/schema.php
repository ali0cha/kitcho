<?php
declare(strict_types=1);

/**
 * Colonnes ajoutées après la première version du schéma : CREATE TABLE IF NOT EXISTS ne les créerait pas
 * sur une base existante. Chaque entrée : [table, colonne, définition].
 */
const SCHEMA_COLUMNS = [
    ['recipes', 'prep_minutes', 'SMALLINT UNSIGNED NULL'],
    ['recipes', 'cook_minutes', 'SMALLINT UNSIGNED NULL'],
    ['recipes', 'total_minutes', 'SMALLINT UNSIGNED NULL'],
];

/** Applique sql/schema.sql (idempotent) puis ajoute les colonnes manquantes. Retourne le nombre d'instructions exécutées. */
function schema_migrate(): int
{
    $sql = preg_replace('/^\s*--.*$/m', '', (string) file_get_contents(ROOT . '/sql/schema.sql'));
    $n = 0;
    foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $statement) {
        db()->exec($statement);
        $n++;
    }
    foreach (SCHEMA_COLUMNS as [$table, $column, $definition]) {
        $exists = (int) db_val(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );
        if (!$exists) {
            db()->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
            $n++;
        }
    }
    return $n;
}

function schema_marker_file(): string
{
    $f = ROOT . '/.schema_ok';
    return (is_file($f) ? is_writable($f) : is_writable(ROOT)) ? $f : sys_get_temp_dir() . '/kitcho_schema_' . md5(ROOT);
}

/**
 * Met la base à jour une seule fois par version du schéma (empreinte mémorisée dans un fichier) :
 * une mise à jour du code n'exige alors aucune commande, même sur un hébergement sans accès SSH.
 */
function schema_ensure(): void
{
    $hash = md5((string) file_get_contents(ROOT . '/sql/schema.sql') . serialize(SCHEMA_COLUMNS));
    $marker = schema_marker_file();
    if (is_file($marker) && trim((string) @file_get_contents($marker)) === $hash) {
        return;
    }
    try {
        schema_migrate();
        @file_put_contents($marker, $hash, LOCK_EX);
    } catch (Throwable $e) {
        error_log('Kitcho : migration du schéma impossible : ' . $e->getMessage());
    }
}
