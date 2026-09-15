<?php
/**
 * Admin: request list with filters, search and pagination.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/auth.php';
require dirname(__DIR__) . '/includes/layout.php';

rp_require_admin();

const RP_PER_PAGE = 25;

/** Accept only YYYY-MM-DD, otherwise ''. */
$validDate = static function (mixed $value): string {
    $value = is_string($value) ? trim($value) : '';
    $date  = DateTimeImmutable::createFromFormat('Y-m-d', $value);

    return ($date && $date->format('Y-m-d') === $value) ? $value : '';
};

$filters = [
    'status'    => in_array($_GET['status'] ?? '', rp_statuses(), true) ? (string) $_GET['status'] : '',
    'type'      => in_array($_GET['type'] ?? '', rp_request_types(), true) ? (string) $_GET['type'] : '',
    'q'         => rp_clean_string($_GET['q'] ?? '', 120),
    'date_from' => $validDate($_GET['date_from'] ?? ''),
    'date_to'   => $validDate($_GET['date_to'] ?? ''),
];

$where  = [];
$params = [];

if ($filters['status'] !== '') {
    $where[]           = 'r.status = :status';
    $params['status']  = $filters['status'];
}
if ($filters['type'] !== '') {
    $where[]        = 'r.type = :type';
    $params['type'] = $filters['type'];
}
if ($filters['q'] !== '') {
    // Native prepared statements bind each placeholder once, so every searched
    // column gets its own parameter.
    $like    = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $filters['q']) . '%';
    $columns = [
        'r.public_code',
        'r.requester_name',
        'r.requester_contact',
        'r.target_location',
        'r.description',
        'r.extra_comment',
    ];

    $likeParts = [];
    foreach ($columns as $index => $column) {
        $likeParts[]           = $column . ' LIKE :q' . $index . " ESCAPE '\\\\'";
        $params['q' . $index]  = $like;
    }
    $where[] = '(' . implode(' OR ', $likeParts) . ')';
}
if ($filters['date_from'] !== '') {
    $where[]             = 'r.created_at >= :date_from';
    $params['date_from'] = rp_local_day_to_utc($filters['date_from'], false);
}
if ($filters['date_to'] !== '') {
    $where[]           = 'r.created_at <= :date_to';
    $params['date_to'] = rp_local_day_to_utc($filters['date_to'], true);
}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$pdo      = rp_db();

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM ' . RP_TABLE_REQUESTS . ' r' . $whereSql);
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$pages = max(1, (int) ceil($total / RP_PER_PAGE));
$page  = max(1, min($pages, (int) ($_GET['page'] ?? 1)));

$listStmt = $pdo->prepare(
    'SELECT r.*, (SELECT COUNT(*) FROM ' . RP_TABLE_FILES . ' f WHERE f.request_id = r.id) AS file_count
     FROM ' . RP_TABLE_REQUESTS . ' r'
    . $whereSql .
    ' ORDER BY r.created_at DESC, r.id DESC
      LIMIT ' . RP_PER_PAGE . ' OFFSET ' . (($page - 1) * RP_PER_PAGE)
);
$listStmt->execute($params);
$requests = $listStmt->fetchAll();

/** Build a list URL preserving the active filters. */
$pageUrl = static function (int $target) use ($filters): string {
    $query = array_filter($filters, static fn (string $value): bool => $value !== '');
    $query['page'] = $target;

    return rp_url('admin/index.php') . '?' . http_build_query($query);
};

rp_header(__('admin.list_title'), 'admin');
?>
<div class="card">
    <h1><?= e(__('admin.list_title')) ?></h1>
    <form method="get" action="<?= e(rp_url('admin/index.php')) ?>" class="filters">
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
            <label for="type"><?= e(__('admin.filter.type')) ?></label>
            <select id="type" name="type">
                <option value=""><?= e(__('common.all')) ?></option>
                <?php foreach (rp_request_types() as $type): ?>
                    <option value="<?= e($type) ?>" <?= $filters['type'] === $type ? 'selected' : '' ?>>
                        <?= e(rp_type_label($type)) ?>
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
            <a class="btn" href="<?= e(rp_url('admin/index.php')) ?>"><?= e(__('common.reset')) ?></a>
        </div>
    </form>
</div>

<div class="card">
    <p class="muted"><?= e(__('admin.total', $total)) ?></p>

    <?php if (!$requests): ?>
        <p><?= e(__('admin.table.empty')) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="list">
                <thead>
                <tr>
                    <th><?= e(__('admin.table.code')) ?></th>
                    <th><?= e(__('admin.table.created')) ?></th>
                    <th><?= e(__('admin.table.type')) ?></th>
                    <th><?= e(__('admin.table.target')) ?></th>
                    <th><?= e(__('admin.table.requester')) ?></th>
                    <th><?= e(__('admin.table.files')) ?></th>
                    <th><?= e(__('admin.table.status')) ?></th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($requests as $request): ?>
                    <tr>
                        <td class="code-cell"><?= e((string) $request['public_code']) ?></td>
                        <td><?= e(rp_format_datetime((string) $request['created_at'])) ?></td>
                        <td><?= e(rp_type_label((string) $request['type'])) ?></td>
                        <td><?= e(mb_strimwidth((string) $request['target_location'], 0, 60, '…', 'UTF-8')) ?></td>
                        <td>
                            <?= e((string) $request['requester_name']) ?>
                            <small class="meta"><?= e((string) $request['requester_contact']) ?></small>
                        </td>
                        <td><?= (int) $request['file_count'] ?></td>
                        <td>
                            <span class="badge status-<?= e((string) $request['status']) ?>">
                                <?= e(rp_status_label((string) $request['status'])) ?>
                            </span>
                        </td>
                        <td>
                            <a class="btn btn-small"
                               href="<?= e(rp_url('admin/view.php?id=' . (int) $request['id'])) ?>">
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
