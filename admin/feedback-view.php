<?php
/**
 * Admin: question / suggestion detail.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/auth.php';
require dirname(__DIR__) . '/includes/uploads.php';
require dirname(__DIR__) . '/includes/layout.php';

$admin = rp_require_admin();
$pdo   = rp_db();

$id       = (int) ($_GET['id'] ?? 0);
$feedback = $id > 0 ? rp_find_feedback($pdo, $id) : null;

if ($feedback === null) {
    rp_flash('error', __('admin.not_found'));
    rp_redirect('admin/feedback.php');
}

$targets    = rp_feedback_reply_targets($feedback);
$replyDraft = (string) ($_SESSION['rp_reply_draft'][$id] ?? '');
unset($_SESSION['rp_reply_draft'][$id]);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!rp_csrf_valid($_POST['csrf_token'] ?? null)) {
        rp_flash('error', __('error.csrf'));
        rp_redirect('admin/feedback-view.php?id=' . $id);
    }

    $form = (string) ($_POST['form'] ?? 'manage');

    if ($form === 'reply') {
        $channel = (string) ($_POST['reply_channel'] ?? $_POST['reply_channel_btn'] ?? '');
        $body    = rp_clean_string($_POST['reply_body'] ?? '', 4000);
        $next    = (string) ($_POST['reply_status'] ?? '');
        $target  = $targets[$channel] ?? '';

        $_SESSION['rp_reply_draft'][$id] = $body;

        if ($body === '') {
            rp_flash('error', __('error.reply.empty'));
            rp_redirect('admin/feedback-view.php?id=' . $id);
        }
        if ($target === '' || !in_array($channel, rp_reply_channels(), true)) {
            rp_flash('error', __('error.reply.channel'));
            rp_redirect('admin/feedback-view.php?id=' . $id);
        }

        $oldStatus = (string) $feedback['status'];
        $newStatus = in_array($next, ['in_progress', 'done'], true) ? $next : $oldStatus;

        $pdo->beginTransaction();
        try {
            $update = $pdo->prepare(
                'UPDATE ' . RP_TABLE_FEEDBACK . '
                 SET status = :status, updated_at = NOW()
                 WHERE id = :id'
            );
            $update->execute([
                'status' => $newStatus,
                'id'     => $id,
            ]);
            rp_log_feedback_history(
                $pdo,
                $id,
                'replied',
                $target,
                $channel,
                $body,
                $admin['username']
            );
            if ($newStatus !== $oldStatus) {
                rp_log_feedback_history(
                    $pdo,
                    $id,
                    'status_changed',
                    $oldStatus,
                    $newStatus,
                    null,
                    $admin['username']
                );
            }
            $pdo->commit();
            unset($_SESSION['rp_reply_draft'][$id]);
            rp_flash('success', __('admin.reply.saved'));
        } catch (Throwable $exception) {
            $pdo->rollBack();
            error_log('[request-portal] feedback reply failed: ' . $exception->getMessage());
            rp_flash('error', __('error.save_failed'));
        }

        rp_redirect('admin/feedback-view.php?id=' . $id);
    }

    $newStatus = in_array($_POST['status'] ?? '', rp_statuses(), true)
        ? (string) $_POST['status']
        : (string) $feedback['status'];
    $newNote   = rp_clean_string($_POST['admin_note'] ?? '', 5000);
    $comment   = rp_clean_string($_POST['status_comment'] ?? '', 1000);

    $oldStatus     = (string) $feedback['status'];
    $oldNote       = (string) ($feedback['admin_note'] ?? '');
    $statusChanged = $newStatus !== $oldStatus;
    $noteChanged   = $newNote !== $oldNote;

    if ($statusChanged || $noteChanged) {
        $pdo->beginTransaction();
        try {
            $update = $pdo->prepare(
                'UPDATE ' . RP_TABLE_FEEDBACK . '
                 SET status = :status, admin_note = :note, updated_at = NOW()
                 WHERE id = :id'
            );
            $update->execute([
                'status' => $newStatus,
                'note'   => $newNote !== '' ? $newNote : null,
                'id'     => $id,
            ]);
            if ($statusChanged) {
                rp_log_feedback_history($pdo, $id, 'status_changed', $oldStatus, $newStatus, $comment, $admin['username']);
            }
            if ($noteChanged) {
                rp_log_feedback_history($pdo, $id, 'note_updated', null, null, $statusChanged ? null : $comment, $admin['username']);
            }
            $pdo->commit();
            rp_flash('success', __('admin.saved'));
        } catch (Throwable $exception) {
            $pdo->rollBack();
            error_log('[request-portal] feedback update failed: ' . $exception->getMessage());
            rp_flash('error', __('error.save_failed'));
        }
    } else {
        rp_flash('success', __('admin.nothing_changed'));
    }

    rp_redirect('admin/feedback-view.php?id=' . $id);
}

$files   = rp_feedback_files($pdo, $id);
$history = rp_feedback_history($pdo, $id);
$replyBody = $replyDraft !== ''
    ? $replyDraft
    : __('admin.reply.template', (string) $feedback['requester_name'], (string) $feedback['public_code']);
$replySubject = __('admin.reply.subject', (string) $feedback['public_code']);

$historyLabel = static function (array $entry): string {
    $action = (string) $entry['action'];
    if ($action === 'replied') {
        $channel = (string) $entry['new_value'];
        $label   = __('admin.reply.channel.' . $channel);
        if ($label === 'admin.reply.channel.' . $channel) {
            $label = $channel;
        }

        return __('history.replied', $label, (string) $entry['old_value']);
    }

    return match ($action) {
        'created'        => __('history.created'),
        'status_changed' => __(
            'history.status_changed',
            rp_status_label((string) $entry['old_value']),
            rp_status_label((string) $entry['new_value'])
        ),
        'note_updated'   => __('history.note_updated'),
        'file_added'     => __('history.file_added', (string) $entry['new_value']),
        default          => $action,
    };
};

$availableChannels = [];
foreach (rp_reply_channels() as $channel) {
    if (($targets[$channel] ?? '') !== '') {
        $availableChannels[] = $channel;
    }
}
$channelLabel = rp_channel_label((string) ($feedback['requester_channel'] ?? ''));

rp_header(__('admin.feedback.view_title', (string) $feedback['public_code']), 'admin');
?>
<p><a href="<?= e(rp_url('admin/feedback.php')) ?>">&larr; <?= e(__('common.back')) ?></a></p>

<div class="card">
    <h1>
        <?= e(__('admin.feedback.view_title', (string) $feedback['public_code'])) ?>
        <span class="badge status-<?= e((string) $feedback['status']) ?>">
            <?= e(rp_status_label((string) $feedback['status'])) ?>
        </span>
    </h1>
    <dl class="details">
        <dt><?= e(__('feedback.message')) ?></dt>
        <dd><?= e((string) $feedback['message']) ?></dd>
        <dt><?= e(__('form.name')) ?></dt>
        <dd><?= e((string) $feedback['requester_name']) ?></dd>
        <dt><?= e(__('form.faculty')) ?></dt>
        <dd><?= e((string) ($feedback['faculty'] ?? '')) ?: e(__('common.none')) ?></dd>
        <dt><?= e(__('form.department')) ?></dt>
        <dd><?= e((string) ($feedback['department'] ?? '')) ?: e(__('common.none')) ?></dd>
        <dt><?= e(__('form.phone')) ?></dt>
        <dd><?= e((string) ($feedback['requester_phone'] ?? '')) ?: e(__('common.none')) ?></dd>
        <dt><?= e(__('form.channel')) ?></dt>
        <dd><?= $channelLabel !== '' ? e($channelLabel) : e(__('common.none')) ?></dd>
        <dt><?= e(__('form.channel_contact')) ?></dt>
        <dd><?= e((string) $feedback['requester_contact']) ?: e(__('common.none')) ?></dd>
        <dt><?= e(__('admin.table.created')) ?></dt>
        <dd><?= e(rp_format_datetime((string) $feedback['created_at'])) ?></dd>
        <dt><?= e(__('admin.meta.updated')) ?></dt>
        <dd><?= e(rp_format_datetime((string) $feedback['updated_at'])) ?></dd>
    </dl>
</div>

<div class="card">
    <h2><?= e(__('admin.reply.title')) ?></h2>
    <p class="muted"><?= e(__('admin.reply.intro')) ?></p>
    <form method="post" action="<?= e(rp_url('admin/feedback-view.php?id=' . $id)) ?>"
          id="reply-form" data-subject="<?= e($replySubject) ?>">
        <?= rp_csrf_field() ?>
        <input type="hidden" name="form" value="reply">
        <input type="hidden" name="reply_channel" id="reply_channel" value="">
        <div class="field">
            <label for="reply_body"><?= e(__('admin.reply.body')) ?></label>
            <textarea id="reply_body" name="reply_body" rows="7" maxlength="4000"><?= e($replyBody) ?></textarea>
            <small><?= e(__('admin.reply.hint')) ?></small>
        </div>
        <div class="field">
            <label for="reply_status"><?= e(__('admin.reply.status')) ?></label>
            <select id="reply_status" name="reply_status">
                <option value=""><?= e(__('admin.reply.status_keep')) ?></option>
                <option value="in_progress"><?= e(rp_status_label('in_progress')) ?></option>
                <option value="done"><?= e(rp_status_label('done')) ?></option>
            </select>
        </div>
        <?php if ($availableChannels): ?>
            <div class="reply-actions">
                <?php foreach ($availableChannels as $index => $channel): ?>
                    <button type="submit" class="btn<?= $index === 0 ? ' btn-primary' : '' ?>"
                            name="reply_channel_btn" value="<?= e($channel) ?>"
                            title="<?= e((string) $targets[$channel]) ?>">
                        <?= e(__('admin.reply.open.' . $channel)) ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <?php if ($targets['telegram'] !== ''): ?>
                <small class="muted"><?= e(__('admin.reply.telegram_hint')) ?></small>
            <?php endif; ?>
        <?php else: ?>
            <p class="muted"><?= e(__('admin.reply.unavailable')) ?></p>
        <?php endif; ?>
    </form>
    <script type="application/json" id="reply-targets"><?= json_encode($targets, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?></script>
    <script>
    (function () {
        var form = document.getElementById('reply-form');
        var raw = document.getElementById('reply-targets');
        var channelInput = document.getElementById('reply_channel');
        if (!form || !raw || !channelInput) return;
        var targets = {};
        try { targets = JSON.parse(raw.textContent || '{}'); } catch (e) { return; }
        var subject = form.getAttribute('data-subject') || '';

        function launchUrl(channel, target, body) {
            if (!target) return '';
            if (channel === 'email') {
                return 'mailto:' + target + '?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(body);
            }
            if (channel === 'telegram') {
                return 'https://t.me/' + encodeURIComponent(String(target).replace(/^@/, ''));
            }
            if (channel === 'whatsapp') {
                return 'https://wa.me/' + target + (body ? '?text=' + encodeURIComponent(body) : '');
            }
            if (channel === 'phone') {
                return 'tel:+' + String(target).replace(/^\+/, '');
            }
            return '';
        }

        function openChannel(url, channel) {
            if (!url) return;
            if (channel === 'telegram') {
                var body = (form.querySelector('#reply_body') || {}).value || '';
                if (body && navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(body).catch(function () {});
                }
            }
            if (channel === 'email' || channel === 'phone') {
                window.location.href = url;
                return;
            }
            var link = document.createElement('a');
            link.href = url;
            link.target = '_blank';
            link.rel = 'noopener';
            document.body.appendChild(link);
            link.click();
            link.remove();
        }

        form.addEventListener('submit', function (event) {
            if (form.getAttribute('data-reply-sent') === '1') return;
            var btn = event.submitter;
            var channel = (btn && btn.name === 'reply_channel_btn') ? btn.value : channelInput.value;
            if (!channel) {
                event.preventDefault();
                return;
            }
            channelInput.value = channel;
            var target = targets[channel] || '';
            var body = (form.querySelector('#reply_body') || {}).value || '';
            var url = launchUrl(channel, target, body);
            event.preventDefault();
            openChannel(url, channel);
            form.setAttribute('data-reply-sent', '1');
            window.setTimeout(function () {
                form.submit();
            }, 250);
        });
    })();
    </script>
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
                        <a href="<?= e(rp_url('admin/feedback-download.php?id=' . (int) $file['id'])) ?>">
                            <?= e((string) $file['original_name']) ?>
                        </a>
                    <?php else: ?>
                        <?= e((string) $file['original_name']) ?>
                        <span class="badge status-rejected"><?= e(__('admin.file_missing')) ?></span>
                    <?php endif; ?>
                    <span class="meta">
                        <?= e(rp_format_bytes((int) $file['size_bytes'])) ?> ·
                        <?= e((string) $file['mime_type']) ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<div class="card">
    <h2><?= e(__('admin.section.manage')) ?></h2>
    <form method="post" action="<?= e(rp_url('admin/feedback-view.php?id=' . $id)) ?>">
        <?= rp_csrf_field() ?>
        <input type="hidden" name="form" value="manage">
        <div class="grid-2">
            <div class="field">
                <label for="status"><?= e(__('admin.filter.status')) ?></label>
                <select id="status" name="status">
                    <?php foreach (rp_statuses() as $status): ?>
                        <option value="<?= e($status) ?>" <?= (string) $feedback['status'] === $status ? 'selected' : '' ?>>
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
            <textarea id="admin_note" name="admin_note" rows="4" maxlength="5000"><?= e((string) ($feedback['admin_note'] ?? '')) ?></textarea>
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
                    <div class="<?= (string) $entry['action'] === 'replied' ? 'history-note' : '' ?>">
                        <?= nl2br(e((string) $entry['note']), false) ?>
                    </div>
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
