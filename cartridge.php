<?php
/**
 * Public cartridge refill form, status lookup and print blank.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
require __DIR__ . '/includes/cartridge.php';

$errors = [];
$values = [
    'building'         => '',
    'room'             => '',
    'printer_model'    => '',
    'cartridge_model'  => '',
    'name'             => '',
    'phone'            => '',
];
$lookupError = '';
$ticket      = null;

$codeParam = rp_clean_string($_GET['code'] ?? '', 20);
if ($codeParam !== '') {
    $ticket = rp_find_cartridge_by_code(rp_db(), $codeParam, true);
    if ($ticket === null) {
        $lookupError = __('cartridge.not_found');
    }
}

$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$action = rp_clean_string($_POST['action'] ?? '', 20);

if ($isPost && $action === 'lookup') {
    $code = strtoupper(rp_clean_string($_POST['code'] ?? '', 20));
    if ($code === '') {
        $lookupError = __('cartridge.error.code');
    } else {
        rp_redirect('cartridge.php?code=' . rawurlencode($code));
    }
}

if ($isPost && $action === 'create') {
    $values['building']        = rp_clean_string($_POST['building'] ?? '', 80);
    $values['room']            = rp_clean_string($_POST['room'] ?? '', 80);
    $values['printer_model']   = rp_clean_string($_POST['printer_model'] ?? '', 160);
    $values['cartridge_model'] = rp_clean_string($_POST['cartridge_model'] ?? '', 160);
    $values['name']            = rp_clean_string($_POST['name'] ?? '', 160);
    $values['phone']           = rp_clean_string($_POST['phone'] ?? '', 80);

    if (!rp_csrf_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = __('error.csrf');
    }
    if ($values['building'] === '') {
        $errors[] = __('cartridge.error.building');
    }
    if ($values['room'] === '') {
        $errors[] = __('cartridge.error.room');
    }
    if ($values['printer_model'] === '') {
        $errors[] = __('cartridge.error.printer');
    }
    if ($values['cartridge_model'] === '') {
        $errors[] = __('cartridge.error.cartridge');
    }
    if ($values['phone'] !== '' && !rp_phone_looks_valid($values['phone'])) {
        $errors[] = __('error.phone_invalid');
    }

    if (rp_clean_string($_POST['website'] ?? '', 100) !== '') {
        rp_redirect('cartridge.php?code=' . urlencode(rp_generate_code('CRG')));
    }

    $rateLimit = (int) rp_config('security.rate_limit_per_hour', 0);
    if (!$errors && $rateLimit > 0 && rp_recent_cartridge_count(rp_db(), rp_client_ip()) >= $rateLimit) {
        $errors[] = __('error.rate_limit');
    }

    if (!$errors) {
        $pdo = rp_db();
        try {
            $pdo->beginTransaction();
            for ($attempt = 1; ; $attempt++) {
                $code = rp_generate_code('CRG');
                try {
                    $stmt = $pdo->prepare(
                        'INSERT INTO ' . RP_TABLE_CARTRIDGE . '
                            (public_code, building, room, printer_model, cartridge_model,
                             requester_name, requester_phone, status, lang, ip_address, created_at, updated_at)
                         VALUES (:code, :building, :room, :printer, :cartridge,
                                 :name, :phone, \'new\', :lang, :ip, NOW(), NOW())'
                    );
                    $stmt->execute([
                        'code'      => $code,
                        'building'  => $values['building'],
                        'room'      => $values['room'],
                        'printer'   => $values['printer_model'],
                        'cartridge' => $values['cartridge_model'],
                        'name'      => $values['name'],
                        'phone'     => $values['phone'],
                        'lang'      => rp_lang(),
                        'ip'        => rp_client_ip(),
                    ]);
                    break;
                } catch (PDOException $exception) {
                    if ($attempt >= 5 || $exception->getCode() !== '23000') {
                        throw $exception;
                    }
                }
            }

            $id = (int) $pdo->lastInsertId();
            rp_log_cartridge_history($pdo, $id, 'created', null, 'new', null, $values['name'] !== '' ? $values['name'] : 'guest');

            $row = rp_find_cartridge($pdo, $id);
            if ($row === null) {
                throw new RuntimeException('Cartridge row missing after insert');
            }
            $rel = rp_cartridge_write_pdf($row);
            $upd = $pdo->prepare('UPDATE ' . RP_TABLE_CARTRIDGE . ' SET pdf_path = :pdf WHERE id = :id');
            $upd->execute(['pdf' => $rel, 'id' => $id]);

            $pdo->commit();
            unset($_SESSION['rp_csrf']);
            rp_redirect('cartridge.php?code=' . urlencode($code));
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[request-portal] cartridge submit failed: ' . $exception->getMessage());
            $errors[] = rp_config('app.debug', false)
                ? $exception->getMessage()
                : __('error.save_failed');
        }
    }
}

rp_header(__('cartridge.title'));

if ($ticket !== null):
    $code = (string) $ticket['public_code'];
    $url  = rp_cartridge_status_url($code);
    ?>
    <div class="card cartridge-blank">
        <div class="page-head no-print">
            <h1><?= e(__('cartridge.blank_title')) ?></h1>
        </div>
        <div class="cartridge-letterhead">
            <img src="<?= e(rp_url('assets/logo-nufvsu.png')) ?>" alt="<?= e(__('home.logo_alt')) ?>">
            <div>
                <strong><?= e(__('home.logo_alt')) ?></strong>
                <div><?= e(__('cartridge.blank_title')) ?></div>
            </div>
        </div>
        <p class="cartridge-code"><?= e($code) ?></p>
        <dl class="details">
            <dt><?= e(__('cartridge.building')) ?></dt>
            <dd><?= e((string) $ticket['building']) ?></dd>
            <dt><?= e(__('cartridge.room')) ?></dt>
            <dd><?= e((string) $ticket['room']) ?></dd>
            <dt><?= e(__('cartridge.printer')) ?></dt>
            <dd><?= e((string) $ticket['printer_model']) ?></dd>
            <dt><?= e(__('cartridge.cartridge')) ?></dt>
            <dd><?= e((string) $ticket['cartridge_model']) ?></dd>
            <dt><?= e(__('cartridge.date')) ?></dt>
            <dd><?= e(rp_format_datetime((string) $ticket['created_at'])) ?></dd>
            <dt><?= e(__('cartridge.status')) ?></dt>
            <dd>
                <span class="badge status-<?= e((string) $ticket['status']) ?>">
                    <?= e(rp_cartridge_status_label((string) $ticket['status'])) ?>
                </span>
            </dd>
            <?php if (trim((string) $ticket['requester_name']) !== ''): ?>
                <dt><?= e(__('cartridge.name')) ?></dt>
                <dd><?= e((string) $ticket['requester_name']) ?></dd>
            <?php endif; ?>
            <?php if (trim((string) $ticket['requester_phone']) !== ''): ?>
                <dt><?= e(__('cartridge.phone')) ?></dt>
                <dd><?= e((string) $ticket['requester_phone']) ?></dd>
            <?php endif; ?>
        </dl>
        <div class="cartridge-qr">
            <?= rp_cartridge_qr_svg($url, 168) ?>
            <div class="cartridge-qr-code"><?= e($code) ?></div>
        </div>
        <p class="no-print cartridge-actions">
            <button type="button" class="btn btn-primary" onclick="window.print()"><?= e(__('cartridge.print')) ?></button>
            <a class="btn" href="<?= e(rp_url('cartridge-pdf.php?code=' . rawurlencode($code))) ?>"><?= e(__('cartridge.download_pdf')) ?></a>
            <a class="btn" href="<?= e(rp_url('cartridge.php')) ?>"><?= e(__('cartridge.success.new')) ?></a>
        </p>
    </div>
<?php else: ?>
    <div class="card">
        <div class="page-head">
            <h1><?= e(__('cartridge.title')) ?></h1>
            <?php rp_page_tip('cartridge.page_tip', 'assets/help/cartridge.mp4', 'assets/help/cartridge.jpg'); ?>
        </div>
        <p class="muted"><?= e(__('cartridge.intro')) ?></p>

        <?php if ($lookupError !== ''): ?>
            <div class="alert alert-error"><?= e($lookupError) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= e(rp_url('cartridge.php')) ?>" class="cartridge-lookup">
            <?= rp_csrf_field() ?>
            <input type="hidden" name="action" value="lookup">
            <div class="field">
                <label for="lookup-code"><?= e(__('cartridge.lookup')) ?></label>
                <div class="cartridge-lookup-row">
                    <input type="text" id="lookup-code" name="code" maxlength="20"
                           value="<?= e($codeParam) ?>" placeholder="CRG-2026-XXXXXX" autocomplete="off">
                    <button type="submit" class="btn"><?= e(__('cartridge.lookup_submit')) ?></button>
                </div>
            </div>
        </form>
    </div>

    <div class="card">
        <h2><?= e(__('cartridge.form_title')) ?></h2>
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

        <form method="post" action="<?= e(rp_url('cartridge.php')) ?>" novalidate>
            <?= rp_csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <p class="honeypot">
                <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </p>

            <div class="grid-2">
                <div class="field">
                    <label for="building"><?= e(__('cartridge.building')) ?> *</label>
                    <input type="text" id="building" name="building" maxlength="80" required
                           value="<?= e($values['building']) ?>">
                </div>
                <div class="field">
                    <label for="room"><?= e(__('cartridge.room')) ?> *</label>
                    <input type="text" id="room" name="room" maxlength="80" required
                           value="<?= e($values['room']) ?>">
                </div>
            </div>
            <div class="grid-2">
                <div class="field">
                    <label for="printer_model"><?= e(__('cartridge.printer')) ?> *</label>
                    <input type="text" id="printer_model" name="printer_model" maxlength="160" required
                           value="<?= e($values['printer_model']) ?>">
                </div>
                <div class="field">
                    <label for="cartridge_model"><?= e(__('cartridge.cartridge')) ?> *</label>
                    <input type="text" id="cartridge_model" name="cartridge_model" maxlength="160" required
                           value="<?= e($values['cartridge_model']) ?>">
                </div>
            </div>
            <div class="grid-2">
                <div class="field">
                    <label for="name"><?= e(__('cartridge.name')) ?></label>
                    <input type="text" id="name" name="name" maxlength="160" autocomplete="name"
                           value="<?= e($values['name']) ?>">
                    <small><?= e(__('cartridge.optional_hint')) ?></small>
                </div>
                <div class="field">
                    <label for="phone"><?= e(__('cartridge.phone')) ?></label>
                    <input type="tel" id="phone" name="phone" maxlength="18" autocomplete="tel" inputmode="tel"
                           data-ua-phone data-ua-phone-msg="<?= e(__('error.phone_invalid')) ?>"
                           value="<?= e($values['phone']) ?>">
                    <small><?= e(__('cartridge.phone_hint')) ?></small>
                </div>
            </div>
            <p class="muted"><?= e(__('common.required_hint')) ?></p>
            <button type="submit" class="btn btn-primary"><?= e(__('common.submit_cartridge')) ?></button>
        </form>
    </div>
<?php endif; ?>

<?php rp_footer();
