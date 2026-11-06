<?php
declare(strict_types=1);

const CATEGORIES = [
    'vegetarien' => 'Végétarien',
    'poisson'    => 'Poisson',
    'viande'     => 'Viande',
    'soupe'      => 'Soupe',
    'salade'     => 'Salade',
    'autre'      => 'Autre',
];

/** Ordre = ordre de parcours du magasin. */
const AISLES = [
    'Fruits & légumes',
    'Boulangerie',
    'Boucherie & poissonnerie',
    'Crèmerie',
    'Épicerie',
    'Surgelés',
    'Boissons',
    'Autre',
];

const SLOTS = ['lunch' => 'Déjeuner', 'dinner' => 'Dîner'];

function strip_accents(string $s): string
{
    return strtr($s, [
        'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'œ' => 'oe', 'æ' => 'ae', 'ÿ' => 'y', '’' => "'",
    ]);
}

/** Clé de comparaison : minuscules, sans accents, espaces normalisés. */
function norm_key(string $s): string
{
    $s = strip_accents(mb_strtolower($s));
    return trim(preg_replace('/\s+/u', ' ', $s));
}

function mb_ucfirst(string $s): string
{
    return mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1);
}

// ---------------------------------------------------------------- catégories / rayons

function guess_category(string $name): string
{
    $k = norm_key($name);
    $has = fn(array $w) => (bool) array_filter($w, fn($x) => str_contains($k, $x));
    if ($has(['saumon', 'poisson', 'cabillaud', 'thon', 'crevette', 'fish', 'tartare de'])) {
        return 'poisson';
    }
    if (preg_match('/^(soupe|veloute|potage|gaspacho)/', $k)) {
        return 'soupe';
    }
    if (str_starts_with($k, 'salade')) {
        return 'salade';
    }
    $meat = $has(['lardon', 'saucisse', 'poulet', 'jambon', 'merguez', 'bolo', 'carbo', 'porc', 'boulette', 'hachis', 'steak', 'boeuf', 'agneau', 'dinde', 'chipolata', 'amatrician', 'tartiflette', 'raclette', 'lasagne viande']);
    if ($meat) {
        return 'viande';
    }
    if ($has(['vege', 'tofu', 'halloumi', 'chevre', 'feta', 'dahl', 'ratatouille', 'champignon', 'epinard', 'risotto', 'gnocchi', 'portobello', 'courgette', 'pesto', 'pizza', 'flammekueche', 'noodle', 'pates a la alice'])) {
        return 'vegetarien';
    }
    return 'autre';
}

// ---------------------------------------------------------------- plats similaires

/** Mots significatifs d'un nom de plat : sans accents, sans mots vides, au singulier. */
function dish_tokens(string $name): array
{
    static $stop = ['le', 'la', 'les', 'de', 'du', 'des', 'd', 'l', 'au', 'aux', 'a', 'et', 'en', 'un', 'une', 'avec', 'sur', 'sauce'];
    $out = [];
    foreach (preg_split('/[^a-z0-9]+/', norm_key($name), -1, PREG_SPLIT_NO_EMPTY) as $w) {
        if (in_array($w, $stop, true)) {
            continue;
        }
        if (strlen($w) > 3 && (str_ends_with($w, 's') || str_ends_with($w, 'x'))) {
            $w = substr($w, 0, -1);
        }
        $out[$w] = $w;
    }
    return array_values($out);
}

/** Deux mots désignent la même chose : égaux, abréviation (« bolo » / « bolognaise ») ou coquille. */
function tokens_match(string $a, string $b): bool
{
    if ($a === $b) {
        return true;
    }
    $min = min(strlen($a), strlen($b));
    if ($min >= 4 && (str_starts_with($a, $b) || str_starts_with($b, $a))) {
        return true;
    }
    return $min >= 6 && levenshtein($a, $b) <= 1;
}

/**
 * Cherche dans $recipes (lignes avec id et name) un plat déjà connu qui ressemble à $label.
 *  - exact  : même nom (accents, casse, pluriels, « de/du/la » ignorés) ou simple coquille
 *  - weak   : un nom inclus dans l'autre (« Quiche saumon » / « Quiche saumon épinards ») ou fort recouvrement
 *
 * @return array{recipe: array, strength: string}|null
 */
function find_similar_recipe(array $recipes, string $label): ?array
{
    $a = dish_tokens($label);
    if (!$a) {
        return null;
    }
    $best = null;
    $bestScore = -1.0;
    foreach ($recipes as $r) {
        $b = dish_tokens($r['name']);
        if (!$b) {
            continue;
        }
        $inter = 0;
        $used = [];
        foreach ($a as $ta) {
            foreach ($b as $i => $tb) {
                if (!isset($used[$i]) && tokens_match($ta, $tb)) {
                    $used[$i] = true;
                    $inter++;
                    break;
                }
            }
        }
        if ($inter === 0) {
            continue;
        }
        $union = count($a) + count($b) - $inter;
        $jaccard = $inter / $union;
        if ($inter === count($a) && $inter === count($b)) {
            $strength = 'exact';
        } elseif ($inter === min(count($a), count($b)) || $jaccard >= 0.6) {
            $strength = 'weak';
        } else {
            continue;
        }
        $score = $jaccard + ($strength === 'exact' ? 10 : 0);
        if ($score > $bestScore) {
            $bestScore = $score;
            $best = ['recipe' => $r, 'strength' => $strength];
        }
    }
    return $best;
}

/** Libellés qui ne sont pas des plats (sortie, restes…) : on ne les ajoute pas aux recettes. */
function is_non_dish(string $label): bool
{
    $k = norm_key($label);
    if (str_starts_with($k, 'chez ')) {
        return true;
    }
    $words = preg_split('/[^a-z0-9]+/', $k, -1, PREG_SPLIT_NO_EMPTY);
    $skip = ['resto', 'restaurant', 'reste', 'restes', 'dehors', 'invite', 'invites', 'invitation', 'cantine', 'traiteur', 'livraison', 'commande', 'sortie', 'rien', 'jeune', 'frigo', 'sandwich', 'sandwichs', 'fete', 'apero'];
    return (bool) array_intersect($words, $skip);
}

function match_aisle(string $s): ?string
{
    $k = norm_key($s);
    foreach (AISLES as $a) {
        if (norm_key($a) === $k || str_starts_with(norm_key($a), $k) && strlen($k) >= 3) {
            return $a;
        }
    }
    return null;
}

function guess_aisle(string $name): string
{
    static $rules = [
        'Surgelés'                 => ['surgele', 'glace', 'frites surgel'],
        '_epicerie_spec'           => ['vinaigre', 'lait de coco', 'tomates pelee', 'concentre', 'coulis', 'passata', 'bouillon', 'conserve', 'pois chiche', 'haricots blanc', 'haricots noir', 'lentille', 'pate a tartiner', 'pate feuillet', 'pate brisee', 'pate sablee'],
        'Boissons'                 => ['vin$', 'biere', 'jus$', 'eau$', 'cafe', 'the$', 'sirop', 'cidre'],
        'Boulangerie'              => ['pain', 'baguette', 'brioche', 'wrap', 'tortilla', 'bun$', 'pita', 'croissant', 'galette', 'croute'],
        'Crèmerie'                 => ['lait$', 'creme', 'beurre', 'fromage', 'oeuf', 'yaourt', 'yogourt', 'mozzarella', 'mozza', 'feta', 'chevre', 'parmesan', 'pecorino', 'gruyere', 'emmental', 'mascarpone', 'ricotta', 'halloumi', 'raclette', 'gorgonzola', 'bleu', 'skyr', 'tofu', 'comte', 'cantal', 'boursin', 'burrata', 'tzatziki', 'houmous'],
        'Boucherie & poissonnerie' => ['poulet', 'boeuf', 'porc', 'saucisse', 'lardon', 'jambon', 'viande', 'hache', 'steak', 'saumon', 'cabillaud', 'poisson', 'crevette', 'merguez', 'chipolata', 'boulette', 'bacon', 'dinde', 'lard', 'agneau', 'andouille', 'cuisse', 'filet', 'escalope', 'thon'],
        'Fruits & légumes'         => ['tomate', 'oignon', 'ail$', 'carotte', 'courgette', 'poireau', 'chicon', 'endive', 'epinard', 'champignon', 'champi', 'pomme', 'patate', 'pdt', 'salade', 'concombre', 'poivron', 'aubergine', 'citron', 'persil', 'basilic', 'ciboulette', 'aneth', 'cerfeuil', 'coriandre', 'menthe', 'celeri', 'avocat', 'banane', 'orange', 'fruit', 'legume', 'haricot', 'brocoli', 'chou', 'radis', 'courge', 'potiron', 'betterave', 'scarole', 'blette', 'asperge', 'herbe', 'gingembre', 'echalote', 'roquette', 'mache$', 'cresson', 'pourpier', 'petit pois', 'mais$', 'salsifi', 'panais', 'fenouil', 'laitue', 'romaine', 'kale', 'cerise', 'fraise', 'framboise', 'poire', 'melon', 'figue', 'raisin', 'mangue', 'germes', 'pousses', 'thym', 'romarin', 'estragon'],
        'Épicerie'                 => ['pate', 'riz$', 'farine', 'sucre', 'sel$', 'poivre', 'huile', 'sauce', 'semoule', 'quinoa', 'couscous', 'noix', 'amande', 'noisette', 'chocolat', 'miel', 'moutarde', 'mayonnaise', 'ketchup', 'epice', 'curry', 'cumin', 'paprika', 'cannelle', 'levure', 'biscuit', 'cereale', 'avoine', 'chapelure', 'maizena', 'tahini', 'olive', 'cornichon', 'capre', 'orzo', 'nouille', 'spaghetti', 'tagliatelle', 'penne', 'macaroni', 'lasagne', 'gnocchi', 'ble$', 'graine', 'soja', 'wasabi', 'nori'],
    ];
    $k = norm_key($name);
    $words = preg_split('/[^a-z0-9]+/', $k, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($rules as $aisle => $keywords) {
        foreach ($keywords as $kw) {
            if (str_contains($kw, ' ')) {
                if (str_contains($k, $kw)) {
                    return $aisle === '_epicerie_spec' ? 'Épicerie' : $aisle;
                }
                continue;
            }
            $exact = str_ends_with($kw, '$');
            $kw = rtrim($kw, '$');
            foreach ($words as $w) {
                if ($exact ? $w === $kw : str_starts_with($w, $kw)) {
                    return $aisle === '_epicerie_spec' ? 'Épicerie' : $aisle;
                }
            }
        }
    }
    return 'Autre';
}

// ---------------------------------------------------------------- saisie d'ingrédients

/** Unités reconnues : regex (début de chaîne) => forme canonique. */
function unit_patterns(): array
{
    $end = "(?:\\([sx]\\))?(?=[\\s.,;:)]|$)";   // « sachet(s) », « gousse(s) »
    $of = "(?=\\s+(?:de|du|des|d['’]))";
    return [
        "/^(?:kg|kilos?|kilogrammes?)$end/iu"                              => 'kg',
        "/^(?:g|gr|grs|grammes?)$end/iu"                                   => 'g',
        "/^(?:ml|millilitres?)$end/iu"                                     => 'ml',
        "/^(?:cl|centilitres?)$end/iu"                                     => 'cl',
        "/^(?:dl|decilitres?|décilitres?)$end/iu"                          => 'dl',
        "/^(?:l|litres?)$end/iu"                                           => 'l',
        "/^(?:cuill?(?:[eè]res?|[eè]r[ée]es?)\\s+à\\s+soupe|cuill?\\.?\\s*à\\s*\\.?\\s*soupe|c\\.?\\s*à\\s*\\.?\\s*soupe|c\\.?\\s*à\\s*\\.?\\s*s\\.?|càs|cs)$end/iu" => 'c. à soupe',
        "/^(?:cuill?(?:[eè]res?|[eè]r[ée]es?)\\s+à\\s+caf[eé]|cuill?\\.?\\s*à\\s*\\.?\\s*caf[eé]|c\\.?\\s*à\\s*\\.?\\s*caf[eé]|c\\.?\\s*à\\s*\\.?\\s*c\\.?|càc|cc)$end/iu" => 'c. à café',
        "/^pinc[ée]es?$end/iu"                                             => 'pincée',
        "/^gousses?$end/iu"                                                => 'gousse',
        "/^tranches?$end/iu"                                               => 'tranche',
        "/^bo[iî]tes?$end/iu"                                              => 'boîte',
        "/^sachets?$end/iu"                                                => 'sachet',
        "/^pots?$end/iu"                                                   => 'pot',
        "/^bottes?$end/iu"                                                 => 'botte',
        "/^paquets?$end/iu"                                                => 'paquet',
        "/^brins?$end/iu"                                                  => 'brin',
        "/^feuilles?$end/iu"                                               => 'feuille',
        "/^verres?$end/iu"                                                 => 'verre',
        "/^tasses?$end/iu"                                                 => 'tasse',
        "/^bouquets?$end/iu"                                               => 'bouquet',
        "/^barquettes?$end/iu"                                             => 'barquette',
        "/^poign[ée]es?$end/iu"                                            => 'poignée',
        "/^tiges?$end/iu"                                                  => 'tige',
        "/^branches?$end/iu"                                               => 'branche',
        "/^morceaux?$end/iu"                                               => 'morceau',
        "/^b[âa]tons?$end/iu"                                              => 'bâton',
        "/^noix$of/iu"                                                     => 'noix',
        "/^filets?$of/iu"                                                  => 'filet',
        "/^cubes?$of/iu"                                                   => 'cube',
    ];
}

const PLURAL_UNITS = ['pincée', 'gousse', 'tranche', 'boîte', 'sachet', 'pot', 'botte', 'paquet', 'brin', 'feuille', 'verre', 'tasse', 'bouquet', 'barquette', 'poignée', 'tige', 'branche', 'filet', 'cube', 'bâton'];

/** « Farine : 250 g » ou « Farine (250 g) » → « 250 g Farine » (quantité en tête, comme sur la plupart des sites français). */
function ingredient_quantity_first(string $line): string
{
    if (preg_match('/^(?<name>[^\d:()]{2,}?)\s*:\s*(?<q>\d.*)$/u', $line, $m)) {
        return trim($m['q']) . ' ' . trim($m['name']);
    }
    if (preg_match('/^(?<name>[^\d(][^()]*?)\s*\((?<q>\d[\d.,\/ ½¼¾]*\s*(?:[a-zA-Zà-ÿ.]{1,12})?)\)\s*$/u', $line, $m)) {
        return trim($m['q']) . ' ' . trim($m['name']);
    }
    // « feta bio 200 g », « œufs bio 3 », « aneth 1/4 bouquet » : quantité en fin de ligne.
    if (!preg_match('/^[\d½¼¾]/u', $line) && preg_match('#^(?<name>\D.*?)\s+(?<q>\d+(?:[.,]\d+)?(?:\s*/\s*\d+)?)\s*(?<u>[a-zA-Zà-ÿ.]{1,12}(?:\s*à\s*[a-zA-Z]{1,6}\.?)?)?\s*$#u', $line, $m)) {
        $tail = trim($m['q'] . ' ' . ($m['u'] ?? ''));
        $probe = parse_ingredient_line_tail($tail);
        if ($probe !== null) {
            return $tail . ' ' . trim($m['name']);
        }
    }
    return $line;
}

/** Vrai (non null) si « 200 g » / « 3 » / « 1/4 bouquet » est une quantité valide, avec une unité connue ou sans unité. */
function parse_ingredient_line_tail(string $tail): ?bool
{
    if (!preg_match('#^(\d+(?:[.,]\d+)?(?:\s*/\s*\d+)?)\s*(.*)$#u', $tail, $m)) {
        return null;
    }
    if (trim($m[2]) === '') {
        return true;
    }
    foreach (unit_patterns() as $re => $canon) {
        if (preg_match($re, trim($m[2]), $x) && trim(substr(trim($m[2]), strlen($x[0])), '. ') === '') {
            return true;
        }
    }
    return null;
}

/**
 * « 200 g pâtes », « 2 oignons », « 1/2 c. à café de sel ; Épicerie », « sel »,
 * « une pincée de sel », « 1 ½ c. à soupe d'huile », « 2 à 3 gousses d'ail », « Farine (250 g) ».
 * @return array{name:string, quantity:?float, unit:?string, aisle:string}|null
 */
function parse_ingredient_line(string $line): ?array
{
    $line = trim(preg_replace('/^[\s\-*•·–]+/u', '', $line));
    $line = trim(preg_replace('/\s+/u', ' ', str_replace(["\u{00A0}", "\u{202F}"], ' ', $line)));
    if ($line === '') {
        return null;
    }
    $aisle = null;
    if (str_contains($line, ';')) {
        [$line, $a] = array_pad(explode(';', $line, 2), 2, '');
        $aisle = match_aisle(trim($a));
        $line = trim($line);
    }
    $line = ingredient_quantity_first($line);

    $frac = ['½' => 0.5, '¼' => 0.25, '¾' => 0.75, '⅓' => 1 / 3, '⅔' => 2 / 3];
    $qty = null;
    if (preg_match('/^(?:un|une)\s+demi(?:e)?\s+/iu', $line, $m)) {
        $qty = 0.5;
    } elseif (preg_match('/^(?:un|une)\s+(?!peu\b|petit\b|brin\b)/iu', $line, $m)) {
        $qty = 1.0;
    } elseif (preg_match('/^(\d+)\s*([½¼¾⅓⅔])\s*/u', $line, $m)) {
        $qty = (int) $m[1] + $frac[$m[2]];
    } elseif (preg_match('#^(\d+)\s+(\d+)\s*/\s*(\d+)\s*#u', $line, $m) && (int) $m[3] > 0) {
        $qty = (int) $m[1] + (int) $m[2] / (int) $m[3];
    } elseif (preg_match('#^(\d+)\s*/\s*(\d+)\s*#u', $line, $m) && (int) $m[2] > 0) {
        $qty = (int) $m[1] / (int) $m[2];
    } elseif (preg_match('/^(\d+(?:[.,]\d+)?)\s*/u', $line, $m)) {
        $qty = (float) str_replace(',', '.', $m[1]);
    } elseif (preg_match('/^([½¼¾⅓⅔])\s*/u', $line, $m)) {
        $qty = $frac[$m[1]];
    }
    if ($qty !== null) {
        $line = substr($line, strlen($m[0]));
    }
    // Fourchette (« 2 à 3 », « 2-3 ») : on retient la borne haute, plus sûre pour les courses.
    $range = function () use (&$line, &$qty): void {
        if ($qty !== null && preg_match('/^(?:-|–|à|ou)\s*(\d+(?:[.,]\d+)?)\s*/iu', $line, $r)) {
            $qty = max($qty, (float) str_replace(',', '.', $r[1]));
            $line = substr($line, strlen($r[0]));
        }
    };
    $range();
    $unit = null;
    if ($qty !== null) {
        foreach (unit_patterns() as $re => $canon) {
            if (preg_match($re, $line, $m)) {
                $unit = $canon;
                $line = ltrim(substr($line, strlen($m[0])), ". \t");
                $range();
                break;
            }
        }
    }
    $line = trim(preg_replace("/^(?:de la |de l'|de l’|d'|d’|du |des |de )/iu", '', trim($line)));
    $line = rtrim($line, " .;,");
    $line = mb_substr($line, 0, 150);
    if ($line === '') {
        return null;
    }
    return ['name' => $line, 'quantity' => $qty, 'unit' => $unit, 'aisle' => $aisle ?? guess_aisle($line)];
}

/** @return array<int, array{name:string, quantity:?float, unit:?string, aisle:string}> */
function parse_ingredients(string $text): array
{
    $out = [];
    foreach (preg_split('/\R/u', $text) as $line) {
        if (($p = parse_ingredient_line($line)) !== null) {
            $out[] = $p;
        }
    }
    return $out;
}

/** Inverse de parse_ingredients, pour pré-remplir le formulaire d'édition. */
function ingredients_to_text(array $rows): string
{
    $lines = [];
    foreach ($rows as $r) {
        $parts = [];
        if ($r['quantity'] !== null) {
            $parts[] = format_qty((float) $r['quantity']);
        }
        if ($r['unit']) {
            $parts[] = unit_label($r['unit'], $r['quantity'] !== null ? (float) $r['quantity'] : null);
        }
        $parts[] = $r['name'];
        $lines[] = implode(' ', $parts) . ($r['aisle'] ? ' ; ' . $r['aisle'] : '');
    }
    return implode("\n", $lines);
}

function format_qty(float $q): string
{
    $q = round($q, 2);
    if (abs($q - round($q)) < 0.005) {
        return (string) (int) round($q);
    }
    return rtrim(rtrim(number_format($q, 2, ',', ''), '0'), ',');
}

function unit_label(?string $unit, ?float $qty): string
{
    if (!$unit) {
        return '';
    }
    if ($unit === 'morceau') {
        return ($qty !== null && $qty > 1) ? 'morceaux' : $unit;
    }
    return ($qty !== null && $qty > 1 && in_array($unit, PLURAL_UNITS, true)) ? $unit . 's' : $unit;
}

/** Remplace les ingrédients d'une recette par ceux du texte. */
function save_recipe_ingredients(int $recipeId, string $text): int
{
    db_run('DELETE FROM recipe_ingredients WHERE recipe_id = ?', [$recipeId]);
    $n = 0;
    foreach (parse_ingredients($text) as $i => $ing) {
        db_insert('recipe_ingredients', [
            'recipe_id' => $recipeId,
            'name'      => $ing['name'],
            'quantity'  => $ing['quantity'],
            'unit'      => $ing['unit'],
            'aisle'     => $ing['aisle'],
            'position'  => $i,
        ]);
        $n++;
    }
    return $n;
}

// ---------------------------------------------------------------- liste de courses

/**
 * Agrège les ingrédients des repas de la semaine (ajustés au nombre de portions).
 *
 * @return array{groups: array<string, array>, missing: string[], free: string[], total: int, done: int}
 */
function build_shopping_list(int $householdId, DateTimeImmutable $monday, int $defaultServings): array
{
    $meals = db_all(
        'SELECT m.id, m.recipe_id, m.label, m.servings, r.name AS recipe_name, r.servings AS recipe_servings
           FROM meals m LEFT JOIN recipes r ON r.id = m.recipe_id
          WHERE m.household_id = ? AND m.meal_date BETWEEN ? AND ?
          ORDER BY m.meal_date, m.slot',
        [$householdId, ymd($monday), ymd($monday->modify('+6 days'))]
    );

    $recipeIds = array_values(array_unique(array_filter(array_map(fn($m) => $m['recipe_id'], $meals))));
    $ingByRecipe = [];
    if ($recipeIds) {
        foreach (db_all('SELECT * FROM recipe_ingredients WHERE recipe_id IN (' . in_clause($recipeIds) . ') ORDER BY position', $recipeIds) as $ing) {
            $ingByRecipe[$ing['recipe_id']][] = $ing;
        }
    }

    $items = [];
    $missing = [];
    $free = [];
    foreach ($meals as $m) {
        if (!$m['recipe_id']) {
            $free[] = (string) $m['label'];
            continue;
        }
        $ings = $ingByRecipe[$m['recipe_id']] ?? [];
        if (!$ings) {
            $missing[$m['recipe_name']] = $m['recipe_name'];
            continue;
        }
        $factor = ((int) ($m['servings'] ?: $defaultServings)) / max(1, (int) $m['recipe_servings']);
        foreach ($ings as $ing) {
            $qty = $ing['quantity'] !== null ? (float) $ing['quantity'] * $factor : null;
            $unit = $ing['unit'] ?: null;
            if ($qty !== null) {
                [$qty, $unit] = to_base_unit($qty, $unit);
            }
            $key = norm_key($ing['name']) . '|' . ($unit ?? '');
            if (!isset($items[$key])) {
                $items[$key] = ['key' => sha1($key), 'name' => $ing['name'], 'unit' => $unit, 'qty' => null,
                                'aisle' => $ing['aisle'] ?: guess_aisle($ing['name']), 'from' => [], 'extra_id' => null];
            }
            if ($qty !== null) {
                $items[$key]['qty'] = ($items[$key]['qty'] ?? 0) + $qty;
            }
            $items[$key]['from'][$m['recipe_name']] = true;
        }
    }

    $checked = array_flip(array_column(db_all(
        'SELECT item_key FROM shopping_checks WHERE household_id = ? AND week_start = ?',
        [$householdId, ymd($monday)]
    ), 'item_key'));

    $groups = [];
    foreach ($items as $it) {
        [$it['qty'], $it['unit']] = from_base_unit($it['qty'], $it['unit']);
        $it['from'] = array_keys($it['from']);
        $it['checked'] = isset($checked[$it['key']]);
        $groups[$it['aisle']][] = $it;
    }
    foreach (db_all('SELECT * FROM shopping_extras WHERE household_id = ? AND week_start = ? ORDER BY id', [$householdId, ymd($monday)]) as $x) {
        $aisle = in_array($x['aisle'], AISLES, true) ? $x['aisle'] : 'Autre';
        $groups[$aisle][] = ['key' => 'x' . $x['id'], 'name' => $x['name'], 'unit' => null, 'qty' => null, 'qty_text' => $x['quantity'],
                             'aisle' => $aisle, 'from' => [], 'checked' => (bool) $x['checked'], 'extra_id' => (int) $x['id']];
    }

    $ordered = [];
    $total = $done = 0;
    foreach (AISLES as $a) {
        if (empty($groups[$a])) {
            continue;
        }
        usort($groups[$a], fn($p, $q) => [$p['checked'], norm_key($p['name'])] <=> [$q['checked'], norm_key($q['name'])]);
        foreach ($groups[$a] as $it) {
            $total++;
            $done += $it['checked'] ? 1 : 0;
        }
        $ordered[$a] = $groups[$a];
    }
    return ['groups' => $ordered, 'missing' => array_values($missing), 'free' => $free, 'total' => $total, 'done' => $done];
}

/** kg → g, l/cl/dl → ml pour pouvoir additionner. */
function to_base_unit(float $qty, ?string $unit): array
{
    return match ($unit) {
        'kg' => [$qty * 1000, 'g'],
        'l'  => [$qty * 1000, 'ml'],
        'cl' => [$qty * 10, 'ml'],
        'dl' => [$qty * 100, 'ml'],
        default => [$qty, $unit],
    };
}

function from_base_unit(?float $qty, ?string $unit): array
{
    if ($qty === null) {
        return [null, $unit];
    }
    if ($unit === 'g' && $qty >= 1000) {
        return [$qty / 1000, 'kg'];
    }
    if ($unit === 'ml' && $qty >= 1000) {
        return [$qty / 1000, 'l'];
    }
    return [$qty, $unit];
}

function item_quantity_text(array $it): string
{
    if (!empty($it['qty_text'])) {
        return (string) $it['qty_text'];
    }
    if ($it['qty'] === null) {
        return '';
    }
    $q = (float) $it['qty'];
    return trim(format_qty($q) . ' ' . unit_label($it['unit'], $q));
}

/** Version texte (copier/coller, WhatsApp...) de la liste. */
function shopping_list_text(array $list, string $title): string
{
    $out = [$title, ''];
    foreach ($list['groups'] as $aisle => $items) {
        $out[] = strtoupper_fr($aisle);
        foreach ($items as $it) {
            $q = item_quantity_text($it);
            $out[] = ($it['checked'] ? '[x] ' : '[ ] ') . ($q !== '' ? $q . ' ' : '') . $it['name'];
        }
        $out[] = '';
    }
    return trim(implode("\n", $out));
}

function strtoupper_fr(string $s): string
{
    return mb_strtoupper($s);
}

// ---------------------------------------------------------------- quantités adaptées au nombre de personnes
// Même algorithme que assets/scale.js (affichage initial côté serveur, puis boutons − / + côté navigateur).

/** Arrondi « moitié vers le haut » stable (126,49999999999999 → 126,5 → 127), identique à rnd() dans assets/scale.js. */
function round_stable(float $x, int $places = 0): float
{
    $m = 10 ** $places;
    return round((float) sprintf('%.15g', $x * $m)) / $m;
}

/**
 * Fraction lisible : « ½ », « 1 ¼ », « ⅓ », « 12 ».
 * @return array{0: string, 1: float} [texte, valeur affichée]
 */
function fraction_text(float $q): array
{
    if ($q >= 10) {
        $n = (int) round_stable($q);
        return [(string) $n, (float) $n];
    }
    $whole = (int) floor($q);
    $frac = $q - $whole;
    $candidates = [[0.0, ''], [1 / 4, '¼'], [1 / 3, '⅓'], [1 / 2, '½'], [2 / 3, '⅔'], [3 / 4, '¾'], [1.0, '']];
    if ($whole === 0) {
        array_splice($candidates, 1, 0, [[1 / 8, '⅛']]);        // « ⅛ » seulement pour de très petites quantités
    }
    $best = $candidates[0];
    foreach ($candidates as $c) {
        if (abs($frac - $c[0]) < abs($frac - $best[0]) - 1e-9) {
            $best = $c;
        }
    }
    [$value, $glyph] = $best;
    if ($value >= 1.0) {
        $whole++;
        $value = 0.0;
        $glyph = '';
    }
    if ($whole === 0 && $glyph === '') {                          // quantité minuscule : jamais « 0 »
        $value = 1 / 8;
        $glyph = '⅛';
    }
    $text = ($whole > 0 ? (string) $whole : '') . ($glyph !== '' ? ($whole > 0 ? ' ' : '') . $glyph : '');
    return [$text, $whole + $value];
}

/** Nombre décimal français sans zéros inutiles : 1,5 · 250 · 0,25. */
function decimal_text(float $n): string
{
    $n = round_stable($n, 2);
    if (abs($n - round($n)) < 0.005) {
        return (string) (int) round($n);
    }
    return rtrim(rtrim(number_format($n, 2, ',', ''), '0'), ',');
}

/**
 * Quantité + unité mises à l'échelle, prêtes à afficher : « 1 ½ gousses », « 1,5 kg », « 250 ml », « ½ ».
 * Les poids et volumes sont convertis (1 500 g → 1,5 kg ; 0,5 kg → 500 g), les autres quantités sont arrondies
 * aux fractions usuelles (¼, ⅓, ½, ⅔, ¾) et l'unité prend le pluriel.
 */
function scale_quantity(float $q, ?string $unit): string
{
    if ($unit === 'kg' && $q < 1) {
        [$q, $unit] = [$q * 1000, 'g'];
    } elseif ($unit === 'l' && $q < 1) {
        [$q, $unit] = [$q * 1000, 'ml'];
    }
    if ($unit === 'g' && $q >= 1000) {
        [$q, $unit] = [$q / 1000, 'kg'];
    } elseif ($unit === 'ml' && $q >= 1000) {
        [$q, $unit] = [$q / 1000, 'l'];
    } elseif ($unit === 'cl' && $q >= 100) {
        [$q, $unit] = [$q / 100, 'l'];
    } elseif ($unit === 'dl' && $q >= 10) {
        [$q, $unit] = [$q / 10, 'l'];
    }
    if ($unit === 'g' || $unit === 'ml') {
        $text = decimal_text($q >= 10 ? round_stable($q) : round_stable($q, 1));
        $shown = (float) str_replace(',', '.', $text);
    } elseif (in_array($unit, ['kg', 'l', 'cl', 'dl'], true)) {
        $text = decimal_text($q >= 10 ? round_stable($q) : round_stable($q, 2));
        $shown = (float) str_replace(',', '.', $text);
    } else {
        [$text, $shown] = fraction_text($q);
    }
    $label = unit_label($unit, $shown);
    return $label !== '' ? $text . ' ' . $label : $text;
}
