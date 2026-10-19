<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/auth.php';
require dirname(__DIR__) . '/includes/layout.php';

$admin = rp_require_admin('rental');
$pdo   = rp_db();
$id    = (int) ($_GET['id'] ?? 0);
$room  = $id > 0 ? rp_rental_room($pdo, $id) : null;
if ($room === null) {
    rp_flash('error', __('content.not_found'));
    rp_redirect('admin/rental-rooms.php');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && rp_csrf_valid($_POST['csrf_token'] ?? null) && rp_rental_can('rooms', $admin)) {
    try {
        if (isset($_POST['save_room'])) {
            rp_rental_update_room($pdo, $id, $_POST, rp_rental_actor($admin));
            if (rp_rental_can('tariffs', $admin) && isset($_POST['t_min'])) {
                $tiers = [];
                foreach ((array) $_POST['t_min'] as $i => $min) {
                    $tiers[] = [
                        'min'   => $min,
                        'max'   => $_POST['t_max'][$i] ?? '',
                        'price' => $_POST['t_price'][$i] ?? '0',
                    ];
                }
                rp_rental_replace_tariffs($pdo, $id, $tiers, rp_rental_actor($admin));
            }
            rp_flash('success', __('rental.saved'));
        } elseif (isset($_POST['holiday_rate']) && rp_rental_can('tariffs', $admin)) {
            rp_rental_set_holiday_rate(
                $pdo,
                $id,
                rp_clean_string($_POST['holiday_date'] ?? '', 10),
                (string) ($_POST['holiday_price'] ?? '0'),
                rp_rental_actor($admin)
            );
            rp_flash('success', __('rental.saved'));
        }
    } catch (Throwable $e) {
        rp_flash('error', $e->getMessage());
    }
    rp_redirect('admin/rental-room.php?id=' . $id);
}

$tiers = rp_rental_tariffs($pdo, $id);
rp_header(rp_rental_room_label($room), 'admin');
?>
<div class="card">
    <h1><?= e(rp_rental_room_label($room)) ?></h1>
    <?php if (rp_rental_can('rooms', $admin)): ?>
        <form method="post">
            <?= rp_csrf_field() ?>
            <div class="grid-2">
                <div class="field">
                    <label><?= e(__('rental.name')) ?></label>
                    <input name="name" value="<?= e((string) $room['name']) ?>" required>
                </div>
                <div class="field">
                    <label><?= e(__('rental.cal.building')) ?></label>
                    <input name="building" value="<?= e((string) $room['building']) ?>">
                </div>
                <div class="field">
                    <label><?= e(__('rental.cal.room')) ?></label>
                    <input name="room_number" value="<?= e((string) ($room['room_number'] ?? '')) ?>">
                </div>
                <div class="field">
                    <label><?= e(__('rental.area')) ?></label>
                    <input name="area_m2" value="<?= e((string) ($room['area_m2'] ?? '')) ?>">
                </div>
            </div>
            <div class="field">
                <label class="check">
                    <input type="checkbox" name="is_active" value="1" <?= (int) $room['is_active'] ? 'checked' : '' ?>>
                    <?= e(__('rental.active')) ?>
                </label>
            </div>
            <input type="hidden" name="type" value="<?= e((string) $room['type']) ?>">
            <?php if (rp_rental_can('tariffs', $admin)): ?>
                <h2><?= e(__('rental.tariff')) ?></h2>
                <?php foreach ($tiers as $i => $tier): ?>
                    <div class="grid-2">
                        <input type="number" name="t_min[]" value="<?= (int) $tier['min_participants'] ?>" min="1">
                        <input type="number" name="t_max[]" value="<?= e((string) ($tier['max_participants'] ?? '')) ?>" placeholder="∞">
                        <input type="text" name="t_price[]" value="<?= e((string) $tier['price']) ?>">
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <p><button class="btn btn-primary" name="save_room" value="1"><?= e(__('common.save')) ?></button></p>
        </form>
        <?php if (rp_rental_can('tariffs', $admin)): ?>
            <h2><?= e(__('rental.holiday_rate')) ?></h2>
            <form method="post" class="grid-2">
                <?= rp_csrf_field() ?>
                <div class="field">
                    <label><?= e(__('rental.holiday_date')) ?></label>
                    <input type="date" name="holiday_date" required>
                </div>
                <div class="field">
                    <label><?= e(__('rental.holiday_rate')) ?></label>
                    <input name="holiday_price" required>
                </div>
                <p><button class="btn" name="holiday_rate" value="1"><?= e(__('common.save')) ?></button></p>
            </form>
        <?php endif; ?>
    <?php else: ?>
        <p><?= e(rp_rental_type_label((string) $room['type'])) ?></p>
        <p><?= e(__('rental.area')) ?>:
            <?= $room['area_m2'] === null ? e(__('rental.area_unknown')) : e((string) $room['area_m2']) ?></p>
    <?php endif; ?>
    <p><a class="btn" href="<?= e(rp_url('admin/rental-rooms.php')) ?>"><?= e(__('common.back')) ?></a></p>
</div>
<?php rp_footer();