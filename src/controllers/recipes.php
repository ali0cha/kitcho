<?php
declare(strict_types=1);

function load_recipe(int $id, int $householdId): array
{
    $r = db_one('SELECT * FROM recipes WHERE id = ? AND household_id = ?', [$id, $householdId]);
    if (!$r) {
        abort(404, 'Recette introuvable.');
    }
    return $r;
}

/**
 * Recette visible par ce foyer : la sienne, ou une recette publique d'un autre foyer (lecture seule).
 * Ajoute 'owner_name' et 'is_own'.
 */
function load_recipe_viewable(int $id, int $householdId): array
{
    $r = db_one('SELECT r.*, h.name AS owner_name FROM recipes r JOIN households h ON h.id = r.household_id WHERE r.id = ?', [$id]);
    if (!$r || ((int) $r['household_id'] !== $householdId && !(int) $r['is_public'])) {
        abort(404, 'Recette introuvable.');
    }
    $r['is_own'] = (int) $r['household_id'] === $householdId;
    return $r;
}

/** Recettes publiques des autres foyers, hors celles que ce foyer a déjà copiées. */
const PUBLIC_RECIPES_WHERE = 'r.is_public = 1 AND r.household_id <> ? AND NOT EXISTS (SELECT 1 FROM recipes c WHERE c.household_id = ? AND c.source_id = r.id)';

function recipes_index(): void
{
    $hh = require_household();
    $hid = (int) $hh['id'];
    $q = input_str('q');
    $cat = input_str('cat');
    $sort = input_str('sort', 'name');
    $pub = input_str('pub') === '1';

    // Toutes les recettes sont envoyées : le filtrage (texte + catégorie) se fait dans le navigateur pendant la frappe.
    $order = match ($sort) {
        'recent' => 'r.id DESC',
        'last'   => 'last_planned IS NULL, last_planned DESC, r.name',
        'times'  => 'times_planned DESC, r.name',
        default  => 'r.name',
    };
    $recipes = db_all(
        "SELECT r.*, (SELECT COUNT(*) FROM recipe_ingredients i WHERE i.recipe_id = r.id) AS ingredient_count,
                (SELECT MAX(m.meal_date) FROM meals m WHERE m.recipe_id = r.id) AS last_planned,
                (SELECT COUNT(*) FROM meals m WHERE m.recipe_id = r.id) AS times_planned
           FROM recipes r WHERE r.household_id = ? ORDER BY $order",
        [$hid]
    );
    $publicCount = (int) db_val('SELECT COUNT(*) FROM recipes r WHERE ' . PUBLIC_RECIPES_WHERE, [$hid, $hid]);
    $public = [];
    if ($pub && $publicCount) {
        $public = db_all(
            'SELECT r.id, r.name, r.category, r.season_months, h.name AS owner_name,
                    (SELECT COUNT(*) FROM recipe_ingredients i WHERE i.recipe_id = r.id) AS ingredient_count
               FROM recipes r JOIN households h ON h.id = r.household_id
              WHERE ' . PUBLIC_RECIPES_WHERE . ' ORDER BY r.name LIMIT 1000',
            [$hid, $hid]
        );
    }
    $month = (int) today()->format('n');
    $season = recipes_season_map(array_merge($recipes, $public), $month);
    if ($sort === 'season') {                       // de saison d'abord, hors saison en dernier
        $rank = ['in' => 0, 'mixed' => 1, 'none' => 2, 'out' => 3];
        usort($recipes, fn($a, $b) => [$rank[$season[$a['id']]['status']], $a['name']] <=> [$rank[$season[$b['id']]['status']], $b['name']]);
    }
    render('recipes/index', [
        'title' => 'Recettes', 'recipes' => $recipes, 'q' => $q, 'cat' => $cat, 'sort' => $sort,
        'season' => $season, 'seasonOn' => input_str('saison') === '1', 'seasonMonth' => today(),
        'pub' => $pub, 'public' => $public, 'publicCount' => $publicCount,
        'total' => count($recipes) + count($public),
    ]);
}

function recipes_show(int $id): void
{
    $hh = require_household();
    $r = load_recipe_viewable($id, (int) $hh['id']);
    $ings = db_all('SELECT * FROM recipe_ingredients WHERE recipe_id = ? ORDER BY position', [$id]);
    $steps = recipe_steps($id);
    $own = $r['is_own'];
    $history = $own ? db_all('SELECT meal_date, slot FROM meals WHERE recipe_id = ? ORDER BY meal_date DESC LIMIT 8', [$id]) : [];
    $copyId = null;
    if (!$own) {
        $r['notes'] = null;                       // les notes restent privées au foyer qui partage
        $copyId = db_val('SELECT id FROM recipes WHERE household_id = ? AND source_id = ? ORDER BY id LIMIT 1', [$hh['id'], $id]) ?: null;
    }
    // Nombre de personnes affiché : ?p=N, sinon celui du foyer. Les quantités sont recalculées par rapport aux portions de la recette.
    $people = input_int('p');
    $people = $people !== null && $people >= 1 && $people <= 30 ? $people : (int) $hh['default_servings'];
    $month = (int) today()->format('n');
    $override = $r['season_months'] !== null ? (int) $r['season_months'] : null;
    $classified = season_classify($ings);
    render('recipes/show', [
        'season' => season_status($classified, $month, $override, (string) $r['category']),
        'seasonYear' => season_year($classified, $override, (string) $r['category']),
        'seasonMonth' => today(),
        'title' => $r['name'], 'r' => $r, 'ings' => $ings, 'steps' => $steps, 'history' => $history, 'hh' => $hh,
        'people' => $people, 'baseServings' => max(1, (int) $r['servings']), 'own' => $own, 'copyId' => $copyId,
    ]);
}

/** Copie une recette publique d'un autre foyer (ingrédients et étapes compris) dans les recettes de ce foyer. */
function recipes_copy(int $id): void
{
    $hh = require_household();
    require_post();
    $r = load_recipe_viewable($id, (int) $hh['id']);
    if ($r['is_own']) {
        redirect('/recipes/' . $id);
    }
    $existing = db_val('SELECT id FROM recipes WHERE household_id = ? AND source_id = ? ORDER BY id LIMIT 1', [$hh['id'], $id]);
    if ($existing) {
        flash('info', 'Cette recette est déjà dans vos recettes.');
        redirect('/recipes/' . (int) $existing);
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $newId = db_insert('recipes', [
            'household_id' => $hh['id'], 'name' => $r['name'], 'category' => $r['category'], 'url' => $r['url'],
            'servings' => $r['servings'], 'prep_minutes' => $r['prep_minutes'], 'cook_minutes' => $r['cook_minutes'],
            'total_minutes' => $r['total_minutes'], 'favorite' => 0, 'auto_ok' => 1, 'is_public' => 0, 'season_months' => $r['season_months'],
            'source_id' => $r['id'], 'created_by' => current_user()['id'],
        ]);
        db_run('INSERT INTO recipe_ingredients (recipe_id, name, quantity, unit, aisle, position) SELECT ?, name, quantity, unit, aisle, position FROM recipe_ingredients WHERE recipe_id = ?', [$newId, $id]);
        db_run('INSERT INTO recipe_steps (recipe_id, position, kind, text) SELECT ?, position, kind, text FROM recipe_steps WHERE recipe_id = ?', [$newId, $id]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    flash('success', 'Recette « ' . $r['name'] . ' » ajoutée à vos recettes : vous pouvez la modifier et la planifier.');
    redirect('/recipes/' . $newId);
}

function recipe_from_input(): array
{
    $cat = input_str('category');
    return [
        'name'     => mb_substr(input_str('name'), 0, 200),
        'category' => isset(CATEGORIES[$cat]) ? $cat : 'autre',
        'url'      => ($u = safe_url(input_str('url'))) !== null ? mb_substr($u, 0, 500) : null,
        'notes'    => ($n = input_str('notes')) !== '' ? mb_substr($n, 0, 5000) : null,
        'servings' => max(1, min(30, (int) input_int('servings', 4))),
        'prep_minutes'  => minutes_input('prep_minutes'),
        'cook_minutes'  => minutes_input('cook_minutes'),
        'total_minutes' => minutes_input('total_minutes'),
        'favorite' => input_str('favorite') === '1' ? 1 : 0,
        'auto_ok'  => input_str('auto_ok') === '1' ? 1 : 0,
        'is_public' => input_str('is_public') === '1' ? 1 : 0,
        'season_months' => season_mask_from_input((array) ($_POST['season'] ?? [])),
    ];
}

function recipes_form(?int $id = null): void
{
    $hh = require_household();
    $recipe = ['name' => '', 'category' => 'autre', 'url' => '', 'notes' => '', 'servings' => $hh['default_servings'], 'favorite' => 0, 'auto_ok' => 1, 'is_public' => 0, 'season_months' => null];
    $ingText = '';
    $stepsText = '';
    if ($id !== null) {
        $recipe = load_recipe($id, (int) $hh['id']);
        $ingText = ingredients_to_text(db_all('SELECT * FROM recipe_ingredients WHERE recipe_id = ? ORDER BY position', [$id]));
        $stepsText = steps_to_text(recipe_steps($id));
    }
    if (is_post()) {
        csrf_verify();
        $data = recipe_from_input();
        $ingText = (string) ($_POST['ingredients'] ?? '');
        $stepsText = (string) ($_POST['steps'] ?? '');
        if ($data['name'] === '') {
            flash('error', 'Le nom de la recette est obligatoire.');
            $recipe = array_merge($recipe, $data);
        } else {
            if (input_str('url') !== '' && $data['url'] === null) {
                flash('error', "Le lien doit commencer par http:// ou https:// (il n'a pas été enregistré).");
            }
            if ($id === null) {
                $id = db_insert('recipes', $data + ['household_id' => $hh['id'], 'created_by' => current_user()['id']]);
            } else {
                $set = implode(', ', array_map(fn($c) => "`$c` = ?", array_keys($data)));
                db_run("UPDATE recipes SET $set WHERE id = ? AND household_id = ?", [...array_values($data), $id, $hh['id']]);
            }
            $n = save_recipe_ingredients($id, $ingText);
            $ns = save_recipe_steps($id, $stepsText);
            flash('success', 'Recette enregistrée (' . $n . ' ' . plural($n, 'ingrédient', 'ingrédients') . ', ' . $ns . ' ' . plural($ns, 'étape', 'étapes') . ').');
            redirect('/recipes/' . $id);
        }
    }
    render('recipes/form', [
        'title' => $id ? 'Modifier la recette' : 'Nouvelle recette',
        'recipe' => $recipe, 'ingText' => $ingText, 'stepsText' => $stepsText, 'id' => $id, 'action' => '', 'notice' => null,
    ]);
}

function recipes_new(): void
{
    recipes_form(null);
}

function recipes_edit(int $id): void
{
    recipes_form($id);
}

function recipes_delete(int $id): void
{
    $hh = require_household();
    require_post();
    $r = load_recipe($id, (int) $hh['id']);
    db_run('DELETE FROM recipes WHERE id = ?', [$r['id']]);
    flash('success', 'Recette « ' . $r['name'] . ' » supprimée. Les repas déjà planifiés conservent leur nom.');
    redirect('/recipes');
}

function recipes_favorite(int $id): void
{
    $hh = require_household();
    require_post();
    $r = load_recipe($id, (int) $hh['id']);
    db_run('UPDATE recipes SET favorite = 1 - favorite WHERE id = ?', [$r['id']]);
    redirect(app_path(input_str('back'), '/recipes'));
}

/** Import rapide : une recette par ligne (le nom seulement). */
function recipes_import(): void
{
    $hh = require_household();
    require_post();
    $existing = array_flip(array_map('norm_key', array_column(db_all('SELECT name FROM recipes WHERE household_id = ?', [$hh['id']]), 'name')));
    $added = $skipped = 0;
    foreach (preg_split('/\R/u', (string) ($_POST['names'] ?? '')) as $line) {
        $name = trim(preg_replace('/^[\s\-*•]+/u', '', $line));
        $name = mb_substr($name, 0, 200);
        if ($name === '') {
            continue;
        }
        $key = norm_key($name);
        if (isset($existing[$key])) {
            $skipped++;
            continue;
        }
        $existing[$key] = true;
        db_insert('recipes', [
            'household_id' => $hh['id'], 'name' => mb_ucfirst($name), 'category' => guess_category($name),
            'servings' => $hh['default_servings'], 'created_by' => current_user()['id'],
        ]);
        $added++;
    }
    flash('success', "$added " . plural($added, 'recette ajoutée', 'recettes ajoutées') . ($skipped ? " ($skipped déjà " . plural($skipped, 'présente', 'présentes') . ')' : '') . '.');
    redirect('/recipes');
}

// ---------------------------------------------------------------- export / import de fichier

/** Télécharge toutes les recettes du foyer (avec leurs ingrédients) au format JSON. */
function recipes_export(): void
{
    $hh = require_household();
    $hid = (int) $hh['id'];
    $recipes = db_all('SELECT id, name, category, url, notes, servings, prep_minutes, cook_minutes, total_minutes, favorite, auto_ok FROM recipes WHERE household_id = ? ORDER BY name', [$hid]);
    $byRecipe = [];
    foreach (db_all(
        'SELECT i.recipe_id, i.name, i.quantity, i.unit, i.aisle FROM recipe_ingredients i
           JOIN recipes r ON r.id = i.recipe_id WHERE r.household_id = ? ORDER BY i.recipe_id, i.position',
        [$hid]
    ) as $i) {
        $byRecipe[$i['recipe_id']][] = [
            'name' => $i['name'], 'quantity' => $i['quantity'] !== null ? (float) $i['quantity'] : null,
            'unit' => $i['unit'], 'aisle' => $i['aisle'],
        ];
    }
    $stepsByRecipe = [];
    foreach (db_all(
        'SELECT s.recipe_id, s.kind, s.text FROM recipe_steps s JOIN recipes r ON r.id = s.recipe_id WHERE r.household_id = ? ORDER BY s.recipe_id, s.position, s.id',
        [$hid]
    ) as $st) {
        $stepsByRecipe[$st['recipe_id']][] = ['kind' => $st['kind'], 'text' => $st['text']];
    }
    $out = [];
    foreach ($recipes as $r) {
        $out[] = [
            'name' => $r['name'], 'category' => $r['category'], 'url' => $r['url'], 'notes' => $r['notes'],
            'servings' => (int) $r['servings'], 'favorite' => (bool) $r['favorite'], 'auto_ok' => (bool) $r['auto_ok'],
            'prep_minutes' => $r['prep_minutes'] !== null ? (int) $r['prep_minutes'] : null,
            'cook_minutes' => $r['cook_minutes'] !== null ? (int) $r['cook_minutes'] : null,
            'total_minutes' => $r['total_minutes'] !== null ? (int) $r['total_minutes'] : null,
            'ingredients' => $byRecipe[$r['id']] ?? [],
            'steps' => $stepsByRecipe[$r['id']] ?? [],
        ];
    }
    $file = 'kitcho-recettes-' . date('Y-m-d') . '.json';
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    header('Cache-Control: no-store');
    echo json_encode(
        ['app' => 'kitcho', 'version' => 1, 'exported_at' => date('c'), 'recipes' => $out],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
    );
    exit;
}

/** Insère des ingrédients déjà structurés (quantité, unité, rayon) ; retourne leur nombre. */
function insert_ingredients(int $recipeId, array $ingredients): int
{
    $n = 0;
    foreach ($ingredients as $ing) {
        if (!is_array($ing) || !is_string($ing['name'] ?? null) || trim($ing['name']) === '') {
            continue;
        }
        $name = mb_substr(trim($ing['name']), 0, 150);
        $qty = isset($ing['quantity']) && is_numeric($ing['quantity']) && $ing['quantity'] >= 0 && $ing['quantity'] < 1000000 ? round((float) $ing['quantity'], 2) : null;
        $unit = is_string($ing['unit'] ?? null) && trim($ing['unit']) !== '' ? mb_substr(trim($ing['unit']), 0, 30) : null;
        $aisle = in_array($ing['aisle'] ?? null, AISLES, true) ? $ing['aisle'] : guess_aisle($name);
        db_insert('recipe_ingredients', [
            'recipe_id' => $recipeId, 'name' => $name, 'quantity' => $qty, 'unit' => $unit, 'aisle' => $aisle, 'position' => $n,
        ]);
        $n++;
    }
    return $n;
}

/**
 * Importe un fichier d'export Kitcho. Les recettes déjà présentes (même nom) sont conservées telles quelles ;
 * si l'une d'elles n'a aucun ingrédient et que le fichier en fournit, ils lui sont ajoutés.
 */
function recipes_import_file(): void
{
    $hh = require_household();
    require_post();
    $f = $_FILES['file'] ?? null;
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || $f['size'] > 2 * 1024 * 1024) {
        flash('error', 'Choisissez un fichier d\'export JSON (2 Mo maximum).');
        redirect('/recipes');
    }
    $data = json_decode((string) file_get_contents($f['tmp_name']), true);
    $list = is_array($data) ? ($data['recipes'] ?? $data) : null;
    if (!is_array($list) || !array_is_list($list) || count($list) > 5000) {
        flash('error', 'Fichier non reconnu : utilisez un export produit par Kitcho (bouton « Exporter mes recettes »).');
        redirect('/recipes');
    }

    $hid = (int) $hh['id'];
    $existing = [];
    foreach (db_all(
        'SELECT r.id, r.name, (SELECT COUNT(*) FROM recipe_ingredients i WHERE i.recipe_id = r.id) AS n,
                (SELECT COUNT(*) FROM recipe_steps s WHERE s.recipe_id = r.id) AS s FROM recipes r WHERE r.household_id = ?',
        [$hid]
    ) as $r) {
        $existing[norm_key($r['name'])] = ['id' => (int) $r['id'], 'n' => (int) $r['n'], 's' => (int) $r['s']];
    }

    $added = $completed = $skipped = $ingredients = $stepsAdded = 0;
    $pdo = db();
    $pdo->beginTransaction();
    try {
        foreach ($list as $r) {
            if (!is_array($r) || !is_string($r['name'] ?? null) || trim($r['name']) === '') {
                continue;
            }
            $name = mb_substr(trim($r['name']), 0, 200);
            $key = norm_key($name);
            $ings = is_array($r['ingredients'] ?? null) ? $r['ingredients'] : [];
            $steps = sanitize_steps($r['steps'] ?? []);
            if (isset($existing[$key])) {
                // Recette déjà présente : jamais modifiée, sauf pour la compléter (ingrédients ou étapes absents).
                $filled = false;
                if ($existing[$key]['n'] === 0 && $ings && ($n = insert_ingredients($existing[$key]['id'], $ings)) > 0) {
                    $existing[$key]['n'] = $n;
                    $ingredients += $n;
                    $filled = true;
                }
                if ($existing[$key]['s'] === 0 && $steps) {
                    save_recipe_steps($existing[$key]['id'], $steps);
                    $existing[$key]['s'] = count($steps);
                    $stepsAdded += count(array_filter($steps, fn($x) => $x['kind'] === 'step'));
                    $filled = true;
                }
                if ($filled) {
                    db_run(
                        'UPDATE recipes SET prep_minutes = COALESCE(prep_minutes, ?), cook_minutes = COALESCE(cook_minutes, ?), total_minutes = COALESCE(total_minutes, ?) WHERE id = ?',
                        [minutes_value($r['prep_minutes'] ?? null), minutes_value($r['cook_minutes'] ?? null), minutes_value($r['total_minutes'] ?? null), $existing[$key]['id']]
                    );
                    $completed++;
                } else {
                    $skipped++;
                }
                continue;
            }
            $cat = $r['category'] ?? '';
            $id = db_insert('recipes', [
                'household_id' => $hid,
                'name'         => $name,
                'category'     => is_string($cat) && isset(CATEGORIES[$cat]) ? $cat : guess_category($name),
                'url'          => is_string($r['url'] ?? null) ? mb_substr((string) safe_url($r['url']), 0, 500) ?: null : null,
                'notes'        => is_string($r['notes'] ?? null) && trim($r['notes']) !== '' ? mb_substr($r['notes'], 0, 5000) : null,
                'servings'     => max(1, min(30, (int) ($r['servings'] ?? $hh['default_servings']))),
                'prep_minutes' => minutes_value($r['prep_minutes'] ?? null),
                'cook_minutes' => minutes_value($r['cook_minutes'] ?? null),
                'total_minutes' => minutes_value($r['total_minutes'] ?? null),
                'favorite'     => !empty($r['favorite']) ? 1 : 0,
                'auto_ok'      => !array_key_exists('auto_ok', $r) || !empty($r['auto_ok']) ? 1 : 0,
                'created_by'   => current_user()['id'],
            ]);
            $existing[$key] = ['id' => $id, 'n' => 0, 's' => count($steps)];
            $existing[$key]['n'] = insert_ingredients($id, $ings);
            $ingredients += $existing[$key]['n'];
            if ($steps) {
                save_recipe_steps($id, $steps);
                $stepsAdded += count(array_filter($steps, fn($x) => $x['kind'] === 'step'));
            }
            $added++;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    flash('success', "Import terminé : $added " . plural($added, 'recette ajoutée', 'recettes ajoutées')
        . ($completed ? ", $completed " . plural($completed, 'complétée', 'complétées') . ' avec leurs ingrédients ou étapes' : '')
        . ", $ingredients " . plural($ingredients, 'ingrédient', 'ingrédients')
        . ", $stepsAdded " . plural($stepsAdded, 'étape', 'étapes')
        . ($skipped ? ", $skipped déjà " . plural($skipped, 'présente', 'présentes') . ' (inchangée' . ($skipped > 1 ? 's' : '') . ')' : '') . '.');
    redirect('/recipes');
}

// ---------------------------------------------------------------- import depuis un lien

/** 15 imports par tranche de 10 minutes et par session : évite d'utiliser Kitcho comme relais de requêtes. */
function import_throttle(): bool
{
    $now = time();
    $_SESSION['imports'] = array_values(array_filter($_SESSION['imports'] ?? [], fn($t) => $t > $now - 600));
    if (count($_SESSION['imports']) >= 15) {
        return false;
    }
    $_SESSION['imports'][] = $now;
    return true;
}

/** Bandeau affiché au-dessus du formulaire prérempli : ce qui a été trouvé, et ce qu'il faut vérifier. */
function import_notice(array $f, string $host, array $extra = []): array
{
    $c = $f['counts'];
    $found = $c['ingredients'] . ' ' . plural($c['ingredients'], 'ingrédient', 'ingrédients') . ' et ' . $c['steps'] . ' ' . plural($c['steps'], 'étape', 'étapes');
    $items = array_merge($extra, $f['warnings']);
    $good = $c['ingredients'] > 0 && $c['steps'] > 0 && !$items;
    return [
        'class' => $good ? 'k-banner--ok' : 'k-banner--warn', 'icon' => $good ? 'check' : 'warn',
        'title' => 'Recette lue depuis ' . $host,
        'text' => ' ' . $found . ' trouvés. Vérifiez et corrigez si besoin, puis enregistrez : rien n\'est encore ajouté à vos recettes.',
        'items' => $items,
    ];
}

function recipes_import_url(): void
{
    $hh = require_household();
    $hid = (int) $hh['id'];
    $page = ['title' => 'Importer depuis un lien', 'url' => input_str('url')];
    if (!is_post()) {
        render('recipes/import', $page);
        return;
    }
    csrf_verify();
    if (!import_throttle()) {
        flash('error', "Trop d'imports d'affilée : patientez quelques minutes.");
        render('recipes/import', $page);
        return;
    }
    try {
        $fetched = import_fetch($page['url']);
        $f = import_to_form(import_parse($fetched['html']), $fetched['url'], $hh);
    } catch (ImportException $e) {
        flash('error', $e->getMessage());
        render('recipes/import', $page);
        return;
    }

    $extra = [];
    if ($f['recipe']['url'] && ($dup = db_one('SELECT id, name FROM recipes WHERE household_id = ? AND url = ? LIMIT 1', [$hid, $f['recipe']['url']]))) {
        $extra[] = 'Vous avez déjà importé ce lien : « ' . $dup['name'] . ' ». Vous pouvez quand même enregistrer une copie.';
    } elseif ($f['recipe']['name'] !== '' && ($sim = find_similar_recipe(db_all('SELECT id, name FROM recipes WHERE household_id = ?', [$hid]), $f['recipe']['name']))) {
        $extra[] = 'Une recette proche existe déjà : « ' . $sim['recipe']['name'] . ' ».';
    }
    render('recipes/form', [
        'title' => 'Vérifier la recette importée', 'recipe' => $f['recipe'], 'ingText' => $f['ingText'], 'stepsText' => $f['stepsText'],
        'id' => null, 'action' => url('/recipes/new'), 'notice' => import_notice($f, import_host($fetched['url']), $extra),
    ]);
}

/**
 * « Récupérer depuis le lien » sur une recette existante : relit la page d'origine et propose ce qui manque
 * (ingrédients, étapes, temps, description). Rien n'est écrasé et rien n'est enregistré avant validation.
 */
function recipes_refetch(int $id): void
{
    $hh = require_household();
    require_post();
    $r = load_recipe($id, (int) $hh['id']);
    $url = safe_url($r['url']);
    if (!$url) {
        flash('error', "Cette recette n'a pas de lien à relire : ajoutez l'adresse de la recette.");
        redirect('/recipes/' . $id . '/edit');
    }
    if (!import_throttle()) {
        flash('error', "Trop d'imports d'affilée : patientez quelques minutes.");
        redirect('/recipes/' . $id);
    }
    try {
        $fetched = import_fetch($url);
        $f = import_to_form(import_parse($fetched['html']), $fetched['url'], $hh);
    } catch (ImportException $e) {
        flash('error', $e->getMessage());
        redirect('/recipes/' . $id);
    }

    $ingText = ingredients_to_text(db_all('SELECT * FROM recipe_ingredients WHERE recipe_id = ? ORDER BY position', [$id]));
    $stepsText = steps_to_text(recipe_steps($id));
    $filled = [];
    if ($ingText === '' && $f['ingText'] !== '') {
        $ingText = $f['ingText'];
        $filled[] = 'les ingrédients';
    }
    if ($stepsText === '' && $f['stepsText'] !== '') {
        $stepsText = $f['stepsText'];
        $filled[] = 'les étapes';
    }
    $timed = false;
    foreach (['prep_minutes', 'cook_minutes', 'total_minutes'] as $col) {
        if (empty($r[$col]) && !empty($f['recipe'][$col])) {
            $r[$col] = $f['recipe'][$col];
            $timed = true;
        }
    }
    if ($timed) {
        $filled[] = 'les temps';
    }
    if (empty($r['notes']) && !empty($f['recipe']['notes'])) {
        $r['notes'] = $f['recipe']['notes'];
        $filled[] = 'la description';
    }
    $notice = import_notice($f, import_host($fetched['url']));
    $notice['text'] = $filled
        ? ' Récupéré : ' . implode(', ', $filled) . '. Vos données existantes n\'ont pas été modifiées. Vérifiez, puis enregistrez.'
        : ' Rien de plus à récupérer : cette page ne contient rien qui manque à votre recette.';
    $notice['class'] = $filled ? 'k-banner--ok' : 'k-banner--warn';
    $notice['icon'] = $filled ? 'check' : 'info';
    render('recipes/form', [
        'title' => 'Compléter la recette', 'recipe' => $r, 'ingText' => $ingText, 'stepsText' => $stepsText,
        'id' => $id, 'action' => url('/recipes/' . $id . '/edit'), 'notice' => $notice,
    ]);
}
