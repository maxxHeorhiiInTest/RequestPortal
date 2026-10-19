<?php
/**
 * Rental CRM dashboard.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/auth.php';
require dirname(__DIR__) . '/includes/layout.php';

$admin = rp_rental_require('calendar');
$pdo   = rp_db();
rp_rental_expire_overdue($pdo);

$month = rp_clean_string($_GET['month'] ?? '', 7);
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = (new DateTimeImmutable('now', rp_app_timezone()))->format('Y-m');
}

$stats = rp_rental_dashboard($pdo, $month);

rp_header(__('rental.dash.title'), 'admin');
?>
<div class="card">
    <h1><?= e(__('rental.dash.title')) ?></h1>
    <form method="get" class="filters">
        <label><?= e(__('rental.dash.month')) ?>
            <input type="month" name="month" value="<?= e($month) ?>">
        </label>
        <button class="btn" type="submit"><?= e(__('common.search')) ?></button>
    </form>

    <?php if ($stats && rp_rental_can('finance', $admin)): ?>
        <div class="rental-stats">
            <div class="rental-stat">
                <div class="muted"><?= e(__('rental.dash.confirmed')) ?></div>
                <div class="num"><?= e(rp_rental_format_money($stats['confirmed'])) ?></div>
            </div>
            <div class="rental-stat expected">
                <div class="muted"><?= e(__('rental.dash.expected')) ?></div>
                <div class="num"><?= e(rp_rental_format_money($stats['expected'])) ?></div>
            </div>
            <div class="rental-stat">
                <div class="muted"><?= e(__('rental.dash.refunds')) ?></div>
                <div class="num"><?= e(rp_rental_format_money($stats['refunds'])) ?></div>
            </div>
            <div class="rental-stat">
                <div class="muted"><?= e(__('rental.dash.income')) ?></div>
                <div class="num"><?= e(rp_rental_format_money($stats['income'])) ?></div>
            </div>
            <div class="rental-stat expected">
                <div class="muted"><?= e(__('rental.dash.total')) ?></div>
                <div class="num"><?= e(rp_rental_format_money($stats['total_expected'])) ?></div>
            </div>
            <div class="rental-stat">
                <div class="muted"><?= e(__('rental.dash.new_clients')) ?></div>
                <div class="num"><?= (int) $stats['new_clients'] ?></div>
            </div>
            <div class="rental-stat">
                <div class="muted"><?= e(__('rental.dash.all_clients')) ?></div>
                <div class="num"><?= (int) $stats['all_clients'] ?></div>
            </div>
        </div>
    <?php endif; ?>

    <h2><?= e(__('rental.dash.hours_free')) ?></h2>
    <div class="rental-stats">
        <div class="rental-stat">
            <div class="muted"><?= e(__('rental.dash.hours_paid')) ?></div>
            <div class="num"><?= e((string) ($stats['hours_paid'] ?? '0.00')) ?></div>
        </div>
        <div class="rental-stat expected">
            <div class="muted"><?= e(__('rental.dash.hours_hold')) ?></div>
            <div class="num"><?= e((string) ($stats['hours_hold'] ?? '0.00')) ?></div>
        </div>
        <div class="rental-stat">
            <div class="muted"><?= e(__('rental.dash.hours_class')) ?></div>
            <div class="num"><?= e((string) ($stats['hours_class'] ?? '0.00')) ?></div>
        </div>
        <div class="rental-stat">
            <div class="muted"><?= e(__('rental.dash.hours_ok')) ?></div>
            <div class="num"><?= e((string) ($stats['hours_free'] ?? '0.00')) ?></div>
        </div>
        <div class="rental-stat expected">
            <div class="muted"><?= e(__('rental.dash.hours_wait')) ?></div>
            <div class="num"><?= e((string) ($stats['hours_approve'] ?? '0.00')) ?></div>
        </div>
    </div>
</div>
<?php rp_footer();