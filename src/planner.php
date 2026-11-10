<?php
declare(strict_types=1);

/**
 * Nombre de jours entre la semaine cible et la dernière fois que chaque recette a été planifiée
 * (en dehors de cette semaine). Absent = jamais planifiée.
 *
 * @return array<int, int> recipe_id => jours d'écart
 */
function recipe_gaps(int $householdId, DateTimeImmutable $monday): array
{
    $sunday = $monday->modify('+6 days');
    $rows = db_all(
        'SELECT recipe_id,
                MIN(CASE WHEN meal_date < ? THEN DATEDIFF(?, meal_date) ELSE DATEDIFF(meal_date, ?) END) AS gap
           FROM meals
          WHERE household_id = ? AND recipe_id IS NOT NULL AND (meal_date < ? OR meal_date > ?)
          GROUP BY recipe_id',
        [ymd($monday), ymd($monday), ymd($sunday), $householdId, ymd($monday), ymd($sunday)]
    );
    return array_column($rows, 'gap', 'recipe_id');
}

/**
 * Choisit $count recettes en privilégiant d'abord la DIVERSITÉ (jamais optionnelle) :
 * noms qui ne se ressemblent pas, pas deux fois la même famille d'ingrédients (poulet, pâtes, riz…) ni les mêmes
 * ingrédients vedettes dans la semaine (voir src/diversity.php). Viennent ensuite, pour départager : on évite les recettes
 * cuisinées récemment, on favorise les favorites, la saison (si activée) et un peu de hasard. Une catégorie n'est pas
 * répétée à l'excès et un minimum de repas végétariens est garanti.
 *
 * @param int[]      $excludeIds recettes déjà prévues cette semaine (ou à écarter) : elles comptent aussi comme contexte de diversité
 * @param array|null $report     reçoit ['similar' => nombre de choix qui ressemblent à un autre faute de recettes assez variées]
 * @return array[] lignes de recipes, rangées de façon que deux jours consécutifs se ressemblent le moins possible
 */
function pick_recipes(int $householdId, DateTimeImmutable $monday, int $count, int $avoidWeeks = 3, int $minVeg = 0, array $excludeIds = [], ?bool $seasonal = null, ?array &$report = null): array
{
    $report = ['similar' => 0];
    if ($count <= 0) {
        return [];
    }
    $recipes = db_all('SELECT id, name, category, favorite, season_months FROM recipes WHERE household_id = ? AND auto_ok = 1', [$householdId]);
    $recipes = array_values(array_filter($recipes, fn($r) => !in_array((int) $r['id'], $excludeIds, true)));
    if (!$recipes) {
        return [];
    }

    // Saison : bonus pour les recettes de saison, malus pour celles dont des produits frais sont hors saison (mois du milieu de la semaine).
    $seasonal ??= (bool) db_val('SELECT seasonal_pref FROM households WHERE id = ?', [$householdId]);
    $season = $seasonal ? recipes_season_map($recipes, (int) $monday->modify('+3 days')->format('n')) : [];

    $gaps = recipe_gaps($householdId, $monday);
    foreach ($recipes as &$r) {
        $gap = $gaps[$r['id']] ?? 365;
        $r['gap'] = (int) $gap;
        $r['recent'] = $gap < $avoidWeeks * 7;
        // Plus c'est ancien, plus c'est haut ; un peu de hasard pour varier d'une génération à l'autre.
        $r['score'] = min($gap, 180) + ($r['favorite'] ? 40 : 0) + mt_rand(0, 60) - ($r['recent'] ? 1000 : 0) + ($season[(int) $r['id']]['points'] ?? 0);
    }
    unset($r);

    // Diversité : profil de chaque candidate + repas déjà prévus autour de la période.
    $profiles = recipe_profiles($recipes);
    $context = diversity_context($householdId, $monday, array_map('intval', $excludeIds));

    $maxPerCategory = max(2, (int) ceil($count / 2));
    $chosen = [];
    $perCat = [];
    $similar = 0;
    while (count($chosen) < $count && $recipes) {
        $needVeg = count(array_filter($chosen, fn($c) => $c['category'] === 'vegetarien')) < $minVeg;
        // Trois passes de plus en plus souples : (1) contraintes complètes, (2) sans plafond de catégorie, (3) sans exigence végétarienne.
        foreach ([[true, true], [false, true], [false, false]] as [$capCategory, $vegFirst]) {
            $best = null;
            $bestScore = -INF;
            $bestHard = false;
            foreach ($recipes as $i => $r) {
                if ($vegFirst && $needVeg && $r['category'] !== 'vegetarien') {
                    continue;
                }
                if ($capCategory && $r['category'] !== 'autre' && ($perCat[$r['category']] ?? 0) >= $maxPerCategory) {
                    continue;
                }
                $ctx = $context;
                foreach ($chosen as $c) {
                    $ctx[] = [$profiles[(int) $c['id']], 1.0];
                }
                $pen = diversity_penalty($profiles[(int) $r['id']], $ctx);
                $score = $r['score'] - $pen['points'];
                if ($score > $bestScore) {
                    $best = $i;
                    $bestScore = $score;
                    $bestHard = $pen['hard'];
                }
            }
            if ($best !== null) {
                break;
            }
        }
        if ($best === null) {
            break;
        }
        $r = $recipes[$best];
        $chosen[] = $r;
        $perCat[$r['category']] = ($perCat[$r['category']] ?? 0) + 1;
        $similar += $bestHard ? 1 : 0;
        array_splice($recipes, $best, 1);
    }
    $report = ['similar' => $similar];
    return order_for_variety($chosen, $profiles);
}

/**
 * Remplit la semaine : un repas par jour coché dans $dayIndexes (0 = lundi).
 * @return int nombre de repas créés
 */
function generate_week(int $householdId, int $userId, DateTimeImmutable $monday, array $dayIndexes, string $slot, int $avoidWeeks, int $minVeg, bool $replace, ?bool $seasonal = null, ?array &$report = null): int
{
    $days = week_days($monday);
    $targets = [];
    foreach (array_unique($dayIndexes) as $i) {
        if (!isset($days[$i])) {
            continue;
        }
        $date = ymd($days[$i]);
        $exists = (int) db_val('SELECT COUNT(*) FROM meals WHERE household_id = ? AND meal_date = ? AND slot = ?', [$householdId, $date, $slot]);
        if ($exists && !$replace) {
            continue;
        }
        $targets[] = $date;
    }
    sort($targets);
    if (!$targets) {
        return 0;
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($replace) {
            foreach ($targets as $date) {
                db_run('DELETE FROM meals WHERE household_id = ? AND meal_date = ? AND slot = ?', [$householdId, $date, $slot]);
            }
        }
        $already = array_map('intval', array_column(db_all(
            'SELECT recipe_id FROM meals WHERE household_id = ? AND recipe_id IS NOT NULL AND meal_date BETWEEN ? AND ?',
            [$householdId, ymd($monday), ymd($monday->modify('+6 days'))]
        ), 'recipe_id'));
        $picked = pick_recipes($householdId, $monday, count($targets), $avoidWeeks, $minVeg, $already, $seasonal, $report);
        foreach ($picked as $i => $r) {
            db_insert('meals', [
                'household_id' => $householdId, 'meal_date' => $targets[$i], 'slot' => $slot,
                'recipe_id' => $r['id'], 'created_by' => $userId,
            ]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return count($picked);
}
