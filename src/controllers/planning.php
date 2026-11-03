<?php
declare(strict_types=1);

function week_url(DateTimeImmutable|string $date): string
{
    $d = is_string($date) ? (parse_date($date) ?? today()) : $date;
    return url('/week', ['d' => ymd(monday_of($d))]);
}

function redirect_week(DateTimeImmutable|string $date): never
{
    header('Location: ' . week_url($date));
    exit;
}

function load_meal(int $id, int $householdId): array
{
    $m = db_one('SELECT * FROM meals WHERE id = ? AND household_id = ?', [$id, $householdId]);
    if (!$m) {
        abort(404, 'Repas introuvable.');
    }
    return $m;
}

// ---------------------------------------------------------------- vues

function planning_week(): void
{
    $hh = require_household();
    $hid = (int) $hh['id'];
    $monday = requested_week();
    $days = week_days($monday);

    $rows = db_all(
        'SELECT m.*, r.name AS recipe_name, r.category, r.url AS recipe_url,
                (SELECT COUNT(*) FROM recipe_ingredients i WHERE i.recipe_id = m.recipe_id) AS ingredient_count
           FROM meals m LEFT JOIN recipes r ON r.id = m.recipe_id
          WHERE m.household_id = ? AND m.meal_date BETWEEN ? AND ?
          ORDER BY m.meal_date, m.slot = "dinner", m.id',
        [$hid, ymd($monday), ymd($days[6])]
    );
    $byDate = [];
    foreach ($rows as $m) {
        $byDate[$m['meal_date']][] = $m;
    }
    $share = db_one('SELECT * FROM share_links WHERE household_id = ? AND week_start = ? ORDER BY id DESC LIMIT 1', [$hid, ymd($monday)]);

    render('planning/week', [
        'onboarding' => onboarding_card($hh, current_user()),
        'title'    => 'Semaine du ' . $monday->format('j') . ' ' . month_name($monday),
        'hh'       => $hh,
        'monday'   => $monday,
        'days'     => $days,
        'byDate'   => $byDate,
        'meals'    => count($rows),
        'recipes'  => db_all('SELECT id, name, category FROM recipes WHERE household_id = ? ORDER BY name', [$hid]),
        'share'    => $share ? $share + ['link' => abs_url('/s/' . $share['token'])] : null,
    ]);
}

function planning_calendar(): void
{
    $hh = require_household();
    $hid = (int) $hh['id'];
    $month = input_str('m');
    $first = parse_date($month . '-01') ?? today()->modify('first day of this month');
    $gridStart = monday_of($first);
    $lastDay = $first->modify('last day of this month');
    $gridEnd = monday_of($lastDay)->modify('+6 days');

    $rows = db_all(
        'SELECT m.meal_date, m.slot, COALESCE(r.name, m.label) AS name
           FROM meals m LEFT JOIN recipes r ON r.id = m.recipe_id
          WHERE m.household_id = ? AND m.meal_date BETWEEN ? AND ?
          ORDER BY m.meal_date, m.slot = "dinner", m.id',
        [$hid, ymd($gridStart), ymd($gridEnd)]
    );
    $byDate = [];
    foreach ($rows as $m) {
        $byDate[$m['meal_date']][] = $m;
    }
    $f = db_one('SELECT token FROM calendar_feeds WHERE household_id = ?', [$hid]);
    $feedUrl = $f ? abs_url('/cal/' . $f['token'] . '.ics') : null;
    render('planning/calendar', [
        'feed' => $feedUrl ? ['link' => $feedUrl, 'webcal' => preg_replace('#^https?://#', 'webcal://', $feedUrl)] : null,
        'title' => ucfirst(month_name($first)) . ' ' . $first->format('Y'),
        'first' => $first, 'gridStart' => $gridStart, 'gridEnd' => $gridEnd, 'byDate' => $byDate,
        'prev' => $first->modify('-1 month')->format('Y-m'), 'next' => $first->modify('+1 month')->format('Y-m'),
    ]);
}

// ---------------------------------------------------------------- repas

function meals_add(): void
{
    $hh = require_household();
    require_post();
    $date = parse_date(input_str('date'));
    if (!$date) {
        abort(404, 'Date invalide.');
    }
    $slot = isset(SLOTS[input_str('slot')]) ? input_str('slot') : 'dinner';
    $recipeId = input_int('recipe_id', 0);
    $label = mb_substr(input_str('label'), 0, 200);
    $servings = input_int('servings');
    $servings = $servings !== null && $servings >= 1 && $servings <= 30 ? $servings : null;

    if ($recipeId) {
        load_recipe($recipeId, (int) $hh['id']);
        $label = '';
    } elseif ($label === '') {
        flash('error', 'Saisissez le nom du repas.');
        redirect_week($date);
    } else {
        // Champ texte unique : on retrouve la recette si elle existe, sinon on la crée (sauf plat similaire déjà connu).
        $all = db_all('SELECT id, name FROM recipes WHERE household_id = ?', [$hh['id']]);
        $similar = find_similar_recipe($all, $label);
        if ($similar && $similar['strength'] === 'exact') {
            $recipeId = (int) $similar['recipe']['id'];
            if (norm_key($similar['recipe']['name']) !== norm_key($label)) {
                flash('info', '« ' . $label . ' » rapproché de la recette existante « ' . $similar['recipe']['name'] . ' ».');
            }
            $label = '';
        } elseif (is_non_dish($label)) {
            // Sortie, restes, restaurant… : repas libre, jamais ajouté aux recettes.
        } elseif ($similar) {
            flash('info', '« ' . $label . ' » ajouté comme repas libre : une recette proche existe déjà (« ' . $similar['recipe']['name']
                . ' »), aucune nouvelle recette n\'a été créée. Tapez son nom pour l\'utiliser à la place.');
        } else {
            $recipeId = db_insert('recipes', [
                'household_id' => $hh['id'], 'name' => mb_ucfirst($label), 'category' => guess_category($label),
                'servings' => $hh['default_servings'], 'created_by' => current_user()['id'],
            ]);
            flash('success', '« ' . mb_ucfirst($label) . ' » ajouté aux recettes. Ouvrez-le pour saisir ses ingrédients et l\'inclure dans la liste de courses.');
            $label = '';
        }
    }
    db_insert('meals', [
        'household_id' => $hh['id'], 'meal_date' => ymd($date), 'slot' => $slot,
        'recipe_id' => $recipeId ?: null, 'label' => $label !== '' ? $label : null,
        'servings' => $servings, 'created_by' => current_user()['id'],
    ]);
    redirect_week($date);
}

function meals_update(int $id): void
{
    $hh = require_household();
    require_post();
    $m = load_meal($id, (int) $hh['id']);
    $date = parse_date(input_str('date')) ?? parse_date($m['meal_date']);
    $slot = isset(SLOTS[input_str('slot')]) ? input_str('slot') : $m['slot'];
    $servings = input_int('servings');
    $servings = $servings !== null && $servings >= 1 && $servings <= 30 ? $servings : null;
    $notes = mb_substr(input_str('notes'), 0, 500);
    db_run(
        'UPDATE meals SET meal_date = ?, slot = ?, servings = ?, notes = ? WHERE id = ?',
        [ymd($date), $slot, $servings, $notes !== '' ? $notes : null, $m['id']]
    );
    redirect_week($date);
}

function meals_delete(int $id): void
{
    $hh = require_household();
    require_post();
    $m = load_meal($id, (int) $hh['id']);
    db_run('DELETE FROM meals WHERE id = ?', [$m['id']]);
    redirect_week($m['meal_date']);
}

/** Remplace la recette d'un repas par une autre suggestion. */
function meals_reroll(int $id): void
{
    $hh = require_household();
    require_post();
    $m = load_meal($id, (int) $hh['id']);
    $monday = monday_of(parse_date($m['meal_date']));
    $inWeek = array_map('intval', array_column(db_all(
        'SELECT recipe_id FROM meals WHERE household_id = ? AND recipe_id IS NOT NULL AND meal_date BETWEEN ? AND ?',
        [$hh['id'], ymd($monday), ymd($monday->modify('+6 days'))]
    ), 'recipe_id'));
    $pick = pick_recipes((int) $hh['id'], $monday, 1, 3, 0, $inWeek);
    if (!$pick) {
        flash('error', 'Aucune autre recette disponible pour la génération.');
    } else {
        db_run('UPDATE meals SET recipe_id = ?, label = NULL WHERE id = ?', [$pick[0]['id'], $m['id']]);
    }
    redirect_week($m['meal_date']);
}

// ---------------------------------------------------------------- semaine entière

function week_generate(): void
{
    $hh = require_household();
    require_post();
    $monday = monday_of(parse_date(input_str('d')) ?? today());
    $days = array_map('intval', (array) ($_POST['days'] ?? []));
    $slot = isset(SLOTS[input_str('slot')]) ? input_str('slot') : 'dinner';
    $avoid = max(0, min(12, (int) input_int('avoid_weeks', 3)));
    $minVeg = max(0, min(7, (int) input_int('min_veg', 0)));
    if (!$days) {
        flash('error', 'Cochez au moins un jour à remplir.');
        redirect_week($monday);
    }
    if (!(int) db_val('SELECT COUNT(*) FROM recipes WHERE household_id = ? AND auto_ok = 1', [$hh['id']])) {
        flash('error', "Ajoutez d'abord des recettes pour pouvoir générer un menu.");
        redirect_week($monday);
    }
    $n = generate_week((int) $hh['id'], (int) current_user()['id'], $monday, $days, $slot, $avoid, $minVeg, input_str('replace') === '1');
    flash($n ? 'success' : 'info', $n ? "$n " . plural($n, 'repas généré', 'repas générés') . '.' : 'Rien à générer : les créneaux choisis sont déjà remplis.');
    redirect_week($monday);
}

function week_copy(): void
{
    $hh = require_household();
    require_post();
    $monday = monday_of(parse_date(input_str('d')) ?? today());
    $prev = $monday->modify('-7 days');
    $src = db_all('SELECT * FROM meals WHERE household_id = ? AND meal_date BETWEEN ? AND ?', [$hh['id'], ymd($prev), ymd($prev->modify('+6 days'))]);
    $n = 0;
    foreach ($src as $m) {
        $date = ymd(parse_date($m['meal_date'])->modify('+7 days'));
        $dup = db_val(
            'SELECT COUNT(*) FROM meals WHERE household_id = ? AND meal_date = ? AND slot = ? AND recipe_id <=> ? AND label <=> ?',
            [$hh['id'], $date, $m['slot'], $m['recipe_id'], $m['label']]
        );
        if ($dup) {
            continue;
        }
        db_insert('meals', [
            'household_id' => $hh['id'], 'meal_date' => $date, 'slot' => $m['slot'], 'recipe_id' => $m['recipe_id'],
            'label' => $m['label'], 'servings' => $m['servings'], 'notes' => $m['notes'], 'created_by' => current_user()['id'],
        ]);
        $n++;
    }
    flash($n ? 'success' : 'info', $n ? "$n " . plural($n, 'repas copié', 'repas copiés') . ' depuis la semaine précédente.' : 'Rien à copier depuis la semaine précédente.');
    redirect_week($monday);
}

function week_clear(): void
{
    $hh = require_household();
    require_post();
    $monday = monday_of(parse_date(input_str('d')) ?? today());
    db_run('DELETE FROM meals WHERE household_id = ? AND meal_date BETWEEN ? AND ?', [$hh['id'], ymd($monday), ymd($monday->modify('+6 days'))]);
    db_run('DELETE FROM shopping_checks WHERE household_id = ? AND week_start = ?', [$hh['id'], ymd($monday)]);
    flash('success', 'Semaine vidée.');
    redirect_week($monday);
}

// ---------------------------------------------------------------- abonnement au calendrier (flux ICS)

/** Flux ICS public, protégé par un jeton secret dans l'adresse (les applications de calendrier ne peuvent pas se connecter). */
function calendar_feed_ics(string $token): void
{
    $hh = db_one('SELECT h.* FROM calendar_feeds f JOIN households h ON h.id = f.household_id WHERE f.token = ?', [$token]);
    if (!$hh) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Lien d'abonnement inconnu ou désactivé.\n";
        exit;
    }
    $body = build_calendar_feed($hh);
    $etag = '"' . sha1($body) . '"';
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: inline; filename="kitcho.ics"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('ETag: ' . $etag);
    header('X-Robots-Tag: noindex, nofollow');
    if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
        http_response_code(304);
        exit;
    }
    echo $body;
    exit;
}

function feed_back(): never
{
    $m = input_str('m');
    $query = ['abo' => 1];
    if (parse_date($m . '-01')) {
        $query['m'] = $m;
    }
    header('Location: ' . url('/calendar', $query) . '#abonnement');
    exit;
}

/** Crée le lien d'abonnement du foyer, ou en génère un nouveau (l'ancien cesse de fonctionner). */
function calendar_feed_create(): void
{
    $hh = require_household();
    require_post();
    db_run(
        'INSERT INTO calendar_feeds (household_id, token, created_by) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE token = VALUES(token), created_by = VALUES(created_by), created_at = NOW()',
        [$hh['id'], new_token(), current_user()['id']]
    );
    flash('success', "Lien d'abonnement prêt. Si vous en aviez déjà un, l'ancien ne fonctionne plus : réabonnez vos calendriers avec le nouveau.");
    feed_back();
}

function calendar_feed_revoke(): void
{
    $hh = require_household();
    require_post();
    db_run('DELETE FROM calendar_feeds WHERE household_id = ?', [$hh['id']]);
    flash('success', "Abonnement désactivé : les calendriers déjà abonnés ne recevront plus de mises à jour.");
    feed_back();
}
