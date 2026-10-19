<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/auth.php';
require dirname(__DIR__) . '/includes/layout.php';

$admin = rp_require_admin('rental');
if (!rp_rental_can('approve', $admin) && !rp_rental_can('deals', $admin)) {
    rp_redirect(rp_admin_home());
}
$pdo = rp_db();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && rp_csrf_valid($_POST['csrf_token'] ?? null) && rp_rental_can('approve', $admin)) {
    try {
        rp_rental_decide_approval(
            $pdo,
            (int) ($_POST['id'] ?? 0),
            isset($_POST['approve']),
            rp_rental_actor($admin),
            (string) ($_POST['note'] ?? '')
        );
        rp_flash('success', __('rental.saved'));
    } catch (Throwable $e) {
        rp_flash('error', $e->getMessage());
    }
    rp_redirect('admin/rental-approvals.php');
}

$rows = $pdo->query(
    'SELECT a.*, r.building, r.name AS room_name, r.room_number
     FROM ' . RP_TABLE_RENTAL_APPROVALS . ' a
     JOIN ' . RP_TABLE_RENTAL_ROOMS . ' r ON r.id = a.room_id
     ORDER BY a.requested_at DESC LIMIT 200'
)->fetchAll();

rp_header(__('rental.nav.approvals'), 'admin');
?>
<div class="card">
    <h1><?= e(__('rental.nav.approvals')) ?></h1>
    <?php foreach ($rows as $row): ?>
        <div class="card">
            <p>
                <?= e(rp_rental_room_label($row + ['name' => $row['room_name']])) ?><br>
                <?= e(rp_format_datetime((string) $row['starts_at'])) ?>
                – <?= e(rp_format_time((string) $row['ends_at'])) ?><br>
                <?= e((string) $row['status']) ?> · <?= e((string) $row['requested_by']) ?>
            </p>
            <?php if ($row['status'] === 'pending' && rp_rental_can('approve', $admin)): ?>
                <form method="post">
                    <?= rp_csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                    <input name="note" placeholder="<?= e(__('rental.comment')) ?>">
                    <button class="btn btn-primary" name="approve" value="1"><?= e(__('rental.approve')) ?></button>
                    <button class="btn" name="reject" value="1"><?= e(__('rental.reject')) ?></button>
                </form>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php rp_footer();