<?php
/**
 * Full admin: soft-delete a content-plan announcement.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/content.php';

$admin = rp_require_full_admin();

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

if (rp_soft_delete($pdo, 'plan', $id, $admin['username'])) {
    rp_flash('success', __('content.deleted'));
} else {
    rp_flash('error', __('admin.trash.cannot_edit'));
}

rp_redirect('admin/plan-edit.php?id=' . $id);
