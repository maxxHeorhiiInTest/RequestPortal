<?php
/**
 * Admin: IT ticket detail.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/auth.php';
require dirname(__DIR__) . '/includes/layout.php';

$admin = rp_require_admin();
$pdo   = rp_db();

$id     = (int) ($_GET['id'] ?? 0);
$ticket = $id > 0 ? rp_find_it_ticket($pdo, $id) : null;

if ($ticket === null) {
    rp_flash('error', __('admin.not_found'));
    rp_redirect('admin/it.php');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!rp_csrf_valid($_POST['csrf_token'] ?? null)) {
        rp_flash('error', __('error.csrf'));
        rp_redirect('admin/it-view.php?id=' . $id);
    }

    $newStatus = in_array($_POST['status'] ?? '', rp_it_statuses(), true)
        ? (string) $_POST['status']
        : (string) $ticket['status'];
    $newNote   = rp_clean_string($_POST['admin_note'] ?? '', 5000);
    $comment   = rp_clean_string($_POST['status_comment'] ?? '', 1000);

    $oldStatus     = (string) $ticket['status'];
    $oldNote       = (string) ($ticket['admin_note'] ?? '');
    $statusChanged = $newStatus !== $oldStatus;
    $noteChanged   = $newNote !== $oldNote;

    if ($statusChanged || $noteChanged) {
        $pdo->beginTransaction();
        try {
            $update = $pdo->prepare(
                'UPDATE ' . RP_TABLE_IT . '
                 SET status = :status, admin_note = :note, updated_at = NOW()
                 WHERE id = :id'
            );
            $update->execute([
                'status' => $newStatus,
                'note'   => $newNote !== '' ? $newNote : null,
                'id'     => $id,
            ]);
            if ($statusChanged) {
                rp_log_it_history($pdo, $id, 'status_changed', $oldStatus, $newStatus, $comment, $admin['username']);
            }
            if ($noteChanged) {
                rp_log_it_history($pdo, $id, 'note_updated', null, null, $statusChanged ? null : $comment, $admin['username']);
            }
            $pdo->commit();
            rp_flash('success', __('admin.saved'));
        } catch (Throwable $exception) {
            $pdo->rollBack();
            error_log('[request-portal] IT update failed: ' . $exception->getMessage());
            rp_flash('error', __('error.save_failed'));
        }
    } else {
        rp_flash('success', __('admin.nothing_changed'));
    }

    rp_redirect('admin/it-view.php?id=' . $id);
}

$history = rp_it_history($pdo, $id);

$historyLabel = static function (array $entry): string {
    return match ((string) $entry['action']) {
        'created'        => __('history.created'),
        'status_changed' => __(
            'history.status_changed',
            rp_status_label((string) $entry['old_value']),
            rp_status_label((string) $entry['new_value'])
        ),
        'note_updated'   => __('history.note_updated'),
        default          => (string) $entry['action'],
    };
};

rp_header(__('admin.it.view_title', (string) $ticket['public_code']), 'admin');
?>
<p><a href="<?= e(rp_url('admin/it.php')) ?>">&larr; <?= e(__('common.back')) ?></a></p>

<div class="card">
    <h1>
        <?= e(__('admin.it.view_title', (string) $ticket['public_code'])) ?>
        <span class="badge status-<?= e((string) $ticket['status']) ?>">
            <?= e(rp_status_label((string) $ticket['status'])) ?>
        </span>
    </h1>

    <h2><?= e(__('admin.section.details')) ?></h2>
    <dl class="details">
        <dt><?= e(__('it.category')) ?></dt>
        <dd><?= e(rp_it_category_label((string) $ticket['category'])) ?></dd>

        <dt><?= e(__('it.name')) ?></dt>
        <dd><?= e((string) $ticket['requester_name']) ?></dd>

        <dt><?= e(__('it.phone')) ?></dt>
        <dd><?= e((string) $ticket['requester_phone']) ?></dd>

        <dt><?= e(__('it.building')) ?></dt>
        <dd><?= e((string) $ticket['building']) ?></dd>

        <dt><?= e(__('it.room')) ?></dt>
        <dd><?= e((string) $ticket['room']) ?></dd>

        <dt><?= e(__('it.message')) ?></dt>
        <dd><?= e((string) $ticket['description']) ?></dd>

        <dt><?= e(__('admin.table.created')) ?></dt>
        <dd><?= e(rp_format_datetime((string) $ticket['created_at'])) ?></dd>

        <dt><?= e(__('admin.meta.updated')) ?></dt>
        <dd><?= e(rp_format_datetime((string) $ticket['updated_at'])) ?></dd>
    </dl>
</div>

<div class="card">
    <h2><?= e(__('admin.section.manage')) ?></h2>
    <form method="post" action="<?= e(rp_url('admin/it-view.php?id=' . $id)) ?>">
        <?= rp_csrf_field() ?>
        <div class="grid-2">
            <div class="field">
                <label for="status"><?= e(__('admin.filter.status')) ?></label>
                <select id="status" name="status">
                    <?php foreach (rp_it_statuses() as $status): ?>
                        <option value="<?= e($status) ?>" <?= (string) $ticket['status'] === $status ? 'selected' : '' ?>>
                            <?= e(rp_status_label($status)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="status_comment"><?= e(__('admin.status_comment')) ?></label>
                <input type="text" id="status_comment" name="status_comment" maxlength="1000">
                <small><?= e(__('admin.status_comment_hint')) ?></small>
            </div>
        </div>
        <div class="field">
            <label for="admin_note"><?= e(__('admin.note')) ?></label>
            <textarea id="admin_note" name="admin_note" rows="4" maxlength="5000"><?= e((string) ($ticket['admin_note'] ?? '')) ?></textarea>
            <small><?= e(__('admin.note_hint')) ?></small>
        </div>
        <button type="submit" class="btn btn-primary"><?= e(__('common.save')) ?></button>
    </form>
</div>

<div class="card">
    <h2><?= e(__('admin.section.history')) ?></h2>
    <ul class="history">
        <?php foreach ($history as $entry): ?>
            <li>
                <?= e($historyLabel($entry)) ?>
                <?php if (!empty($entry['note'])): ?>
                    <div><?= e((string) $entry['note']) ?></div>
                <?php endif; ?>
                <span class="meta">
                    <?= e(rp_format_datetime((string) $entry['created_at'])) ?> ·
                    <?= e(__('history.by')) ?>: <?= e((string) $entry['actor']) ?>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php rp_footer();
