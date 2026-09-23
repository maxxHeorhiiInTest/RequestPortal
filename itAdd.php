<?php
/**
 * Public IT support request form.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$errors = [];
$values = [
    'category' => '',
    'name'     => '',
    'phone'    => '',
    'building' => '',
    'room'     => '',
    'message'  => '',
];

$submitted = null;

$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

if ($isPost) {
    $values['category'] = rp_clean_string($_POST['category'] ?? '', 20);
    $values['name']     = rp_clean_string($_POST['name'] ?? '', 160);
    $values['phone']    = rp_clean_string($_POST['phone'] ?? '', 80);
    $values['building'] = rp_clean_string($_POST['building'] ?? '', 80);
    $values['room']     = rp_clean_string($_POST['room'] ?? '', 80);
    $values['message']  = rp_clean_string($_POST['message'] ?? '', 2000);

    if (!rp_csrf_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = __('error.csrf');
    }
    if (!in_array($values['category'], rp_it_categories(), true)) {
        $errors[] = __('it.error.category');
    }
    if ($values['name'] === '') {
        $errors[] = __('it.error.name');
    }
    if ($values['phone'] === '') {
        $errors[] = __('error.phone');
    } elseif (!rp_phone_looks_valid($values['phone'])) {
        $errors[] = __('error.phone_invalid');
    }
    if ($values['building'] === '') {
        $errors[] = __('it.error.building');
    }
    if ($values['room'] === '') {
        $errors[] = __('it.error.room');
    }
    if ($values['message'] === '') {
        $errors[] = __('it.error.message');
    }

    if (rp_clean_string($_POST['website'] ?? '', 100) !== '') {
        rp_redirect('itAdd.php?sent=' . urlencode(rp_generate_code('IT')));
    }

    $rateLimit = (int) rp_config('security.rate_limit_per_hour', 0);
    if (!$errors && $rateLimit > 0 && rp_recent_it_count(rp_db(), rp_client_ip()) >= $rateLimit) {
        $errors[] = __('error.rate_limit');
    }

    if (!$errors) {
        $pdo = rp_db();

        try {
            $pdo->beginTransaction();

            for ($attempt = 1; ; $attempt++) {
                $code = rp_generate_code('IT');
                try {
                    $stmt = $pdo->prepare(
                        'INSERT INTO ' . RP_TABLE_IT . '
                            (public_code, category, description, requester_name, requester_phone,
                             building, room, status, lang, ip_address, created_at, updated_at)
                         VALUES (:code, :category, :message, :name, :phone,
                                 :building, :room, \'new\', :lang, :ip, NOW(), NOW())'
                    );
                    $stmt->execute([
                        'code'     => $code,
                        'category' => $values['category'],
                        'message'  => $values['message'],
                        'name'     => $values['name'],
                        'phone'    => $values['phone'],
                        'building' => $values['building'],
                        'room'     => $values['room'],
                        'lang'     => rp_lang(),
                        'ip'       => rp_client_ip(),
                    ]);
                    break;
                } catch (PDOException $exception) {
                    if ($attempt >= 5 || $exception->getCode() !== '23000') {
                        throw $exception;
                    }
                }
            }

            $ticketId = (int) $pdo->lastInsertId();
            rp_log_it_history($pdo, $ticketId, 'created', null, null, null, $values['name']);

            $pdo->commit();
            unset($_SESSION['rp_csrf']);
            rp_redirect('itAdd.php?sent=' . urlencode($code));
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[request-portal] IT submit failed: ' . $exception->getMessage());
            $errors[] = rp_config('app.debug', false)
                ? $exception->getMessage()
                : __('error.save_failed');
        }
    }
}

if (isset($_GET['sent']) && is_string($_GET['sent'])) {
    $submitted = rp_clean_string($_GET['sent'], 20);
}

rp_header(__('it.title'));
?>

<?php if ($submitted !== null && $submitted !== ''): ?>
    <div class="card success-card">
        <h1><?= e(__('success.title')) ?></h1>
        <p><?= e(__('it.success.text', $submitted)) ?></p>
        <p>
            <a class="btn btn-primary" href="<?= e(rp_url('itAdd.php')) ?>"><?= e(__('it.success.new')) ?></a>
            <a class="btn" href="<?= e(rp_url('index.php')) ?>"><?= e(__('nav.home')) ?></a>
        </p>
    </div>
<?php else: ?>
    <div class="card">
        <div class="page-head">
            <h1><?= e(__('it.title')) ?></h1>
            <?php rp_page_tip('it.page_tip', 'assets/help/it.mp4', 'assets/help/it.jpg'); ?>
        </div>
        <p class="muted"><?= e(__('it.intro')) ?></p>

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

        <form method="post" action="<?= e(rp_url('itAdd.php')) ?>" novalidate>
            <?= rp_csrf_field() ?>
            <p class="honeypot">
                <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </p>

            <fieldset class="field">
                <legend><?= e(__('it.category')) ?> *</legend>
                <div class="channel-picks">
                    <?php foreach (rp_it_categories() as $category): ?>
                        <label class="radio">
                            <input type="radio" name="category" value="<?= e($category) ?>"
                                <?= $values['category'] === $category ? 'checked' : '' ?>>
                            <span><?= e(rp_it_category_label($category)) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <div class="field">
                <label for="name"><?= e(__('it.name')) ?> *</label>
                <input type="text" id="name" name="name" maxlength="160" required
                       autocomplete="name" value="<?= e($values['name']) ?>">
            </div>

            <div class="field">
                <label for="phone"><?= e(__('it.phone')) ?> *</label>
                <input type="tel" id="phone" name="phone" maxlength="80" required
                       autocomplete="tel" value="<?= e($values['phone']) ?>">
                <small><?= e(__('form.phone_hint')) ?></small>
            </div>

            <div class="grid-2">
                <div class="field">
                    <label for="building"><?= e(__('it.building')) ?> *</label>
                    <input type="text" id="building" name="building" maxlength="80" required
                           value="<?= e($values['building']) ?>">
                </div>
                <div class="field">
                    <label for="room"><?= e(__('it.room')) ?> *</label>
                    <input type="text" id="room" name="room" maxlength="80" required
                           value="<?= e($values['room']) ?>">
                </div>
            </div>

            <div class="field">
                <label for="message"><?= e(__('it.message')) ?> *</label>
                <textarea id="message" name="message" rows="6" maxlength="2000" required><?= e($values['message']) ?></textarea>
                <small><?= e(__('it.message_hint')) ?></small>
            </div>

            <p class="muted"><?= e(__('common.required_hint')) ?></p>
            <button type="submit" class="btn btn-primary"><?= e(__('common.submit_it')) ?></button>
        </form>
    </div>
<?php endif; ?>

<?php rp_footer();
