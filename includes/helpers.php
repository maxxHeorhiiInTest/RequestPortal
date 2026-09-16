<?php

declare(strict_types=1);

/** Escape a value for HTML output. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function rp_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
        return true;
    }

    return (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

/**
 * Base path the application is served from, without a trailing slash ('' for docroot).
 */
function rp_base_url(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $configured = trim((string) rp_config('app.base_url', ''));
    if ($configured !== '') {
        return $base = '/' . trim($configured, '/');
    }

    $docRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $appRoot = realpath(RP_ROOT);
    if ($docRoot && $appRoot && str_starts_with($appRoot, $docRoot)) {
        $relative = str_replace('\\', '/', substr($appRoot, strlen($docRoot)));

        return $base = rtrim('/' . trim($relative, '/'), '/');
    }

    // Fallback: derive from the running script, dropping a trailing /admin.
    $dir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php')));
    $dir = preg_replace('#/admin$#', '', $dir) ?? $dir;

    return $base = rtrim($dir === '/' ? '' : $dir, '/');
}

/** Absolute URL path for an application-relative path. */
function rp_url(string $path = ''): string
{
    return rp_base_url() . '/' . ltrim($path, '/');
}

function rp_redirect(string $path): never
{
    header('Location: ' . (preg_match('#^https?://#', $path) ? $path : rp_url($path)));
    exit;
}

function rp_client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function rp_user_agent(): string
{
    return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
}

/** Trim, collapse control characters and cut a submitted string to length. */
function rp_clean_string(mixed $value, int $maxLength): string
{
    $value = is_string($value) ? $value : '';
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    $value = trim($value);

    return mb_substr($value, 0, $maxLength, 'UTF-8');
}

/** Human readable file size. */
function rp_format_bytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $power = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;
    $power = min($power, count($units) - 1);
    $size  = $bytes / (1024 ** $power);

    return ($power === 0 ? (string) $bytes : number_format($size, 1)) . ' ' . $units[$power];
}

/**
 * Format a UTC DATETIME from MySQL in the configured local timezone (Europe/Kyiv by default).
 */
function rp_format_datetime(?string $sqlDateTime): string
{
    if (!$sqlDateTime) {
        return '—';
    }

    try {
        $utc   = new DateTimeImmutable($sqlDateTime, new DateTimeZone('UTC'));
        $local = $utc->setTimezone(new DateTimeZone((string) rp_config('app.timezone', 'Europe/Kyiv')));

        return $local->format('d.m.Y H:i');
    } catch (Exception) {
        return $sqlDateTime;
    }
}

/**
 * Convert a calendar day in the app timezone to a UTC DATETIME bound for SQL filters.
 */
function rp_local_day_to_utc(string $ymd, bool $endOfDay): string
{
    $tz    = new DateTimeZone((string) rp_config('app.timezone', 'Europe/Kyiv'));
    $local = new DateTimeImmutable($ymd . ($endOfDay ? ' 23:59:59' : ' 00:00:00'), $tz);

    return $local->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

function rp_app_timezone(): DateTimeZone
{
    try {
        return new DateTimeZone((string) rp_config('app.timezone', 'Europe/Kyiv'));
    } catch (Exception) {
        return new DateTimeZone('Europe/Kyiv');
    }
}

/** Parse datetime-local (Y-m-d\TH:i) in the app timezone → UTC SQL DATETIME. */
function rp_local_input_to_utc(string $local): ?string
{
    $local = str_replace(' ', 'T', trim($local));
    $tz    = rp_app_timezone();
    $dt    = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $local, $tz);
    if (!$dt || $dt->format('Y-m-d\TH:i') !== substr($local, 0, 16)) {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $local, $tz);
    }
    if (!$dt) {
        return null;
    }

    return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

/** UTC SQL DATETIME → value for <input type="datetime-local">. */
function rp_utc_to_local_input(?string $sqlDateTime): string
{
    if (!$sqlDateTime) {
        return '';
    }
    try {
        $utc = new DateTimeImmutable($sqlDateTime, new DateTimeZone('UTC'));

        return $utc->setTimezone(rp_app_timezone())->format('Y-m-d\TH:i');
    } catch (Exception) {
        return '';
    }
}

function rp_format_time(?string $sqlDateTime): string
{
    if (!$sqlDateTime) {
        return '';
    }
    try {
        $utc = new DateTimeImmutable($sqlDateTime, new DateTimeZone('UTC'));

        return $utc->setTimezone(rp_app_timezone())->format('H:i');
    } catch (Exception) {
        return '';
    }
}

/** Local calendar date Y-m-d of a UTC DATETIME. */
function rp_local_ymd(?string $sqlDateTime): string
{
    if (!$sqlDateTime) {
        return '';
    }
    try {
        $utc = new DateTimeImmutable($sqlDateTime, new DateTimeZone('UTC'));

        return $utc->setTimezone(rp_app_timezone())->format('Y-m-d');
    } catch (Exception) {
        return '';
    }
}

/** @return list<string> */
function rp_statuses(): array
{
    return ['new', 'in_progress', 'done', 'rejected'];
}

/** @return list<string> */
function rp_request_types(): array
{
    return ['new', 'update'];
}

function rp_status_label(string $status): string
{
    return __('status.' . $status);
}

function rp_type_label(string $type): string
{
    return __('type.' . $type);
}

/** Generate a human friendly public request code, e.g. REQ-2026-4F7K2A. */
function rp_generate_code(string $prefix = 'REQ'): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $suffix   = '';
    for ($i = 0; $i < 6; $i++) {
        $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }

    return $prefix . '-' . date('Y') . '-' . $suffix;
}

/** Largest upload accepted by the PHP runtime, in bytes. */
function rp_php_upload_limit(): int
{
    $toBytes = static function (string $value): int {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }
        $unit   = strtolower($value[strlen($value) - 1]);
        $number = (int) $value;

        return match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => (int) $value,
        };
    };

    $limits = array_filter([
        $toBytes((string) ini_get('upload_max_filesize')),
        $toBytes((string) ini_get('post_max_size')),
    ]);

    return $limits ? (int) min($limits) : 0;
}

/** Effective per-file limit: the smaller of the configured and the PHP limit. */
function rp_effective_file_limit(): int
{
    $configured = (int) rp_config('uploads.max_file_size', 0);
    $php        = rp_php_upload_limit();

    if ($configured <= 0) {
        return $php;
    }
    if ($php <= 0) {
        return $configured;
    }

    return min($configured, $php);
}

/** @return list<string> allowed extensions */
function rp_allowed_extensions(): array
{
    /** @var array<string,list<string>> $allowed */
    $allowed = (array) rp_config('uploads.allowed', []);

    return array_keys($allowed);
}

function rp_accept_attribute(): string
{
    return '.' . implode(',.', rp_allowed_extensions());
}

/** Set a one-time flash message. */
function rp_flash(string $type, string $message): void
{
    $_SESSION['rp_flash'][] = ['type' => $type, 'message' => $message];
}

/** @return list<array{type:string,message:string}> */
function rp_take_flashes(): array
{
    $flashes = $_SESSION['rp_flash'] ?? [];
    unset($_SESSION['rp_flash']);

    return is_array($flashes) ? $flashes : [];
}

/** CSRF token for the current session. */
function rp_csrf_token(): string
{
    if (empty($_SESSION['rp_csrf'])) {
        $_SESSION['rp_csrf'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['rp_csrf'];
}

function rp_csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(rp_csrf_token()) . '">';
}

function rp_csrf_valid(mixed $token): bool
{
    return is_string($token)
        && !empty($_SESSION['rp_csrf'])
        && hash_equals((string) $_SESSION['rp_csrf'], $token);
}

/** Abort with a plain response (used for guard clauses). */
function rp_abort(int $code, string $message = ''): never
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    exit($message !== '' ? $message : (string) $code);
}
