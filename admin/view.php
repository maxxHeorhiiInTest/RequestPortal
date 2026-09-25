<?php
/**
 * Admin: single request — details, attachments, status handling, history.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/auth.php';
require dirname(__DIR__) . '/includes/uploads.php';
require dirname(__DIR__) . '/includes/layout.php';

$admin = rp_require_admin();
$pdo   = rp_db();

$id      = (int) ($_GET['id'] ?? 0);
$request = $id > 0 ? rp_find_request($pdo, $id) : null;

if ($request === null) {
    rp_flash('error', __('admin.not_found'));
    rp_redirect('admin/index.php');
}

$deleted = rp_admin_guard_deleted($request, 'admin/index.php');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if ($deleted) {
        rp_flash('error', __('admin.trash.cannot_edit'));
        rp_redirect('admin/view.php?id=' . $id);
    }
    if (!rp_csrf_valid($_POST['csrf_token'] ?? null)) {
        rp_flash('error', __('error.csrf'));
        rp_redirect('admin/view.php?id=' . $id);
    }

    $newStatus = in_array($_POST['status'] ?? '', rp_statuses(), true)
        ? (string) $_POST['status']
        : (string) $request['status'];
    $newNote   = rp_clean_string($_POST['admin_note'] ?? '', 5000);
    $comment   = rp_clean_string($_POST['status_comment'] ?? '', 1000);

    $oldStatus     = (string) $request['status'];
    $oldNote       = (string) ($request['admin_note'] ?? '');
    $statusChanged = $newStatus !== $oldStatus;
    $noteChanged   = $newNote !== $oldNote;

    if ($statusChanged || $noteChanged) {
        $pdo->beginTransaction();
        try {
            $update = $pdo->prepare(
                'UPDATE ' . RP_TABLE_REQUESTS . '
                 SET status = :status, admin_note = :note, updated_at = NOW()
                 WHERE id = :id'
            );
            $update->execute([
                'status' => $newStatus,
                'note'   => $newNote !== '' ? $newNote : null,
                'id'     => $id,
            ]);

            if ($statusChanged) {
                rp_log_history($pdo, $id, 'status_changed', $oldStatus, $newStatus, $comment, $admin['username']);
            }
            if ($noteChanged) {
                rp_log_history($pdo, $id, 'note_updated', null, null, $statusChanged ? null : $comment, $admin['username']);
            }

            $pdo->commit();
            rp_flash('success', __('admin.saved'));
        } catch (Throwable $exception) {
            $pdo->rollBack();
            error_log('[request-portal] update failed: ' . $exception->getMessage());
            rp_flash('error', __('error.save_failed'));
        }
    } else {
        rp_flash('success', __('admin.nothing_changed'));
    }

    rp_redirect('admin/view.php?id=' . $id);
}

$files   = rp_request_files($pdo, $id);
$history = rp_request_history($pdo, $id);

/** Human readable history line. */
$historyLabel = static function (array $entry): string {
    return match ((string) $entry['action']) {
        'created'        => __('history.created'),
        'status_changed' => __(
            'history.status_changed',
            rp_status_label((string) $entry['old_value']),
            rp_status_label((string) $entry['new_value'])
        ),
        'note_updated'   => __('history.note_updated'),
        'file_added'     => __('history.file_added', (string) $entry['new_value']),
        'deleted'        => __('history.deleted'),
        'restored'       => __('history.restored'),
        default          => (string) $entry['action'],
    };
};

$isLink = (bool) preg_match('#^https?://\S+$#', (string) $request['target_location']);

rp_header(__('admin.view_title', (string) $request['public_code']), 'admin');
?>
<p><a href="<?= e(rp_url('admin/index.php')) ?>">&larr; <?= e(__('common.back')) ?></a></p>

<div class="card">
    <h1>
        <?= e(__('admin.view_title', (string) $request['public_code'])) ?>
        <span class="badge status-<?= e((string) $request['status']) ?>">
            <?= e(rp_status_label((string) $request['status'])) ?>
        </span>
        <?php if ($deleted): ?>
            <span class="badge status-rejected"><?= e(__('admin.trash.badge')) ?></span>
        <?php endif; ?>
    </h1>

    <h2><?= e(__('admin.section.details')) ?></h2>
    <dl class="details">
        <dt><?= e(__('form.type')) ?></dt>
        <dd><?= e(rp_type_label((string) $request['type'])) ?></dd>

        <dt><?= e(__('form.target')) ?></dt>
        <?php // No stray whitespace inside <dd>: the details list renders with white-space: pre-wrap. ?>
        <dd><?php if ($isLink): ?><a href="<?= e((string) $request['target_location']) ?>" target="_blank" rel="noopener nofollow"><?= e((string) $request['target_location']) ?></a><?php else: ?><?= e((string) $request['target_location']) ?><?php endif; ?></dd>

        <dt><?= e(__('form.description')) ?></dt>
        <dd><?= e((string) $request['description']) ?></dd>

        <dt><?= e(__('form.comment')) ?></dt>
        <dd><?= e((string) ($request['extra_comment'] ?? '')) ?: e(__('common.none')) ?></dd>

        <dt><?= e(__('form.name')) ?></dt>
        <dd><?= e((string) $request['requester_name']) ?></dd>

        <dt><?= e(__('form.faculty')) ?></dt>
        <dd><?= e((string) ($request['faculty'] ?? '')) ?: e(__('common.none')) ?></dd>

        <dt><?= e(__('form.department')) ?></dt>
        <dd><?= e((string) ($request['department'] ?? '')) ?: e(__('common.none')) ?></dd>

        <dt><?= e(__('form.contact')) ?></dt>
        <dd><?= e((string) $request['requester_contact']) ?></dd>

        <dt><?= e(__('admin.table.created')) ?></dt>
        <dd><?= e(rp_format_datetime((string) $request['created_at'])) ?></dd>

        <dt><?= e(__('admin.meta.updated')) ?></dt>
        <dd><?= e(rp_format_datetime((string) $request['updated_at'])) ?></dd>
    </dl>
</div>

<div class="card">
    <h2><?= e(__('admin.section.files')) ?></h2>
    <?php if (!$files): ?>
        <p class="muted"><?= e(__('admin.no_files')) ?></p>
    <?php else: ?>
        <ul class="files">
            <?php foreach ($files as $file): ?>
                <?php $exists = rp_resolve_stored_file((string) $file['stored_path']) !== null; ?>
                <li>
                    <?php if ($exists): ?>
                        <a href="<?= e(rp_url('admin/download.php?id=' . (int) $file['id'])) ?>">
                            <?= e((string) $file['original_name']) ?>
                        </a>
                    <?php else: ?>
                        <?= e((string) $file['original_name']) ?>
                        <span class="badge status-rejected"><?= e(__('admin.file_missing')) ?></span>
                    <?php endif; ?>
                    <span class="meta">
                        <?= e(rp_format_bytes((int) $file['size_bytes'])) ?> ·
                        <?= e((string) $file['mime_type']) ?> ·
                        <?= e(rp_format_datetime((string) $file['created_at'])) ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<?php rp_admin_trash_box('request', $id, $deleted); ?>

<?php if (!$deleted): ?>
<div class="card">
    <h2><?= e(__('admin.section.manage')) ?></h2>
    <form method="post" action="<?= e(rp_url('admin/view.php?id=' . $id)) ?>">
        <?= rp_csrf_field() ?>
        <div class="grid-2">
            <div class="field">
                <label for="status"><?= e(__('admin.filter.status')) ?></label>
                <select id="status" name="status">
                    <?php foreach (rp_statuses() as $status): ?>
                        <option value="<?= e($status) ?>" <?= (string) $request['status'] === $status ? 'selected' : '' ?>>
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
            <textarea id="admin_note" name="admin_note" rows="4" maxlength="5000"><?= e((string) ($request['admin_note'] ?? '')) ?></textarea>
            <small><?= e(__('admin.note_hint')) ?></small>
        </div>
        <button type="submit" class="btn btn-primary"><?= e(__('common.save')) ?></button>
    </form>
</div>
<?php endif; ?>

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
