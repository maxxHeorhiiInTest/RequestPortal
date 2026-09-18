<?php
/**
 * Admin: restore a hidden holiday (or all of them) to the content-plan calendar.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/auth.php';
require dirname(__DIR__) . '/includes/holidays.php';

rp_require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !rp_csrf_valid($_POST['csrf_token'] ?? null)) {
    rp_flash('error', __('error.csrf'));
    rp_redirect('admin/plan.php');
}

$pdo   = rp_db();
$year  = (int) ($_POST['year'] ?? 0);
$month = (int) ($_POST['month'] ?? 0);
$all   = (string) ($_POST['all'] ?? '') === '1';
$key   = rp_clean_string($_POST['holiday_key'] ?? '', 80);

if ($all) {
    $count = rp_restore_all_holidays($pdo);
    rp_flash('success', __('content.holiday.restored_all', $count));
} else {
    $holiday = rp_holiday_from_key($key);
    if ($holiday === null || !rp_restore_holiday($pdo, $key)) {
        rp_flash('error', __('content.holiday.not_found'));
        rp_redirect('admin/plan.php');
    }
    rp_flash('success', __('content.holiday.restored'));
    if (preg_match('/^(\d{4})-(\d{2})/', $holiday['date'], $match)) {
        $year  = (int) $match[1];
        $month = (int) $match[2];
    }
}

$query = [];
if ($year >= 2000 && $year <= 2100) {
    $query['year'] = $year;
}
if ($month >= 1 && $month <= 12) {
    $query['month'] = $month;
}

rp_redirect('admin/plan.php' . ($query ? '?' . http_build_query($query) : ''));
