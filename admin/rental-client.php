<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/auth.php';
require dirname(__DIR__) . '/includes/layout.php';

$admin = rp_rental_require('clients');
$pdo   = rp_db();
$id    = (int) ($_GET['id'] ?? 0);
$row   = $id > 0 ? rp_rental_client($pdo, $id) : null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && rp_csrf_valid($_POST['csrf_token'] ?? null)) {
    try {
        $saved = rp_rental_save_client($pdo, $_POST, rp_rental_actor($admin), $id > 0 ? $id : null);
        rp_flash('success', __('rental.saved'));
        rp_redirect('admin/rental-client.php?id=' . $saved);
    } catch (Throwable $e) {
        rp_flash('error', $e->getMessage());
    }
}

$deals = [];
if ($row) {
    $stmt = $pdo->prepare(
        'SELECT * FROM ' . RP_TABLE_RENTAL_DEALS . ' WHERE client_id = :id ORDER BY created_at DESC'
    );
    $stmt->execute(['id' => $id]);
    $deals = $stmt->fetchAll();
}

rp_header($row ? (string) $row['name'] : __('rental.new_client'), 'admin');
?>
<div class="card">
    <h1><?= e($row ? (string) $row['name'] : __('rental.new_client')) ?></h1>
    <form method="post">
        <?= rp_csrf_field() ?>
        <div class="grid-2">
            <div class="field">
                <label><?= e(__('rental.name')) ?> *</label>
                <input name="name" required value="<?= e((string) ($row['name'] ?? '')) ?>">
            </div>
            <div class="field">
                <label><?= e(__('rental.phone')) ?> *</label>
                <input name="phone" required value="<?= e((string) ($row['phone'] ?? '')) ?>">
            </div>
            <div class="field">
                <label><?= e(__('rental.role')) ?></label>
                <select name="type">
                    <?php foreach (['person', 'fop', 'org'] as $t): ?>
                        <option value="<?= e($t) ?>" <?= ($row['type'] ?? '') === $t ? 'selected' : '' ?>>
                            <?= e(rp_rental_client_type_label($t)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Email</label>
                <input name="email" value="<?= e((string) ($row['email'] ?? '')) ?>">
            </div>
            <div class="field">
                <label><?= e(__('rental.contact_person')) ?></label>
                <input name="contact_person" value="<?= e((string) ($row['contact_person'] ?? '')) ?>">
            </div>
            <div class="field">
                <label><?= e(__('rental.tax_id')) ?></label>
                <input name="tax_id" value="<?= e((string) ($row['tax_id'] ?? '')) ?>">
            </div>
        </div>
        <div class="field">
            <label><?= e(__('rental.comment')) ?></label>
            <textarea name="comment" rows="3"><?= e((string) ($row['comment'] ?? '')) ?></textarea>
        </div>
        <button class="btn btn-primary"><?= e(__('common.save')) ?></button>
        <a class="btn" href="<?= e(rp_url('admin/rental-clients.php')) ?>"><?= e(__('common.back')) ?></a>
    </form>
</div>
<?php if ($row): ?>
<div class="card">
    <h2><?= e(__('rental.nav.deals')) ?></h2>
    <ul>
        <?php foreach ($deals as $deal): ?>
            <li>
                <a href="<?= e(rp_url('admin/rental-deal.php?id=' . (int) $deal['id'])) ?>">
                    <?= e((string) $deal['public_code']) ?>
                </a>
                · <?= e(rp_rental_payment_label((string) $deal['payment_status'])) ?>
                · <?= e(rp_rental_format_money((string) $deal['total_amount'])) ?>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>
<?php rp_footer();