<?php
/**
 * Admin: questions / suggestions list.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/auth.php';
require dirname(__DIR__) . '/includes/layout.php';

rp_require_admin();

const RP_FEEDBACK_PER_PAGE = 25;

$validDate = static function (mixed $value): string {
    $value = is_string($value) ? trim($value) : '';
    $date  = DateTimeImmutable::createFromFormat('Y-m-d', $value);

    return ($date && $date->format('Y-m-d') === $value) ? $value : '';
};

$filters = [
    'status'    => in_array($_GET['status'] ?? '', rp_statuses(), true) ? (string) $_GET['status'] : '',
    'q'         => rp_clean_string($_GET['q'] ?? '', 120),
    'date_from' => $validDate($_GET['date_from'] ?? ''),
    'date_to'   => $validDate($_GET['date_to'] ?? ''),
];

$where  = [rp_sql_alive('f')];
$params = [];

if ($filters['status'] !== '') {
    $where[]          = 'f.status = :status';
    $params['status'] = $filters['status'];
}
if ($filters['q'] !== '') {
    $like    = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $filters['q']) . '%';
    $columns = ['f.public_code', 'f.requester_name', 'f.requester_phone', 'f.requester_contact', 'f.requester_channel', 'f.faculty', 'f.department', 'f.message'];
    $parts   = [];
    foreach ($columns as $index => $column) {
        $parts[]              = $column . ' LIKE :q' . $index . " ESCAPE '\\\\'";
        $params['q' . $index] = $like;
    }
    $where[] = '(' . implode(' OR ', $parts) . ')';
}
if ($filters['date_from'] !== '') {
    $where[]             = 'f.created_at >= :date_from';
    $params['date_from'] = rp_local_day_to_utc($filters['date_from'], false);
}
if ($filters['date_to'] !== '') {
    $where[]           = 'f.created_at <= :date_to';
    $params['date_to'] = rp_local_day_to_utc($filters['date_to'], true);
}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$pdo      = rp_db();

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM ' . RP_TABLE_FEEDBACK . ' f' . $whereSql);
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$pages = max(1, (int) ceil($total / RP_FEEDBACK_PER_PAGE));
$page  = max(1, min($pages, (int) ($_GET['page'] ?? 1)));

$listStmt = $pdo->prepare(
    'SELECT f.*, (SELECT COUNT(*) FROM ' . RP_TABLE_FEEDBACK_FILES . ' x WHERE x.feedback_id = f.id) AS file_count,
            (SELECT COUNT(*) FROM ' . RP_TABLE_FEEDBACK_HISTORY . ' h WHERE h.feedback_id = f.id AND h.action = \'replied\') AS reply_count
     FROM ' . RP_TABLE_FEEDBACK . ' f'
    . $whereSql .
    ' ORDER BY f.created_at DESC, f.id DESC
      LIMIT ' . RP_FEEDBACK_PER_PAGE . ' OFFSET ' . (($page - 1) * RP_FEEDBACK_PER_PAGE)
);
$listStmt->execute($params);
$rows = $listStmt->fetchAll();

$pageUrl = static function (int $target) use ($filters): string {
    $query         = array_filter($filters, static fn (string $value): bool => $value !== '');
    $query['page'] = $target;

    return rp_url('admin/feedback.php') . '?' . http_build_query($query);
};

rp_header(__('admin.section.feedback'), 'admin');
?>
<div class="card">
    <h1><?= e(__('admin.section.feedback')) ?></h1>
    <form method="get" action="<?= e(rp_url('admin/feedback.php')) ?>" class="filters">
        <div class="field">
            <label for="q"><?= e(__('admin.filter.query')) ?></label>
            <input type="text" id="q" name="q" maxlength="120" value="<?= e($filters['q']) ?>"
                   placeholder="<?= e(__('admin.filter.query_hint')) ?>">
        </div>
        <div class="field">
            <label for="status"><?= e(__('admin.filter.status')) ?></label>
            <select id="status" name="status">
                <option value=""><?= e(__('common.all')) ?></option>
                <?php foreach (rp_statuses() as $status): ?>
                    <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>>
                        <?= e(rp_status_label($status)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="date_from"><?= e(__('admin.filter.date_from')) ?></label>
            <input type="date" id="date_from" name="date_from" value="<?= e($filters['date_from']) ?>">
        </div>
        <div class="field">
            <label for="date_to"><?= e(__('admin.filter.date_to')) ?></label>
            <input type="date" id="date_to" name="date_to" value="<?= e($filters['date_to']) ?>">
        </div>
        <div class="field actions">
            <button type="submit" class="btn btn-primary"><?= e(__('common.search')) ?></button>
            <a class="btn" href="<?= e(rp_url('admin/feedback.php')) ?>"><?= e(__('common.reset')) ?></a>
        </div>
    </form>
</div>

<div class="card">
    <p class="muted"><?= e(__('admin.feedback.total', $total)) ?></p>
    <?php if (!$rows): ?>
        <p><?= e(__('admin.feedback.empty')) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="list">
                <thead>
                <tr>
                    <th><?= e(__('admin.table.code')) ?></th>
                    <th><?= e(__('admin.table.created')) ?></th>
                    <th><?= e(__('feedback.message')) ?></th>
                    <th><?= e(__('admin.table.requester')) ?></th>
                    <th><?= e(__('admin.table.files')) ?></th>
                    <th><?= e(__('admin.table.status')) ?></th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td class="code-cell"><?= e((string) $row['public_code']) ?></td>
                        <td><?= e(rp_format_datetime((string) $row['created_at'])) ?></td>
                        <td><?= e(mb_strimwidth((string) $row['message'], 0, 80, '…', 'UTF-8')) ?></td>
                        <td>
                            <?= e((string) $row['requester_name']) ?>
                            <?php if (trim((string) ($row['requester_phone'] ?? '')) !== ''): ?>
                                <small class="meta"><?= e((string) $row['requester_phone']) ?></small>
                            <?php endif; ?>
                            <?php
                            $channelLabel = rp_channel_label((string) ($row['requester_channel'] ?? ''));
                            $contactLine  = trim((string) $row['requester_contact']);
                            if ($channelLabel !== '' && $contactLine !== ''):
                            ?>
                                <small class="meta"><?= e($channelLabel) ?>: <?= e($contactLine) ?></small>
                            <?php elseif ($contactLine !== ''): ?>
                                <small class="meta"><?= e($contactLine) ?></small>
                            <?php endif; ?>
                            <?php
                            $unit = trim((string) ($row['faculty'] ?? '') . ' / ' . (string) ($row['department'] ?? ''), ' /');
                            if ($unit !== ''):
                            ?>
                                <small class="meta"><?= e($unit) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= (int) $row['file_count'] ?></td>
                        <td>
                            <span class="badge status-<?= e((string) $row['status']) ?>">
                                <?= e(rp_status_label((string) $row['status'])) ?>
                            </span>
                            <?php if ((int) ($row['reply_count'] ?? 0) > 0): ?>
                                <small class="meta"><?= e(__('admin.feedback.replied')) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a class="btn btn-small"
                               href="<?= e(rp_url('admin/feedback-view.php?id=' . (int) $row['id'])) ?>">
                                <?= e(__('admin.open')) ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a class="btn btn-small" href="<?= e($pageUrl($page - 1)) ?>"><?= e(__('admin.prev')) ?></a>
                <?php endif; ?>
                <span class="muted"><?= e(__('admin.pagination', $page, $pages)) ?></span>
                <?php if ($page < $pages): ?>
                    <a class="btn btn-small" href="<?= e($pageUrl($page + 1)) ?>"><?= e(__('admin.next')) ?></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php rp_footer();
