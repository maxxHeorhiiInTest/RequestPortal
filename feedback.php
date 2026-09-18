<?php
/**
 * Public question / suggestion form.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/uploads.php';
require __DIR__ . '/includes/layout.php';

$errors = [];
$values = [
    'message'    => '',
    'faculty'    => '',
    'department' => '',
    'name'       => '',
    'phone'      => '',
    'channel'    => '',
    'contact'    => '',
];

$maxFiles  = (int) rp_config('uploads.max_files', 10);
$maxSize   = rp_effective_file_limit();
$submitted = null;

$isPost         = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$postOverflowed = $isPost && empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;

if ($postOverflowed) {
    $errors[] = __('error.post_too_large', rp_format_bytes(rp_php_upload_limit()));
} elseif ($isPost) {
    $values['message']    = rp_clean_string($_POST['message'] ?? '', 20000);
    $values['faculty']    = rp_clean_string($_POST['faculty'] ?? '', 255);
    $values['department'] = rp_clean_string($_POST['department'] ?? '', 255);
    $values['name']       = rp_clean_string($_POST['name'] ?? '', 160);
    $values['phone']      = rp_clean_string($_POST['phone'] ?? '', 80);
    $values['channel']    = rp_clean_string($_POST['channel'] ?? '', 20);
    $values['contact']    = rp_clean_string($_POST['contact'] ?? '', 255);

    if (!rp_csrf_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = __('error.csrf');
    }
    if ($values['message'] === '') {
        $errors[] = __('feedback.error.message');
    }
    if ($values['faculty'] === '') {
        $errors[] = __('error.faculty');
    }
    if ($values['department'] === '') {
        $errors[] = __('error.department');
    }
    if ($values['name'] === '') {
        $errors[] = __('error.name');
    }
    if ($values['phone'] === '') {
        $errors[] = __('error.phone');
    } elseif (!rp_phone_looks_valid($values['phone'])) {
        $errors[] = __('error.phone_invalid');
    }
    if (!in_array($values['channel'], rp_messenger_channels(), true)) {
        $errors[] = __('error.channel');
    } elseif ($values['contact'] === '') {
        $errors[] = __('error.channel_contact');
    } elseif (!rp_contact_matches_channel($values['channel'], $values['contact'])) {
        $errors[] = __('error.channel_contact.' . $values['channel']);
    }

    if (rp_clean_string($_POST['website'] ?? '', 100) !== '') {
        rp_redirect('feedback.php?sent=' . urlencode(rp_generate_code('QST')));
    }

    $files  = rp_collect_uploads();
    $errors = array_merge($errors, rp_validate_uploads($files));

    $rateLimit = (int) rp_config('security.rate_limit_per_hour', 0);
    if (!$errors && $rateLimit > 0 && rp_recent_feedback_count(rp_db(), rp_client_ip()) >= $rateLimit) {
        $errors[] = __('error.rate_limit');
    }

    if (!$errors) {
        $pdo    = rp_db();
        $stored = [];

        try {
            $pdo->beginTransaction();

            for ($attempt = 1; ; $attempt++) {
                $code = rp_generate_code('QST');
                try {
                    $stmt = $pdo->prepare(
                        'INSERT INTO ' . RP_TABLE_FEEDBACK . '
                            (public_code, message, faculty, department, requester_name, requester_phone,
                             requester_contact, requester_channel, status, lang, ip_address, created_at, updated_at)
                         VALUES (:code, :message, :faculty, :department, :name, :phone,
                                 :contact, :channel, \'new\', :lang, :ip, NOW(), NOW())'
                    );
                    $stmt->execute([
                        'code'       => $code,
                        'message'    => $values['message'],
                        'faculty'    => $values['faculty'],
                        'department' => $values['department'],
                        'name'       => $values['name'],
                        'phone'      => $values['phone'],
                        'contact'    => $values['contact'],
                        'channel'    => $values['channel'],
                        'lang'       => rp_lang(),
                        'ip'         => rp_client_ip(),
                    ]);
                    break;
                } catch (PDOException $exception) {
                    if ($attempt >= 5 || $exception->getCode() !== '23000') {
                        throw $exception;
                    }
                }
            }

            $feedbackId = (int) $pdo->lastInsertId();
            rp_log_feedback_history($pdo, $feedbackId, 'created', null, null, null, $values['name']);
            $stored = rp_store_feedback_uploads($pdo, $feedbackId, $code, $files);

            $pdo->commit();
            unset($_SESSION['rp_csrf']);
            rp_redirect('feedback.php?sent=' . urlencode($code));
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            rp_delete_stored_files($stored);
            error_log('[request-portal] feedback submit failed: ' . $exception->getMessage());
            $errors[] = rp_config('app.debug', false)
                ? $exception->getMessage()
                : __('error.save_failed');
        }
    }
}

if (isset($_GET['sent']) && is_string($_GET['sent'])) {
    $submitted = rp_clean_string($_GET['sent'], 20);
}

rp_header(__('feedback.title'));
?>

<?php if ($submitted !== null && $submitted !== ''): ?>
    <div class="card success-card">
        <h1><?= e(__('success.title')) ?></h1>
        <p><?= e(__('feedback.success.text', $submitted)) ?></p>
        <p>
            <a class="btn btn-primary" href="<?= e(rp_url('feedback.php')) ?>"><?= e(__('feedback.success.new')) ?></a>
            <a class="btn" href="<?= e(rp_url('index.php')) ?>"><?= e(__('nav.home')) ?></a>
        </p>
    </div>
<?php else: ?>
    <div class="card">
        <div class="page-head">
            <h1><?= e(__('feedback.title')) ?></h1>
            <?php rp_page_tip('feedback.page_tip', 'assets/help/feedback.mp4', 'assets/help/feedback.jpg'); ?>
        </div>
        <p class="muted"><?= e(__('feedback.intro')) ?></p>

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

        <form method="post" action="<?= e(rp_url('feedback.php')) ?>" enctype="multipart/form-data" novalidate>
            <?= rp_csrf_field() ?>
            <p class="honeypot">
                <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </p>

            <div class="field">
                <label for="message"><?= e(__('feedback.message')) ?> *</label>
                <textarea id="message" name="message" rows="7" maxlength="20000" required><?= e($values['message']) ?></textarea>
                <small><?= e(__('feedback.message_hint')) ?></small>
            </div>

            <div class="field">
                <label for="attachments"><?= e(__('form.files')) ?></label>
                <input type="file" id="attachments" name="attachments[]" multiple
                       accept="<?= e(rp_accept_attribute()) ?>">
                <small><?= e(__('form.files_hint', $maxFiles, rp_format_bytes($maxSize), implode(', ', rp_allowed_extensions()))) ?></small>
                <small id="file-count" class="muted"></small>
            </div>

            <div class="grid-2">
                <div class="field">
                    <label for="faculty"><?= e(__('form.faculty')) ?> *</label>
                    <input type="text" id="faculty" name="faculty" maxlength="255" required value="<?= e($values['faculty']) ?>">
                </div>
                <div class="field">
                    <label for="department"><?= e(__('form.department')) ?> *</label>
                    <input type="text" id="department" name="department" maxlength="255" required value="<?= e($values['department']) ?>">
                </div>
            </div>

            <div class="field">
                <label for="name"><?= e(__('form.name')) ?> *</label>
                <input type="text" id="name" name="name" maxlength="160" required value="<?= e($values['name']) ?>">
            </div>

            <div class="field">
                <label for="phone"><?= e(__('form.phone')) ?> *</label>
                <input type="tel" id="phone" name="phone" maxlength="80" required
                       autocomplete="tel" value="<?= e($values['phone']) ?>">
                <small><?= e(__('form.phone_hint')) ?></small>
            </div>

            <fieldset class="field">
                <legend><?= e(__('form.channel')) ?> *</legend>
                <div class="channel-picks">
                    <?php foreach (rp_messenger_channels() as $channel): ?>
                        <label class="radio">
                            <input type="radio" name="channel" value="<?= e($channel) ?>"
                                <?= $values['channel'] === $channel ? 'checked' : '' ?>>
                            <span><?= e(rp_channel_label($channel)) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <div class="field">
                <label for="contact" id="contact-label"><?= e(__('form.channel_contact')) ?> *</label>
                <input type="text" id="contact" name="contact" maxlength="255" required
                       value="<?= e($values['contact']) ?>">
                <small id="contact-hint"><?= e(__('form.channel_contact_hint')) ?></small>
            </div>

            <p class="muted"><?= e(__('common.required_hint')) ?></p>
            <button type="submit" class="btn btn-primary"><?= e(__('common.submit_feedback')) ?></button>
        </form>
    </div>
    <script>
        (function () {
            var input = document.getElementById('attachments');
            var label = document.getElementById('file-count');
            if (input && label) {
                input.addEventListener('change', function () {
                    label.textContent = input.files.length
                        ? <?= json_encode(__('form.files_selected'), JSON_UNESCAPED_UNICODE) ?>.replace('%d', input.files.length)
                        : '';
                });
            }

            var form = document.querySelector('form');
            if (!form) return;
            var phone = document.getElementById('phone');
            var contact = document.getElementById('contact');
            var contactLabel = document.getElementById('contact-label');
            var contactHint = document.getElementById('contact-hint');
            var copy = <?= json_encode([
                'email' => [
                    'label' => __('form.channel_contact.email') . ' *',
                    'hint'  => __('form.channel_hint.email'),
                    'type'  => 'email',
                ],
                'telegram' => [
                    'label' => __('form.channel_contact.telegram') . ' *',
                    'hint'  => __('form.channel_hint.telegram'),
                    'type'  => 'text',
                ],
                'whatsapp' => [
                    'label' => __('form.channel_contact.whatsapp') . ' *',
                    'hint'  => __('form.channel_hint.whatsapp'),
                    'type'  => 'tel',
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>;
            var lastChannel = '';
            function selectedChannel() {
                var checked = form.querySelector('input[name="channel"]:checked');
                return checked ? checked.value : '';
            }
            function syncChannel() {
                var channel = selectedChannel();
                var meta = copy[channel];
                if (!meta || !contact || !contactLabel || !contactHint) return;
                contactLabel.textContent = meta.label;
                contactHint.textContent = meta.hint;
                contact.type = meta.type;
                contact.setAttribute('placeholder', meta.hint);
                if (channel === 'whatsapp' && phone && !contact.value && phone.value) {
                    contact.value = phone.value;
                }
            }
            form.querySelectorAll('input[name="channel"]').forEach(function (radio) {
                radio.addEventListener('change', syncChannel);
            });
            syncChannel();
        })();
    </script>
<?php endif; ?>

<?php rp_footer();
