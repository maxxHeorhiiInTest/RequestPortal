<?php
/**
 * Admin: public-page visit statistics.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/auth.php';
require dirname(__DIR__) . '/includes/layout.php';

rp_require_admin();

const RP_STATS_PER_PAGE = 25;

$validDate = static function (mixed $value): string {
    $value = is_string($value) ? trim($value) : '';
    $date  = DateTimeImmutable::createFromFormat('Y-m-d', $value);

    return ($date && $date->format('Y-m-d') === $value) ? $value : '';
};

$tz      = rp_app_timezone();
$today   = (new DateTimeImmutable('now', $tz))->format('Y-m-d');
$weekAgo = (new DateTimeImmutable('now', $tz))->modify('-6 days')->format('Y-m-d');
$allTime = (string) ($_GET['all'] ?? '') === '1';

$dateFrom = $validDate($_GET['date_from'] ?? '');
$dateTo   = $validDate($_GET['date_to'] ?? '');

if (!$allTime && $dateFrom === '' && $dateTo === '') {
    $dateFrom = $weekAgo;
    $dateTo   = $today;
}

$filters = [
    'date_from' => $allTime ? '' : $dateFrom,
    'date_to'   => $allTime ? '' : $dateTo,
    'ip'        => rp_clean_string($_GET['ip'] ?? '', 45),
    'path'      => in_array($_GET['path'] ?? '', rp_public_visit_scripts(), true)
        ? (string) $_GET['path']
        : '',
];

$where  = [];
$params = [];

if ($filters['date_from'] !== '') {
    $where[]             = 'v.created_at >= :date_from';
    $params['date_from'] = rp_local_day_to_utc($filters['date_from'], false);
}
if ($filters['date_to'] !== '') {
    $where[]           = 'v.created_at <= :date_to';
    $params['date_to'] = rp_local_day_to_utc($filters['date_to'], true);
}
if ($filters['ip'] !== '') {
    $like            = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $filters['ip']) . '%';
    $where[]         = 'v.ip_address LIKE :ip ESCAPE \'\\\\\'';
    $params['ip']    = $like;
}
if ($filters['path'] !== '') {
    $where[]        = 'v.path = :path';
    $params['path'] = $filters['path'];
}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$pdo      = rp_db();

$summaryStmt = $pdo->prepare(
    'SELECT COUNT(*) AS views,
            COUNT(DISTINCT ip_address) AS ips,
            COUNT(DISTINCT session_id) AS sessions
     FROM ' . RP_TABLE_VISITS . ' v' . $whereSql
);
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch() ?: ['views' => 0, 'ips' => 0, 'sessions' => 0];

$pagesStmt = $pdo->prepare(
    'SELECT v.path,
            COUNT(*) AS views,
            COUNT(DISTINCT v.ip_address) AS ips,
            COUNT(DISTINCT v.session_id) AS sessions
     FROM ' . RP_TABLE_VISITS . ' v' . $whereSql . '
     GROUP BY v.path
     ORDER BY views DESC, v.path ASC'
);
$pagesStmt->execute($params);
$pages = $pagesStmt->fetchAll();

$ipStmt = $pdo->prepare(
    'SELECT v.ip_address,
            COUNT(*) AS views,
            COUNT(DISTINCT v.path) AS pages,
            MAX(v.created_at) AS last_seen
     FROM ' . RP_TABLE_VISITS . ' v' . $whereSql . '
     GROUP BY v.ip_address
     ORDER BY views DESC, last_seen DESC
     LIMIT 30'
);
$ipStmt->execute($params);
$topIps = $ipStmt->fetchAll();

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM ' . RP_TABLE_VISITS . ' v' . $whereSql);
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$listPages = max(1, (int) ceil($total / RP_STATS_PER_PAGE));
$page      = max(1, min($listPages, (int) ($_GET['page'] ?? 1)));

$listStmt = $pdo->prepare(
    'SELECT v.created_at, v.path, v.ip_address, v.lang
     FROM ' . RP_TABLE_VISITS . ' v' . $whereSql . '
     ORDER BY v.created_at DESC, v.id DESC
     LIMIT ' . RP_STATS_PER_PAGE . ' OFFSET ' . (($page - 1) * RP_STATS_PER_PAGE)
);
$listStmt->execute($params);
$visits = $listStmt->fetchAll();

$queryBase = array_filter(
    [
        'date_from' => $filters['date_from'],
        'date_to'   => $filters['date_to'],
        'ip'        => $filters['ip'],
        'path'      => $filters['path'],
        'all'       => $allTime ? '1' : '',
    ],
    static fn (string $value): bool => $value !== ''
);

$pageUrl = static function (int $target) use ($queryBase): string {
    $query         = $queryBase;
    $query['page'] = $target;

    return rp_url('admin/stats.php') . '?' . http_build_query($query);
};

$presetUrl = static function (string $from, string $to, bool $all = false) use ($filters): string {
    $query = array_filter(
        [
            'date_from' => $all ? '' : $from,
            'date_to'   => $all ? '' : $to,
            'ip'        => $filters['ip'],
            'path'      => $filters['path'],
            'all'       => $all ? '1' : '',
        ],
        static fn (string $value): bool => $value !== ''
    );

    return rp_url('admin/stats.php') . ($query ? '?' . http_build_query($query) : '');
};

$monthAgo = (new DateTimeImmutable('now', $tz))->modify('-29 days')->format('Y-m-d');

rp_header(__('admin.stats.title'), 'admin');
?>
<div class="card">
    <h1><?= e(__('admin.stats.title')) ?></h1>
    <p class="muted"><?= e(__('admin.stats.intro')) ?></p>
    <p class="preset-links">
        <a href="<?= e($presetUrl($today, $today)) ?>"><?= e(__('admin.stats.preset.today')) ?></a>
        <a href="<?= e($presetUrl($weekAgo, $today)) ?>"><?= e(__('admin.stats.preset.week')) ?></a>
        <a href="<?= e($presetUrl($monthAgo, $today)) ?>"><?= e(__('admin.stats.preset.month')) ?></a>
        <a href="<?= e($presetUrl('', '', true)) ?>"><?= e(__('admin.stats.preset.all')) ?></a>
    </p>
    <form method="get" action="<?= e(rp_url('admin/stats.php')) ?>" class="filters">
        <div class="field">
            <label for="date_from"><?= e(__('admin.filter.date_from')) ?></label>
            <input type="date" id="date_from" name="date_from" value="<?= e($filters['date_from']) ?>">
        </div>
        <div class="field">
            <label for="date_to"><?= e(__('admin.filter.date_to')) ?></label>
            <input type="date" id="date_to" name="date_to" value="<?= e($filters['date_to']) ?>">
        </div>
        <div class="field">
            <label for="path"><?= e(__('admin.stats.filter.page')) ?></label>
            <select id="path" name="path">
                <option value=""><?= e(__('common.all')) ?></option>
                <?php foreach (rp_public_visit_scripts() as $script): ?>
                    <option value="<?= e($script) ?>" <?= $filters['path'] === $script ? 'selected' : '' ?>>
                        <?= e(rp_visit_page_label($script)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="ip"><?= e(__('admin.stats.filter.ip')) ?></label>
            <input type="text" id="ip" name="ip" maxlength="45" value="<?= e($filters['ip']) ?>"
                   placeholder="192.168…">
        </div>
        <div class="field actions">
            <button type="submit" class="btn btn-primary"><?= e(__('common.search')) ?></button>
            <a class="btn" href="<?= e(rp_url('admin/stats.php')) ?>"><?= e(__('common.reset')) ?></a>
        </div>
    </form>
</div>

<div class="stat-grid">
    <div class="stat-box">
        <div class="stat-value"><?= (int) $summary['views'] ?></div>
        <div class="stat-label"><?= e(__('admin.stats.views')) ?></div>
    </div>
    <div class="stat-box">
        <div class="stat-value"><?= (int) $summary['sessions'] ?></div>
        <div class="stat-label"><?= e(__('admin.stats.unique_people')) ?></div>
    </div>
    <div class="stat-box">
        <div class="stat-value"><?= (int) $summary['ips'] ?></div>
        <div class="stat-label"><?= e(__('admin.stats.unique_ip')) ?></div>
    </div>
</div>

<?php
$createdWhere  = [];
$createdParams = [];
if ($filters['date_from'] !== '') {
    $createdWhere[]              = 'created_at >= :date_from';
    $createdParams['date_from']  = $params['date_from'];
}
if ($filters['date_to'] !== '') {
    $createdWhere[]           = 'created_at <= :date_to';
    $createdParams['date_to'] = $params['date_to'];
}
$createdSql = $createdWhere ? ' WHERE ' . implode(' AND ', $createdWhere) : '';

$countCreated = static function (string $table, string $extra = '') use ($pdo, $createdSql, $createdParams): int {
    $sql = 'SELECT COUNT(*) FROM ' . $table . $createdSql;
    if ($extra !== '') {
        $sql .= ($createdSql === '' ? ' WHERE ' : ' AND ') . $extra;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($createdParams);

    return (int) $stmt->fetchColumn();
};

$submitted = [
    'requests' => $countCreated(RP_TABLE_REQUESTS),
    'plan'     => $countCreated(RP_TABLE_CONTENT, "created_by LIKE 'guest:%'"),
    'feedback' => $countCreated(RP_TABLE_FEEDBACK),
];
?>
<div class="card">
    <h2><?= e(__('admin.stats.submissions')) ?></h2>
    <p class="muted"><?= e(__('admin.stats.sub.intro')) ?></p>
</div>
<div class="stat-grid">
    <div class="stat-box">
        <div class="stat-value"><?= $submitted['requests'] ?></div>
        <div class="stat-label"><?= e(__('admin.stats.sub.requests')) ?></div>
    </div>
    <div class="stat-box">
        <div class="stat-value"><?= $submitted['plan'] ?></div>
        <div class="stat-label"><?= e(__('admin.stats.sub.plan')) ?></div>
    </div>
    <div class="stat-box">
        <div class="stat-value"><?= $submitted['feedback'] ?></div>
        <div class="stat-label"><?= e(__('admin.stats.sub.feedback')) ?></div>
    </div>
</div>

<div class="card">
    <h2><?= e(__('admin.stats.pages')) ?></h2>
    <?php if (!$pages): ?>
        <p><?= e(__('admin.stats.empty')) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="list">
                <thead>
                <tr>
                    <th><?= e(__('admin.stats.table.page')) ?></th>
                    <th><?= e(__('admin.stats.views')) ?></th>
                    <th><?= e(__('admin.stats.unique_people')) ?></th>
                    <th><?= e(__('admin.stats.unique_ip')) ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($pages as $row): ?>
                    <tr>
                        <td><?= e(rp_visit_page_label((string) $row['path'])) ?></td>
                        <td><?= (int) $row['views'] ?></td>
                        <td><?= (int) $row['sessions'] ?></td>
                        <td><?= (int) $row['ips'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <h2><?= e(__('admin.stats.top_ips')) ?></h2>
    <?php if (!$topIps): ?>
        <p><?= e(__('admin.stats.empty')) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="list">
                <thead>
                <tr>
                    <th><?= e(__('admin.stats.table.ip')) ?></th>
                    <th><?= e(__('admin.stats.views')) ?></th>
                    <th><?= e(__('admin.stats.table.pages')) ?></th>
                    <th><?= e(__('admin.stats.table.last_seen')) ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($topIps as $row): ?>
                    <tr>
                        <td class="code-cell"><?= e((string) ($row['ip_address'] ?: '—')) ?></td>
                        <td><?= (int) $row['views'] ?></td>
                        <td><?= (int) $row['pages'] ?></td>
                        <td><?= e(rp_format_datetime((string) $row['last_seen'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <h2><?= e(__('admin.stats.recent')) ?></h2>
    <p class="muted"><?= e(__('admin.stats.total', $total)) ?></p>
    <?php if (!$visits): ?>
        <p><?= e(__('admin.stats.empty')) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="list">
                <thead>
                <tr>
                    <th><?= e(__('admin.stats.table.when')) ?></th>
                    <th><?= e(__('admin.stats.table.page')) ?></th>
                    <th><?= e(__('admin.stats.table.ip')) ?></th>
                    <th><?= e(__('admin.stats.table.lang')) ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($visits as $visit): ?>
                    <tr>
                        <td><?= e(rp_format_datetime((string) $visit['created_at'])) ?></td>
                        <td><?= e(rp_visit_page_label((string) $visit['path'])) ?></td>
                        <td class="code-cell"><?= e((string) ($visit['ip_address'] ?: '—')) ?></td>
                        <td><?= e(strtoupper((string) ($visit['lang'] ?: '—'))) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($listPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a class="btn btn-small" href="<?= e($pageUrl($page - 1)) ?>"><?= e(__('admin.prev')) ?></a>
                <?php endif; ?>
                <span class="muted"><?= e(__('admin.pagination', $page, $listPages)) ?></span>
                <?php if ($page < $listPages): ?>
                    <a class="btn btn-small" href="<?= e($pageUrl($page + 1)) ?>"><?= e(__('admin.next')) ?></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php rp_footer();
