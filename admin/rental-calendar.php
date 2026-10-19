<?php
/**
 * Rental calendar: day / week / month.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/auth.php';
require dirname(__DIR__) . '/includes/layout.php';

$admin = rp_rental_require('calendar');
$pdo   = rp_db();
rp_rental_expire_overdue($pdo);
$tz    = rp_app_timezone();
$showFinance = rp_rental_can('finance', $admin);

$view = in_array($_GET['view'] ?? '', ['day', 'week', 'month'], true) ? (string) $_GET['view'] : 'day';
$date = rp_clean_string($_GET['date'] ?? '', 10);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = (new DateTimeImmutable('now', $tz))->format('Y-m-d');
}
$building = rp_clean_string($_GET['building'] ?? '', 20);
$roomId   = (int) ($_GET['room_id'] ?? 0);
$kind     = rp_clean_string($_GET['kind'] ?? '', 20);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && rp_csrf_valid($_POST['csrf_token'] ?? null)) {
    $actor = rp_rental_actor($admin);
    try {
        if (isset($_POST['confirm_month']) && rp_rental_can('confirm_month', $admin)) {
            rp_rental_confirm_month($pdo, substr($date, 0, 7), $actor);
            rp_flash('success', __('rental.month_ok', substr($date, 0, 7)));
        } elseif (isset($_POST['add_class']) && rp_rental_can('classes', $admin)) {
            rp_rental_add_class(
                $pdo,
                (int) $_POST['room_id'],
                rp_clean_string($_POST['ymd'] ?? '', 10),
                rp_clean_string($_POST['start'] ?? '', 5),
                rp_clean_string($_POST['end'] ?? '', 5),
                rp_clean_string($_POST['title'] ?? '', 255),
                $actor
            );
            rp_flash('success', __('rental.saved'));
        } elseif (isset($_POST['add_block']) && rp_rental_can('rooms', $admin)) {
            rp_rental_add_block(
                $pdo,
                (int) $_POST['room_id'],
                rp_clean_string($_POST['ymd'] ?? '', 10),
                rp_clean_string($_POST['start'] ?? '', 5),
                rp_clean_string($_POST['end'] ?? '', 5),
                rp_clean_string($_POST['title'] ?? '', 255),
                $actor
            );
            rp_flash('success', __('rental.saved'));
        } elseif (isset($_POST['request_approval']) && rp_rental_can('deals', $admin)) {
            rp_rental_request_approval(
                $pdo,
                (int) $_POST['room_id'],
                rp_clean_string($_POST['ymd'] ?? '', 10),
                rp_clean_string($_POST['start'] ?? '', 5),
                rp_clean_string($_POST['end'] ?? '', 5),
                $actor
            );
            rp_flash('success', __('rental.request_ok'));
        }
    } catch (Throwable $e) {
        rp_flash('error', $e->getMessage());
    }
    rp_redirect('admin/rental-calendar.php?view=' . urlencode($view) . '&date=' . urlencode($date));
}

$day = DateTimeImmutable::createFromFormat('Y-m-d', $date, $tz) ?: new DateTimeImmutable('now', $tz);
if ($view === 'week') {
    $from = $day->modify('monday this week')->setTime(0, 0);
    $to   = $from->modify('+7 days');
} elseif ($view === 'month') {
    $from = $day->modify('first day of this month')->setTime(0, 0);
    $to   = $from->modify('first day of next month');
} else {
    $from = $day->setTime(0, 0);
    $to   = $from->modify('+1 day');
}

$events = rp_rental_events(
    $pdo,
    $from->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
    $to->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
    $roomId > 0 ? $roomId : null,
    $building !== '' ? $building : null
);
if ($kind !== '') {
    $events = array_values(array_filter($events, static fn (array $e): bool => $e['kind'] === $kind));
}

$rooms = rp_rental_rooms($pdo, true);
$buildings = array_values(array_unique(array_map(static fn (array $r): string => (string) $r['building'], $rooms)));
$monthConfirmed = rp_rental_month_confirmed($pdo, $day->format('Y-m'));

rp_header(__('rental.nav.calendar'), 'admin');
?>
<div class="card">
    <h1><?= e(__('rental.nav.calendar')) ?></h1>
    <p><?= $monthConfirmed
        ? e(__('rental.month_ok', $day->format('Y-m')))
        : e(__('rental.month_pending', $day->format('Y-m'))) ?></p>

    <form method="get" class="filters">
        <input type="hidden" name="view" value="<?= e($view) ?>">
        <label><?= e(__('rental.cal.day')) ?>
            <input type="date" name="date" value="<?= e($date) ?>">
        </label>
        <label><?= e(__('rental.cal.building')) ?>
            <select name="building">
                <option value=""><?= e(__('rental.filter.all')) ?></option>
                <?php foreach ($buildings as $b): ?>
                    <option value="<?= e($b) ?>" <?= $building === $b ? 'selected' : '' ?>><?= e($b) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><?= e(__('rental.cal.room')) ?>
            <select name="room_id">
                <option value="0"><?= e(__('rental.filter.all')) ?></option>
                <?php foreach ($rooms as $room): ?>
                    <option value="<?= (int) $room['id'] ?>" <?= $roomId === (int) $room['id'] ? 'selected' : '' ?>>
                        <?= e(rp_rental_room_label($room)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><?= e(__('rental.cal.filter_kind')) ?>
            <select name="kind">
                <option value=""><?= e(__('rental.filter.all')) ?></option>
                <?php foreach (['class', 'unpaid', 'paid', 'block', 'approval'] as $k): ?>
                    <option value="<?= e($k) ?>" <?= $kind === $k ? 'selected' : '' ?>><?= e(__('rental.event.' . ($k === 'unpaid' ? 'unpaid' : ($k === 'paid' ? 'paid' : $k)))) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="btn" type="submit"><?= e(__('common.search')) ?></button>
        <a class="btn" href="<?= e(rp_url('admin/rental-calendar.php?view=day&date=' . $date)) ?>"><?= e(__('rental.cal.day')) ?></a>
        <a class="btn" href="<?= e(rp_url('admin/rental-calendar.php?view=week&date=' . $date)) ?>"><?= e(__('rental.cal.week')) ?></a>
        <a class="btn" href="<?= e(rp_url('admin/rental-calendar.php?view=month&date=' . $date)) ?>"><?= e(__('rental.cal.month')) ?></a>
    </form>

    <?php if (rp_rental_can('confirm_month', $admin) && !$monthConfirmed): ?>
        <form method="post">
            <?= rp_csrf_field() ?>
            <button class="btn btn-primary" name="confirm_month" value="1"><?= e(__('rental.confirm_month')) ?></button>
        </form>
    <?php endif; ?>

    <table class="rental-cal">
        <thead>
        <tr>
            <th><?= e(__('rental.cal.room')) ?></th>
            <th><?= e(__('content.field.when')) ?></th>
            <th><?= e(__('rental.cal.filter_kind')) ?></th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$events): ?>
            <tr><td colspan="3"><?= e(__('content.empty_day')) ?></td></tr>
        <?php endif; ?>
        <?php foreach ($events as $event): ?>
            <tr>
                <td><?= e((string) $event['room']) ?></td>
                <td>
                    <?= e(rp_format_datetime((string) $event['starts_at'])) ?>
                    – <?= e(rp_format_time((string) $event['ends_at'])) ?>
                </td>
                <td>
                    <span class="rental-chip <?= e((string) $event['kind']) ?>">
                        <span class="kind"><?= e((string) $event['label']) ?></span>
                        <?= e((string) $event['title']) ?>
                        <?php if ($showFinance && isset($event['client'])): ?>
                            · <?= e((string) $event['client']) ?>
                            · <?= e(rp_rental_format_money((string) $event['amount'])) ?>
                        <?php endif; ?>
                        <?php if (($event['kind'] ?? '') === 'unpaid' && !empty($event['deadline'])): ?>
                            <div class="muted"><?= e(__('rental.deadline')) ?>: <?= e(rp_format_datetime((string) $event['deadline'])) ?></div>
                        <?php endif; ?>
                        <?php if ($showFinance && !empty($event['deal_id'])): ?>
                            <a href="<?= e(rp_url('admin/rental-deal.php?id=' . (int) $event['deal_id'])) ?>"><?= e(__('admin.open')) ?></a>
                        <?php endif; ?>
                    </span>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if (rp_rental_can('classes', $admin) || rp_rental_can('deals', $admin) || rp_rental_can('rooms', $admin)): ?>
<div class="card">
    <?php if (rp_rental_can('classes', $admin)): ?>
        <h2><?= e(__('rental.add_class')) ?></h2>
        <form method="post" class="grid-2">
            <?= rp_csrf_field() ?>
            <div class="field">
                <label><?= e(__('rental.cal.room')) ?></label>
                <select name="room_id" required>
                    <?php foreach ($rooms as $room): ?>
                        <option value="<?= (int) $room['id'] ?>"><?= e(rp_rental_room_label($room)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label><?= e(__('rental.name')) ?></label>
                <input type="text" name="title" required maxlength="255">
            </div>
            <div class="field">
                <label><?= e(__('rental.cal.day')) ?></label>
                <input type="date" name="ymd" value="<?= e($date) ?>" required>
            </div>
            <div class="field">
                <label><?= e(__('rental.hours')) ?></label>
                <input type="time" name="start" value="09:00" required>
                <input type="time" name="end" value="10:00" required>
            </div>
            <p><button class="btn btn-primary" name="add_class" value="1"><?= e(__('rental.add_class')) ?></button></p>
        </form>
    <?php endif; ?>

    <?php if (rp_rental_can('deals', $admin)): ?>
        <h2><?= e(__('rental.nav.approvals')) ?></h2>
        <form method="post" class="grid-2">
            <?= rp_csrf_field() ?>
            <div class="field">
                <label><?= e(__('rental.cal.room')) ?></label>
                <select name="room_id" required>
                    <?php foreach ($rooms as $room): ?>
                        <option value="<?= (int) $room['id'] ?>"><?= e(rp_rental_room_label($room)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label><?= e(__('rental.cal.day')) ?></label>
                <input type="date" name="ymd" value="<?= e($date) ?>" required>
            </div>
            <div class="field">
                <label><?= e(__('rental.hours')) ?></label>
                <input type="time" name="start" value="10:00" required>
                <input type="time" name="end" value="11:00" required>
            </div>
            <p><button class="btn" name="request_approval" value="1"><?= e(__('rental.nav.approvals')) ?></button></p>
        </form>
        <p><a class="btn btn-primary" href="<?= e(rp_url('admin/rental-deal.php')) ?>"><?= e(__('rental.nav.new_deal')) ?></a></p>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php rp_footer();