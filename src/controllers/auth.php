<?php
declare(strict_types=1);

/** Connecté : le planning. Déconnecté : la page de présentation publique (indexable). */
function home_index(): void
{
    if (current_user()) {
        redirect('/week');
    }
    render('landing', [], false);
}

function auth_login(): void
{
    if (current_user()) {
        redirect('/week');
    }
    $email = '';
    if (is_post()) {
        csrf_verify();
        $email = mb_strtolower(input_str('email'));
        $password = (string) ($_POST['password'] ?? '');
        if (login_throttled($email)) {
            flash('error', 'Trop de tentatives. Réessayez dans quelques minutes.');
        } else {
            $user = db_one('SELECT * FROM users WHERE email = ?', [$email]);
            // Vérification factice si l'utilisateur n'existe pas : même temps de réponse.
            $hash = $user['password_hash'] ?? '$2y$10$usesomesillystringforsalt.abcdefghijklmnopqrstuvwxyzABC';
            if (password_verify($password, $hash) && $user) {
                clear_login_failures($email);
                if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
                    db_run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
                }
                login_user($user);
                after_login_redirect((int) $user['id']);
            }
            record_login_failure($email);
            flash('error', 'E-mail ou mot de passe incorrect.');
        }
    }
    render('auth/login', ['title' => 'Connexion', 'email' => $email, 'invite' => pending_invite_info()]);
}

function pending_invite_info(): ?array
{
    return empty($_SESSION['pending_invite']) ? null : find_invitation($_SESSION['pending_invite']);
}

function auth_register(): void
{
    if (current_user()) {
        redirect('/week');
    }
    $invite = pending_invite_info();
    if (!$invite && !config('app.allow_registration', true)) {
        abort(403, "Les inscriptions sont fermées. Demandez un lien d'invitation à un membre d'un foyer.");
    }
    $old = ['name' => '', 'email' => '', 'household' => ''];
    if (is_post()) {
        csrf_verify();
        $old = ['name' => input_str('name'), 'email' => mb_strtolower(input_str('email')), 'household' => input_str('household')];
        $password = (string) ($_POST['password'] ?? '');
        $errors = [];
        if ($old['name'] === '' || mb_strlen($old['name']) > 100) {
            $errors[] = 'Indiquez votre prénom ou nom (100 caractères max).';
        }
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['email']) > 190) {
            $errors[] = 'Adresse e-mail invalide.';
        } elseif (db_val('SELECT id FROM users WHERE email = ?', [$old['email']])) {
            $errors[] = 'Un compte existe déjà avec cet e-mail.';
        }
        if ($e = valid_password($password)) {
            $errors[] = $e;
        }
        if ($password !== (string) ($_POST['password2'] ?? '')) {
            $errors[] = 'Les deux mots de passe ne correspondent pas.';
        }
        if (!$errors) {
            $uid = db_insert('users', [
                'email' => $old['email'], 'name' => $old['name'],
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            session_regenerate_id(true);
            $_SESSION['uid'] = $uid;
            unset($_SESSION['hid']);
            if (!$invite) {
                $name = $old['household'] !== '' ? mb_substr($old['household'], 0, 100) : 'Foyer de ' . $old['name'];
                $hid = create_household($uid, $name);
                $_SESSION['hid'] = $hid;
                db_run('UPDATE users SET last_household_id = ? WHERE id = ?', [$hid, $uid]);
            }
            flash('success', 'Compte créé. Bienvenue !');
            after_login_redirect($uid);
        }
        foreach ($errors as $e) {
            flash('error', $e);
        }
    }
    render('auth/register', ['title' => 'Inscription', 'old' => $old, 'invite' => $invite]);
}

function auth_logout(): void
{
    require_post();
    logout_user();
    session_start();
    flash('success', 'Vous êtes déconnecté.');
    redirect('/login');
}

function auth_profile(): void
{
    $user = require_login();
    if (is_post()) {
        csrf_verify();
        $action = input_str('action');
        if ($action === 'name') {
            $name = input_str('name');
            if ($name === '' || mb_strlen($name) > 100) {
                flash('error', 'Nom invalide.');
            } else {
                db_run('UPDATE users SET name = ? WHERE id = ?', [$name, $user['id']]);
                flash('success', 'Profil mis à jour.');
            }
        } elseif ($action === 'password') {
            $row = db_one('SELECT password_hash FROM users WHERE id = ?', [$user['id']]);
            $new = (string) ($_POST['new_password'] ?? '');
            if (!password_verify((string) ($_POST['current_password'] ?? ''), $row['password_hash'])) {
                flash('error', 'Mot de passe actuel incorrect.');
            } elseif ($e = valid_password($new)) {
                flash('error', $e);
            } elseif ($new !== (string) ($_POST['new_password2'] ?? '')) {
                flash('error', 'Les deux nouveaux mots de passe ne correspondent pas.');
            } else {
                db_run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $user['id']]);
                session_regenerate_id(true);
                flash('success', 'Mot de passe modifié.');
            }
        }
        redirect('/profile');
    }
    $row = db_one('SELECT name, email, created_at FROM users WHERE id = ?', [$user['id']]);
    render('auth/profile', ['title' => 'Mon profil', 'profile' => $row, 'households' => user_households((int) $user['id'])]);
}

/** Lien d'invitation : /join/{token} */
function invite_join(string $token): void
{
    $inv = find_invitation($token);
    if (!$inv) {
        abort(404, "Ce lien d'invitation n'existe pas.");
    }
    $user = current_user();
    if (is_post()) {
        csrf_verify();
        if (!$user) {
            redirect('/login');
        }
        [$ok, $msg, $hid] = accept_invitation($token, (int) $user['id']);
        flash($ok ? 'success' : 'error', $msg);
        if ($ok && $hid) {
            switch_household($hid);
            redirect('/week');
        }
        redirect('/households');
    }
    if (!$user) {
        $_SESSION['pending_invite'] = $token;
    }
    $member = $user ? household_for_user((int) $inv['household_id'], (int) $user['id']) : null;
    render('auth/join', [
        'title' => 'Invitation', 'inv' => $inv, 'state' => invitation_state($inv),
        'user' => $user, 'already' => (bool) $member,
    ]);
}
