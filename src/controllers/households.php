<?php
declare(strict_types=1);

function hh_index(): void
{
    $user = require_login();
    render('households/index', [
        'title' => 'Mes foyers',
        'households' => user_households((int) $user['id']),
        'current' => current_household(),
    ]);
}

function hh_create(): void
{
    $user = require_login();
    require_post();
    $name = input_str('name');
    if ($name === '' || mb_strlen($name) > 100) {
        flash('error', 'Donnez un nom au foyer (100 caractères max).');
        redirect('/households');
    }
    $hid = create_household((int) $user['id'], $name);
    switch_household($hid);
    flash('success', 'Foyer créé. Invitez les autres membres depuis cette page.');
    redirect('/household');
}

function hh_switch(): void
{
    require_login();
    require_post();
    if (!switch_household((int) input_int('id', 0))) {
        abort(403);
    }
    redirect('/week');
}

function hh_settings(): void
{
    $hh = require_household();
    $hid = (int) $hh['id'];
    $members = db_all(
        'SELECT u.id, u.name, u.email, hm.role, hm.joined_at FROM household_members hm
           JOIN users u ON u.id = hm.user_id WHERE hm.household_id = ? ORDER BY hm.role = "owner" DESC, u.name',
        [$hid]
    );
    $invites = [];
    if ($hh['role'] === 'owner') {
        foreach (db_all('SELECT * FROM invitations WHERE household_id = ? AND revoked_at IS NULL ORDER BY id DESC LIMIT 20', [$hid]) as $inv) {
            $inv['state'] = invitation_state($inv);
            $inv['link'] = abs_url('/join/' . $inv['token']);
            $invites[] = $inv;
        }
    }
    render('households/settings', [
        'title' => 'Foyer ' . $hh['name'], 'hh' => $hh, 'members' => $members, 'invites' => $invites,
        'owners' => count(array_filter($members, fn($m) => $m['role'] === 'owner')),
    ]);
}

function hh_update(): void
{
    $hh = require_owner();
    require_post();
    $name = input_str('name');
    $servings = input_int('default_servings', 4);
    if ($name === '' || mb_strlen($name) > 100 || $servings < 1 || $servings > 30) {
        flash('error', 'Nom ou nombre de portions invalide.');
    } else {
        db_run('UPDATE households SET name = ?, default_servings = ? WHERE id = ?', [$name, $servings, $hh['id']]);
        flash('success', 'Foyer mis à jour.');
    }
    redirect('/household');
}

function hh_invite(): void
{
    $hh = require_owner();
    require_post();
    $days = input_int('days', 7);
    $days = in_array($days, [1, 7, 30, 0], true) ? $days : 7;
    db_insert('invitations', [
        'household_id' => $hh['id'],
        'token'        => new_token(),
        'created_by'   => current_user()['id'],
        'expires_at'   => $days > 0 ? date('Y-m-d H:i:s', strtotime("+$days days")) : null,
        'max_uses'     => input_str('single_use') === '1' ? 1 : null,
    ]);
    flash('success', "Lien d'invitation créé. Copiez-le et envoyez-le à la personne à inviter.");
    redirect('/household');
}

function hh_invite_revoke(): void
{
    $hh = require_owner();
    require_post();
    db_run('UPDATE invitations SET revoked_at = NOW() WHERE id = ? AND household_id = ?', [input_int('id', 0), $hh['id']]);
    flash('success', 'Invitation désactivée.');
    redirect('/household');
}

function owners_count(int $householdId): int
{
    return (int) db_val('SELECT COUNT(*) FROM household_members WHERE household_id = ? AND role = "owner"', [$householdId]);
}

function hh_member_remove(): void
{
    $hh = require_owner();
    require_post();
    $uid = (int) input_int('user_id', 0);
    $target = db_one('SELECT role FROM household_members WHERE household_id = ? AND user_id = ?', [$hh['id'], $uid]);
    if (!$target || $uid === (int) current_user()['id']) {
        flash('error', 'Membre introuvable (utilisez « Quitter le foyer » pour vous retirer).');
    } else {
        db_run('DELETE FROM household_members WHERE household_id = ? AND user_id = ?', [$hh['id'], $uid]);
        flash('success', 'Membre retiré du foyer.');
    }
    redirect('/household');
}

function hh_member_role(): void
{
    $hh = require_owner();
    require_post();
    $uid = (int) input_int('user_id', 0);
    $role = input_str('role') === 'owner' ? 'owner' : 'member';
    $target = db_one('SELECT role FROM household_members WHERE household_id = ? AND user_id = ?', [$hh['id'], $uid]);
    if (!$target) {
        flash('error', 'Membre introuvable.');
    } elseif ($role === 'member' && $target['role'] === 'owner' && owners_count((int) $hh['id']) <= 1) {
        flash('error', 'Il doit rester au moins un administrateur.');
    } else {
        db_run('UPDATE household_members SET role = ? WHERE household_id = ? AND user_id = ?', [$role, $hh['id'], $uid]);
        flash('success', 'Rôle mis à jour.');
    }
    redirect('/household');
}

function hh_leave(): void
{
    $hh = require_household();
    require_post();
    $uid = (int) current_user()['id'];
    $hid = (int) $hh['id'];
    $others = (int) db_val('SELECT COUNT(*) FROM household_members WHERE household_id = ? AND user_id <> ?', [$hid, $uid]);
    if ($others === 0) {
        flash('error', 'Vous êtes le seul membre : supprimez le foyer plutôt que de le quitter.');
        redirect('/household');
    }
    if ($hh['role'] === 'owner' && owners_count($hid) <= 1) {
        flash('error', 'Nommez un autre administrateur avant de quitter le foyer.');
        redirect('/household');
    }
    db_run('DELETE FROM household_members WHERE household_id = ? AND user_id = ?', [$hid, $uid]);
    unset($_SESSION['hid']);
    flash('success', 'Vous avez quitté le foyer.');
    redirect('/households');
}

function hh_delete(): void
{
    $hh = require_owner();
    require_post();
    if (mb_strtolower(input_str('confirm_name')) !== mb_strtolower($hh['name'])) {
        flash('error', 'Pour supprimer le foyer, saisissez son nom exact.');
        redirect('/household');
    }
    db_run('DELETE FROM households WHERE id = ?', [$hh['id']]);
    unset($_SESSION['hid']);
    flash('success', 'Foyer supprimé.');
    redirect('/households');
}
