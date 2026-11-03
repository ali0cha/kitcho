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
 * Choisit $count recettes variées : on évite celles cuisinées récemment, on favorise les favorites,
 * on limite la répétition d'une même catégorie et on garantit un minimum de repas végétariens.
 *
 * @param int[] $excludeIds recettes déjà prévues cette semaine (ou à écarter)
 * @return array[] lignes de recipes
 */
function pick_recipes(int $householdId, DateTimeImmutable $monday, int $count, int $avoidWeeks = 3, int $minVeg = 0, array $excludeIds = []): array
{
    if ($count <= 0) {
        return [];
    }
    $recipes = db_all('SELECT id, name, category, favorite FROM recipes WHERE household_id = ? AND auto_ok = 1', [$householdId]);
    $recipes = array_values(array_filter($recipes, fn($r) => !in_array((int) $r['id'], $excludeIds, true)));
    if (!$recipes) {
        return [];
    }

    $gaps = recipe_gaps($householdId, $monday);
    foreach ($recipes as &$r) {
        $gap = $gaps[$r['id']] ?? 365;
        $r['gap'] = (int) $gap;
        $r['recent'] = $gap < $avoidWeeks * 7;
        // Plus c'est ancien, plus c'est haut ; un peu de hasard pour varier d'une génération à l'autre.
        $r['score'] = min($gap, 180) + ($r['favorite'] ? 40 : 0) + mt_rand(0, 60) - ($r['recent'] ? 1000 : 0);
    }
    unset($r);
    usort($recipes, fn($a, $b) => $b['score'] <=> $a['score']);

    $maxPerCategory = max(2, (int) ceil($count / 2));
    $chosen = [];
    $perCat = [];
    $take = function (int $i) use (&$recipes, &$chosen, &$perCat): void {
        $r = $recipes[$i];
        $chosen[] = $r;
        $perCat[$r['category']] = ($perCat[$r['category']] ?? 0) + 1;
        array_splice($recipes, $i, 1);
    };

    for ($v = 0; $v < $minVeg && count($chosen) < $count; $v++) {
        foreach ($recipes as $i => $r) {
            if ($r['category'] === 'vegetarien') {
                $take($i);
                break;
            }
        }
    }
    while (count($chosen) < $count && $recipes) {
        $idx = null;
        foreach ($recipes as $i => $r) {
            if ($r['category'] === 'autre' || ($perCat[$r['category']] ?? 0) < $maxPerCategory) {
                $idx = $i;
                break;
            }
        }
        $take($idx ?? 0);
    }
    shuffle($chosen);
    return $chosen;
}

/**
 * Remplit la semaine : un repas par jour coché dans $dayIndexes (0 = lundi).
 * @return int nombre de repas créés
 */
function generate_week(int $householdId, int $userId, DateTimeImmutable $monday, array $dayIndexes, string $slot, int $avoidWeeks, int $minVeg, bool $replace): int
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
        $picked = pick_recipes($householdId, $monday, count($targets), $avoidWeeks, $minVeg, $already);
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
