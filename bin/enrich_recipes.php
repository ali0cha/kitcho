<?php
declare(strict_types=1);

/**
 * Complète en masse les recettes qui ont un lien : relit chaque page et ajoute ce qui manque
 * (étapes de préparation, ingrédients si la recette n'en a aucun, temps de préparation/cuisson, description).
 * Ne remplace jamais ce qui existe déjà.
 *
 *   php bin/enrich_recipes.php                      liste les foyers
 *   php bin/enrich_recipes.php <foyer> --dry-run    montre ce qui serait ajouté, sans rien écrire
 *   php bin/enrich_recipes.php <foyer>              complète les recettes sans étapes
 *   options : --limit=N (nombre max de recettes)   --delay=S (secondes entre deux pages, 2 par défaut)
 *
 * Dans Docker : docker compose exec app php bin/enrich_recipes.php 1 --dry-run
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/src/bootstrap.php';

$ids = [];
$opts = ['dry' => false, 'limit' => 0, 'delay' => 2];
foreach (array_slice($argv, 1) as $a) {
    if ($a === '--dry-run') {
        $opts['dry'] = true;
    } elseif (preg_match('/^--(limit|delay)=(\d+)$/', $a, $m)) {
        $opts[$m[1]] = (int) $m[2];
    } elseif (ctype_digit($a)) {
        $ids[] = (int) $a;
    }
}
if (!$ids) {
    fwrite(STDERR, "Usage : php bin/enrich_recipes.php <foyer> [--dry-run] [--limit=N] [--delay=S]\nFoyers :\n");
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

$todo = db_all(
    'SELECT r.* FROM recipes r
      WHERE r.household_id = ? AND r.url IS NOT NULL AND r.url <> ""
        AND NOT EXISTS (SELECT 1 FROM recipe_steps s WHERE s.recipe_id = r.id)
      ORDER BY r.name',
    [$hh['id']]
);
$todo = array_values(array_filter($todo, fn($r) => safe_url($r['url']) !== null));
if ($opts['limit'] > 0) {
    $todo = array_slice($todo, 0, $opts['limit']);
}
echo 'Foyer « ' . $hh['name'] . ' » : ' . count($todo) . ' recette(s) avec un lien et sans étapes' . ($opts['dry'] ? ' (simulation, rien ne sera écrit)' : '') . ".\n\n";

$stats = ['ok' => 0, 'steps' => 0, 'ingredients' => 0, 'rien' => 0, 'erreur' => 0];
foreach ($todo as $i => $r) {
    printf('[%d/%d] %s … ', $i + 1, count($todo), $r['name']);
    try {
        $fetched = import_fetch($r['url']);
        $f = import_to_form(import_parse($fetched['html']), $fetched['url'], $hh);
    } catch (ImportException $e) {
        echo '✗ ' . $e->getMessage() . "\n";
        $stats['erreur']++;
        sleep($opts['delay']);
        continue;
    }
    $steps = parse_steps($f['stepsText']);
    $nSteps = count(array_filter($steps, fn($s) => $s['kind'] === 'step'));
    $hasIngs = (int) db_val('SELECT COUNT(*) FROM recipe_ingredients WHERE recipe_id = ?', [$r['id']]) > 0;
    $newIngs = !$hasIngs && $f['ingText'] !== '' ? $f['counts']['ingredients'] : 0;
    if ($nSteps === 0 && $newIngs === 0) {
        echo "· rien d'exploitable sur cette page (" . $f['counts']['source'] . ")\n";
        $stats['rien']++;
        sleep($opts['delay']);
        continue;
    }
    echo "✓ $nSteps étape(s)" . ($newIngs ? ", $newIngs ingrédient(s)" : '') . ($f['recipe']['prep_minutes'] || $f['recipe']['cook_minutes'] ? ', temps' : '') . "\n";
    if (!$opts['dry']) {
        if ($nSteps) {
            save_recipe_steps((int) $r['id'], $steps);
        }
        if ($newIngs) {
            save_recipe_ingredients((int) $r['id'], $f['ingText']);
        }
        db_run(
            'UPDATE recipes SET prep_minutes = COALESCE(prep_minutes, ?), cook_minutes = COALESCE(cook_minutes, ?), total_minutes = COALESCE(total_minutes, ?),
                    notes = COALESCE(notes, ?) WHERE id = ?',
            [$f['recipe']['prep_minutes'], $f['recipe']['cook_minutes'], $f['recipe']['total_minutes'], $f['recipe']['notes'], $r['id']]
        );
    }
    $stats['ok']++;
    $stats['steps'] += $nSteps;
    $stats['ingredients'] += $newIngs;
    if ($i < count($todo) - 1) {
        sleep($opts['delay']);
    }
}
echo "\nTerminé : {$stats['ok']} recette(s) complétée(s) ({$stats['steps']} étapes, {$stats['ingredients']} ingrédients ajoutés), {$stats['rien']} sans données, {$stats['erreur']} en erreur.\n";
