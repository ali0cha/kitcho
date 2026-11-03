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

function recipes_index(): void
{
    $hh = require_household();
    $q = input_str('q');
    $cat = input_str('cat');
    $sort = input_str('sort', 'name');

    // Toutes les recettes sont envoyées : le filtrage (texte + catégorie) se fait dans le navigateur pendant la frappe.
    $params = [$hh['id']];
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
        $params
    );
    render('recipes/index', [
        'title' => 'Recettes', 'recipes' => $recipes, 'q' => $q, 'cat' => $cat, 'sort' => $sort,
        'total' => (int) db_val('SELECT COUNT(*) FROM recipes WHERE household_id = ?', [$hh['id']]),
    ]);
}

function recipes_show(int $id): void
{
    $hh = require_household();
    $r = load_recipe($id, (int) $hh['id']);
    $ings = db_all('SELECT * FROM recipe_ingredients WHERE recipe_id = ? ORDER BY position', [$id]);
    $history = db_all('SELECT meal_date, slot FROM meals WHERE recipe_id = ? ORDER BY meal_date DESC LIMIT 8', [$id]);
    render('recipes/show', ['title' => $r['name'], 'r' => $r, 'ings' => $ings, 'history' => $history, 'hh' => $hh]);
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
        'favorite' => input_str('favorite') === '1' ? 1 : 0,
        'auto_ok'  => input_str('auto_ok') === '1' ? 1 : 0,
    ];
}

function recipes_form(?int $id = null): void
{
    $hh = require_household();
    $recipe = ['name' => '', 'category' => 'autre', 'url' => '', 'notes' => '', 'servings' => $hh['default_servings'], 'favorite' => 0, 'auto_ok' => 1];
    $ingText = '';
    if ($id !== null) {
        $recipe = load_recipe($id, (int) $hh['id']);
        $ingText = ingredients_to_text(db_all('SELECT * FROM recipe_ingredients WHERE recipe_id = ? ORDER BY position', [$id]));
    }
    if (is_post()) {
        csrf_verify();
        $data = recipe_from_input();
        $ingText = (string) ($_POST['ingredients'] ?? '');
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
            flash('success', 'Recette enregistrée (' . $n . ' ' . plural($n, 'ingrédient', 'ingrédients') . ').');
            redirect('/recipes/' . $id);
        }
    }
    render('recipes/form', [
        'title' => $id ? 'Modifier la recette' : 'Nouvelle recette',
        'recipe' => $recipe, 'ingText' => $ingText, 'id' => $id,
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
    $recipes = db_all('SELECT id, name, category, url, notes, servings, favorite, auto_ok FROM recipes WHERE household_id = ? ORDER BY name', [$hid]);
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
    $out = [];
    foreach ($recipes as $r) {
        $out[] = [
            'name' => $r['name'], 'category' => $r['category'], 'url' => $r['url'], 'notes' => $r['notes'],
            'servings' => (int) $r['servings'], 'favorite' => (bool) $r['favorite'], 'auto_ok' => (bool) $r['auto_ok'],
            'ingredients' => $byRecipe[$r['id']] ?? [],
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
        'SELECT r.id, r.name, (SELECT COUNT(*) FROM recipe_ingredients i WHERE i.recipe_id = r.id) AS n FROM recipes r WHERE r.household_id = ?',
        [$hid]
    ) as $r) {
        $existing[norm_key($r['name'])] = ['id' => (int) $r['id'], 'n' => (int) $r['n']];
    }

    $added = $completed = $skipped = $ingredients = 0;
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
            if (isset($existing[$key])) {
                if ($existing[$key]['n'] === 0 && $ings && ($n = insert_ingredients($existing[$key]['id'], $ings)) > 0) {
                    $existing[$key]['n'] = $n;
                    $ingredients += $n;
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
                'favorite'     => !empty($r['favorite']) ? 1 : 0,
                'auto_ok'      => !array_key_exists('auto_ok', $r) || !empty($r['auto_ok']) ? 1 : 0,
                'created_by'   => current_user()['id'],
            ]);
            $existing[$key] = ['id' => $id, 'n' => 0];
            $existing[$key]['n'] = insert_ingredients($id, $ings);
            $ingredients += $existing[$key]['n'];
            $added++;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    flash('success', "Import terminé : $added " . plural($added, 'recette ajoutée', 'recettes ajoutées')
        . ($completed ? ", $completed " . plural($completed, 'complétée', 'complétées') . ' avec leurs ingrédients' : '')
        . ", $ingredients " . plural($ingredients, 'ingrédient', 'ingrédients')
        . ($skipped ? ", $skipped déjà " . plural($skipped, 'présente', 'présentes') . ' (inchangée' . ($skipped > 1 ? 's' : '') . ')' : '') . '.');
    redirect('/recipes');
}
