<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/auth.php';
require dirname(__DIR__) . '/includes/layout.php';

$admin = rp_rental_require('refund');
$pdo   = rp_db();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && rp_csrf_valid($_POST['csrf_token'] ?? null)) {
    try {
        rp_rental_confirm_refund($pdo, (int) ($_POST['id'] ?? 0), rp_rental_actor($admin));
        rp_flash('success', __('rental.saved'));
    } catch (Throwable $e) {
        rp_flash('error', $e->getMessage());
    }
    rp_redirect('admin/rental-refunds.php');
}

$rows = $pdo->query(
    'SELECT r.*, d.public_code FROM ' . RP_TABLE_RENTAL_REFUNDS . ' r
     JOIN ' . RP_TABLE_RENTAL_DEALS . ' d ON d.id = r.deal_id
     ORDER BY r.created_at DESC LIMIT 200'
)->fetchAll();

rp_header(__('rental.nav.refunds'), 'admin');
?>
<div class="card">
    <h1><?= e(__('rental.nav.refunds')) ?></h1>
    <ul>
        <?php foreach ($rows as $row): ?>
            <li>
                <?= e((string) $row['public_code']) ?>
                · <?= e(rp_rental_format_money((string) $row['amount'])) ?>
                · <?= $row['status'] === 'done' ? e(__('rental.refund_done')) : e(__('rental.refund_pending')) ?>
                <?php if ($row['status'] === 'pending'): ?>
                    <form method="post" style="display:inline">
                        <?= rp_csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                        <button class="btn btn-small"><?= e(__('rental.confirm_refund')) ?></button>
                    </form>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php rp_footer();