<?php
/**
 * CLI helper to create an administrator or reset a password, for when
 * install.php has already been deleted.
 *
 *   php tools/admin_user.php <username> <password> [full|it]
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script runs from the command line only.\n");
}

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/auth.php';

$username = $argv[1] ?? '';
$password = $argv[2] ?? '';
$role     = $argv[3] ?? '';

if (!preg_match('/^[A-Za-z0-9._-]{3,80}$/', $username) || strlen($password) < 10) {
    fwrite(STDERR, "Usage: php tools/admin_user.php <username> <password> [full|it]\n"
        . "  username: 3-80 chars of A-Z a-z 0-9 . _ -\n"
        . "  password: at least 10 characters\n"
        . "  role:     full (default) or it\n");
    exit(1);
}

if ($role !== '' && !in_array($role, rp_admin_roles(), true)) {
    fwrite(STDERR, "Unknown role «{$role}». Use full or it.\n");
    exit(1);
}

$pdo = rp_db();

if (!rp_tables_exist($pdo)) {
    foreach (rp_schema() as $ddl) {
        $pdo->exec($ddl);
    }
    echo "Tables created.\n";
}

if ($role !== '') {
    $stmt = $pdo->prepare(
        'INSERT INTO ' . RP_TABLE_ADMINS . ' (username, password_hash, role, created_at)
         VALUES (:username, :hash, :role, NOW())
         ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), role = VALUES(role)'
    );
    $stmt->execute([
        'username' => $username,
        'hash'     => password_hash($password, PASSWORD_DEFAULT),
        'role'     => $role,
    ]);
} else {
    $stmt = $pdo->prepare(
        'INSERT INTO ' . RP_TABLE_ADMINS . ' (username, password_hash, created_at)
         VALUES (:username, :hash, NOW())
         ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)'
    );
    $stmt->execute([
        'username' => $username,
        'hash'     => password_hash($password, PASSWORD_DEFAULT),
    ]);
}

$shownRole = $role !== '' ? $role : 'full';
echo "Administrator '{$username}' is ready (role: {$shownRole}).\n";
