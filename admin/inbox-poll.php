<?php
/**
 * Admin: JSON poll for new public submissions.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (rp_admin_user() === null) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'auth']);
    exit;
}

$now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
$raw = rp_clean_string($_GET['since'] ?? '', 32);
$dt  = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $raw, new DateTimeZone('UTC'));
$since = ($dt && $dt->format('Y-m-d H:i:s') === $raw) ? $raw : '';

if ($since === '') {
    $since = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
        ->modify('-15 minutes')
        ->format('Y-m-d H:i:s');
}

$pdo     = rp_db();
$items   = [];
$fullInbox = rp_admin_can('requests');

$snippet = static function (string $text): string {
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

    return mb_strimwidth($text, 0, 90, '…', 'UTF-8');
};

if ($fullInbox) {
    $stmt = $pdo->prepare(
        'SELECT id, public_code, description, requester_name, created_at
         FROM ' . RP_TABLE_REQUESTS . '
         WHERE created_at >= :since AND ' . rp_sql_alive() . '
         ORDER BY created_at ASC, id ASC
         LIMIT 20'
    );
    $stmt->execute(['since' => $since]);
    foreach ($stmt->fetchAll() as $row) {
        $items[] = [
            'kind'    => 'request',
            'id'      => (int) $row['id'],
            'code'    => (string) $row['public_code'],
            'title'   => $snippet((string) $row['description']),
            'who'     => (string) $row['requester_name'],
            'url'     => rp_url('admin/view.php?id=' . (int) $row['id']),
            'created' => (string) $row['created_at'],
        ];
    }

    $stmt = $pdo->prepare(
        'SELECT id, public_code, message, requester_name, created_at
         FROM ' . RP_TABLE_FEEDBACK . '
         WHERE created_at >= :since AND ' . rp_sql_alive() . '
         ORDER BY created_at ASC, id ASC
         LIMIT 20'
    );
    $stmt->execute(['since' => $since]);
    foreach ($stmt->fetchAll() as $row) {
        $items[] = [
            'kind'    => 'feedback',
            'id'      => (int) $row['id'],
            'code'    => (string) $row['public_code'],
            'title'   => $snippet((string) $row['message']),
            'who'     => (string) $row['requester_name'],
            'url'     => rp_url('admin/feedback-view.php?id=' . (int) $row['id']),
            'created' => (string) $row['created_at'],
        ];
    }

    $stmt = $pdo->prepare(
        'SELECT id, title, department, created_at
         FROM ' . RP_TABLE_CONTENT . "
         WHERE created_by LIKE 'guest:%' AND created_at >= :since AND " . rp_sql_alive() . '
         ORDER BY created_at ASC, id ASC
         LIMIT 20'
    );
    $stmt->execute(['since' => $since]);
    foreach ($stmt->fetchAll() as $row) {
        $items[] = [
            'kind'    => 'plan',
            'id'      => (int) $row['id'],
            'code'    => '',
            'title'   => $snippet((string) $row['title']),
            'who'     => (string) $row['department'],
            'url'     => rp_url('admin/plan-edit.php?id=' . (int) $row['id']),
            'created' => (string) $row['created_at'],
        ];
    }
}

if (rp_admin_can('it')) {
    $stmt = $pdo->prepare(
        'SELECT id, public_code, description, requester_name, created_at
         FROM ' . RP_TABLE_IT . '
         WHERE created_at >= :since AND ' . rp_sql_alive() . '
         ORDER BY created_at ASC, id ASC
         LIMIT 20'
    );
    $stmt->execute(['since' => $since]);
    foreach ($stmt->fetchAll() as $row) {
        $items[] = [
            'kind'    => 'it',
            'id'      => (int) $row['id'],
            'code'    => (string) $row['public_code'],
            'title'   => $snippet((string) $row['description']),
            'who'     => (string) $row['requester_name'],
            'url'     => rp_url('admin/it-view.php?id=' . (int) $row['id']),
            'created' => (string) $row['created_at'],
        ];
    }
}

usort($items, static function (array $a, array $b): int {
    return strcmp((string) $a['created'], (string) $b['created']) ?: ($a['id'] <=> $b['id']);
});

echo json_encode(['ok' => true, 'now' => $now, 'items' => $items], JSON_UNESCAPED_UNICODE);
