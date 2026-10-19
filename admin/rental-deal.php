<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/auth.php';
require dirname(__DIR__) . '/includes/uploads.php';
require dirname(__DIR__) . '/includes/layout.php';

$admin = rp_rental_require('deals');
$pdo   = rp_db();
rp_rental_expire_overdue($pdo);
$id    = (int) ($_GET['id'] ?? 0);
$deal  = $id > 0 ? rp_rental_deal($pdo, $id) : null;
$actor = rp_rental_actor($admin);
$errors = [];
$conflicts = [];
$preview = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && rp_csrf_valid($_POST['csrf_token'] ?? null)) {
    try {
        if (isset($_POST['create_deal'])) {
            $clientId = (int) ($_POST['client_id'] ?? 0);
            if ($clientId <= 0 && rp_clean_string($_POST['new_name'] ?? '', 255) !== '') {
                $clientId = rp_rental_save_client($pdo, [
                    'name'  => $_POST['new_name'] ?? '',
                    'phone' => $_POST['new_phone'] ?? '',
                    'type'  => $_POST['new_type'] ?? 'person',
                ], $actor);
            }
            $rows = [];
            foreach ((array) ($_POST['room_id'] ?? []) as $i => $roomId) {
                if ((int) $roomId <= 0) {
                    continue;
                }
                $part = trim((string) ($_POST['participants'][$i] ?? ''));
                $rows[] = [
                    'room_id'      => (int) $roomId,
                    'ymd'          => rp_clean_string($_POST['ymd'][$i] ?? '', 10),
                    'start'        => rp_clean_string($_POST['start'][$i] ?? '', 5),
                    'end'          => rp_clean_string($_POST['end'][$i] ?? '', 5),
                    'participants' => $part === '' ? null : (int) $part,
                ];
            }
            $result = rp_rental_create_deal($pdo, $clientId, $rows, $actor);
            if ($result['ok']) {
                rp_flash('success', __('rental.saved'));
                rp_redirect('admin/rental-deal.php?id=' . (int) $result['id']);
            }
            $errors    = $result['errors'];
            $conflicts = $result['conflicts'];
        } elseif ($deal && isset($_POST['pay']) && rp_rental_can('pay', $admin)) {
            $files = rp_collect_uploads();
            if ($files) {
                $verr = rp_validate_uploads($files);
                if ($verr) {
                    throw new RuntimeException(implode(' ', $verr));
                }
                rp_rental_store_receipts($pdo, $id, $files, $actor);
            }
            rp_rental_confirm_payment($pdo, $id, $actor);
            rp_flash('success', __('rental.saved'));
            rp_redirect('admin/rental-deal.php?id=' . $id);
        } elseif ($deal && isset($_POST['receipt_only'])) {
            $files = rp_collect_uploads();
            $verr  = rp_validate_uploads($files);
            if ($verr) {
                throw new RuntimeException(implode(' ', $verr));
            }
            rp_rental_store_receipts($pdo, $id, $files, $actor);
            rp_flash('success', __('rental.saved'));
            rp_redirect('admin/rental-deal.php?id=' . $id);
        } elseif ($deal && isset($_POST['sign'])) {
            rp_rental_sign_contract($pdo, $id, $actor);
            rp_flash('success', __('rental.saved'));
            rp_redirect('admin/rental-deal.php?id=' . $id);
        } elseif ($deal && isset($_POST['cancel_session']) && rp_rental_can('cancel', $admin)) {
            rp_rental_cancel_session($pdo, (int) $_POST['session_id'], $actor);
            rp_flash('success', __('rental.saved'));
            rp_redirect('admin/rental-deal.php?id=' . $id);
        } elseif ($deal && isset($_POST['no_show']) && rp_rental_can('cancel', $admin)) {
            rp_rental_mark_no_show($pdo, (int) $_POST['session_id'], $actor);
            rp_flash('success', __('rental.saved'));
            rp_redirect('admin/rental-deal.php?id=' . $id);
        } elseif ($deal && isset($_POST['move_session']) && rp_rental_can('move', $admin)) {
            rp_rental_move_session(
                $pdo,
                (int) $_POST['session_id'],
                rp_clean_string($_POST['move_ymd'] ?? '', 10),
                rp_clean_string($_POST['move_start'] ?? '', 5),
                rp_clean_string($_POST['move_end'] ?? '', 5),
                $actor
            );
            rp_flash('success', __('rental.saved'));
            rp_redirect('admin/rental-deal.php?id=' . $id);
        }
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

$rooms = rp_rental_rooms($pdo, true);
$clients = rp_rental_clients($pdo);
$sessions = $deal ? rp_rental_deal_sessions($pdo, $id) : [];
$receipts = $deal ? rp_rental_deal_receipts($pdo, $id) : [];
$maxFiles = (int) rp_config('uploads.max_files', 10);
$maxSize  = rp_effective_file_limit();

rp_header($deal ? (string) $deal['public_code'] : __('rental.nav.new_deal'), 'admin');
?>
<div class="card">
    <h1><?= e($deal ? (string) $deal['public_code'] : __('rental.nav.new_deal')) ?></h1>

    <?php if ($errors): ?>
        <div class="alert alert-error">
            <strong><?= $conflicts ? e(__('rental.conflicts_title')) : e(__('error.form_title')) ?></strong>
            <ul><?php foreach ($errors as $err): ?><li><?= e((string) $err) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <?php if (!$deal): ?>
        <form method="post">
            <?= rp_csrf_field() ?>
            <div class="field">
                <label><?= e(__('rental.nav.clients')) ?></label>
                <select name="client_id">
                    <option value="0">—</option>
                    <?php foreach ($clients as $c): ?>
                        <option value="<?= (int) $c['id'] ?>"><?= e((string) $c['name'] . ' · ' . $c['phone']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grid-2">
                <div class="field">
                    <label><?= e(__('rental.new_client')) ?></label>
                    <input name="new_name" placeholder="<?= e(__('rental.name')) ?>">
                </div>
                <div class="field">
                    <label><?= e(__('rental.phone')) ?></label>
                    <input name="new_phone">
                </div>
            </div>
            <div id="sessions" class="session-rows">
                <div class="grid-2 session-row">
                    <select name="room_id[]" required>
                        <?php foreach ($rooms as $room): ?>
                            <option value="<?= (int) $room['id'] ?>"><?= e(rp_rental_room_label($room)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="date" name="ymd[]" required>
                    <input type="time" name="start[]" value="18:00" required>
                    <input type="time" name="end[]" value="19:00" required>
                    <input type="number" name="participants[]" min="1" placeholder="<?= e(__('rental.participants')) ?>">
                </div>
            </div>
            <p>
                <button type="button" class="btn" id="add-row"><?= e(__('rental.add_session')) ?></button>
                <button class="btn btn-primary" name="create_deal" value="1"><?= e(__('rental.create_deal')) ?></button>
            </p>
        </form>
        <script>
            document.getElementById('add-row').addEventListener('click', function () {
                var box = document.getElementById('sessions');
                var row = box.querySelector('.session-row').cloneNode(true);
                box.appendChild(row);
            });
        </script>
    <?php else: ?>
        <p>
            <?= e((string) $deal['client_name']) ?> · <?= e((string) $deal['client_phone']) ?><br>
            <?= e(rp_rental_payment_label((string) $deal['payment_status'])) ?>
            · <?= e(rp_rental_format_money((string) $deal['total_amount'])) ?><br>
            <?= e(__('rental.deadline')) ?>: <?= e(rp_format_datetime((string) $deal['pay_deadline'])) ?>
        </p>
        <p>
            <?= (int) $deal['contract_signed']
                ? e(__('rental.signed', rp_format_datetime((string) $deal['contract_signed_at']), (string) $deal['contract_signed_by']))
                : e(__('rental.unsigned')) ?>
        </p>

        <h2><?= e(__('rental.nav.deals')) ?></h2>
        <ul class="files">
            <?php foreach ($sessions as $session): ?>
                <li>
                    <?= e(rp_rental_room_label($session + ['name' => $session['room_name']])) ?>
                    · <?= e(rp_format_datetime((string) $session['starts_at'])) ?>
                    – <?= e(rp_format_time((string) $session['ends_at'])) ?>
                    · <?= e(rp_rental_session_status_label((string) $session['status'])) ?>
                    · <?= e(rp_rental_format_money((string) $session['amount'])) ?>
                    <?php if (in_array($session['status'], ['hold', 'booked'], true) && rp_rental_can('cancel', $admin)): ?>
                        <form method="post" style="display:inline">
                            <?= rp_csrf_field() ?>
                            <input type="hidden" name="session_id" value="<?= (int) $session['id'] ?>">
                            <button class="btn btn-small" name="cancel_session" value="1"><?= e(__('rental.cancel_session')) ?></button>
                        </form>
                    <?php endif; ?>
                    <?php if ($session['status'] === 'booked' && rp_rental_can('cancel', $admin)): ?>
                        <form method="post" style="display:inline">
                            <?= rp_csrf_field() ?>
                            <input type="hidden" name="session_id" value="<?= (int) $session['id'] ?>">
                            <button class="btn btn-small" name="no_show" value="1"><?= e(__('rental.no_show')) ?></button>
                        </form>
                    <?php endif; ?>
                    <?php if (in_array($session['status'], ['hold', 'booked'], true) && rp_rental_can('move', $admin)): ?>
                        <form method="post" class="grid-2">
                            <?= rp_csrf_field() ?>
                            <input type="hidden" name="session_id" value="<?= (int) $session['id'] ?>">
                            <input type="date" name="move_ymd" required>
                            <input type="time" name="move_start" required>
                            <input type="time" name="move_end" required>
                            <button class="btn btn-small" name="move_session" value="1"><?= e(__('rental.move_session')) ?></button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <h2><?= e(__('rental.receipts')) ?></h2>
        <ul class="files">
            <?php foreach ($receipts as $file): ?>
                <li>
                    <a href="<?= e(rp_url('admin/rental-receipt.php?id=' . (int) $file['id'])) ?>">
                        <?= e((string) $file['original_name']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <form method="post" enctype="multipart/form-data">
            <?= rp_csrf_field() ?>
            <div class="field">
                <label><?= e(__('rental.attach')) ?></label>
                <input type="file" name="attachments[]" multiple accept="<?= e(rp_accept_attribute()) ?>">
                <small><?= e(__('form.files_hint', $maxFiles, rp_format_bytes($maxSize), implode(', ', rp_allowed_extensions()))) ?></small>
            </div>
            <button class="btn" name="receipt_only" value="1"><?= e(__('rental.attach')) ?></button>
            <?php if ($deal['payment_status'] === 'unpaid' && rp_rental_can('pay', $admin)): ?>
                <button class="btn btn-primary" name="pay" value="1"><?= e(__('rental.pay_confirm')) ?></button>
            <?php endif; ?>
            <?php if (!(int) $deal['contract_signed']): ?>
                <button class="btn" name="sign" value="1"><?= e(__('rental.sign')) ?></button>
            <?php endif; ?>
        </form>
        <p><a class="btn" href="<?= e(rp_url('admin/rental-deals.php')) ?>"><?= e(__('common.back')) ?></a></p>
    <?php endif; ?>
</div>
<?php rp_footer();