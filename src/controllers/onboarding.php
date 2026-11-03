<?php
declare(strict_types=1);

/** Étapes que l'utilisateur marque lui-même (le reste se déduit des données du foyer). */
const ONBOARDING_USER_STEPS = ['shopping', 'install'];

function onboarding_mark(int $userId, string $step): void
{
    db_run('INSERT IGNORE INTO onboarding_progress (user_id, step) VALUES (?, ?)', [$userId, $step]);
}

/**
 * Carte « Premiers pas » : null si l'utilisateur l'a masquée.
 * Les étapes se cochent toutes seules : recettes, ingrédients, menu et invitations viennent de la base ;
 * « liste de courses » et « installation » sont enregistrées quand la page est ouverte / l'appli lancée en plein écran.
 */
function onboarding_card(array $hh, array $user): ?array
{
    $uid = (int) $user['id'];
    $hid = (int) $hh['id'];
    $marked = array_column(db_all('SELECT step FROM onboarding_progress WHERE user_id = ?', [$uid]), 'step');
    if (in_array('dismissed', $marked, true)) {
        return null;
    }
    $c = db_one(
        'SELECT (SELECT COUNT(*) FROM recipes WHERE household_id = ?) AS recipes,
                (SELECT COUNT(*) FROM recipe_ingredients i JOIN recipes r ON r.id = i.recipe_id WHERE r.household_id = ?) AS ingredients,
                (SELECT COUNT(*) FROM meals WHERE household_id = ?) AS meals,
                (SELECT COUNT(*) FROM household_members WHERE household_id = ?) AS members,
                (SELECT COUNT(*) FROM invitations WHERE household_id = ?) AS invites',
        [$hid, $hid, $hid, $hid, $hid]
    );
    $noIngredients = db_val(
        'SELECT r.id FROM recipes r WHERE r.household_id = ? AND NOT EXISTS (SELECT 1 FROM recipe_ingredients i WHERE i.recipe_id = r.id) ORDER BY r.id LIMIT 1',
        [$hid]
    );

    $steps = [
        ['key' => 'recipes', 'icon' => 'book', 'title' => 'Ajoutez vos recettes',
         'text' => 'Collez une liste de plats ou créez votre première recette : elles sont partagées avec tout le foyer.',
         'cta' => 'Ajouter des recettes', 'url' => url('/recipes'), 'done' => (int) $c['recipes'] > 0],
        ['key' => 'ingredients', 'icon' => 'jar', 'title' => "Saisissez les ingrédients d'une recette",
         'text' => "C'est ce qui remplit la liste de courses. Un ingrédient par ligne, par exemple « 200 g pâtes ».",
         'cta' => 'Ajouter des ingrédients', 'url' => $noIngredients ? url('/recipes/' . (int) $noIngredients . '/edit') : url('/recipes'),
         'done' => (int) $c['ingredients'] > 0],
        ['key' => 'menu', 'icon' => 'week', 'title' => 'Planifiez votre première semaine',
         'text' => 'Générez un menu en un clic, ou ajoutez vos repas jour par jour avec le champ « Ajouter un repas ».',
         'cta' => 'Planifier la semaine', 'url' => '#generer', 'done' => (int) $c['meals'] > 0],
        ['key' => 'shopping', 'icon' => 'basket', 'title' => 'Ouvrez la liste de courses',
         'text' => 'Elle se remplit toute seule à partir du menu. En magasin, cochez les articles au fil des rayons.',
         'cta' => 'Voir la liste de courses', 'url' => url('/shopping'), 'done' => in_array('shopping', $marked, true)],
    ];
    if ($hh['role'] === 'owner') {
        $steps[] = ['key' => 'invite', 'icon' => 'home', 'title' => 'Invitez votre foyer',
                    'text' => 'Créez un lien d\'invitation : chacun partage les recettes, le menu et les courses.',
                    'cta' => 'Inviter quelqu\'un', 'url' => url('/household'), 'done' => (int) $c['members'] > 1 || (int) $c['invites'] > 0];
    }
    $steps[] = ['key' => 'install', 'icon' => 'plus', 'title' => "Installez l'application",
                'text' => 'Icône sur l\'écran d\'accueil, plein écran et dernières pages disponibles sans réseau.',
                'cta' => 'Installer l\'application', 'url' => url('/profile') . '#application', 'done' => in_array('install', $marked, true)];

    $done = count(array_filter($steps, fn($s) => $s['done']));
    $next = null;
    foreach ($steps as $s) {
        if (!$s['done']) {
            $next = $s;
            break;
        }
    }
    return ['steps' => $steps, 'done' => $done, 'total' => count($steps), 'next' => $next, 'complete' => $next === null, 'fresh' => $done === 0];
}

function onboarding_dismiss(): void
{
    $user = require_login();
    require_post();
    onboarding_mark((int) $user['id'], 'dismissed');
    redirect('/week');
}

/** Affiche de nouveau la carte (depuis le profil). */
function onboarding_reset(): void
{
    $user = require_login();
    require_post();
    db_run('DELETE FROM onboarding_progress WHERE user_id = ? AND step = "dismissed"', [$user['id']]);
    flash('success', 'Les premiers pas sont de retour sur la page Semaine.');
    redirect('/week');
}

/** Appelé par l'appli quand elle détecte qu'elle tourne installée (plein écran). */
function onboarding_seen(): void
{
    $user = require_login();
    require_post();
    $step = input_str('step');
    if (in_array($step, ONBOARDING_USER_STEPS, true)) {
        onboarding_mark((int) $user['id'], $step);
    }
    if (wants_json()) {
        json_response(['ok' => true]);
    }
    redirect('/week');
}
