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

/** True when the string has 7–15 digits (allows +380 50 123 45 67). */
function rp_phone_looks_valid(string $phone): bool
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';

    return strlen($digits) >= 7 && strlen($digits) <= 15;
}

/** Digits only, with a UA-friendly 380 prefix when the number looks local. */
function rp_phone_digits(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if ($digits === '') {
        return '';
    }
    if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
        return '38' . $digits;
    }
    if (strlen($digits) === 9) {
        return '380' . $digits;
    }
    if (strlen($digits) === 11 && str_starts_with($digits, '80')) {
        return '3' . $digits;
    }

    return $digits;
}

/**
 * Guess the messenger channel from a free-text contact field.
 *
 * @return array{channel: string, target: string, label: string}
 */
function rp_parse_messenger(string $raw): array
{
    $raw = trim($raw);
    if ($raw === '') {
        return ['channel' => '', 'target' => '', 'label' => ''];
    }

    if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $raw, $match)) {
        $email = $match[0];

        return ['channel' => 'email', 'target' => $email, 'label' => $email];
    }

    if (preg_match('~(?:https?://)?(?:t|telegram)\.me/([A-Za-z0-9_]+)~i', $raw, $match)) {
        $nick = $match[1];

        return ['channel' => 'telegram', 'target' => $nick, 'label' => '@' . $nick];
    }
    if (preg_match('/^@([A-Za-z0-9_]{4,32})$/', $raw, $match)) {
        $nick = $match[1];

        return ['channel' => 'telegram', 'target' => $nick, 'label' => '@' . $nick];
    }
    if (preg_match('/(?:telegram|телеграм)\s*:?\s*@?([A-Za-z0-9_]{4,32})/iu', $raw, $match)) {
        $nick = $match[1];

        return ['channel' => 'telegram', 'target' => $nick, 'label' => '@' . $nick];
    }

    if (preg_match('~wa\.me/(\+?\d+)~i', $raw, $match)) {
        $digits = rp_phone_digits($match[1]);

        return ['channel' => 'whatsapp', 'target' => $digits, 'label' => '+' . $digits];
    }
    if (preg_match('/whatsapp|вотсап|ватсап/iu', $raw)) {
        $digits = rp_phone_digits($raw);
        if ($digits !== '') {
            return ['channel' => 'whatsapp', 'target' => $digits, 'label' => '+' . $digits];
        }
    }

    if (rp_phone_looks_valid($raw)) {
        $digits = rp_phone_digits($raw);

        return ['channel' => 'whatsapp', 'target' => $digits, 'label' => '+' . $digits];
    }

    return ['channel' => '', 'target' => $raw, 'label' => $raw];
}

/** @return list<string> */
function rp_messenger_channels(): array
{
    return ['email', 'telegram', 'whatsapp'];
}

function rp_channel_label(string $channel): string
{
    if ($channel === '') {
        return '';
    }
    $key   = 'form.channel.' . $channel;
    $label = __($key);

    return $label === $key ? $channel : $label;
}

function rp_telegram_nick(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }
    if (preg_match('~(?:https?://)?(?:t|telegram)\.me/([A-Za-z0-9_]+)~i', $raw, $match)) {
        return $match[1];
    }
    if (preg_match('/^@?([A-Za-z0-9_]{4,32})$/', $raw, $match)) {
        return $match[1];
    }

    return '';
}

function rp_contact_matches_channel(string $channel, string $value): bool
{
    $value = trim($value);
    if ($value === '' || !in_array($channel, rp_messenger_channels(), true)) {
        return false;
    }

    return match ($channel) {
        'email' => (bool) filter_var($value, FILTER_VALIDATE_EMAIL),
        'telegram' => rp_telegram_nick($value) !== '',
        'whatsapp' => rp_phone_looks_valid($value),
        default => false,
    };
}

/**
 * Channels the admin can open for this feedback item.
 *
 * @return array{phone: string, email: string, telegram: string, whatsapp: string}
 */
function rp_feedback_reply_targets(array $feedback): array
{
    $phone   = rp_phone_digits((string) ($feedback['requester_phone'] ?? ''));
    $contact = (string) ($feedback['requester_contact'] ?? '');
    $channel = (string) ($feedback['requester_channel'] ?? '');
    if (!in_array($channel, rp_messenger_channels(), true)) {
        $channel = '';
    }

    $email    = '';
    $telegram = '';
    $whatsapp = '';

    if ($channel === 'email') {
        $parsed = rp_parse_messenger($contact);
        $email  = $parsed['channel'] === 'email' ? $parsed['target'] : trim($contact);
    } elseif ($channel === 'telegram') {
        $telegram = rp_telegram_nick($contact);
        if ($telegram === '') {
            $telegram = ltrim(trim($contact), '@');
        }
    } elseif ($channel === 'whatsapp') {
        $whatsapp = rp_phone_digits($contact);
        if ($whatsapp === '') {
            $whatsapp = $phone;
        }
    } else {
        $parsed   = rp_parse_messenger($contact);
        $email    = $parsed['channel'] === 'email' ? $parsed['target'] : '';
        $telegram = $parsed['channel'] === 'telegram' ? $parsed['target'] : '';
        $whatsapp = $parsed['channel'] === 'whatsapp' ? rp_phone_digits($parsed['target']) : '';
        if ($whatsapp === '' && $phone !== '') {
            $whatsapp = $phone;
        }
    }

    return [
        'phone'    => $phone,
        'email'    => $email,
        'telegram' => $telegram,
        'whatsapp' => $whatsapp,
    ];
}

/** @return list<string> */
function rp_reply_channels(): array
{
    return ['email', 'telegram', 'whatsapp', 'phone'];
}

function rp_reply_launch_url(string $channel, string $target, string $body, string $subject = ''): ?string
{
    $target = trim($target);
    if ($target === '' || !in_array($channel, rp_reply_channels(), true)) {
        return null;
    }

    return match ($channel) {
        'email' => 'mailto:' . $target . '?' . http_build_query(
            array_filter(['subject' => $subject, 'body' => $body], static fn (string $value): bool => $value !== ''),
            '',
            '&',
            PHP_QUERY_RFC3986
        ),
        'telegram' => 'https://t.me/' . rawurlencode(ltrim($target, '@')),
        'whatsapp' => 'https://wa.me/' . rp_phone_digits($target)
            . ($body !== '' ? '?text=' . rawurlencode($body) : ''),
        'phone' => 'tel:+' . ltrim(rp_phone_digits($target), '+'),
        default => null,
    };
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
function rp_it_statuses(): array
{
    return ['new', 'in_progress', 'done'];
}

/** @return list<string> */
function rp_it_categories(): array
{
    return ['printer', 'cartridge', 'computer', 'network', 'other'];
}

function rp_it_category_label(string $category): string
{
    return __('it.category.' . $category);
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

/** Escape user text for Telegram legacy Markdown. */
function rp_telegram_md(string $text): string
{
    return str_replace(
        ['\\', '_', '*', '`', '['],
        ['\\\\', '\\_', '\\*', '\\`', '\\['],
        $text
    );
}

/**
 * Notify Telegram about a public submission. Failures are logged and never
 * block the form.
 *
 * @param 'plan'|'request'|'feedback'|'it' $kind
 * @param array<string,string>             $fields  label => value
 */
function rp_telegram_notify(string $kind, array $fields): void
{
    if (!rp_config('telegram.enabled', true)) {
        return;
    }

    $dest = rp_telegram_destination($kind);
    if ($dest['token'] === '' || $dest['chat'] === '') {
        return;
    }

    $titles = [
        'plan'     => 'Новий анонс (SMM)',
        'request'  => 'Нова заявка на сайт',
        'feedback' => 'Нове питання / пропозиція',
        'it'       => 'Нова заявка до IT-відділу',
    ];

    $lines = ['📌 *' . ($titles[$kind] ?? rp_telegram_md($kind)) . '*'];
    foreach ($fields as $label => $value) {
        $value = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? (string) $value);
        if ($value === '') {
            continue;
        }
        $lines[] = '*' . rp_telegram_md($label) . ':* ' . rp_telegram_md($value);
    }

    $text = implode("\n", $lines);
    if ($text === '') {
        return;
    }
    if (mb_strlen($text) > 4000) {
        $text = mb_substr($text, 0, 3990) . '…';
    }

    $ok = rp_telegram_send($dest['token'], $dest['chat'], $dest['thread'], $text);
    if (!$ok) {
        error_log('[request-portal] Telegram notify failed for ' . $kind);
    }
}

/**
 * Bot, chat and forum topic for a public form kind.
 * IT tickets use a separate bot and group.
 *
 * @return array{token: string, chat: string, thread: int}
 */
function rp_telegram_destination(string $kind): array
{
    if ($kind === 'it') {
        return [
            'token'  => trim((string) rp_config('telegram.it_bot_token', '')),
            'chat'   => trim((string) rp_config('telegram.it_chat_id', '')),
            'thread' => (int) rp_config('telegram.thread_it', 3),
        ];
    }

    return [
        'token'  => trim((string) rp_config('telegram.bot_token', '')),
        'chat'   => trim((string) rp_config('telegram.chat_id', '')),
        'thread' => rp_telegram_thread($kind),
    ];
}

/** Telegram forum topic for SMM / site / feedback alerts. */
function rp_telegram_thread(string $kind): int
{
    return match ($kind) {
        'plan'     => (int) rp_config('telegram.thread_plan', 6),
        'feedback' => (int) rp_config('telegram.thread_feedback', 95),
        default    => (int) rp_config('telegram.thread_request', 96),
    };
}

function rp_telegram_send(string $token, string $chat, int $threadId, string $text): bool
{
    $url    = 'https://api.telegram.org/bot' . $token . '/sendMessage';
    $fields = [
        'chat_id'           => $chat,
        'message_thread_id' => (string) $threadId,
        'parse_mode'        => 'Markdown',
        'text'              => $text,
    ];

    $raw = rp_http_post_form($url, $fields);
    $decoded = json_decode($raw, true);
    if (is_array($decoded) && !empty($decoded['ok'])) {
        return true;
    }

    $description = is_array($decoded) ? (string) ($decoded['description'] ?? '') : '';
    $markdownRejected = is_array($decoded)
        && (int) ($decoded['error_code'] ?? 0) === 400
        && (str_contains($description, 'parse') || str_contains($description, 'markdown'));

    if (!$markdownRejected) {
        return false;
    }

    unset($fields['parse_mode']);
    $raw = rp_http_post_form($url, $fields);
    $decoded = json_decode($raw, true);

    return is_array($decoded) && !empty($decoded['ok']);
}

/** @param array<string,string> $fields */
function rp_http_post_form(string $url, array $fields): string
{
    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        if ($curl === false) {
            return '';
        }
        curl_setopt_array($curl, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $fields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT        => 8,
        ]);
        $out = curl_exec($curl);
        curl_close($curl);

        return is_string($out) ? $out : '';
    }

    $context = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($fields),
            'timeout' => 8,
        ],
    ]);
    $out = @file_get_contents($url, false, $context);

    return is_string($out) ? $out : '';
}
