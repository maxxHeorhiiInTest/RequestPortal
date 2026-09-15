<?php
/**
 * Public request form: create a "new publication" or "update" request.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/uploads.php';
require __DIR__ . '/includes/layout.php';

$errors = [];
$values = [
    'type'        => 'new',
    'target'      => '',
    'description' => '',
    'comment'     => '',
    'name'        => '',
    'contact'     => '',
];

$maxFiles   = (int) rp_config('uploads.max_files', 10);
$maxSize    = rp_effective_file_limit();
$submitted  = null;

/*
 * When the upload exceeds post_max_size PHP delivers an empty $_POST, so the
 * request method is the only reliable signal that something was sent.
 */
$isPost         = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$postOverflowed = $isPost && empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;

if ($postOverflowed) {
    $errors[] = __('error.post_too_large', rp_format_bytes(rp_php_upload_limit()));
} elseif ($isPost) {
    $values['type']        = in_array($_POST['type'] ?? '', rp_request_types(), true) ? (string) $_POST['type'] : '';
    $values['target']      = rp_clean_string($_POST['target'] ?? '', 1000);
    $values['description'] = rp_clean_string($_POST['description'] ?? '', 20000);
    $values['comment']     = rp_clean_string($_POST['comment'] ?? '', 5000);
    $values['name']        = rp_clean_string($_POST['name'] ?? '', 160);
    $values['contact']     = rp_clean_string($_POST['contact'] ?? '', 255);

    if (!rp_csrf_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = __('error.csrf');
    }
    if ($values['type'] === '') {
        $errors[] = __('error.type');
    }
    if ($values['target'] === '') {
        $errors[] = __('error.target');
    }
    if ($values['description'] === '') {
        $errors[] = __('error.description');
    }
    if ($values['name'] === '') {
        $errors[] = __('error.name');
    }
    if ($values['contact'] === '') {
        $errors[] = __('error.contact');
    } elseif (mb_strlen($values['contact']) < 4) {
        $errors[] = __('error.contact_invalid');
    }

    // Honeypot: a filled-in hidden field means a bot, answer as if it worked.
    if (rp_clean_string($_POST['website'] ?? '', 100) !== '') {
        rp_redirect('index.php?sent=' . urlencode(rp_generate_code()));
    }

    $files  = rp_collect_uploads();
    $errors = array_merge($errors, rp_validate_uploads($files));

    $rateLimit = (int) rp_config('security.rate_limit_per_hour', 0);
    if (!$errors && $rateLimit > 0 && rp_recent_request_count(rp_db(), rp_client_ip()) >= $rateLimit) {
        $errors[] = __('error.rate_limit');
    }

    if (!$errors) {
        $pdo    = rp_db();
        $stored = [];

        try {
            $pdo->beginTransaction();

            // Retry on the (very unlikely) duplicate public code.
            for ($attempt = 1; ; $attempt++) {
                $code = rp_generate_code();
                try {
                    $stmt = $pdo->prepare(
                        'INSERT INTO ' . RP_TABLE_REQUESTS . '
                            (public_code, type, target_location, description, extra_comment,
                             requester_name, requester_contact, status, lang, ip_address, user_agent,
                             created_at, updated_at)
                         VALUES (:code, :type, :target, :description, :comment,
                                 :name, :contact, \'new\', :lang, :ip, :ua, NOW(), NOW())'
                    );
                    $stmt->execute([
                        'code'        => $code,
                        'type'        => $values['type'],
                        'target'      => $values['target'],
                        'description' => $values['description'],
                        'comment'     => $values['comment'] !== '' ? $values['comment'] : null,
                        'name'        => $values['name'],
                        'contact'     => $values['contact'],
                        'lang'        => rp_lang(),
                        'ip'          => rp_client_ip(),
                        'ua'          => rp_user_agent(),
                    ]);
                    break;
                } catch (PDOException $exception) {
                    if ($attempt >= 5 || $exception->getCode() !== '23000') {
                        throw $exception;
                    }
                }
            }

            $requestId = (int) $pdo->lastInsertId();
            rp_log_history($pdo, $requestId, 'created', null, null, null, $values['name']);
            $stored = rp_store_uploads($pdo, $requestId, $code, $files);

            $pdo->commit();
            unset($_SESSION['rp_csrf']);

            rp_redirect('index.php?sent=' . urlencode($code));
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            rp_delete_stored_files($stored);
            error_log('[request-portal] submit failed: ' . $exception->getMessage());
            $errors[] = rp_config('app.debug', false)
                ? $exception->getMessage()
                : __('error.save_failed');
        }
    }
}

if (isset($_GET['sent']) && is_string($_GET['sent'])) {
    $submitted = rp_clean_string($_GET['sent'], 20);
}

rp_header(__('form.title'));
?>

<?php if ($submitted !== null && $submitted !== ''): ?>
    <div class="card success-card">
        <h1><?= e(__('success.title')) ?></h1>
        <p><?= e(__('success.text', $submitted)) ?></p>
        <p><a class="btn" href="<?= e(rp_url('index.php')) ?>"><?= e(__('success.new_one')) ?></a></p>
    </div>
<?php else: ?>
    <div class="card">
        <h1><?= e(__('form.title')) ?></h1>
        <p class="muted"><?= e(__('form.intro')) ?></p>

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

        <form method="post" action="<?= e(rp_url('index.php')) ?>" enctype="multipart/form-data" novalidate>
            <?= rp_csrf_field() ?>
            <p class="honeypot">
                <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </p>

            <fieldset class="field">
                <legend><?= e(__('form.type')) ?> *</legend>
                <?php foreach (rp_request_types() as $type): ?>
                    <label class="radio">
                        <input type="radio" name="type" value="<?= e($type) ?>"
                            <?= $values['type'] === $type ? 'checked' : '' ?>>
                        <span>
                            <strong><?= e(rp_type_label($type)) ?></strong>
                            <small><?= e(__('form.type.' . $type . '_hint')) ?></small>
                        </span>
                    </label>
                <?php endforeach; ?>
            </fieldset>

            <div class="field">
                <label for="target"><?= e(__('form.target')) ?> *</label>
                <input type="text" id="target" name="target" maxlength="1000"
                       placeholder="<?= e(__('form.target_placeholder')) ?>"
                       value="<?= e($values['target']) ?>">
                <small><?= e(__('form.target_hint')) ?></small>
            </div>

            <div class="field">
                <label for="description"><?= e(__('form.description')) ?> *</label>
                <textarea id="description" name="description" rows="7" maxlength="20000"><?= e($values['description']) ?></textarea>
                <small><?= e(__('form.description_hint')) ?></small>
            </div>

            <div class="field">
                <label for="comment"><?= e(__('form.comment')) ?></label>
                <textarea id="comment" name="comment" rows="3" maxlength="5000"><?= e($values['comment']) ?></textarea>
                <small><?= e(__('form.comment_hint')) ?></small>
            </div>

            <div class="grid-2">
                <div class="field">
                    <label for="name"><?= e(__('form.name')) ?> *</label>
                    <input type="text" id="name" name="name" maxlength="160" value="<?= e($values['name']) ?>">
                </div>
                <div class="field">
                    <label for="contact"><?= e(__('form.contact')) ?> *</label>
                    <input type="text" id="contact" name="contact" maxlength="255" value="<?= e($values['contact']) ?>">
                    <small><?= e(__('form.contact_hint')) ?></small>
                </div>
            </div>

            <div class="field">
                <label for="attachments"><?= e(__('form.files')) ?></label>
                <input type="file" id="attachments" name="attachments[]" multiple
                       accept="<?= e(rp_accept_attribute()) ?>">
                <small><?= e(__('form.files_hint', $maxFiles, rp_format_bytes($maxSize), implode(', ', rp_allowed_extensions()))) ?></small>
                <small id="file-count" class="muted"></small>
            </div>

            <p class="muted"><?= e(__('common.required_hint')) ?></p>
            <button type="submit" class="btn btn-primary"><?= e(__('common.submit')) ?></button>
        </form>
    </div>

    <script>
        (function () {
            var input = document.getElementById('attachments');
            var label = document.getElementById('file-count');
            if (!input || !label) return;
            input.addEventListener('change', function () {
                label.textContent = input.files.length
                    ? <?= json_encode(__('form.files_selected'), JSON_UNESCAPED_UNICODE) ?>.replace('%d', input.files.length)
                    : '';
            });
        })();
    </script>
<?php endif; ?>

<?php rp_footer();
