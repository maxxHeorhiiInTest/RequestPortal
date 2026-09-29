<?php
/**
 * Full admin: restore a soft-deleted item.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/auth.php';

$admin = rp_require_full_admin();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !rp_csrf_valid($_POST['csrf_token'] ?? null)) {
    rp_flash('error', __('error.csrf'));
    rp_redirect('admin/trash.php');
}

$kind = (string) ($_POST['kind'] ?? '');
$id   = (int) ($_POST['id'] ?? 0);
if (!in_array($kind, rp_trash_kinds(), true) || $id <= 0) {
    rp_flash('error', __('admin.not_found'));
    rp_redirect('admin/trash.php');
}

$pdo = rp_db();
$row = rp_trash_find($pdo, $kind, $id);
if ($row === null) {
    rp_flash('error', __('admin.not_found'));
    rp_redirect('admin/trash.php');
}

if (rp_soft_restore($pdo, $kind, $id, $admin['username'])) {
    rp_flash('success', __('admin.trash.restored'));
} else {
    rp_flash('error', __('admin.trash.cannot_edit'));
}

rp_redirect(rp_trash_view_url($kind, $id));
