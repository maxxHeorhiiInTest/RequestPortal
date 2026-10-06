<?php
/**
 * Admin: A4 pickup sheet for cartridges that are not yet refilled.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/auth.php';
require dirname(__DIR__) . '/includes/layout.php';
require dirname(__DIR__) . '/includes/cartridge.php';

rp_require_admin();

$pdo  = rp_db();
$stmt = $pdo->prepare(
    'SELECT * FROM ' . RP_TABLE_CARTRIDGE . '
     WHERE status IN (\'new\', \'sent\') AND ' . rp_sql_alive() . '
     ORDER BY created_at ASC, id ASC'
);
$stmt->execute();
$rows = $stmt->fetchAll();

rp_header(__('admin.cartridge.print_firm'), 'admin');
?>
<div class="card cartridge-firm no-print">
    <div class="page-head">
        <h1><?= e(__('admin.cartridge.print_firm')) ?></h1>
        <button type="button" class="btn btn-primary" onclick="window.print()"><?= e(__('cartridge.print')) ?></button>
    </div>
    <p class="muted"><?= e(__('admin.cartridge.print_hint')) ?></p>
</div>

<div class="card cartridge-firm-sheet">
    <div class="cartridge-letterhead">
        <img src="<?= e(rp_url('assets/logo-nufvsu.png')) ?>" alt="">
        <div>
            <strong><?= e(__('home.logo_alt')) ?></strong>
            <div><?= e(__('admin.cartridge.print_firm')) ?></div>
            <div class="muted"><?= e(rp_format_datetime(gmdate('Y-m-d H:i:s'))) ?></div>
        </div>
    </div>
    <?php if (!$rows): ?>
        <p><?= e(__('admin.cartridge.print_empty')) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="list">
                <thead>
                <tr>
                    <th><?= e(__('admin.table.code')) ?></th>
                    <th><?= e(__('admin.table.created')) ?></th>
                    <th><?= e(__('cartridge.building')) ?></th>
                    <th><?= e(__('cartridge.room')) ?></th>
                    <th><?= e(__('cartridge.printer')) ?></th>
                    <th><?= e(__('cartridge.cartridge')) ?></th>
                    <th><?= e(__('admin.table.status')) ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td class="code-cell"><?= e((string) $row['public_code']) ?></td>
                        <td><?= e(rp_format_datetime((string) $row['created_at'])) ?></td>
                        <td><?= e((string) $row['building']) ?></td>
                        <td><?= e((string) $row['room']) ?></td>
                        <td><?= e((string) $row['printer_model']) ?></td>
                        <td><?= e((string) $row['cartridge_model']) ?></td>
                        <td><?= e(rp_cartridge_status_label((string) $row['status'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php rp_footer();
