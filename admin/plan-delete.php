<?php
/**
 * Admin: delete a content-plan announcement.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/auth.php';
require dirname(__DIR__) . '/includes/content.php';

rp_require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !rp_csrf_valid($_POST['csrf_token'] ?? null)) {
    rp_flash('error', __('error.csrf'));
    rp_redirect('admin/plan.php');
}

$pdo = rp_db();
$id  = (int) ($_POST['id'] ?? 0);
$row = $id > 0 ? rp_find_content($pdo, $id) : null;

if ($row === null) {
    rp_flash('error', __('content.not_found'));
    rp_redirect('admin/plan.php');
}

rp_delete_content($pdo, $id);
rp_flash('success', __('content.deleted'));

$local = rp_local_ymd((string) $row['event_at']);
$query = [];
if (preg_match('/^(\d{4})-(\d{2})/', $local, $match)) {
    $query = ['year' => (int) $match[1], 'month' => (int) $match[2]];
}

rp_redirect('admin/plan.php' . ($query ? '?' . http_build_query($query) : ''));
