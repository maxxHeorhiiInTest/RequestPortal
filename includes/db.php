<?php

declare(strict_types=1);

const RP_TABLE_REQUESTS = 'rp_requests';
const RP_TABLE_FILES    = 'rp_request_files';
const RP_TABLE_HISTORY  = 'rp_request_history';
const RP_TABLE_ADMINS   = 'rp_admin_users';
const RP_TABLE_CONTENT  = 'rp_content_items';
const RP_TABLE_FEEDBACK = 'rp_feedback';
const RP_TABLE_FEEDBACK_FILES = 'rp_feedback_files';
const RP_TABLE_FEEDBACK_HISTORY = 'rp_feedback_history';
const RP_TABLE_VISITS = 'rp_page_visits';
const RP_TABLE_HIDDEN_HOLIDAYS = 'rp_hidden_holidays';
const RP_VISIT_DEDUP_MINUTES = 30;
const RP_VISIT_RETENTION_DAYS = 90;

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

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    // Managed MySQL (HolderPOS) requires TLS; their cert does not chain to OS CAs.
    if (rp_config('db.ssl', false)) {
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = (bool) rp_config('db.ssl_verify', false);
        $options[PDO::MYSQL_ATTR_SSL_CIPHER] = (string) rp_config('db.ssl_cipher', 'DEFAULT');
        $ca = rp_config('db.ssl_ca', '');
        if (is_string($ca) && $ca !== '') {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $ca;
        }
    }

    try {
        $pdo = new PDO($dsn, (string) rp_config('db.user', ''), (string) rp_config('db.pass', ''), $options);
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
                faculty VARCHAR(255) NOT NULL DEFAULT \'\',
                department VARCHAR(255) NOT NULL DEFAULT \'\',
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

        RP_TABLE_FEEDBACK => '
            CREATE TABLE IF NOT EXISTS ' . RP_TABLE_FEEDBACK . ' (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_code VARCHAR(20) NOT NULL,
                message TEXT NOT NULL,
                faculty VARCHAR(255) NOT NULL DEFAULT \'\',
                department VARCHAR(255) NOT NULL DEFAULT \'\',
                requester_name VARCHAR(160) NOT NULL,
                requester_phone VARCHAR(80) NOT NULL DEFAULT \'\',
                requester_contact VARCHAR(255) NOT NULL,
                requester_channel VARCHAR(20) NOT NULL DEFAULT \'\',
                status ENUM(\'new\', \'in_progress\', \'done\', \'rejected\') NOT NULL DEFAULT \'new\',
                admin_note TEXT NULL,
                lang CHAR(2) NOT NULL DEFAULT \'uk\',
                ip_address VARCHAR(45) NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_feedback_code (public_code),
                KEY idx_status (status),
                KEY idx_created_at (created_at),
                KEY idx_ip_created (ip_address, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

        RP_TABLE_FEEDBACK_FILES => '
            CREATE TABLE IF NOT EXISTS ' . RP_TABLE_FEEDBACK_FILES . ' (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                feedback_id INT UNSIGNED NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                stored_path VARCHAR(255) NOT NULL,
                mime_type VARCHAR(120) NOT NULL,
                size_bytes BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_feedback (feedback_id),
                CONSTRAINT fk_feedback_files FOREIGN KEY (feedback_id)
                    REFERENCES ' . RP_TABLE_FEEDBACK . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

        RP_TABLE_FEEDBACK_HISTORY => '
            CREATE TABLE IF NOT EXISTS ' . RP_TABLE_FEEDBACK_HISTORY . ' (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                feedback_id INT UNSIGNED NOT NULL,
                action VARCHAR(40) NOT NULL,
                old_value VARCHAR(255) NULL,
                new_value VARCHAR(255) NULL,
                note TEXT NULL,
                actor VARCHAR(160) NOT NULL,
                actor_ip VARCHAR(45) NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_feedback_created (feedback_id, created_at),
                CONSTRAINT fk_feedback_history FOREIGN KEY (feedback_id)
                    REFERENCES ' . RP_TABLE_FEEDBACK . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

        RP_TABLE_VISITS => '
            CREATE TABLE IF NOT EXISTS ' . RP_TABLE_VISITS . ' (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                path VARCHAR(255) NOT NULL,
                ip_address VARCHAR(45) NULL,
                session_id VARCHAR(128) NULL,
                user_agent VARCHAR(255) NULL,
                lang CHAR(2) NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_created (created_at),
                KEY idx_path_created (path, created_at),
                KEY idx_ip_created (ip_address, created_at),
                KEY idx_session_path_created (session_id, path, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

        RP_TABLE_HIDDEN_HOLIDAYS => '
            CREATE TABLE IF NOT EXISTS ' . RP_TABLE_HIDDEN_HOLIDAYS . ' (
                holiday_key VARCHAR(80) NOT NULL,
                hidden_by VARCHAR(160) NOT NULL,
                hidden_at DATETIME NOT NULL,
                PRIMARY KEY (holiday_key)
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
    rp_ensure_columns($pdo);
    $done = true;
}

function rp_table_has_column(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->query('SHOW COLUMNS FROM `' . $table . '` LIKE ' . $pdo->quote($column));

    return (bool) $stmt->fetch();
}

/** Add columns introduced after the initial install. */
function rp_ensure_columns(PDO $pdo): void
{
    $columns = [
        RP_TABLE_REQUESTS => [
            'faculty'    => "VARCHAR(255) NOT NULL DEFAULT ''",
            'department' => "VARCHAR(255) NOT NULL DEFAULT ''",
        ],
        RP_TABLE_FEEDBACK => [
            'faculty'          => "VARCHAR(255) NOT NULL DEFAULT ''",
            'department'       => "VARCHAR(255) NOT NULL DEFAULT ''",
            'requester_phone'   => "VARCHAR(80) NOT NULL DEFAULT ''",
            'requester_channel' => "VARCHAR(20) NOT NULL DEFAULT ''",
        ],
    ];

    foreach ($columns as $table => $defs) {
        foreach ($defs as $name => $ddl) {
            if (!rp_table_has_column($pdo, $table, $name)) {
                $pdo->exec('ALTER TABLE `' . $table . '` ADD COLUMN `' . $name . '` ' . $ddl);
            }
        }
    }
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

function rp_log_feedback_history(
    PDO $pdo,
    int $feedbackId,
    string $action,
    ?string $oldValue = null,
    ?string $newValue = null,
    ?string $note = null,
    ?string $actor = null
): void {
    $stmt = $pdo->prepare(
        'INSERT INTO ' . RP_TABLE_FEEDBACK_HISTORY . '
            (feedback_id, action, old_value, new_value, note, actor, actor_ip, created_at)
         VALUES (:feedback_id, :action, :old_value, :new_value, :note, :actor, :actor_ip, NOW())'
    );
    $stmt->execute([
        'feedback_id' => $feedbackId,
        'action'      => $action,
        'old_value'   => $oldValue,
        'new_value'   => $newValue,
        'note'        => $note !== null && $note !== '' ? $note : null,
        'actor'       => $actor ?? 'system',
        'actor_ip'    => rp_client_ip(),
    ]);
}

/** @return array<string,mixed>|null */
function rp_find_feedback(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM ' . RP_TABLE_FEEDBACK . ' WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    return $row ?: null;
}

/** @return list<array<string,mixed>> */
function rp_feedback_files(PDO $pdo, int $feedbackId): array
{
    $stmt = $pdo->prepare(
        'SELECT * FROM ' . RP_TABLE_FEEDBACK_FILES . ' WHERE feedback_id = :id ORDER BY id'
    );
    $stmt->execute(['id' => $feedbackId]);

    return $stmt->fetchAll();
}

/** @return list<array<string,mixed>> */
function rp_feedback_history(PDO $pdo, int $feedbackId): array
{
    $stmt = $pdo->prepare(
        'SELECT * FROM ' . RP_TABLE_FEEDBACK_HISTORY . '
         WHERE feedback_id = :id ORDER BY created_at DESC, id DESC'
    );
    $stmt->execute(['id' => $feedbackId]);

    return $stmt->fetchAll();
}

function rp_recent_feedback_count(PDO $pdo, string $ip): int
{
    if ($ip === '') {
        return 0;
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM ' . RP_TABLE_FEEDBACK . '
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

/** @return list<string> */
function rp_public_visit_scripts(): array
{
    return ['index.php', 'addData.php', 'planAdd.php', 'feedback.php'];
}

function rp_visit_page_label(string $path): string
{
    $labels = [
        'index.php'    => __('admin.stats.page.home'),
        'addData.php'  => __('admin.stats.page.request'),
        'planAdd.php'  => __('admin.stats.page.plan'),
        'feedback.php' => __('admin.stats.page.feedback'),
    ];

    return $labels[$path] ?? $path;
}

/** Record a public page view. Failures are logged and never break the page. */
function rp_track_visit(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
        return;
    }
    if (!empty($_SESSION['rp_admin']['id'])) {
        return;
    }

    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (preg_match('#/(admin|tools)/#', $scriptName)) {
        return;
    }

    $path = basename($scriptName);
    if (!in_array($path, rp_public_visit_scripts(), true)) {
        return;
    }

    $ua = rp_user_agent();
    if ($ua !== '' && preg_match('/bot|crawl|spider|slurp|facebookexternalhit|preview/i', $ua)) {
        return;
    }

    try {
        $pdo = rp_db();
        $sid = session_id();

        if ($sid !== '') {
            $dup = $pdo->prepare(
                'SELECT id FROM ' . RP_TABLE_VISITS . '
                 WHERE session_id = :sid AND path = :path
                   AND created_at >= (NOW() - INTERVAL ' . RP_VISIT_DEDUP_MINUTES . ' MINUTE)
                 LIMIT 1'
            );
            $dup->execute(['sid' => $sid, 'path' => $path]);
            if ($dup->fetch()) {
                return;
            }
        }

        $ip = rp_client_ip();
        $ins = $pdo->prepare(
            'INSERT INTO ' . RP_TABLE_VISITS . '
                (path, ip_address, session_id, user_agent, lang, created_at)
             VALUES (:path, :ip, :sid, :ua, :lang, NOW())'
        );
        $ins->execute([
            'path' => substr($path, 0, 255),
            'ip'   => $ip !== '' ? $ip : null,
            'sid'  => $sid !== '' ? substr($sid, 0, 128) : null,
            'ua'   => $ua !== '' ? $ua : null,
            'lang' => rp_lang(),
        ]);

        if (empty($_SESSION['rp_visits_pruned'])) {
            $pdo->exec(
                'DELETE FROM ' . RP_TABLE_VISITS . '
                 WHERE created_at < (NOW() - INTERVAL ' . RP_VISIT_RETENTION_DAYS . ' DAY)'
            );
            $_SESSION['rp_visits_pruned'] = 1;
        }
    } catch (Throwable $exception) {
        error_log('[request-portal] visit log failed: ' . $exception->getMessage());
    }
}
