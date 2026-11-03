<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = config('db');
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $c['host'], $c['name'], $c['charset'] ?? 'utf8mb4');
        $pdo = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec("SET time_zone = '" . (new DateTime())->format('P') . "'");
    }
    return $pdo;
}

function db_run(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function db_all(string $sql, array $params = []): array
{
    return db_run($sql, $params)->fetchAll();
}

function db_one(string $sql, array $params = []): ?array
{
    $r = db_run($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function db_val(string $sql, array $params = []): mixed
{
    $r = db_run($sql, $params)->fetchColumn();
    return $r === false ? null : $r;
}

function db_insert(string $table, array $data): int
{
    $cols = implode(',', array_map(fn($c) => "`$c`", array_keys($data)));
    $ph = implode(',', array_fill(0, count($data), '?'));
    db_run("INSERT INTO `$table` ($cols) VALUES ($ph)", array_values($data));
    return (int) db()->lastInsertId();
}

/** Placeholders "?,?,?" pour un IN (...). */
function in_clause(array $values): string
{
    return implode(',', array_fill(0, count($values), '?'));
}
