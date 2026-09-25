<?php
/**
 * Admin: announcements submitted through the public planAdd.php form.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/content.php';
require dirname(__DIR__) . '/includes/layout.php';

rp_require_admin();

$items = rp_content_public_list(rp_db());

rp_header(__('content.inbox.title'), 'admin');
?>
<div class="card">
    <h1><?= e(__('content.inbox.title')) ?></h1>
    <p class="muted"><?= e(__('content.inbox.intro')) ?></p>
    <p class="muted"><?= e(__('content.inbox.total', count($items))) ?></p>

    <?php if (!$items): ?>
        <p><?= e(__('content.inbox.empty')) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="list">
                <thead>
                <tr>
                    <th><?= e(__('content.inbox.submitted')) ?></th>
                    <th><?= e(__('content.field.when')) ?></th>
                    <th><?= e(__('content.field.title')) ?></th>
                    <th><?= e(__('content.field.department')) ?></th>
                    <th><?= e(__('content.field.responsible')) ?></th>
                    <th><?= e(__('content.field.status')) ?></th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $event): ?>
                    <tr>
                        <td><?= e(rp_format_datetime((string) $event['created_at'])) ?></td>
                        <td><?= e(rp_format_datetime((string) $event['event_at'])) ?></td>
                        <td>
                            <?= e((string) $event['title']) ?>
                            <div class="muted"><?= e(__('content.source.site')) ?></div>
                        </td>
                        <td><?= e((string) $event['department']) ?></td>
                        <td><?= e((string) $event['responsible']) ?></td>
                        <td>
                            <span class="badge status-<?= e((string) $event['status']) ?>">
                                <?= e(rp_content_status_label((string) $event['status'])) ?>
                            </span>
                        </td>
                        <td>
                            <a class="btn btn-small"
                               href="<?= e(rp_url('admin/plan-edit.php?id=' . (int) $event['id'])) ?>">
                                <?= e(__('admin.open')) ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php rp_footer();
