<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/auth.php';
require dirname(__DIR__) . '/includes/layout.php';

$admin = rp_rental_require('deals');
$pdo   = rp_db();
rp_rental_expire_overdue($pdo);

$status = rp_clean_string($_GET['status'] ?? '', 20);
$sql    = 'SELECT d.*, c.name AS client_name FROM ' . RP_TABLE_RENTAL_DEALS . ' d
           JOIN ' . RP_TABLE_RENTAL_CLIENTS . ' c ON c.id = d.client_id';
$params = [];
if (in_array($status, ['unpaid', 'paid', 'expired', 'cancelled'], true)) {
    $sql .= ' WHERE d.payment_status = :st';
    $params['st'] = $status;
}
$sql .= ' ORDER BY d.created_at DESC LIMIT 200';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

rp_header(__('rental.nav.deals'), 'admin');
?>
<div class="card">
    <h1><?= e(__('rental.nav.deals')) ?></h1>
    <p>
        <a class="btn btn-primary" href="<?= e(rp_url('admin/rental-deal.php')) ?>"><?= e(__('rental.nav.new_deal')) ?></a>
        <?php foreach (['', 'unpaid', 'paid', 'expired', 'cancelled'] as $st): ?>
            <a class="btn" href="<?= e(rp_url('admin/rental-deals.php' . ($st !== '' ? '?status=' . $st : ''))) ?>">
                <?= $st === '' ? e(__('rental.filter.all')) : e(rp_rental_payment_label($st)) ?>
            </a>
        <?php endforeach; ?>
    </p>
    <div class="table-wrap">
        <table class="list">
            <thead>
            <tr>
                <th><?= e(__('rental.nav.deals')) ?></th>
                <th><?= e(__('rental.nav.clients')) ?></th>
                <th><?= e(__('rental.amount')) ?></th>
                <th><?= e(__('rental.deadline')) ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td>
                        <?= e((string) $row['public_code']) ?>
                        <div class="muted"><?= e(rp_rental_payment_label((string) $row['payment_status'])) ?></div>
                    </td>
                    <td><?= e((string) $row['client_name']) ?></td>
                    <td><?= e(rp_rental_format_money((string) $row['total_amount'])) ?></td>
                    <td><?= e(rp_format_datetime((string) $row['pay_deadline'])) ?></td>
                    <td>
                        <a class="btn btn-small" href="<?= e(rp_url('admin/rental-deal.php?id=' . (int) $row['id'])) ?>">
                            <?= e(__('admin.open')) ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php rp_footer();