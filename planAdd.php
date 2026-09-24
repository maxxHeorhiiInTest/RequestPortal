<?php
/**
 * Public form: add a content-plan announcement without admin login.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/content.php';
require __DIR__ . '/includes/layout.php';

$pdo    = rp_db();
$errors = [];
$sent   = isset($_GET['sent']) && is_string($_GET['sent']) && $_GET['sent'] !== '';

$values = [
    'title'        => '',
    'event_at'     => '',
    'department'   => '',
    'description'  => '',
    'responsible'  => '',
    'extra_info'   => '',
    'channels'     => [],
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!rp_csrf_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = __('error.csrf');
    }

    // Honeypot: treat bots as success without writing.
    if (rp_clean_string($_POST['rp_hp'] ?? '', 100) !== '') {
        rp_redirect('planAdd.php?sent=1');
    }

    $values['title']       = rp_clean_string($_POST['title'] ?? '', 255);
    $values['event_at']    = rp_clean_string($_POST['event_at'] ?? '', 32);
    $values['department']  = rp_clean_string($_POST['department'] ?? '', 255);
    $values['description'] = rp_clean_string($_POST['description'] ?? '', 5000);
    $values['responsible'] = rp_clean_string($_POST['responsible'] ?? '', 160);
    $values['extra_info']  = rp_clean_string($_POST['extra_info'] ?? '', 2000);
    $values['channels']    = rp_content_posted_channels($_POST['channels'] ?? []);

    $eventUtc = rp_local_input_to_utc($values['event_at']);

    if ($values['title'] === '') {
        $errors[] = __('content.error.title');
    }
    if ($eventUtc === null) {
        $errors[] = __('content.error.when');
    } elseif (rp_utc_is_past($eventUtc)) {
        $errors[] = __('content.error.past');
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

    $rateLimit = (int) rp_config('security.rate_limit_per_hour', 0);
    if (!$errors && $rateLimit > 0 && rp_recent_guest_content_count($pdo, rp_client_ip()) >= $rateLimit) {
        $errors[] = __('error.rate_limit');
    }

    if (!$errors && $eventUtc !== null) {
        $channelsJson = json_encode($values['channels'], JSON_UNESCAPED_UNICODE);
        $extra        = $values['extra_info'] !== '' ? $values['extra_info'] : null;

        try {
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
                'status'      => 'draft',
                'created_by'  => 'guest:' . rp_client_ip(),
            ]);

            unset($_SESSION['rp_csrf']);

            $channelNames = [];
            foreach ($values['channels'] as $channel) {
                $channelNames[] = rp_content_channel_label((string) $channel);
            }

            rp_telegram_notify('plan', [
                __('content.field.title')       => $values['title'],
                __('content.field.when')        => $eventUtc !== null ? rp_format_datetime($eventUtc) : $values['event_at'],
                __('content.field.department')  => $values['department'],
                __('content.field.responsible') => $values['responsible'],
                __('content.field.description') => $values['description'],
                __('content.field.extra')       => $values['extra_info'],
                __('content.field.channels')    => implode(', ', $channelNames),
                __('content.field.status')      => rp_content_status_label('draft'),
            ]);

            rp_redirect('planAdd.php?sent=1');
        } catch (Throwable $exception) {
            error_log('[request-portal] public content save failed: ' . $exception->getMessage());
            $errors[] = rp_config('app.debug', false)
                ? $exception->getMessage()
                : __('error.save_failed');
        }
    }
}

$minLocal = (new DateTimeImmutable('now', rp_app_timezone()))->format('Y-m-d\TH:i');
rp_header(__('content.public.title'));
?>

<?php if ($sent): ?>
    <div class="card success-card">
        <h1><?= e(__('content.public.success')) ?></h1>
        <p><?= e(__('content.public.success_text')) ?></p>
        <p>
            <a class="btn btn-primary" href="<?= e(rp_url('planAdd.php')) ?>"><?= e(__('content.public.another')) ?></a>
            <a class="btn" href="<?= e(rp_url('index.php')) ?>"><?= e(__('nav.home')) ?></a>
        </p>
    </div>
<?php else: ?>
    <div class="card">
        <div class="page-head">
            <h1><?= e(__('content.public.title')) ?></h1>
            <?php rp_page_tip('content.public.page_tip', 'assets/help/plan.mp4', 'assets/help/plan.jpg'); ?>
        </div>
        <p class="muted"><?= e(__('content.public.intro')) ?></p>

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

        <form method="post" action="<?= e(rp_url('planAdd.php')) ?>">
            <?= rp_csrf_field() ?>
            <p class="honeypot">
                <label>Website<input type="text" name="rp_hp" tabindex="-1" autocomplete="off"></label>
            </p>

            <div class="field">
                <label for="title"><?= e(__('content.field.title')) ?> *</label>
                <input type="text" id="title" name="title" maxlength="255" required
                       value="<?= e($values['title']) ?>">
            </div>

            <div class="field">
                <label for="event_at"><?= e(__('content.field.when')) ?> *</label>
                <input type="datetime-local" id="event_at" name="event_at" required
                       value="<?= e($values['event_at']) ?>"
                       min="<?= e($minLocal) ?>">
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
            <button type="submit" class="btn btn-primary"><?= e(__('common.submit_plan')) ?></button>
            <a class="btn" href="<?= e(rp_url('index.php')) ?>"><?= e(__('common.back')) ?></a>
        </form>
    </div>
<?php endif; ?>

<?php rp_footer();
