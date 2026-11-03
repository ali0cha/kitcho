<?php
declare(strict_types=1);

// ---------------------------------------------------------------- utilisateur

function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['uid'])) {
            $user = db_one('SELECT id, email, name, last_household_id FROM users WHERE id = ?', [$_SESSION['uid']]);
            if (!$user) {
                unset($_SESSION['uid']);
            }
        }
    }
    return $user;
}

/** Adresses qui renvoient des données (JSON, fichiers) : jamais une destination valable après connexion. */
function is_data_path(string $uri): bool
{
    $path = (string) parse_url($uri, PHP_URL_PATH);
    return (bool) preg_match('#/(shopping/state|recipes/export)$#', $path);
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        // Requête de fond (fetch) : on répond « non connecté » au lieu de rediriger,
        // et on ne retient surtout pas cette adresse comme page de retour.
        if (wants_json()) {
            json_response(['error' => 'auth'], 401);
        }
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $_SESSION['next'] = ($_SERVER['REQUEST_METHOD'] === 'GET' && !is_data_path($uri)) ? $uri : '';
        redirect('/login');
    }
    return $u;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $user['id'];
    unset($_SESSION['hid']);
    db_run('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function valid_password(string $p): ?string
{
    if (mb_strlen($p) < 8) {
        return 'Le mot de passe doit contenir au moins 8 caractères.';
    }
    return null;
}

// ---------------------------------------------------------------- anti brute-force

function client_ip(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
}

function login_throttled(string $email): bool
{
    $n = (int) db_val(
        'SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND email = ? AND attempted_at > (NOW() - INTERVAL 15 MINUTE)',
        [client_ip(), $email]
    );
    return $n >= 5;
}

function record_login_failure(string $email): void
{
    db_insert('login_attempts', ['ip' => client_ip(), 'email' => $email]);
    db_run('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
}

function clear_login_failures(string $email): void
{
    db_run('DELETE FROM login_attempts WHERE ip = ? AND email = ?', [client_ip(), $email]);
}

// ---------------------------------------------------------------- foyers

function user_households(int $userId): array
{
    return db_all(
        'SELECT h.id, h.name, hm.role,
                (SELECT COUNT(*) FROM household_members x WHERE x.household_id = h.id) AS members
           FROM households h JOIN household_members hm ON hm.household_id = h.id
          WHERE hm.user_id = ? ORDER BY h.name',
        [$userId]
    );
}

/** Foyer actif de l'utilisateur (vérifié à chaque requête), avec son rôle. */
function current_household(): ?array
{
    static $cache = false;
    $user = current_user();
    if (!$user) {
        return null;
    }
    if ($cache !== false) {
        return $cache;
    }
    $candidates = array_filter([$_SESSION['hid'] ?? null, $user['last_household_id']]);
    $hh = null;
    foreach ($candidates as $id) {
        $hh = household_for_user((int) $id, (int) $user['id']);
        if ($hh) {
            break;
        }
    }
    if (!$hh) {
        $hh = db_one(
            'SELECT h.*, hm.role FROM households h JOIN household_members hm ON hm.household_id = h.id
              WHERE hm.user_id = ? ORDER BY hm.joined_at LIMIT 1',
            [$user['id']]
        );
    }
    if ($hh) {
        $_SESSION['hid'] = (int) $hh['id'];
    }
    return $cache = $hh;
}

function household_for_user(int $householdId, int $userId): ?array
{
    return db_one(
        'SELECT h.*, hm.role FROM households h JOIN household_members hm ON hm.household_id = h.id
          WHERE h.id = ? AND hm.user_id = ?',
        [$householdId, $userId]
    );
}

function switch_household(int $householdId): bool
{
    $user = current_user();
    if (!$user || !household_for_user($householdId, (int) $user['id'])) {
        return false;
    }
    $_SESSION['hid'] = $householdId;
    db_run('UPDATE users SET last_household_id = ? WHERE id = ?', [$householdId, $user['id']]);
    return true;
}

/** Utilisateur connecté + foyer actif, sinon redirections. */
function require_household(): array
{
    require_login();
    $hh = current_household();
    if (!$hh) {
        redirect('/households');
    }
    return $hh;
}

function require_owner(): array
{
    $hh = require_household();
    if ($hh['role'] !== 'owner') {
        abort(403, 'Réservé aux administrateurs du foyer.');
    }
    return $hh;
}

function create_household(int $userId, string $name, int $servings = 4): int
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $id = db_insert('households', ['name' => $name, 'default_servings' => $servings, 'created_by' => $userId]);
        db_insert('household_members', ['household_id' => $id, 'user_id' => $userId, 'role' => 'owner']);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return $id;
}

// ---------------------------------------------------------------- invitations

function invitation_state(array $inv): string
{
    if ($inv['revoked_at'] !== null) {
        return 'revoked';
    }
    if ($inv['expires_at'] !== null && strtotime($inv['expires_at']) < time()) {
        return 'expired';
    }
    if ($inv['max_uses'] !== null && (int) $inv['uses'] >= (int) $inv['max_uses']) {
        return 'used';
    }
    return 'active';
}

function find_invitation(string $token): ?array
{
    if (!preg_match('/^[0-9a-f]{40}$/', $token)) {
        return null;
    }
    return db_one(
        'SELECT i.*, h.name AS household_name,
                (SELECT COUNT(*) FROM household_members m WHERE m.household_id = h.id) AS members
           FROM invitations i JOIN households h ON h.id = i.household_id WHERE i.token = ?',
        [$token]
    );
}

/** @return array{0: bool, 1: string, 2: ?int} [succès, message, id du foyer] */
function accept_invitation(string $token, int $userId): array
{
    $inv = find_invitation($token);
    if (!$inv) {
        return [false, "Ce lien d'invitation n'existe pas.", null];
    }
    $hid = (int) $inv['household_id'];
    if (household_for_user($hid, $userId)) {
        return [true, 'Vous faites déjà partie de ce foyer.', $hid];
    }
    $state = invitation_state($inv);
    if ($state !== 'active') {
        $msg = ['revoked' => 'Ce lien a été désactivé.', 'expired' => 'Ce lien a expiré.', 'used' => "Ce lien a déjà été utilisé."][$state];
        return [false, $msg, null];
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Verrou sur la ligne pour respecter max_uses en cas de clics simultanés.
        $fresh = db_one('SELECT * FROM invitations WHERE id = ? FOR UPDATE', [$inv['id']]);
        if (!$fresh || invitation_state($fresh) !== 'active') {
            $pdo->rollBack();
            return [false, "Ce lien n'est plus valide.", null];
        }
        db_insert('household_members', ['household_id' => $hid, 'user_id' => $userId, 'role' => 'member']);
        db_run('UPDATE invitations SET uses = uses + 1 WHERE id = ?', [$inv['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return [true, 'Bienvenue dans le foyer « ' . $inv['household_name'] . ' » !', $hid];
}

/** Après connexion / inscription : consomme une invitation en attente, puis choisit où aller. */
function after_login_redirect(int $userId): never
{
    if (!empty($_SESSION['pending_invite'])) {
        [$ok, $msg, $hid] = accept_invitation($_SESSION['pending_invite'], $userId);
        unset($_SESSION['pending_invite']);
        flash($ok ? 'success' : 'error', $msg);
        if ($ok && $hid) {
            $_SESSION['hid'] = $hid;
            db_run('UPDATE users SET last_household_id = ? WHERE id = ?', [$hid, $userId]);
        }
    }
    $next = $_SESSION['next'] ?? '';
    unset($_SESSION['next']);
    // N'autorise qu'un chemin local, jamais une URL externe.
    if (is_string($next) && $next !== '' && $next[0] === '/' && !str_starts_with($next, '//') && !str_contains($next, "\n") && !is_data_path($next)) {
        header('Location: ' . $next);
        exit;
    }
    redirect('/week');
}
