<?php

declare(strict_types=1);

const RP_TABLE_REQUESTS = 'rp_requests';
const RP_TABLE_FILES    = 'rp_request_files';
const RP_TABLE_HISTORY  = 'rp_request_history';
const RP_TABLE_ADMINS   = 'rp_admin_users';
const RP_TABLE_CONTENT  = 'rp_content_items';

/**
 * Shared PDO connection.
 */
function rp_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        (string) rp_config('db.host', 'localhost'),
        (int) rp_config('db.port', 3306),
        (string) rp_config('db.name', ''),
        (string) rp_config('db.charset', 'utf8mb4')
    );

    try {
        $pdo = new PDO($dsn, (string) rp_config('db.user', ''), (string) rp_config('db.pass', ''), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        rp_apply_db_timezone($pdo);
        rp_ensure_schema($pdo);
    } catch (PDOException $exception) {
        if (rp_config('app.debug', false)) {
            rp_abort(500, 'Database connection failed: ' . $exception->getMessage());
        }
        error_log('[request-portal] DB connection failed: ' . $exception->getMessage());
        rp_abort(500, 'Database connection failed. Check config.php.');
    }

    return $pdo;
}

/**
 * Store DATETIME values in UTC so display can convert them to the app timezone.
 * Named zones like Europe/Kyiv need MySQL timezone tables; a numeric offset always works.
 */
function rp_apply_db_timezone(PDO $pdo): void
{
    $pdo->exec("SET time_zone = '+00:00'");
}

/**
 * DDL for every table, in creation order. Used by install.php.
 *
 * @return array<string,string> table name => CREATE TABLE statement
 */
function rp_schema(): array
{
    return [
        RP_TABLE_REQUESTS => '
            CREATE TABLE IF NOT EXISTS ' . RP_TABLE_REQUESTS . ' (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_code VARCHAR(20) NOT NULL,
                type ENUM(\'new\', \'update\') NOT NULL,
                target_location VARCHAR(1000) NOT NULL,
                description TEXT NOT NULL,
                extra_comment TEXT NULL,
                requester_name VARCHAR(160) NOT NULL,
                requester_contact VARCHAR(255) NOT NULL,
                status ENUM(\'new\', \'in_progress\', \'done\', \'rejected\') NOT NULL DEFAULT \'new\',
                admin_note TEXT NULL,
                lang CHAR(2) NOT NULL DEFAULT \'uk\',
                ip_address VARCHAR(45) NULL,
                user_agent VARCHAR(255) NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_public_code (public_code),
                KEY idx_status (status),
                KEY idx_type (type),
                KEY idx_created_at (created_at),
                KEY idx_ip_created (ip_address, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

        RP_TABLE_FILES => '
            CREATE TABLE IF NOT EXISTS ' . RP_TABLE_FILES . ' (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                request_id INT UNSIGNED NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                stored_path VARCHAR(255) NOT NULL,
                mime_type VARCHAR(120) NOT NULL,
                size_bytes BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_request (request_id),
                CONSTRAINT fk_files_request FOREIGN KEY (request_id)
                    REFERENCES ' . RP_TABLE_REQUESTS . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

        RP_TABLE_HISTORY => '
            CREATE TABLE IF NOT EXISTS ' . RP_TABLE_HISTORY . ' (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                request_id INT UNSIGNED NOT NULL,
                action VARCHAR(40) NOT NULL,
                old_value VARCHAR(255) NULL,
                new_value VARCHAR(255) NULL,
                note TEXT NULL,
                actor VARCHAR(160) NOT NULL,
                actor_ip VARCHAR(45) NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_request_created (request_id, created_at),
                CONSTRAINT fk_history_request FOREIGN KEY (request_id)
                    REFERENCES ' . RP_TABLE_REQUESTS . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

        RP_TABLE_ADMINS => '
            CREATE TABLE IF NOT EXISTS ' . RP_TABLE_ADMINS . ' (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                username VARCHAR(80) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL,
                last_login_at DATETIME NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_username (username)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

        RP_TABLE_CONTENT => '
            CREATE TABLE IF NOT EXISTS ' . RP_TABLE_CONTENT . ' (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                title VARCHAR(255) NOT NULL,
                event_at DATETIME NOT NULL,
                department VARCHAR(255) NOT NULL,
                description TEXT NOT NULL,
                responsible VARCHAR(160) NOT NULL,
                extra_info TEXT NULL,
                channels TEXT NULL,
                status ENUM(\'draft\', \'planned\', \'preparing\', \'published\', \'cancelled\') NOT NULL DEFAULT \'draft\',
                created_by VARCHAR(160) NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_event_at (event_at),
                KEY idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    ];
}

/** Create any missing tables (safe to run on every request). */
function rp_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    foreach (rp_schema() as $ddl) {
        $pdo->exec($ddl);
    }
    $done = true;
}

/**
 * Append an entry to a request's history log.
 */
function rp_log_history(
    PDO $pdo,
    int $requestId,
    string $action,
    ?string $oldValue = null,
    ?string $newValue = null,
    ?string $note = null,
    ?string $actor = null
): void {
    $stmt = $pdo->prepare(
        'INSERT INTO ' . RP_TABLE_HISTORY . '
            (request_id, action, old_value, new_value, note, actor, actor_ip, created_at)
         VALUES (:request_id, :action, :old_value, :new_value, :note, :actor, :actor_ip, NOW())'
    );

    $stmt->execute([
        'request_id' => $requestId,
        'action'     => $action,
        'old_value'  => $oldValue,
        'new_value'  => $newValue,
        'note'       => $note !== null && $note !== '' ? $note : null,
        'actor'      => $actor ?? 'system',
        'actor_ip'   => rp_client_ip(),
    ]);
}

/**
 * @return array<string,mixed>|null
 */
function rp_find_request(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM ' . RP_TABLE_REQUESTS . ' WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    return $row ?: null;
}

/**
 * @return list<array<string,mixed>>
 */
function rp_request_files(PDO $pdo, int $requestId): array
{
    $stmt = $pdo->prepare(
        'SELECT * FROM ' . RP_TABLE_FILES . ' WHERE request_id = :id ORDER BY id'
    );
    $stmt->execute(['id' => $requestId]);

    return $stmt->fetchAll();
}

/**
 * @return list<array<string,mixed>>
 */
function rp_request_history(PDO $pdo, int $requestId): array
{
    $stmt = $pdo->prepare(
        'SELECT * FROM ' . RP_TABLE_HISTORY . ' WHERE request_id = :id ORDER BY created_at DESC, id DESC'
    );
    $stmt->execute(['id' => $requestId]);

    return $stmt->fetchAll();
}

/** Number of requests submitted from an IP within the last hour. */
function rp_recent_request_count(PDO $pdo, string $ip): int
{
    if ($ip === '') {
        return 0;
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM ' . RP_TABLE_REQUESTS . '
         WHERE ip_address = :ip AND created_at >= (NOW() - INTERVAL 1 HOUR)'
    );
    $stmt->execute(['ip' => $ip]);

    return (int) $stmt->fetchColumn();
}

/** True when the schema has been installed. */
function rp_tables_exist(PDO $pdo): bool
{
    try {
        $pdo->query('SELECT 1 FROM ' . RP_TABLE_REQUESTS . ' LIMIT 1');

        return true;
    } catch (PDOException) {
        return false;
    }
}
