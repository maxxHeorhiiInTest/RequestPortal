<?php

declare(strict_types=1);

const RP_LOGIN_MAX_ATTEMPTS = 5;
const RP_LOGIN_LOCK_SECONDS = 300;

/**
 * @return array{id:int,username:string}|null
 */
function rp_admin_user(): ?array
{
    $admin = $_SESSION['rp_admin'] ?? null;
    if (!is_array($admin) || empty($admin['id'])) {
        return null;
    }

    return ['id' => (int) $admin['id'], 'username' => (string) $admin['username']];
}

function rp_require_admin(): array
{
    $admin = rp_admin_user();
    if ($admin === null) {
        rp_redirect('admin/login.php');
    }

    return $admin;
}

/** Seconds left of a login lockout, 0 when not locked. */
function rp_login_lock_remaining(): int
{
    $until = (int) ($_SESSION['rp_login_locked_until'] ?? 0);

    return max(0, $until - time());
}

/**
 * Verify credentials and start an admin session.
 */
function rp_attempt_login(string $username, string $password): bool
{
    $pdo  = rp_db();
    $stmt = $pdo->prepare('SELECT * FROM ' . RP_TABLE_ADMINS . ' WHERE username = :username');
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    $valid = $user && password_verify($password, (string) $user['password_hash']);

    if (!$valid) {
        usleep(300000);
        $_SESSION['rp_login_attempts'] = (int) ($_SESSION['rp_login_attempts'] ?? 0) + 1;
        if ($_SESSION['rp_login_attempts'] >= RP_LOGIN_MAX_ATTEMPTS) {
            $_SESSION['rp_login_locked_until'] = time() + RP_LOGIN_LOCK_SECONDS;
            $_SESSION['rp_login_attempts']     = 0;
        }

        return false;
    }

    if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
        $update = $pdo->prepare('UPDATE ' . RP_TABLE_ADMINS . ' SET password_hash = :hash WHERE id = :id');
        $update->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $user['id']]);
    }

    $pdo->prepare('UPDATE ' . RP_TABLE_ADMINS . ' SET last_login_at = NOW() WHERE id = :id')
        ->execute(['id' => $user['id']]);

    session_regenerate_id(true);
    unset($_SESSION['rp_login_attempts'], $_SESSION['rp_login_locked_until'], $_SESSION['rp_csrf']);
    $_SESSION['rp_admin'] = ['id' => (int) $user['id'], 'username' => (string) $user['username']];

    return true;
}

function rp_admin_logout(): void
{
    unset($_SESSION['rp_admin'], $_SESSION['rp_csrf']);
    session_regenerate_id(true);
}

/** True when at least one administrator account exists. */
function rp_has_admin_users(PDO $pdo): bool
{
    try {
        return (int) $pdo->query('SELECT COUNT(*) FROM ' . RP_TABLE_ADMINS)->fetchColumn() > 0;
    } catch (PDOException) {
        return false;
    }
}
