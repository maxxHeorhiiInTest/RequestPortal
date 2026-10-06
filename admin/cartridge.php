<?php
/**
 * Admin: cartridge refill requests list.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/auth.php';
require dirname(__DIR__) . '/includes/layout.php';
require dirname(__DIR__) . '/includes/cartridge.php';

rp_require_admin();

const RP_CARTRIDGE_PER_PAGE = 25;

$validDate = static function (mixed $value): string {
    $value = is_string($value) ? trim($value) : '';
    $date  = DateTimeImmutable::createFromFormat('Y-m-d', $value);

    return ($date && $date->format('Y-m-d') === $value) ? $value : '';
};

$filters = [
    'status'    => in_array($_GET['status'] ?? '', rp_cartridge_statuses(), true) ? (string) $_GET['status'] : '',
    'q'         => rp_clean_string($_GET['q'] ?? '', 120),
    'date_from' => $validDate($_GET['date_from'] ?? ''),
    'date_to'   => $validDate($_GET['date_to'] ?? ''),
];

$where  = [rp_sql_alive('t')];
$params = [];

if ($filters['status'] !== '') {
    $where[]          = 't.status = :status';
    $params['status'] = $filters['status'];
}
if ($filters['q'] !== '') {
    $like    = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $filters['q']) . '%';
    $columns = ['t.public_code', 't.building', 't.room', 't.printer_model', 't.cartridge_model', 't.requester_name', 't.requester_phone'];
    $parts   = [];
    foreach ($columns as $index => $column) {
        $parts[]              = $column . ' LIKE :q' . $index . " ESCAPE '\\\\'";
        $params['q' . $index] = $like;
    }
    $where[] = '(' . implode(' OR ', $parts) . ')';
}
if ($filters['date_from'] !== '') {
    $where[]             = 't.created_at >= :date_from';
    $params['date_from'] = rp_local_day_to_utc($filters['date_from'], false);
}
if ($filters['date_to'] !== '') {
    $where[]           = 't.created_at <= :date_to';
    $params['date_to'] = rp_local_day_to_utc($filters['date_to'], true);
}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$pdo      = rp_db();

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM ' . RP_TABLE_CARTRIDGE . ' t' . $whereSql);
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$pages = max(1, (int) ceil($total / RP_CARTRIDGE_PER_PAGE));
$page  = max(1, min($pages, (int) ($_GET['page'] ?? 1)));

$listStmt = $pdo->prepare(
    'SELECT t.* FROM ' . RP_TABLE_CARTRIDGE . ' t'
    . $whereSql .
    ' ORDER BY t.created_at DESC, t.id DESC
      LIMIT ' . RP_CARTRIDGE_PER_PAGE . ' OFFSET ' . (($page - 1) * RP_CARTRIDGE_PER_PAGE)
);
$listStmt->execute($params);
$rows = $listStmt->fetchAll();

$pageUrl = static function (int $target) use ($filters): string {
    $query         = array_filter($filters, static fn (string $value): bool => $value !== '');
    $query['page'] = $target;

    return rp_url('admin/cartridge.php') . '?' . http_build_query($query);
};

rp_header(__('admin.section.cartridge'), 'admin');
?>
<div class="card">
    <div class="page-head">
        <h1><?= e(__('admin.section.cartridge')) ?></h1>
        <a class="btn btn-primary" href="<?= e(rp_url('admin/cartridge-print.php')) ?>"><?= e(__('admin.cartridge.print_firm')) ?></a>
    </div>
    <form method="get" action="<?= e(rp_url('admin/cartridge.php')) ?>" class="filters">
        <div class="field">
            <label for="q"><?= e(__('admin.filter.query')) ?></label>
            <input type="text" id="q" name="q" maxlength="120" value="<?= e($filters['q']) ?>"
                   placeholder="<?= e(__('admin.cartridge.query_hint')) ?>">
        </div>
        <div class="field">
            <label for="status"><?= e(__('admin.filter.status')) ?></label>
            <select id="status" name="status">
                <option value=""><?= e(__('common.all')) ?></option>
                <?php foreach (rp_cartridge_statuses() as $status): ?>
                    <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>>
                        <?= e(rp_cartridge_status_label($status)) ?>
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
            <a class="btn" href="<?= e(rp_url('admin/cartridge.php')) ?>"><?= e(__('common.reset')) ?></a>
        </div>
    </form>
</div>

<div class="card">
    <p class="muted"><?= e(__('admin.cartridge.total', $total)) ?></p>
    <?php if (!$rows): ?>
        <p><?= e(__('admin.cartridge.empty')) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="list">
                <thead>
                <tr>
                    <th><?= e(__('admin.table.code')) ?></th>
                    <th><?= e(__('admin.table.created')) ?></th>
                    <th><?= e(__('cartridge.building')) ?></th>
                    <th><?= e(__('cartridge.room')) ?></th>
                    <th><?= e(__('cartridge.printer')) ?></th>
                    <th><?= e(__('cartridge.cartridge')) ?></th>
                    <th><?= e(__('admin.table.status')) ?></th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td class="code-cell"><?= e((string) $row['public_code']) ?></td>
                        <td><?= e(rp_format_datetime((string) $row['created_at'])) ?></td>
                        <td><?= e((string) $row['building']) ?></td>
                        <td><?= e((string) $row['room']) ?></td>
                        <td><?= e((string) $row['printer_model']) ?></td>
                        <td><?= e((string) $row['cartridge_model']) ?></td>
                        <td>
                            <span class="badge status-<?= e((string) $row['status']) ?>">
                                <?= e(rp_cartridge_status_label((string) $row['status'])) ?>
                            </span>
                        </td>
                        <td>
                            <a class="btn btn-small"
                               href="<?= e(rp_url('admin/cartridge-view.php?id=' . (int) $row['id'])) ?>">
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
