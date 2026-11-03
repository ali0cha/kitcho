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
