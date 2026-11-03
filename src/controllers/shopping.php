<?php
declare(strict_types=1);

function shopping_index(): void
{
    $hh = require_household();
    $monday = requested_week();
    $list = build_shopping_list((int) $hh['id'], $monday, (int) $hh['default_servings']);
    $title = 'Courses — semaine du ' . $monday->format('j') . ' ' . month_name($monday);
    render('shopping/index', [
        'title' => $title, 'monday' => $monday, 'list' => $list,
        'text'  => shopping_list_text($list, $title),
        'meals' => (int) db_val('SELECT COUNT(*) FROM meals WHERE household_id = ? AND meal_date BETWEEN ? AND ?',
            [$hh['id'], ymd($monday), ymd($monday->modify('+6 days'))]),
    ]);
}

/** État courant (articles cochés) : interrogé régulièrement pour synchroniser les membres du foyer. */
function shopping_state(): void
{
    $hh = require_household();
    $monday = requested_week();
    $list = build_shopping_list((int) $hh['id'], $monday, (int) $hh['default_servings']);
    $items = [];
    foreach ($list['groups'] as $group) {
        foreach ($group as $it) {
            $items[$it['key']] = $it['checked'];
        }
    }
    header('Cache-Control: no-store');
    json_response(['items' => $items, 'done' => $list['done'], 'total' => $list['total']]);
}

function shopping_back(DateTimeImmutable $monday): never
{
    header('Location: ' . url('/shopping', ['d' => ymd($monday)]));
    exit;
}

function shopping_toggle(): void
{
    $hh = require_household();
    require_post();
    $monday = monday_of(parse_date(input_str('d')) ?? today());
    $key = input_str('key');
    $checked = input_str('checked') === '1';
    $hid = (int) $hh['id'];

    if (preg_match('/^x(\d+)$/', $key, $m)) {
        db_run('UPDATE shopping_extras SET checked = ? WHERE id = ? AND household_id = ?', [$checked ? 1 : 0, $m[1], $hid]);
    } elseif (preg_match('/^[0-9a-f]{40}$/', $key)) {
        if ($checked) {
            db_run('INSERT IGNORE INTO shopping_checks (household_id, week_start, item_key) VALUES (?, ?, ?)', [$hid, ymd($monday), $key]);
        } else {
            db_run('DELETE FROM shopping_checks WHERE household_id = ? AND week_start = ? AND item_key = ?', [$hid, ymd($monday), $key]);
        }
    } else {
        abort(404);
    }
    if (wants_json()) {
        $list = build_shopping_list($hid, $monday, (int) $hh['default_servings']);
        json_response(['ok' => true, 'done' => $list['done'], 'total' => $list['total']]);
    }
    shopping_back($monday);
}

function shopping_extra_add(): void
{
    $hh = require_household();
    require_post();
    $monday = monday_of(parse_date(input_str('d')) ?? today());
    $name = mb_substr(input_str('name'), 0, 150);
    if ($name === '') {
        flash('error', "Saisissez le nom de l'article.");
        shopping_back($monday);
    }
    $aisle = match_aisle(input_str('aisle')) ?? guess_aisle($name);
    $qty = mb_substr(input_str('quantity'), 0, 50);
    db_insert('shopping_extras', [
        'household_id' => $hh['id'], 'week_start' => ymd($monday), 'name' => $name,
        'quantity' => $qty !== '' ? $qty : null, 'aisle' => $aisle,
    ]);
    shopping_back($monday);
}

function shopping_extra_delete(): void
{
    $hh = require_household();
    require_post();
    $monday = monday_of(parse_date(input_str('d')) ?? today());
    db_run('DELETE FROM shopping_extras WHERE id = ? AND household_id = ?', [input_int('id', 0), $hh['id']]);
    shopping_back($monday);
}

function shopping_reset(): void
{
    $hh = require_household();
    require_post();
    $monday = monday_of(parse_date(input_str('d')) ?? today());
    db_run('DELETE FROM shopping_checks WHERE household_id = ? AND week_start = ?', [$hh['id'], ymd($monday)]);
    db_run('UPDATE shopping_extras SET checked = 0 WHERE household_id = ? AND week_start = ?', [$hh['id'], ymd($monday)]);
    shopping_back($monday);
}

// ---------------------------------------------------------------- partage public (lecture seule)

function share_create(): void
{
    $hh = require_household();
    require_post();
    $monday = monday_of(parse_date(input_str('d')) ?? today());
    $exists = db_val('SELECT id FROM share_links WHERE household_id = ? AND week_start = ?', [$hh['id'], ymd($monday)]);
    if (!$exists) {
        db_insert('share_links', [
            'household_id' => $hh['id'], 'week_start' => ymd($monday), 'token' => new_token(), 'created_by' => current_user()['id'],
        ]);
    }
    flash('success', 'Lien de partage créé : toute personne qui le possède peut consulter (sans modifier) ce menu et sa liste de courses.');
    redirect_week($monday);
}

function share_revoke(): void
{
    $hh = require_household();
    require_post();
    $monday = monday_of(parse_date(input_str('d')) ?? today());
    db_run('DELETE FROM share_links WHERE household_id = ? AND week_start = ?', [$hh['id'], ymd($monday)]);
    flash('success', 'Lien de partage supprimé.');
    redirect_week($monday);
}

function share_view(string $token): void
{
    $s = db_one(
        'SELECT s.*, h.name AS household_name, h.default_servings FROM share_links s JOIN households h ON h.id = s.household_id WHERE s.token = ?',
        [$token]
    );
    if (!$s) {
        abort(404, "Ce lien de partage n'existe plus.");
    }
    $monday = parse_date($s['week_start']);
    $hid = (int) $s['household_id'];
    $rows = db_all(
        'SELECT m.meal_date, m.slot, m.servings, m.notes, COALESCE(r.name, m.label) AS name, r.url
           FROM meals m LEFT JOIN recipes r ON r.id = m.recipe_id
          WHERE m.household_id = ? AND m.meal_date BETWEEN ? AND ?
          ORDER BY m.meal_date, m.slot = "dinner", m.id',
        [$hid, ymd($monday), ymd($monday->modify('+6 days'))]
    );
    $byDate = [];
    foreach ($rows as $m) {
        $byDate[$m['meal_date']][] = $m;
    }
    header('X-Robots-Tag: noindex, nofollow');
    render('shopping/shared', [
        'title' => 'Menu de la semaine — ' . $s['household_name'], 's' => $s, 'monday' => $monday, 'days' => week_days($monday),
        'byDate' => $byDate, 'list' => build_shopping_list($hid, $monday, (int) $s['default_servings']),
    ], false);
}
