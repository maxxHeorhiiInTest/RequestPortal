<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/auth.php';
require dirname(__DIR__) . '/includes/layout.php';

$admin = rp_rental_require('clients');
$pdo   = rp_db();
$q     = rp_clean_string($_GET['q'] ?? '', 80);
$list  = rp_rental_clients($pdo, $q);

rp_header(__('rental.nav.clients'), 'admin');
?>
<div class="card">
    <h1><?= e(__('rental.nav.clients')) ?></h1>
    <form method="get" class="filters">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(__('common.search')) ?>">
        <button class="btn" type="submit"><?= e(__('common.search')) ?></button>
        <a class="btn btn-primary" href="<?= e(rp_url('admin/rental-client.php')) ?>"><?= e(__('rental.new_client')) ?></a>
    </form>
    <div class="table-wrap">
        <table class="list">
            <thead>
            <tr>
                <th><?= e(__('rental.name')) ?></th>
                <th><?= e(__('rental.phone')) ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($list as $row): ?>
                <tr>
                    <td>
                        <?= e((string) $row['name']) ?>
                        <div class="muted"><?= e(rp_rental_client_type_label((string) $row['type'])) ?></div>
                    </td>
                    <td><?= e((string) $row['phone']) ?></td>
                    <td>
                        <a class="btn btn-small" href="<?= e(rp_url('admin/rental-client.php?id=' . (int) $row['id'])) ?>">
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