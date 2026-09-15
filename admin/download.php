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

$downloadName = preg_replace('/[^\p{L}\p{N}\.\-_ ]+/u', '_', (string) $file['original_name']) ?: 'attachment';

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/octet-stream');
header('Content-Length: ' . filesize($absPath));
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadName) . '"; '
    . "filename*=UTF-8''" . rawurlencode((string) $file['original_name']));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');

readfile($absPath);
exit;
