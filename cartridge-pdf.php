<?php
/**
 * Public: stream the stored cartridge blank PDF by request code.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/cartridge.php';

$code = strtoupper(rp_clean_string($_GET['code'] ?? '', 20));
if ($code === '') {
    rp_abort(400, 'Bad request');
}

$row = rp_find_cartridge_by_code(rp_db(), $code, true);
if ($row === null) {
    rp_abort(404, 'Not found');
}

$rel  = (string) ($row['pdf_path'] ?? '');
$path = $rel !== '' ? rp_resolve_cartridge_pdf($rel) : null;
if ($path === null) {
    try {
        $rel = rp_cartridge_write_pdf($row);
        $upd = rp_db()->prepare('UPDATE ' . RP_TABLE_CARTRIDGE . ' SET pdf_path = :pdf, updated_at = NOW() WHERE id = :id');
        $upd->execute(['pdf' => $rel, 'id' => (int) $row['id']]);
        $path = rp_resolve_cartridge_pdf($rel);
    } catch (Throwable $exception) {
        error_log('[request-portal] cartridge pdf rebuild failed: ' . $exception->getMessage());
        rp_abort(404, 'File is missing');
    }
}

if ($path === null) {
    rp_abort(404, 'File is missing');
}

$downloadName = $code . '.pdf';
while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');

readfile($path);
exit;
