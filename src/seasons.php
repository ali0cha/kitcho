<?php
declare(strict_types=1);

/**
 * Saisonnalité des recettes (Belgique / Europe du Nord-Ouest).
 *
 * Principe : les ingrédients frais de la recette sont comparés à un calendrier des fruits et légumes cultivés localement
 * (pleine saison + conservation naturelle : pommes, courges, racines…). Les produits importés toute l'année (banane,
 * avocat, citron…), les conserves, surgelés et produits secs ne comptent pas : ils restent neutres.
 *
 * Résultat pour un mois donné : 'in' (de saison), 'mixed' (en partie), 'out' (hors saison) ou 'none' (aucun signal).
 * Un réglage manuel de la recette (mois cochés) l'emporte sur le calcul.
 */

/**
 * Mois de disponibilité locale : « 4-6 » = avril à juin, « 9-3 » = septembre à mars, « 4-6,9-11 » = deux périodes.
 * '*' = disponible toute l'année (neutre) : sert surtout à empêcher qu'un mot plus court prenne le dessus
 * (« pomme de terre » ne doit pas être traitée comme « pomme »).
 */
const SEASON_PRODUCE = [
    // légumes
    'asperge' => '4-6', 'aubergine' => '7-9', 'betterave' => '6-3', 'blette' => '6-10', 'brocoli' => '6-11',
    'celeri rave' => '9-3', 'celeri branche' => '7-10', 'chicon' => '9-5', 'endive' => '9-5',
    'chou fleur' => '6-11', 'chou de bruxelles' => '10-2', 'chou rouge' => '9-4', 'chou blanc' => '9-4',
    'chou vert' => '10-3', 'chou frise' => '10-3', 'chou kale' => '10-3', 'kale' => '10-3', 'chou pointu' => '6-11',
    'chou rave' => '5-10', 'chou' => '9-4',
    'concombre' => '5-9', 'courge' => '9-2', 'potiron' => '9-2', 'butternut' => '9-2', 'potimarron' => '9-2', 'citrouille' => '9-2',
    'courgette' => '6-9', 'epinard' => '4-6,9-11', 'fenouil' => '6-10', 'artichaut' => '6-10',
    'laitue' => '4-10', 'batavia' => '4-10', 'romaine' => '4-10', 'salade verte' => '4-10', 'salade' => '4-10',
    'roquette' => '4-10', 'cresson' => '4-10', 'scarole' => '9-12', 'frisee' => '9-12', 'mache' => '10-3',
    'navet' => '10-4', 'panais' => '10-3', 'poireau' => '9-4', 'poivron' => '6-9', 'petit pois' => '5-7', 'feve' => '5-7',
    'haricot vert' => '6-10', 'radis' => '4-10', 'rutabaga' => '10-3', 'salsifis' => '10-3', 'topinambour' => '10-3',
    'tomate' => '5-10', 'rhubarbe' => '4-6',
    // toute l'année (explicitement neutres)
    'pomme de terre' => '*', 'patate douce' => '*', 'patate' => '*', 'carotte' => '*', 'oignon' => '*', 'echalote' => '*',
    'ail' => '*', 'champignon' => '*',
    // fruits
    'pomme' => '8-4', 'poire' => '8-3', 'fraise' => '5-8', 'framboise' => '6-9', 'cerise' => '6-8', 'abricot' => '7-8',
    'peche' => '7-9', 'nectarine' => '7-9', 'prune' => '8-10', 'mirabelle' => '8-9', 'quetsche' => '8-10', 'raisin' => '8-10',
    'melon' => '6-9', 'pasteque' => '6-9', 'mure' => '7-9', 'myrtille' => '6-9', 'groseille' => '6-8', 'cassis' => '6-8',
    'figue' => '8-10', 'coing' => '10-12', 'chataigne' => '10-12',
    'clementine' => '11-3', 'mandarine' => '11-3', 'orange' => '11-3', 'pamplemousse' => '11-3',
    // herbes fraîches (poids réduit, voir SEASON_LIGHT)
    'basilic' => '6-9', 'menthe' => '5-9', 'aneth' => '5-9', 'estragon' => '5-9', 'ciboulette' => '4-10', 'cerfeuil' => '4-10',
];

/** Ingrédients qui pèsent peu dans le calcul (une pincée d'herbes ne fait pas une recette d'été). */
const SEASON_LIGHT = ['basilic' => 0.3, 'menthe' => 0.3, 'aneth' => 0.3, 'estragon' => 0.3, 'ciboulette' => 0.3, 'cerfeuil' => 0.3];

/** Mots qui indiquent un produit transformé (conserve, séché, jus…) : l'ingrédient n'est pas jugé. */
const SEASON_PROCESSED = [
    'pele', 'pelee', 'conserve', 'boite', 'bocal', 'surgele', 'surgelee', 'sec', 'seche', 'sechee', 'concentre', 'concentree',
    'coulis', 'passata', 'puree', 'jus', 'confit', 'confite', 'poudre', 'sauce', 'sirop', 'confiture', 'compote', 'bouillon',
    'cube', 'lyophilise', 'lyophilisee', 'zeste', 'extrait', 'arome', 'vinaigre', 'huile',
];

/** Plats typés « saison froide » ou « saison chaude » quand aucun produit frais ne permet de juger. */
const SEASON_CATEGORY_MONTHS = ['soupe' => '10-3', 'salade' => '5-9'];

/** Nom en liste de mots au singulier, sans accents : « Tomates cerises » → ['tomate', 'cerise']. */
function season_tokens(string $s): array
{
    $words = preg_split('/[^a-z0-9]+/', norm_key($s), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    return array_map(fn(string $w) => strlen($w) > 3 && ($w[-1] === 's' || $w[-1] === 'x') ? substr($w, 0, -1) : $w, $words);
}

/** « 9-3 » ou « 4-6,9-11 » → masque de mois (bit 0 = janvier). '*' → null (toute l'année). */
function season_mask(string $spec): ?int
{
    if ($spec === '*') {
        return null;
    }
    $mask = 0;
    foreach (explode(',', $spec) as $range) {
        [$a, $b] = array_map('intval', explode('-', $range) + [1 => 0]);
        $b = $b ?: $a;
        for ($m = $a; ; $m = $m % 12 + 1) {
            $mask |= 1 << ($m - 1);
            if ($m === $b) {
                break;
            }
        }
    }
    return $mask;
}

/** Table prête à l'emploi : [ [tokens, masque|null, poids, clé], … ], la clé la plus précise d'abord. */
function season_table(): array
{
    static $table = null;
    if ($table === null) {
        $table = [];
        foreach (SEASON_PRODUCE as $key => $spec) {
            $table[] = [season_tokens($key), season_mask($spec), SEASON_LIGHT[$key] ?? 1.0, $key];
        }
        usort($table, fn($a, $b) => [count($b[0]), strlen($b[3])] <=> [count($a[0]), strlen($a[3])]);
    }
    return $table;
}

function season_contains(array $haystack, array $needle): bool
{
    $n = count($needle);
    for ($i = 0, $max = count($haystack) - $n; $i <= $max; $i++) {
        if (array_slice($haystack, $i, $n) === $needle) {
            return true;
        }
    }
    return false;
}

/**
 * Ne retient que les produits frais jugeables d'une liste d'ingrédients (lignes avec 'name' et 'aisle').
 * @return array[] chaque entrée : ['name' => libellé affiché, 'mask' => mois de disponibilité, 'weight' => poids]
 */
function season_classify(array $ingredients): array
{
    static $processed = null;
    $processed ??= array_flip(array_map(fn($w) => season_tokens($w)[0] ?? $w, SEASON_PROCESSED));
    $out = [];
    foreach ($ingredients as $ing) {
        $aisle = (string) ($ing['aisle'] ?? '');
        if (!in_array($aisle, ['', 'Fruits & légumes', 'Autre'], true)) {
            continue;                                   // épicerie, surgelés, crèmerie… : pas un produit frais de saison
        }
        $tokens = season_tokens((string) $ing['name']);
        if (!$tokens || array_intersect_key(array_flip($tokens), $processed)) {
            continue;
        }
        foreach (season_table() as [$kt, $mask, $weight, $key]) {
            if (season_contains($tokens, $kt)) {
                if ($mask !== null) {
                    $out[] = ['name' => mb_strtolower(trim((string) $ing['name'])), 'key' => $key, 'mask' => $mask, 'weight' => $weight];
                }
                break;                                  // la clé la plus précise décide (« pomme de terre » avant « pomme »)
            }
        }
    }
    return $out;
}

/**
 * Situation d'une recette pour un mois (1-12).
 *
 * @param array    $classified  résultat de season_classify()
 * @param int|null $override    mois cochés à la main (masque), ou null pour le calcul automatique
 * @return array{status:string, in:array, out:array, points:int, source:string}
 */
function season_status(array $classified, int $month, ?int $override = null, string $category = ''): array
{
    $bit = 1 << ($month - 1);
    if ($override !== null) {
        if ($override === 0 || $override === 4095) {
            return ['status' => 'none', 'in' => [], 'out' => [], 'points' => 0, 'source' => 'manual'];
        }
        $in = ($override & $bit) !== 0;
        return ['status' => $in ? 'in' : 'out', 'in' => [], 'out' => [], 'points' => $in ? 50 : -75, 'source' => 'manual'];
    }
    $inW = $outW = 0.0;
    $in = $out = [];
    foreach ($classified as $c) {
        if ($c['mask'] & $bit) {
            $inW += $c['weight'];
            $in[] = $c;
        } else {
            $outW += $c['weight'];
            $out[] = $c;
        }
    }
    $total = $inW + $outW;
    if ($total < 0.5) {                                 // rien de significatif : on s'appuie sur le type de plat
        if (isset(SEASON_CATEGORY_MONTHS[$category]) && (season_mask(SEASON_CATEGORY_MONTHS[$category]) & $bit)) {
            return ['status' => 'in', 'in' => [], 'out' => [], 'points' => 30, 'source' => 'category'];
        }
        return ['status' => 'none', 'in' => $in, 'out' => $out, 'points' => 0, 'source' => 'auto'];
    }
    $status = $outW === 0.0 ? 'in' : ($inW === 0.0 || $outW / $total > 0.5 ? 'out' : 'mixed');
    $points = (int) round(50 * (($inW - 1.5 * $outW) / $total));
    return ['status' => $status, 'in' => $in, 'out' => $out, 'points' => $points, 'source' => 'auto'];
}

/** Statut de chacun des 12 mois (index 1 à 12). */
function season_year(array $classified, ?int $override = null, string $category = ''): array
{
    $year = [];
    for ($m = 1; $m <= 12; $m++) {
        $year[$m] = season_status($classified, $m, $override, $category)['status'];
    }
    return $year;
}

/**
 * Calcule la saison d'une liste de recettes (lignes avec id, category, season_months) pour un mois, en une seule requête.
 * @return array<int, array> id de recette → résultat de season_status()
 */
function recipes_season_map(array $recipes, int $month): array
{
    $ids = array_map(fn($r) => (int) $r['id'], $recipes);
    $byRecipe = [];
    if ($ids) {
        $marks = implode(',', array_fill(0, count($ids), '?'));
        foreach (db_all("SELECT recipe_id, name, aisle FROM recipe_ingredients WHERE recipe_id IN ($marks)", $ids) as $i) {
            $byRecipe[(int) $i['recipe_id']][] = $i;
        }
    }
    $map = [];
    foreach ($recipes as $r) {
        $override = isset($r['season_months']) && $r['season_months'] !== null ? (int) $r['season_months'] : null;
        $map[(int) $r['id']] = season_status(season_classify($byRecipe[(int) $r['id']] ?? []), $month, $override, (string) ($r['category'] ?? ''));
    }
    return $map;
}

/** « sept. à avr. » pour un masque de mois. */
function season_range_label(int $mask): string
{
    static $abbr = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
    $on = [];
    for ($m = 0; $m < 12; $m++) {
        $on[$m] = ($mask >> $m & 1) === 1;
    }
    if ($mask === 4095) {
        return 'toute l\'année';
    }
    $parts = [];
    for ($m = 0; $m < 12; $m++) {                       // débuts de période : mois allumé dont le précédent est éteint
        if ($on[$m] && !$on[($m + 11) % 12]) {
            $e = $m;
            while ($on[($e + 1) % 12] && ($e + 1) % 12 !== $m) {
                $e = ($e + 1) % 12;
            }
            $parts[] = $e === $m ? $abbr[$m] : $abbr[$m] . ' à ' . $abbr[$e];
        }
    }
    return $parts ? implode(' et ', $parts) : '';
}

/** Masque de mois à partir des cases cochées (['1', '5', …]) ; null si rien n'est coché. */
function season_mask_from_input(array $months): ?int
{
    $mask = 0;
    foreach ($months as $m) {
        $m = (int) $m;
        if ($m >= 1 && $m <= 12) {
            $mask |= 1 << ($m - 1);
        }
    }
    return $mask === 0 ? null : $mask;
}

const SEASON_LABELS = [
    'in'    => 'de saison',
    'mixed' => 'en partie de saison',
    'out'   => 'hors saison',
];

/** Pastille « de saison » / « en partie de saison » / « hors saison » (rien si aucun signal). */
function season_badge(array $s): string
{
    return match ($s['status']) {
        'in'    => '<span class="k-badge k-badge--season" title="De saison ce mois-ci">' . icon('leaf', 'sm') . 'de saison</span>',
        'mixed' => '<span class="k-badge k-badge--season-part" title="Une partie des ingrédients est de saison">' . icon('leaf', 'sm') . 'en partie de saison</span>',
        'out'   => '<span class="k-badge k-badge--offseason" title="Des ingrédients frais ne sont pas de saison ce mois-ci">hors saison</span>',
        default => '',
    };
}
