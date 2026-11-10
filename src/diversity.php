<?php
declare(strict_types=1);

/**
 * Diversité des menus : première priorité de la génération, jamais optionnelle.
 *
 * Chaque recette reçoit un « profil » (mots de son nom, ingrédient principal de son nom, ingrédients caractéristiques et
 * familles : volaille, pâtes, riz, pommes de terre, poisson…). Pour une recette candidate, on mesure ce qu'elle répète par rapport
 * aux repas déjà choisis ou déjà prévus autour d'elle :
 *   - un nom qui se ressemble (« Poulet curry » / « Poulet rôti », « Gratin de courgettes » / « Gratin dauphinois ») ;
 *   - une même famille d'ingrédients au-delà de la limite (pas trois fois le poulet ou les pâtes dans la semaine) ;
 *   - des ingrédients caractéristiques en commun (même légume vedette, même fromage…).
 * Ces répétitions coûtent plus de points que tout le reste (saison, favoris, ancienneté, hasard) : la diversité décide d'abord,
 * les autres critères départagent ensuite. Faute de recettes assez variées, on prend la moins répétitive plutôt que de ne rien proposer.
 */

/** Points retirés pour une répétition « dure » (plus que l'écart possible de tous les autres critères réunis). */
const DIVERSITY_HARD = 400;
/** Ingrédient caractéristique déjà présent dans un autre repas : points par occurrence, plafonnés. */
const DIVERSITY_KEY_POINTS = 30;
const DIVERSITY_KEY_CAP = 180;
/** Similitude de nom à partir de laquelle deux recettes sont considérées comme « la même idée ». */
const DIVERSITY_NAME_LIMIT = 0.5;

/** Mots du nom qui ne distinguent pas une recette d'une autre. */
const DIVERSITY_NAME_STOPWORDS = [
    'de', 'du', 'des', 'd', 'la', 'le', 'les', 'l', 'au', 'aux', 'a', 'et', 'en', 'avec', 'sur', 'sans', 'un', 'une', 'ou', 'pour',
    'maison', 'facon', 'express', 'rapide', 'facile', 'simple', 'classique', 'traditionnel', 'traditionnelle', 'meilleur', 'ma', 'mon', 'notre',
    'petit', 'petite', 'bon', 'bonne', 'recette', 'style',
];

/** Ingrédients de base présents partout : ils ne caractérisent pas une recette. */
const DIVERSITY_BASICS = [
    'sel', 'poivre', 'huile', 'beurre', 'farine', 'sucre', 'eau', 'oignon', 'ail', 'echalote', 'bouillon', 'cube', 'epice', 'herbe',
    'persil', 'thym', 'laurier', 'romarin', 'vinaigre', 'moutarde', 'lait', 'creme', 'oeuf', 'fromage', 'parmesan', 'gruyere', 'emmental',
    'citron', 'jus', 'zeste', 'levure', 'chapelure', 'maizena', 'vin', 'sauce', 'soja', 'paprika', 'cumin', 'curry', 'cannelle', 'muscade',
    'piment', 'ciboulette', 'basilic', 'coriandre', 'menthe', 'aneth', 'estragon', 'cerfeuil', 'olive', 'capre', 'cornichon', 'gingembre',
    'miel', 'chocolat', 'pate', 'concentre', 'coulis', 'passata',
];

/**
 * Familles d'ingrédients : chaque famille liste des suites de mots (au singulier, sans accents, sans « de/du/la »).
 * Une famille ne doit pas revenir plus de FAMILY_LIMITS fois (2 par défaut) sur la période considérée.
 */
const DIVERSITY_FAMILIES = [
    'volaille'     => [['poulet'], ['dinde'], ['volaille'], ['pintade'], ['canard']],
    'boeuf'        => [['boeuf'], ['steak'], ['hache'], ['hachee'], ['bolognaise']],
    'porc'         => [['porc'], ['lardon'], ['jambon'], ['saucisse'], ['bacon'], ['chipolata'], ['lard'], ['saucisson'], ['andouille']],
    'agneau'       => [['agneau'], ['merguez'], ['gigot']],
    'poisson'      => [['saumon'], ['cabillaud'], ['poisson'], ['thon'], ['colin'], ['truite'], ['merlu'], ['morue'], ['sole'], ['lieu'], ['haddock'], ['maquereau'], ['sardine'], ['crevette'], ['scampi'], ['moule']],
    'pates'        => [['spaghetti'], ['tagliatelle'], ['penne'], ['macaroni'], ['lasagne'], ['fusilli'], ['nouille'], ['orzo'], ['gnocchi'], ['rigatoni'], ['farfalle'], ['tortellini'], ['ravioli'], ['pate']],
    'riz'          => [['riz']],
    'cereale'      => [['semoule'], ['couscous'], ['quinoa'], ['boulgour'], ['polenta'], ['millet'], ['epeautre'], ['orge']],
    'pomme_de_terre' => [['pomme', 'terre'], ['patate'], ['frite'], ['gratin', 'dauphinois']],
    'legumineuse'  => [['lentille'], ['chiche'], ['haricot', 'blanc'], ['haricot', 'rouge'], ['haricot', 'noir'], ['pois', 'casse']],
    'tofu'         => [['tofu'], ['seitan'], ['tempeh']],
];
const DIVERSITY_FAMILY_LIMITS = ['pomme_de_terre' => 3];
const DIVERSITY_FAMILY_LIMIT_DEFAULT = 2;

/** Premiers mots d'un ingrédient qui ne le caractérisent pas (« filet de saumon » → saumon). */
const DIVERSITY_GENERIC_FIRST = ['filet', 'blanc', 'cuisse', 'escalope', 'morceau', 'tranche', 'pave', 'dos', 'gros', 'grand', 'frais', 'fraiche', 'bio', 'mixte', 'melange', 'rape', 'rapee', 'hache', 'hachee', 'cuit', 'cuite'];

/** Mots qui font d'un « pâte » autre chose que des pâtes (pâte brisée, à tartiner…). */
const DIVERSITY_NOT_PASTA = ['brise', 'brisee', 'feuillete', 'feuilletee', 'sable', 'sablee', 'tartiner', 'pizza', 'amande', 'curry', 'piment', 'tomate', 'fruit'];

/** Mots significatifs d'un nom (au singulier, sans accents, sans mots vides). */
function diversity_words(string $name): array
{
    $stop = array_flip(DIVERSITY_NAME_STOPWORDS);
    return array_values(array_filter(season_tokens($name), fn($w) => strlen($w) > 1 && !isset($stop[$w])));
}

/**
 * Familles d'ingrédients présentes dans une liste de mots.
 * @param array|null $matched reçoit les mots qui ont déclenché une famille (ils caractérisent la recette)
 */
function diversity_families_in(array $words, ?array &$matched = null): array
{
    $found = [];
    foreach (DIVERSITY_FAMILIES as $family => $sequences) {
        foreach ($sequences as $seq) {
            if (!season_contains($words, $seq)) {
                continue;
            }
            if ($seq === ['pate'] && array_intersect($words, DIVERSITY_NOT_PASTA)) {
                continue;
            }
            $found[$family] = true;
            $matched[] = implode('_', $seq);
            break;
        }
    }
    return array_keys($found);
}

/**
 * Profil d'une recette.
 * @param array $ingredients lignes avec 'name'
 * @return array{words:array, head:string, keys:array<string,true>, families:array<string,true>, category:string}
 */
function recipe_profile(string $name, string $category, array $ingredients): array
{
    $words = diversity_words($name);
    $basics = array_flip(DIVERSITY_BASICS);
    $generic = array_flip(DIVERSITY_GENERIC_FIRST);
    $keys = [];
    $families = array_flip(diversity_families_in($words));
    foreach ($ingredients as $ing) {
        $w = diversity_words((string) ($ing['name'] ?? ''));
        if (!$w) {
            continue;
        }
        $matched = [];
        foreach (diversity_families_in($w, $matched) as $f) {
            $families[$f] = true;
        }
        foreach ($matched as $m) {                      // « filet de poulet » → poulet ; « pommes de terre » → pomme_terre
            $keys[$m] = true;
        }
        $head = null;
        foreach ($w as $word) {                         // premier mot qui ne soit pas « filet », « blanc »…
            if (!isset($generic[$word])) {
                $head = $word;
                break;
            }
        }
        if (!$matched && $head !== null && !isset($basics[$head])) {
            $keys[$head] = true;                        // « tomates cerises » → tomate (sauf si une famille l'a déjà reconnu : « pommes de terre » ≠ « pommes »)
        }
    }
    return ['words' => $words, 'head' => $words[0] ?? '', 'keys' => $keys, 'families' => $families, 'category' => $category];
}

/**
 * Profils d'une liste de recettes (lignes avec id, name, category), avec une seule requête pour les ingrédients.
 * @return array<int, array> id → profil
 */
function recipe_profiles(array $recipes): array
{
    $ids = array_map(fn($r) => (int) $r['id'], $recipes);
    $byRecipe = [];
    if ($ids) {
        $marks = implode(',', array_fill(0, count($ids), '?'));
        foreach (db_all("SELECT recipe_id, name FROM recipe_ingredients WHERE recipe_id IN ($marks)", $ids) as $i) {
            $byRecipe[(int) $i['recipe_id']][] = $i;
        }
    }
    $out = [];
    foreach ($recipes as $r) {
        $out[(int) $r['id']] = recipe_profile((string) $r['name'], (string) ($r['category'] ?? ''), $byRecipe[(int) $r['id']] ?? []);
    }
    return $out;
}

/** Ressemblance des noms, de 0 à 1 : mots en commun, ou même ingrédient/plat en tête (« Poulet curry », « Poulet rôti »). */
function diversity_name_similarity(array $a, array $b): float
{
    if (!$a['words'] || !$b['words']) {
        return 0.0;
    }
    $common = count(array_intersect($a['words'], $b['words']));
    $jaccard = $common / count(array_unique(array_merge($a['words'], $b['words'])));
    $sameHead = $a['head'] !== '' && $a['head'] === $b['head'];
    return max($jaccard, $sameHead ? 0.8 : 0.0);
}

/**
 * Ce que la recette $cand répète par rapport au contexte (repas choisis ou déjà prévus).
 *
 * @param array $context liste de [profil, poids] : poids 1 pour la semaine, 0,5 pour les jours voisins
 * @return array{points: float, hard: bool} points à retirer à la recette ; hard = répétition nette avec la semaine elle-même
 */
function diversity_penalty(array $cand, array $context): array
{
    $points = 0.0;
    $hard = false;
    $familyCount = [];
    $keyCount = 0.0;
    foreach ($context as [$p, $w]) {
        $sim = diversity_name_similarity($cand, $p);
        if ($sim >= DIVERSITY_NAME_LIMIT) {
            $points += DIVERSITY_HARD * $w;
            $hard = $hard || $w >= 1.0;
        } else {
            $points += 100 * $sim * $w;
        }
        foreach ($p['families'] as $f => $_) {
            $familyCount[$f] = ($familyCount[$f] ?? 0.0) + $w;
        }
        $keyCount += $w * count(array_intersect_key($cand['keys'], $p['keys']));
    }
    foreach ($cand['families'] as $f => $_) {
        $n = $familyCount[$f] ?? 0.0;
        if ($n <= 0.0) {
            continue;
        }
        $limit = DIVERSITY_FAMILY_LIMITS[$f] ?? DIVERSITY_FAMILY_LIMIT_DEFAULT;
        if ($n + 1 > $limit) {
            $points += DIVERSITY_HARD;
            $hard = $hard || $n >= $limit;
        } else {
            $points += 35 * $n;                         // sous la limite : simple coup de pouce pour espacer
        }
    }
    $points += min(DIVERSITY_KEY_CAP, DIVERSITY_KEY_POINTS * $keyCount);
    return ['points' => $points, 'hard' => $hard];
}

/** Proximité de deux recettes pour les ranger (plus c'est haut, moins elles doivent se suivre). */
function diversity_closeness(array $a, array $b): float
{
    return 2 * diversity_name_similarity($a, $b)
        + 0.6 * count(array_intersect_key($a['families'], $b['families']))
        + 0.25 * count(array_intersect_key($a['keys'], $b['keys']))
        + ($a['category'] !== '' && $a['category'] === $b['category'] ? 0.3 : 0.0);
}

/**
 * Ordonne les recettes choisies pour que deux jours consécutifs se ressemblent le moins possible.
 * @param array[] $picks lignes de recettes (avec id)
 * @param array   $profiles id → profil
 */
function order_for_variety(array $picks, array $profiles): array
{
    if (count($picks) < 3) {
        shuffle($picks);
        return $picks;
    }
    shuffle($picks);                                    // point de départ et départage au hasard
    $ordered = [array_shift($picks)];
    while ($picks) {
        $prev = $profiles[(int) end($ordered)['id']];
        $best = 0;
        $bestScore = INF;
        foreach ($picks as $i => $r) {
            $score = diversity_closeness($prev, $profiles[(int) $r['id']]);
            if ($score < $bestScore - 1e-9) {
                $best = $i;
                $bestScore = $score;
            }
        }
        $ordered[] = $picks[$best];
        array_splice($picks, $best, 1);
    }
    return $ordered;
}

/**
 * Repas déjà présents autour de la période à remplir, qui comptent comme contexte :
 * ceux de la semaine (poids 1) et les 3 jours avant / après (poids 0,5).
 *
 * @param int[] $weekRecipeIds recettes déjà prévues dans la semaine
 * @return array liste de [profil, poids]
 */
function diversity_context(int $householdId, DateTimeImmutable $monday, array $weekRecipeIds): array
{
    $rows = [];
    if ($weekRecipeIds) {
        $marks = implode(',', array_fill(0, count($weekRecipeIds), '?'));
        $rows = array_map(fn($r) => $r + ['w' => 1.0], db_all("SELECT id, name, category FROM recipes WHERE id IN ($marks) AND household_id = ?", [...$weekRecipeIds, $householdId]));
    }
    $seen = array_flip(array_map('intval', $weekRecipeIds));
    $near = db_all(
        'SELECT DISTINCT r.id, r.name, r.category FROM meals m JOIN recipes r ON r.id = m.recipe_id
          WHERE m.household_id = ? AND (m.meal_date BETWEEN ? AND ? OR m.meal_date BETWEEN ? AND ?)',
        [$householdId, ymd($monday->modify('-3 days')), ymd($monday->modify('-1 day')), ymd($monday->modify('+7 days')), ymd($monday->modify('+9 days'))]
    );
    foreach ($near as $r) {
        if (!isset($seen[(int) $r['id']])) {
            $rows[] = $r + ['w' => 0.5];
        }
    }
    $profiles = recipe_profiles($rows);
    return array_map(fn($r) => [$profiles[(int) $r['id']], $r['w']], $rows);
}
