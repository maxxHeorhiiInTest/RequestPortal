<?php
/**
 * Admin: create or edit a content-plan announcement.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/auth.php';
require dirname(__DIR__) . '/includes/content.php';
require dirname(__DIR__) . '/includes/layout.php';

$admin = rp_require_admin();
$pdo   = rp_db();

$id      = (int) ($_GET['id'] ?? 0);
$existing = $id > 0 ? rp_find_content($pdo, $id) : null;

if ($id > 0 && $existing === null) {
    rp_flash('error', __('content.not_found'));
    rp_redirect('admin/plan.php');
}

$values = [
    'title'        => $existing ? (string) $existing['title'] : '',
    'event_at'     => $existing ? rp_utc_to_local_input((string) $existing['event_at']) : '',
    'department'   => $existing ? (string) $existing['department'] : '',
    'description'  => $existing ? (string) $existing['description'] : '',
    'responsible'  => $existing ? (string) $existing['responsible'] : '',
    'extra_info'   => $existing ? (string) ($existing['extra_info'] ?? '') : '',
    'status'       => $existing ? (string) $existing['status'] : 'planned',
    'channels'     => $existing ? rp_content_decode_channels($existing['channels'] ?? '') : [],
];

$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!rp_csrf_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = __('error.csrf');
    }

    $values['title']       = rp_clean_string($_POST['title'] ?? '', 255);
    $values['event_at']    = rp_clean_string($_POST['event_at'] ?? '', 32);
    $values['department']  = rp_clean_string($_POST['department'] ?? '', 255);
    $values['description'] = rp_clean_string($_POST['description'] ?? '', 5000);
    $values['responsible'] = rp_clean_string($_POST['responsible'] ?? '', 160);
    $values['extra_info']  = rp_clean_string($_POST['extra_info'] ?? '', 2000);
    $values['status']      = in_array($_POST['status'] ?? '', rp_content_statuses(), true)
        ? (string) $_POST['status']
        : 'draft';
    $values['channels']    = rp_content_posted_channels($_POST['channels'] ?? []);

    $eventUtc = rp_local_input_to_utc($values['event_at']);

    if ($values['title'] === '') {
        $errors[] = __('content.error.title');
    }
    if ($eventUtc === null) {
        $errors[] = __('content.error.when');
    } elseif (!$existing || !rp_utc_same_minute($eventUtc, (string) $existing['event_at'])) {
        if (rp_utc_is_past($eventUtc)) {
            $errors[] = __('content.error.past');
        }
    }
    if ($values['department'] === '') {
        $errors[] = __('content.error.department');
    }
    if ($values['description'] === '') {
        $errors[] = __('content.error.description');
    }
    if ($values['responsible'] === '') {
        $errors[] = __('content.error.responsible');
    }

    if (!$errors && $eventUtc !== null) {
        $channelsJson = json_encode($values['channels'], JSON_UNESCAPED_UNICODE);
        $extra        = $values['extra_info'] !== '' ? $values['extra_info'] : null;

        try {
            if ($existing) {
                $stmt = $pdo->prepare(
                    'UPDATE ' . RP_TABLE_CONTENT . '
                     SET title = :title, event_at = :event_at, department = :department,
                         description = :description, responsible = :responsible,
                         extra_info = :extra_info, channels = :channels, status = :status,
                         updated_at = NOW()
                     WHERE id = :id'
                );
                $stmt->execute([
                    'title'       => $values['title'],
                    'event_at'    => $eventUtc,
                    'department'  => $values['department'],
                    'description' => $values['description'],
                    'responsible' => $values['responsible'],
                    'extra_info'  => $extra,
                    'channels'    => $channelsJson,
                    'status'      => $values['status'],
                    'id'          => $id,
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO ' . RP_TABLE_CONTENT . '
                        (title, event_at, department, description, responsible, extra_info,
                         channels, status, created_by, created_at, updated_at)
                     VALUES
                        (:title, :event_at, :department, :description, :responsible, :extra_info,
                         :channels, :status, :created_by, NOW(), NOW())'
                );
                $stmt->execute([
                    'title'       => $values['title'],
                    'event_at'    => $eventUtc,
                    'department'  => $values['department'],
                    'description' => $values['description'],
                    'responsible' => $values['responsible'],
                    'extra_info'  => $extra,
                    'channels'    => $channelsJson,
                    'status'      => $values['status'],
                    'created_by'  => $admin['username'],
                ]);
                $id = (int) $pdo->lastInsertId();
            }

            rp_flash('success', __('content.saved'));
            rp_redirect('admin/plan-edit.php?id=' . $id);
        } catch (Throwable $exception) {
            error_log('[request-portal] content save failed: ' . $exception->getMessage());
            $errors[] = rp_config('app.debug', false)
                ? $exception->getMessage()
                : __('error.save_failed');
        }
    }
}

$pageTitle = $existing ? __('content.edit') : __('content.new');
$minLocal  = (new DateTimeImmutable('now', rp_app_timezone()))->format('Y-m-d\TH:i');
$lockMin   = !$existing || !rp_utc_is_past((string) $existing['event_at']);
rp_header($pageTitle, 'admin');
?>
<div class="card">
    <h1><?= e($pageTitle) ?></h1>
    <p class="muted"><?= e(__('content.intro')) ?></p>

    <?php if ($errors): ?>
        <div class="alert alert-error">
            <strong><?= e(__('error.form_title')) ?></strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e(rp_url('admin/plan-edit.php' . ($id > 0 ? '?id=' . $id : ''))) ?>">
        <?= rp_csrf_field() ?>

        <div class="field">
            <label for="title"><?= e(__('content.field.title')) ?> *</label>
            <input type="text" id="title" name="title" maxlength="255" required
                   value="<?= e($values['title']) ?>">
        </div>

        <div class="grid-2">
            <div class="field">
                <label for="event_at"><?= e(__('content.field.when')) ?> *</label>
                <input type="datetime-local" id="event_at" name="event_at" required
                       value="<?= e($values['event_at']) ?>"
                       <?= $lockMin ? 'min="' . e($minLocal) . '"' : '' ?>>
            </div>
            <div class="field">
                <label for="status"><?= e(__('content.field.status')) ?></label>
                <select id="status" name="status">
                    <?php foreach (rp_content_statuses() as $status): ?>
                        <option value="<?= e($status) ?>" <?= $values['status'] === $status ? 'selected' : '' ?>>
                            <?= e(rp_content_status_label($status)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="grid-2">
            <div class="field">
                <label for="department"><?= e(__('content.field.department')) ?> *</label>
                <input type="text" id="department" name="department" maxlength="255" required
                       value="<?= e($values['department']) ?>">
            </div>
            <div class="field">
                <label for="responsible"><?= e(__('content.field.responsible')) ?> *</label>
                <input type="text" id="responsible" name="responsible" maxlength="160" required
                       value="<?= e($values['responsible']) ?>">
            </div>
        </div>

        <div class="field">
            <label for="description"><?= e(__('content.field.description')) ?> *</label>
            <textarea id="description" name="description" rows="5" maxlength="5000" required><?= e($values['description']) ?></textarea>
        </div>

        <div class="field">
            <label for="extra_info"><?= e(__('content.field.extra')) ?></label>
            <textarea id="extra_info" name="extra_info" rows="2" maxlength="2000"><?= e($values['extra_info']) ?></textarea>
            <small><?= e(__('content.field.extra_hint')) ?></small>
        </div>

        <fieldset class="field">
            <legend><?= e(__('content.field.channels')) ?></legend>
            <div class="channel-list">
                <?php foreach (rp_content_channels() as $channel): ?>
                    <label class="check">
                        <input type="checkbox" name="channels[]" value="<?= e($channel) ?>"
                            <?= in_array($channel, $values['channels'], true) ? 'checked' : '' ?>>
                        <?= e(rp_content_channel_label($channel)) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <p class="muted"><?= e(__('common.required_hint')) ?></p>
        <button type="submit" class="btn btn-primary"><?= e(__('common.save')) ?></button>
        <a class="btn" href="<?= e(rp_url('admin/plan.php')) ?>"><?= e(__('common.back')) ?></a>
    </form>
</div>
<?php rp_footer();
