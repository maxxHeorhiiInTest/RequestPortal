<?php

declare(strict_types=1);

/** @return list<string> */
function rp_content_statuses(): array
{
    return ['draft', 'planned', 'preparing', 'published', 'cancelled'];
}

function rp_content_status_label(string $status): string
{
    return __('content.status.' . $status);
}

/**
 * Publication channels available on the form.
 *
 * @return list<string>
 */
function rp_content_channels(): array
{
    return ['website', 'facebook', 'instagram', 'telegram', 'youtube', 'tiktok'];
}

function rp_content_channel_label(string $channel): string
{
    return __('content.channel.' . $channel);
}

/** True when the UTC DATETIME is strictly before now. */
function rp_utc_is_past(string $utcSql): bool
{
    try {
        $utc = new DateTimeImmutable($utcSql, new DateTimeZone('UTC'));
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return $utc < $now;
    } catch (Exception) {
        return true;
    }
}

/** Compare two UTC DATETIME values down to the minute. */
function rp_utc_same_minute(?string $left, ?string $right): bool
{
    if (!$left || !$right) {
        return false;
    }
    try {
        $a = (new DateTimeImmutable($left, new DateTimeZone('UTC')))->format('Y-m-d H:i');
        $b = (new DateTimeImmutable($right, new DateTimeZone('UTC')))->format('Y-m-d H:i');

        return $a === $b;
    } catch (Exception) {
        return false;
    }
}

/**
 * @param mixed $stored JSON string or array
 * @return list<string>
 */
function rp_content_decode_channels(mixed $stored): array
{
    if (is_array($stored)) {
        $list = $stored;
    } else {
        $decoded = json_decode((string) $stored, true);
        $list    = is_array($decoded) ? $decoded : [];
    }

    $allowed = rp_content_channels();
    $out     = [];
    foreach ($list as $item) {
        if (is_string($item) && in_array($item, $allowed, true)) {
            $out[] = $item;
        }
    }

    return array_values(array_unique($out));
}

/**
 * @param mixed $posted
 * @return list<string>
 */
function rp_content_posted_channels(mixed $posted): array
{
    if (!is_array($posted)) {
        return [];
    }

    return rp_content_decode_channels($posted);
}

/** @return array<string,mixed>|null */
function rp_find_content(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM ' . RP_TABLE_CONTENT . ' WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function rp_delete_content(PDO $pdo, int $id): bool
{
    $stmt = $pdo->prepare('DELETE FROM ' . RP_TABLE_CONTENT . ' WHERE id = :id');
    $stmt->execute(['id' => $id]);

    return $stmt->rowCount() > 0;
}

/**
 * Events whose local date falls in [fromUtc, toUtc).
 *
 * @return list<array<string,mixed>>
 */
function rp_content_between(PDO $pdo, string $fromUtc, string $toUtc): array
{
    $stmt = $pdo->prepare(
        'SELECT * FROM ' . RP_TABLE_CONTENT . '
         WHERE event_at >= :from_at AND event_at < :to_at
         ORDER BY event_at ASC, id ASC'
    );
    $stmt->execute(['from_at' => $fromUtc, 'to_at' => $toUtc]);

    return $stmt->fetchAll();
}

/** Number of guest announcements submitted from an IP within the last hour. */
function rp_recent_guest_content_count(PDO $pdo, string $ip): int
{
    if ($ip === '') {
        return 0;
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM ' . RP_TABLE_CONTENT . '
         WHERE created_by = :who AND created_at >= (NOW() - INTERVAL 1 HOUR)'
    );
    $stmt->execute(['who' => 'guest:' . $ip]);

    return (int) $stmt->fetchColumn();
}

function rp_content_is_public(?string $createdBy): bool
{
    return is_string($createdBy) && str_starts_with($createdBy, 'guest:');
}

function rp_content_public_draft_count(PDO $pdo): int
{
    return (int) $pdo->query(
        'SELECT COUNT(*) FROM ' . RP_TABLE_CONTENT . "
         WHERE created_by LIKE 'guest:%' AND status = 'draft'"
    )->fetchColumn();
}

/**
 * Announcements submitted through the public form, newest first.
 *
 * @return list<array<string,mixed>>
 */
function rp_content_public_list(PDO $pdo, int $limit = 200): array
{
    $limit = max(1, min(500, $limit));
    $stmt  = $pdo->query(
        'SELECT * FROM ' . RP_TABLE_CONTENT . "
         WHERE created_by LIKE 'guest:%'
         ORDER BY (status = 'draft') DESC, created_at DESC, id DESC
         LIMIT " . $limit
    );

    return $stmt->fetchAll();
}
