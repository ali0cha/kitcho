<?php
declare(strict_types=1);

/**
 * Affiche la saison de chaque recette d'un foyer (ce que Kitcho en déduit), pour vérifier que le calcul est juste.
 *
 *   php bin/season_report.php                 liste les foyers
 *   php bin/season_report.php <foyer>         recettes du foyer pour le mois en cours
 *   options : --mois=1..12   --seulement=in|mixed|out|none
 *
 * Dans Docker : docker compose exec app php bin/season_report.php 1
 * Rien n'est modifié : lecture seule.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/src/bootstrap.php';

$id = null;
$month = (int) date('n');
$only = null;
foreach (array_slice($argv, 1) as $a) {
    if (ctype_digit($a)) {
        $id = (int) $a;
    } elseif (preg_match('/^--mois=(\d{1,2})$/', $a, $m) && (int) $m[1] >= 1 && (int) $m[1] <= 12) {
        $month = (int) $m[1];
    } elseif (preg_match('/^--seulement=(in|mixed|out|none)$/', $a, $m)) {
        $only = $m[1];
    }
}

if ($id === null) {
    echo "Foyers :\n";
    foreach (db_all('SELECT h.id, h.name, (SELECT COUNT(*) FROM recipes r WHERE r.household_id = h.id) AS n FROM households h ORDER BY h.id') as $h) {
        printf("  %d  %s (%d recettes)\n", $h['id'], $h['name'], $h['n']);
    }
    echo "\nUsage : php bin/season_report.php <numéro du foyer> [--mois=1..12] [--seulement=in|mixed|out|none]\n";
    exit(0);
}

$recipes = db_all('SELECT id, name, category, season_months FROM recipes WHERE household_id = ? ORDER BY name', [$id]);
if (!$recipes) {
    fwrite(STDERR, "Aucune recette dans ce foyer.\n");
    exit(1);
}
$ids = array_column($recipes, 'id');
$byRecipe = [];
foreach (db_all('SELECT recipe_id, name, aisle FROM recipe_ingredients WHERE recipe_id IN (' . implode(',', array_map('intval', $ids)) . ')') as $i) {
    $byRecipe[(int) $i['recipe_id']][] = $i;
}

$labels = ['in' => 'DE SAISON', 'mixed' => 'EN PARTIE', 'out' => 'HORS SAISON', 'none' => '—'];
$counts = ['in' => 0, 'mixed' => 0, 'out' => 0, 'none' => 0];
$monthName = FR_MONTHS[$month - 1];
echo "Saison en $monthName — " . count($recipes) . " recettes\n\n";
foreach ($recipes as $r) {
    $override = $r['season_months'] !== null ? (int) $r['season_months'] : null;
    $classified = season_classify($byRecipe[(int) $r['id']] ?? []);
    $s = season_status($classified, $month, $override, (string) $r['category']);
    $counts[$s['status']]++;
    if ($only !== null && $s['status'] !== $only) {
        continue;
    }
    $year = implode('', array_map(fn($st) => ['in' => '█', 'mixed' => '▒', 'out' => '·', 'none' => '-'][$st], season_year($classified, $override, (string) $r['category'])));
    $why = match ($s['source']) {
        'manual'   => 'mois choisis à la main',
        'category' => 'plat de saison ' . ($r['category'] === 'soupe' ? 'froide' : 'chaude'),
        default    => implode(' ; ', array_filter([
            $s['in'] ? 'saison : ' . implode(', ', array_unique(array_column($s['in'], 'name'))) : '',
            $s['out'] ? 'hors saison : ' . implode(', ', array_unique(array_column($s['out'], 'name'))) : '',
        ])),
    };
    printf("%-12s %s  %-40s %s\n", $labels[$s['status']], $year, mb_strimwidth($r['name'], 0, 40, '…'), $why);
}
echo "\nRésumé : {$counts['in']} de saison, {$counts['mixed']} en partie, {$counts['out']} hors saison, {$counts['none']} sans signal.\n";
echo "Barre de l'année : janvier → décembre ; █ de saison, ▒ en partie, · hors saison, - sans signal.\n";
