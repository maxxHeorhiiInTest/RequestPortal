<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/auth.php';
require dirname(__DIR__) . '/includes/layout.php';

rp_rental_require('audit');
$pdo  = rp_db();
$rows = $pdo->query(
    'SELECT * FROM ' . RP_TABLE_RENTAL_AUDIT . ' ORDER BY id DESC LIMIT 300'
)->fetchAll();

rp_header(__('rental.nav.audit'), 'admin');
?>
<div class="card">
    <h1><?= e(__('rental.nav.audit')) ?></h1>
    <div class="table-wrap">
        <table class="list">
            <thead>
            <tr>
                <th><?= e(__('rental.history')) ?></th>
                <th><?= e(__('rental.role')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td>
                        <?= e((string) $row['action']) ?>
                        · <?= e((string) $row['entity']) ?> #<?= (int) ($row['entity_id'] ?? 0) ?>
                        <div class="muted"><?= e((string) ($row['old_value'] ?? '')) ?>
                            → <?= e((string) ($row['new_value'] ?? '')) ?>
                            <?= e((string) ($row['note'] ?? '')) ?></div>
                    </td>
                    <td>
                        <?= e((string) $row['actor']) ?><br>
                        <span class="muted"><?= e(rp_format_datetime((string) $row['created_at'])) ?></span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php rp_footer();