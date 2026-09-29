<?php
/**
 * Full admin: restore soft-deleted items from any table.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/auth.php';
require dirname(__DIR__) . '/includes/layout.php';

rp_require_full_admin();

$pdo = rp_db();

rp_header(__('admin.section.trash'), 'admin');
?>
<div class="card">
    <h1><?= e(__('admin.trash.title')) ?></h1>
    <p class="muted"><?= e(__('admin.trash.intro')) ?></p>
</div>

<?php foreach (rp_trash_kinds() as $kind): ?>
    <?php $rows = rp_trash_rows($pdo, $kind); ?>
    <div class="card">
        <h2><?= e(__('admin.trash.kind.' . $kind)) ?></h2>
        <?php if (!$rows): ?>
            <p class="muted"><?= e(__('admin.trash.empty')) ?></p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="list">
                    <thead>
                    <tr>
                        <th><?= e(__('admin.trash.item')) ?></th>
                        <th><?= e(__('admin.trash.deleted_at')) ?></th>
                        <th><?= e(__('admin.trash.deleted_by')) ?></th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php $id = (int) $row['id']; ?>
                        <tr>
                            <td>
                                <a href="<?= e(rp_url(rp_trash_view_url($kind, $id))) ?>">
                                    <?= e(rp_trash_title($kind, $row)) ?>
                                </a>
                            </td>
                            <td><?= e(rp_format_datetime((string) $row['deleted_at'])) ?></td>
                            <td><?= e((string) ($row['deleted_by'] ?? '')) ?></td>
                            <td>
                                <form method="post" action="<?= e(rp_url('admin/soft-restore.php')) ?>" class="inline-form">
                                    <?= rp_csrf_field() ?>
                                    <input type="hidden" name="kind" value="<?= e($kind) ?>">
                                    <input type="hidden" name="id" value="<?= $id ?>">
                                    <button type="submit" class="btn btn-small btn-primary"><?= e(__('admin.trash.restore')) ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
<?php rp_footer();
