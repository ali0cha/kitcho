<?php
declare(strict_types=1);

/**
 * Importe des recettes (sans ingrédients) dans un foyer existant.
 *
 *   php bin/import_recipes.php <household_id> [--file=seed/recipes.json] [--min=2]
 *
 * --min=N : ne garde que les plats cuisinés au moins N fois dans l'historique (les idées jamais testées sont toujours importées).
 * Les doublons (même nom, accents/casse ignorés) sont ignorés : la commande peut être relancée sans risque.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/src/bootstrap.php';

$args = array_slice($argv, 1);
$opts = ['file' => ROOT . '/seed/recipes.json', 'min' => 2];
$ids = [];
foreach ($args as $a) {
    if (preg_match('/^--(file|min)=(.+)$/', $a, $m)) {
        $opts[$m[1]] = $m[1] === 'min' ? (int) $m[2] : $m[2];
    } elseif (ctype_digit($a)) {
        $ids[] = (int) $a;
    }
}
if (!$ids) {
    fwrite(STDERR, "Usage : php bin/import_recipes.php <household_id> [--file=...] [--min=2]\nFoyers disponibles :\n");
    foreach (db_all('SELECT id, name FROM households ORDER BY id') as $h) {
        fwrite(STDERR, "  {$h['id']}  {$h['name']}\n");
    }
    exit(1);
}
$hh = db_one('SELECT * FROM households WHERE id = ?', [$ids[0]]);
if (!$hh) {
    fwrite(STDERR, "Foyer introuvable.\n");
    exit(1);
}
$data = json_decode((string) file_get_contents($opts['file']), true);
if (!is_array($data)) {
    fwrite(STDERR, "Fichier JSON illisible.\n");
    exit(1);
}
$existing = array_flip(array_map('norm_key', array_column(db_all('SELECT name FROM recipes WHERE household_id = ?', [$hh['id']]), 'name')));
$added = $skipped = 0;
foreach ($data as $r) {
    $times = (int) ($r['times'] ?? 0);
    if ($times > 0 && $times < $opts['min']) {
        continue;
    }
    $key = norm_key($r['name']);
    if (isset($existing[$key])) {
        $skipped++;
        continue;
    }
    $existing[$key] = true;
    db_insert('recipes', [
        'household_id' => $hh['id'],
        'name'         => mb_substr($r['name'], 0, 200),
        'category'     => isset(CATEGORIES[$r['category'] ?? '']) ? $r['category'] : guess_category($r['name']),
        'url'          => safe_url($r['url'] ?? null),
        'servings'     => $hh['default_servings'],
        'favorite'     => !empty($r['favorite']) ? 1 : 0,
    ]);
    $added++;
}
echo "Foyer « {$hh['name']} » : $added recettes ajoutées, $skipped déjà présentes.\n";
