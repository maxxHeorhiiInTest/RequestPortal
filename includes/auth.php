<?php

declare(strict_types=1);

const RP_LOGIN_MAX_ATTEMPTS = 5;
const RP_LOGIN_LOCK_SECONDS = 300;

/** @return list<string> */
function rp_admin_roles(): array
{
    return ['full', 'it', 'rental', 'rental_manager', 'rental_schedule'];
}

/** @return list<string> */
function rp_rental_roles(): array
{
    return ['rental', 'rental_manager', 'rental_schedule'];
}

function rp_is_rental_role(?string $role): bool
{
    return $role !== null && in_array($role, rp_rental_roles(), true);
}

/**
 * @return array{id:int,username:string,role:string}|null
 */
function rp_admin_user(): ?array
{
    $admin = $_SESSION['rp_admin'] ?? null;
    if (!is_array($admin) || empty($admin['id'])) {
        return null;
    }

    $id       = (int) $admin['id'];
    $username = (string) ($admin['username'] ?? '');
    $role     = 'full';

    try {
        $stmt = rp_db()->prepare(
            'SELECT username, role FROM ' . RP_TABLE_ADMINS . ' WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            unset($_SESSION['rp_admin']);

            return null;
        }
        $username = (string) $row['username'];
        $role     = (string) ($row['role'] ?? 'full');
    } catch (Throwable) {
        $role = (string) ($admin['role'] ?? 'full');
    }

    if (!in_array($role, rp_admin_roles(), true)) {
        $role = 'full';
    }

    $_SESSION['rp_admin']['id']       = $id;
    $_SESSION['rp_admin']['username'] = $username;
    $_SESSION['rp_admin']['role']     = $role;

    return [
        'id'       => $id,
        'username' => $username,
        'role'     => $role,
    ];
}

function rp_admin_can(string $section): bool
{
    $admin = rp_admin_user();
    if ($admin === null) {
        return false;
    }
    $role = (string) ($admin['role'] ?? '');
    if (rp_is_rental_role($role)) {
        return $section === 'rental';
    }
    if ($role === 'full') {
        return $section !== 'rental';
    }

    return $role === 'it' && $section === 'it';
}

function rp_admin_is_full(?array $admin = null): bool
{
    $admin ??= rp_admin_user();

    return $admin !== null && ($admin['role'] ?? '') === 'full';
}

/** @return array{id:int,username:string,role:string} */
function rp_require_full_admin(): array
{
    $admin = rp_require_admin();
    if (!rp_admin_is_full($admin)) {
        rp_flash('error', __('admin.trash.forbidden'));
        rp_redirect(rp_admin_home());
    }

    return $admin;
}

function rp_admin_home(): string
{
    if (rp_admin_can('rental') && !rp_admin_can('requests')) {
        return 'admin/rental.php';
    }

    return rp_admin_can('requests') ? 'admin/index.php' : 'admin/it-board.php';
}

/** Internal admin path from a login ?next= value, or null if it is not safe. */
function rp_admin_safe_next(mixed $raw): ?string
{
    if (!is_string($raw) || $raw === '') {
        return null;
    }
    $raw = trim($raw);
    if ($raw === '' || str_contains($raw, "\0") || str_contains($raw, '://') || str_starts_with($raw, '//')) {
        return null;
    }
    $raw = ltrim($raw, '/');
    if (!preg_match('#^admin/[a-z0-9._-]+\.php(?:\?[a-zA-Z0-9&=._-]+)?$#', $raw)) {
        return null;
    }

    return $raw;
}

function rp_admin_script_section(string $script): ?string
{
    if (in_array($script, ['login.php', 'logout.php', 'inbox-poll.php'], true)) {
        return null;
    }
    if (in_array($script, ['trash.php', 'soft-delete.php', 'soft-restore.php'], true)) {
        return 'full';
    }
    if (in_array($script, ['it.php', 'it-view.php', 'it-board.php', 'it-move.php', 'cartridge.php', 'cartridge-view.php', 'cartridge-print.php'], true)) {
        return 'it';
    }
    if (in_array($script, ['index.php', 'view.php', 'board.php', 'board-move.php', 'download.php'], true)) {
        return 'requests';
    }
    if (in_array($script, [
        'plan.php',
        'plan-edit.php',
        'plan-inbox.php',
        'plan-delete.php',
        'plan-download.php',
        'plan-holiday-hide.php',
        'plan-holiday-restore.php',
    ], true)) {
        return 'plan';
    }
    if (in_array($script, [
        'feedback.php',
        'feedback-view.php',
        'feedback-board.php',
        'feedback-move.php',
        'feedback-download.php',
    ], true)) {
        return 'feedback';
    }
    if ($script === 'stats.php') {
        return 'stats';
    }
    if ($script === 'rental.php' || str_starts_with($script, 'rental-')) {
        return 'rental';
    }

    return 'full';
}

function rp_require_admin(?string $section = null): array
{
    $admin = rp_admin_user();
    if ($admin === null) {
        $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $query  = (string) ($_SERVER['QUERY_STRING'] ?? '');
        $next   = $script !== '' ? 'admin/' . $script . ($query !== '' ? '?' . $query : '') : '';
        $safe   = rp_admin_safe_next($next);
        rp_redirect($safe !== null ? 'admin/login.php?next=' . rawurlencode($safe) : 'admin/login.php');
    }

    if ($section === null) {
        $script  = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $section = rp_admin_script_section($script);
    }
    if ($section !== null && $section !== '' && !rp_admin_can($section)) {
        rp_redirect(rp_admin_home());
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

    $role = (string) ($user['role'] ?? 'full');
    if (!in_array($role, rp_admin_roles(), true)) {
        $role = 'full';
    }

    session_regenerate_id(true);
    unset($_SESSION['rp_login_attempts'], $_SESSION['rp_login_locked_until'], $_SESSION['rp_csrf']);
    $_SESSION['rp_admin'] = [
        'id'       => (int) $user['id'],
        'username' => (string) $user['username'],
        'role'     => $role,
    ];

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
