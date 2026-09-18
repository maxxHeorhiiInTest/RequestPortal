<?php
/**
 * Admin: hide a Ukrainian holiday from the content-plan calendar.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/auth.php';
require dirname(__DIR__) . '/includes/holidays.php';

$admin = rp_require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !rp_csrf_valid($_POST['csrf_token'] ?? null)) {
    rp_flash('error', __('error.csrf'));
    rp_redirect('admin/plan.php');
}

$key = rp_clean_string($_POST['holiday_key'] ?? '', 80);
$year  = (int) ($_POST['year'] ?? 0);
$month = (int) ($_POST['month'] ?? 0);

if (!rp_hide_holiday(rp_db(), $key, (string) $admin['username'])) {
    rp_flash('error', __('content.holiday.not_found'));
    rp_redirect('admin/plan.php');
}

rp_flash('success', __('content.holiday.removed'));

$query = [];
if ($year >= 2000 && $year <= 2100) {
    $query['year'] = $year;
}
if ($month >= 1 && $month <= 12) {
    $query['month'] = $month;
}

rp_redirect('admin/plan.php' . ($query ? '?' . http_build_query($query) : ''));
