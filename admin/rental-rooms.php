<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/auth.php';
require dirname(__DIR__) . '/includes/layout.php';

rp_require_admin('rental');
$pdo   = rp_db();
$rooms = rp_rental_rooms($pdo);

rp_header(__('rental.nav.rooms'), 'admin');
?>
<div class="card">
    <h1><?= e(__('rental.nav.rooms')) ?></h1>
    <div class="table-wrap">
        <table class="list">
            <thead>
            <tr>
                <th><?= e(__('rental.cal.building')) ?></th>
                <th><?= e(__('rental.nav.rooms')) ?></th>
                <th><?= e(__('rental.area')) ?></th>
                <th><?= e(__('rental.tariff')) ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rooms as $room): ?>
                <?php $tiers = rp_rental_tariffs($pdo, (int) $room['id']); ?>
                <tr>
                    <td><?= e((string) $room['building']) ?></td>
                    <td>
                        <?= e(rp_rental_room_label($room)) ?>
                        <div class="muted"><?= e(rp_rental_type_label((string) $room['type'])) ?>
                            · <?= (int) $room['is_active'] ? e(__('rental.active')) : e(__('rental.inactive')) ?></div>
                    </td>
                    <td><?= $room['area_m2'] === null ? e(__('rental.area_unknown')) : e((string) $room['area_m2']) ?></td>
                    <td>
                        <?php foreach ($tiers as $tier): ?>
                            <?php
                            $range = (string) $tier['min_participants'];
                            $range .= $tier['max_participants'] !== null ? '–' . $tier['max_participants'] : '+';
                            ?>
                            <div><?= e($range) ?>: <?= e(rp_rental_format_money((string) $tier['price'])) ?></div>
                        <?php endforeach; ?>
                    </td>
                    <td>
                        <a class="btn btn-small" href="<?= e(rp_url('admin/rental-room.php?id=' . (int) $room['id'])) ?>">
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