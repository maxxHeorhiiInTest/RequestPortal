<?php
/**
 * Admin: stream an attachment. Files live outside the web-accessible paths,
 * so every download passes through this authenticated endpoint.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/auth.php';
require dirname(__DIR__) . '/includes/uploads.php';

rp_require_admin();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    rp_abort(400, 'Bad request');
}

$stmt = rp_db()->prepare('SELECT * FROM ' . RP_TABLE_FILES . ' WHERE id = :id');
$stmt->execute(['id' => $id]);
$file = $stmt->fetch();

if (!$file) {
    rp_abort(404, 'File not found');
}

$absPath = rp_resolve_stored_file((string) $file['stored_path']);
if ($absPath === null) {
    rp_abort(404, 'File is missing on disk');
}

rp_send_stored_download($absPath, (string) $file['original_name'], (string) ($file['mime_type'] ?? ''));
