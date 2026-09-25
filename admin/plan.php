<?php
/**
 * Admin: content-plan calendar of upcoming events / publications.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/content.php';
require dirname(__DIR__) . '/includes/holidays.php';
require dirname(__DIR__) . '/includes/layout.php';

rp_require_admin();

$tz    = rp_app_timezone();
$today = new DateTimeImmutable('now', $tz);

$year  = (int) ($_GET['year'] ?? $today->format('Y'));
$month = (int) ($_GET['month'] ?? $today->format('n'));
if ($year < 2000 || $year > 2100) {
    $year = (int) $today->format('Y');
}
if ($month < 1 || $month > 12) {
    $month = (int) $today->format('n');
}

$monthStart = DateTimeImmutable::createFromFormat('Y-n-j H:i:s', sprintf('%d-%d-1 00:00:00', $year, $month), $tz);
if (!$monthStart) {
    $monthStart = $today->modify('first day of this month')->setTime(0, 0, 0);
}
$nextMonth = $monthStart->modify('first day of next month');
$prevMonth = $monthStart->modify('first day of last month');

// Monday-first grid covering the visible month.
$gridStart = $monthStart;
while ((int) $gridStart->format('N') !== 1) {
    $gridStart = $gridStart->modify('-1 day');
}
$gridEnd = $nextMonth->modify('-1 day');
while ((int) $gridEnd->format('N') !== 7) {
    $gridEnd = $gridEnd->modify('+1 day');
}

$fromUtc = $gridStart->setTime(0, 0, 0)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
$toUtc   = $gridEnd->setTime(23, 59, 59)->modify('+1 second')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

$events = rp_content_between($pdo = rp_db(), $fromUtc, $toUtc);
$gridHolidays  = rp_visible_holidays($pdo, $gridStart->format('Y-m-d'), $gridEnd->format('Y-m-d'));
$holidaysByDay = rp_holidays_by_day($gridHolidays);
$monthHolidays = [];
$monthPrefix   = $monthStart->format('Y-m');
foreach ($gridHolidays as $holiday) {
    if (str_starts_with($holiday['date'], $monthPrefix)) {
        $monthHolidays[] = $holiday;
    }
}
$hiddenHolidays = rp_hidden_holidays_list($pdo);
$byDay  = [];
foreach ($events as $event) {
    $day = rp_local_ymd((string) $event['event_at']);
    if ($day === '') {
        continue;
    }
    $byDay[$day][] = $event;
}

$monthEvents = [];
foreach ($events as $event) {
    $local = rp_local_ymd((string) $event['event_at']);
    if (str_starts_with($local, $monthStart->format('Y-m'))) {
        $monthEvents[] = $event;
    }
}

$monthUrl = static function (DateTimeImmutable $date): string {
    return rp_url('admin/plan.php') . '?' . http_build_query([
        'year'  => $date->format('Y'),
        'month' => $date->format('n'),
    ]);
};

$holidayHideForm = static function (array $holiday) use ($monthStart): void {
    ?>
    <form class="cal-holiday<?= $holiday['kind'] === 'public' ? ' is-public' : '' ?>" method="post" action="<?= e(rp_url('admin/plan-holiday-hide.php')) ?>"
          onsubmit='return confirm(<?= json_encode(__('content.holiday.delete_confirm'), JSON_UNESCAPED_UNICODE) ?>);'>
        <?= rp_csrf_field() ?>
        <input type="hidden" name="holiday_key" value="<?= e($holiday['key']) ?>">
        <input type="hidden" name="year" value="<?= e($monthStart->format('Y')) ?>">
        <input type="hidden" name="month" value="<?= e($monthStart->format('n')) ?>">
        <span class="cal-kind cal-kind-holiday"><?= e(__('content.holiday.tag')) ?></span>
        <span class="cal-holiday-title" title="<?= e($holiday['title']) ?>">
            <?= e(mb_strimwidth($holiday['title'], 0, 42, '…', 'UTF-8')) ?>
        </span>
        <button type="submit" class="cal-holiday-del" aria-label="<?= e(__('content.holiday.remove')) ?>">×</button>
    </form>
    <?php
};

$contentDeleteForm = static function (array $event) use ($monthStart): void {
    ?>
    <form method="post" action="<?= e(rp_url('admin/plan-delete.php')) ?>" class="inline-form"
          onsubmit='return confirm(<?= json_encode(__('content.delete_confirm'), JSON_UNESCAPED_UNICODE) ?>);'>
        <?= rp_csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $event['id'] ?>">
        <button type="submit" class="btn btn-small btn-danger"><?= e(__('common.delete')) ?></button>
    </form>
    <?php
};

rp_header(__('admin.section.plan'), 'admin');
?>
<div class="card" style="padding-bottom:16px">
    <div class="cal-toolbar">
        <h1><?= e(__('admin.section.plan')) ?></h1>
        <div class="cal-nav">
            <a class="btn btn-small" href="<?= e($monthUrl($prevMonth)) ?>"><?= e(__('content.month_prev')) ?></a>
            <strong><?= e(__('content.cal.month.' . (int) $monthStart->format('n')) . ' ' . $monthStart->format('Y')) ?></strong>
            <a class="btn btn-small" href="<?= e($monthUrl($nextMonth)) ?>"><?= e(__('content.month_next')) ?></a>
            <a class="btn btn-small" href="<?= e(rp_url('admin/plan.php')) ?>"><?= e(__('content.today')) ?></a>
            <?php if ($hiddenHolidays): ?>
                <a class="btn btn-small" href="#hidden-holidays"><?= e(__('content.holiday.hidden_link', count($hiddenHolidays))) ?></a>
            <?php endif; ?>
        </div>
    </div>
    <p class="muted" style="margin-bottom:8px"><?= e(__('content.intro')) ?></p>
    <div class="cal-key" aria-hidden="true">
        <span class="cal-key-item cal-key-holiday"><?= e(__('content.holiday.tag')) ?></span>
        <span class="cal-key-item cal-key-event"><?= e(__('content.event.tag')) ?></span>
        <span class="muted"><?= e(__('content.holiday.legend')) ?></span>
    </div>
</div>

<div class="cal-grid-wrap">
    <div class="cal-weekdays">
        <?php for ($dow = 1; $dow <= 7; $dow++): ?>
            <div><?= e(__('content.cal.dow.' . $dow)) ?></div>
        <?php endfor; ?>
    </div>
    <div class="cal-grid">
        <?php
        $cursor = $gridStart;
        while ($cursor <= $gridEnd):
            $ymd       = $cursor->format('Y-m-d');
            $inMonth   = $cursor->format('n') === $monthStart->format('n');
            $isToday   = $ymd === $today->format('Y-m-d');
            $dayEvents = $byDay[$ymd] ?? [];
            ?>
            <section class="cal-cell<?= $inMonth ? '' : ' other-month' ?><?= $isToday ? ' is-today' : '' ?>">
                <div class="cal-cell-head"><?= (int) $cursor->format('j') ?></div>
                <?php
                $dayHolidays = $holidaysByDay[$ymd] ?? [];
                $hasHolidays = $dayHolidays !== [];
                $hasEvents   = $dayEvents !== [];
                ?>
                <?php if ($hasHolidays): ?>
                    <div class="cal-holidays">
                        <?php foreach ($dayHolidays as $holiday): ?>
                            <?php $holidayHideForm($holiday); ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if ($hasHolidays && $hasEvents): ?>
                    <div class="cal-split"><?= e(__('content.cal.announcements')) ?></div>
                <?php endif; ?>
                <?php if ($hasEvents): ?>
                    <div class="cal-items">
                        <?php foreach ($dayEvents as $event): ?>
                            <a class="cal-event status-<?= e((string) $event['status']) ?>"
                               href="<?= e(rp_url('admin/plan-edit.php?id=' . (int) $event['id'])) ?>"
                               title="<?= e((string) $event['title']) ?>">
                                <span class="cal-kind cal-kind-event"><?= e(__('content.event.tag')) ?></span>
                                <span class="cal-event-main">
                                    <span class="cal-event-time"><?= e(rp_format_time((string) $event['event_at'])) ?></span>
                                    <?= e(mb_strimwidth((string) $event['title'], 0, 42, '…', 'UTF-8')) ?>
                                </span>
                                <?php if (rp_content_is_public($event['created_by'] ?? null)): ?>
                                    <span class="cal-event-src"><?= e(__('content.source.site')) ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
            <?php
            $cursor = $cursor->modify('+1 day');
        endwhile;
        ?>
    </div>
</div>

<?php if ($monthHolidays): ?>
<div class="card">
    <h2><?= e(__('content.holiday.list_title')) ?></h2>
    <p class="muted"><?= e(__('content.holiday.list_intro')) ?></p>
    <div class="table-wrap">
        <table class="list">
            <thead>
            <tr>
                <th><?= e(__('content.field.when')) ?></th>
                <th><?= e(__('content.field.title')) ?></th>
                <th><?= e(__('content.holiday.kind')) ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($monthHolidays as $holiday): ?>
                <tr>
                    <td><?= e($holiday['date']) ?></td>
                    <td><?= e($holiday['title']) ?></td>
                    <td><?= e(rp_holiday_kind_label($holiday['kind'])) ?></td>
                    <td>
                        <form method="post" action="<?= e(rp_url('admin/plan-holiday-hide.php')) ?>" class="inline-form"
                              onsubmit='return confirm(<?= json_encode(__('content.holiday.delete_confirm'), JSON_UNESCAPED_UNICODE) ?>);'>
                            <?= rp_csrf_field() ?>
                            <input type="hidden" name="holiday_key" value="<?= e($holiday['key']) ?>">
                            <input type="hidden" name="year" value="<?= e($monthStart->format('Y')) ?>">
                            <input type="hidden" name="month" value="<?= e($monthStart->format('n')) ?>">
                            <button type="submit" class="btn btn-small btn-danger"><?= e(__('common.delete')) ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if ($hiddenHolidays): ?>
<div class="card" id="hidden-holidays">
    <h2><?= e(__('content.holiday.hidden_title')) ?></h2>
    <p class="muted"><?= e(__('content.holiday.hidden_intro')) ?></p>
    <div class="table-wrap">
        <table class="list">
            <thead>
            <tr>
                <th><?= e(__('content.field.when')) ?></th>
                <th><?= e(__('content.field.title')) ?></th>
                <th><?= e(__('content.holiday.kind')) ?></th>
                <th><?= e(__('content.holiday.hidden_at')) ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($hiddenHolidays as $holiday): ?>
                <tr>
                    <td><?= e($holiday['date']) ?></td>
                    <td><?= e($holiday['title']) ?></td>
                    <td><?= e(rp_holiday_kind_label($holiday['kind'])) ?></td>
                    <td><?= e(rp_format_datetime($holiday['hidden_at'])) ?></td>
                    <td>
                        <form method="post" action="<?= e(rp_url('admin/plan-holiday-restore.php')) ?>" class="inline-form">
                            <?= rp_csrf_field() ?>
                            <input type="hidden" name="holiday_key" value="<?= e($holiday['key']) ?>">
                            <input type="hidden" name="year" value="<?= e($monthStart->format('Y')) ?>">
                            <input type="hidden" name="month" value="<?= e($monthStart->format('n')) ?>">
                            <button type="submit" class="btn btn-small btn-primary"><?= e(__('content.holiday.restore')) ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <form method="post" action="<?= e(rp_url('admin/plan-holiday-restore.php')) ?>"
          onsubmit='return confirm(<?= json_encode(__('content.holiday.restore_all_confirm'), JSON_UNESCAPED_UNICODE) ?>);'>
        <?= rp_csrf_field() ?>
        <input type="hidden" name="all" value="1">
        <input type="hidden" name="year" value="<?= e($monthStart->format('Y')) ?>">
        <input type="hidden" name="month" value="<?= e($monthStart->format('n')) ?>">
        <button type="submit" class="btn"><?= e(__('content.holiday.restore_all')) ?></button>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <h2><?= e(__('content.cal.month.' . (int) $monthStart->format('n'))) ?></h2>
    <?php if (!$monthEvents): ?>
        <p class="muted"><?= e(__('content.no_events')) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="list">
                <thead>
                <tr>
                    <th><?= e(__('content.field.when')) ?></th>
                    <th><?= e(__('content.field.title')) ?></th>
                    <th><?= e(__('content.field.department')) ?></th>
                    <th><?= e(__('content.field.responsible')) ?></th>
                    <th><?= e(__('content.field.channels')) ?></th>
                    <th><?= e(__('content.source.label')) ?></th>
                    <th><?= e(__('content.field.status')) ?></th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($monthEvents as $event): ?>
                    <?php
                    $ch = rp_content_decode_channels($event['channels'] ?? '');
                    $labels = array_map('rp_content_channel_label', $ch);
                    ?>
                    <tr>
                        <td><?= e(rp_format_datetime((string) $event['event_at'])) ?></td>
                        <td><?= e((string) $event['title']) ?></td>
                        <td><?= e((string) $event['department']) ?></td>
                        <td><?= e((string) $event['responsible']) ?></td>
                        <td><?= $labels ? e(implode(', ', $labels)) : e(__('common.none')) ?></td>
                        <td><?= e(rp_content_is_public($event['created_by'] ?? null) ? __('content.source.site') : __('content.source.admin')) ?></td>
                        <td>
                            <span class="badge status-<?= e((string) $event['status']) ?>">
                                <?= e(rp_content_status_label((string) $event['status'])) ?>
                            </span>
                        </td>
                        <td>
                            <a class="btn btn-small"
                               href="<?= e(rp_url('admin/plan-edit.php?id=' . (int) $event['id'])) ?>">
                                <?= e(__('admin.open')) ?>
                            </a>
                            <?php $contentDeleteForm($event); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php rp_footer();
