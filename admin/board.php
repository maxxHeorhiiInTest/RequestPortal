<?php
/**
 * Admin: Kanban board of all requests by status.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/auth.php';
require dirname(__DIR__) . '/includes/layout.php';

rp_require_admin();

$pdo = rp_db();
$stmt = $pdo->query(
    'SELECT r.*, (SELECT COUNT(*) FROM ' . RP_TABLE_FILES . ' f WHERE f.request_id = r.id) AS file_count
     FROM ' . RP_TABLE_REQUESTS . ' r
     WHERE ' . rp_sql_alive('r') . '
     ORDER BY r.created_at DESC, r.id DESC'
);
$requests = $stmt->fetchAll();

$columns = [];
foreach (rp_statuses() as $status) {
    $columns[$status] = [];
}
foreach ($requests as $request) {
    $status = (string) $request['status'];
    if (!isset($columns[$status])) {
        $columns[$status] = [];
    }
    $columns[$status][] = $request;
}

rp_header(__('admin.board_title'), 'admin');
?>
<div class="card" style="padding-bottom:14px">
    <h1><?= e(__('admin.board_title')) ?></h1>
    <p class="muted kanban-hint"><?= e(__('admin.board_hint')) ?></p>
</div>

<div class="kanban" id="kanban"
     data-move-url="<?= e(rp_url('admin/board-move.php')) ?>"
     data-csrf="<?= e(rp_csrf_token()) ?>"
     data-fail="<?= e(__('admin.board_move_failed')) ?>">
    <?php foreach ($columns as $status => $cards): ?>
        <section class="kanban-col" data-status="<?= e($status) ?>">
            <div class="kanban-col-head">
                <span class="badge status-<?= e($status) ?>"><?= e(rp_status_label($status)) ?></span>
                <span class="kanban-count"><?= count($cards) ?></span>
            </div>
            <p class="kanban-empty"<?= $cards ? ' hidden' : '' ?>><?= e(__('admin.board_empty')) ?></p>
            <?php foreach ($cards as $request): ?>
                <a class="kanban-card"
                   draggable="true"
                   href="<?= e(rp_url('admin/view.php?id=' . (int) $request['id'])) ?>"
                   data-id="<?= (int) $request['id'] ?>"
                   data-status="<?= e((string) $request['status']) ?>">
                    <span class="code-cell"><?= e((string) $request['public_code']) ?></span>
                    <span class="target"><?= e(mb_strimwidth((string) $request['target_location'], 0, 80, '…', 'UTF-8')) ?></span>
                    <span class="meta">
                        <?= e(rp_type_label((string) $request['type'])) ?> ·
                        <?= e((string) $request['requester_name']) ?>
                        <?php
                        $unit = trim((string) ($request['faculty'] ?? '') . ' / ' . (string) ($request['department'] ?? ''), ' /');
                        if ($unit !== ''):
                        ?>
                            · <?= e($unit) ?>
                        <?php endif; ?>
                        · <?= e(rp_format_datetime((string) $request['created_at'])) ?>
                        <?php if ((int) $request['file_count'] > 0): ?>
                            · <?= (int) $request['file_count'] ?>
                        <?php endif; ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </section>
    <?php endforeach; ?>
</div>

<script>
(function () {
    var board = document.getElementById('kanban');
    if (!board) return;

    var moveUrl = board.getAttribute('data-move-url');
    var csrf = board.getAttribute('data-csrf');
    var failMsg = board.getAttribute('data-fail');
    var dragging = null;
    var didDrag = false;

    function columnOf(el) {
        return el && el.closest ? el.closest('.kanban-col') : null;
    }

    function refreshColumn(col) {
        if (!col) return;
        var cards = col.querySelectorAll('.kanban-card');
        var empty = col.querySelector('.kanban-empty');
        var count = col.querySelector('.kanban-count');
        if (count) count.textContent = String(cards.length);
        if (empty) empty.hidden = cards.length > 0;
    }

    board.addEventListener('click', function (event) {
        if (!didDrag) return;
        var card = event.target.closest('.kanban-card');
        if (card) event.preventDefault();
        didDrag = false;
    });

    board.addEventListener('dragstart', function (event) {
        var card = event.target.closest('.kanban-card');
        if (!card) return;
        dragging = card;
        didDrag = true;
        card.classList.add('dragging');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', card.getAttribute('data-id') || '');
    });

    board.addEventListener('dragend', function () {
        if (dragging) dragging.classList.remove('dragging');
        dragging = null;
        board.querySelectorAll('.kanban-col').forEach(function (col) {
            col.classList.remove('drag-over');
        });
    });

    board.addEventListener('dragover', function (event) {
        var col = columnOf(event.target);
        if (!col || !dragging) return;
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        board.querySelectorAll('.kanban-col').forEach(function (item) {
            item.classList.toggle('drag-over', item === col);
        });
    });

    board.addEventListener('drop', function (event) {
        var col = columnOf(event.target);
        if (!col || !dragging) return;
        event.preventDefault();

        var newStatus = col.getAttribute('data-status') || '';
        var oldStatus = dragging.getAttribute('data-status') || '';
        var fromCol = columnOf(dragging);
        if (newStatus === oldStatus) return;

        var card = dragging;
        var empty = col.querySelector('.kanban-empty');
        if (empty && empty.nextSibling) {
            col.insertBefore(card, empty.nextSibling);
        } else {
            col.appendChild(card);
        }
        card.setAttribute('data-status', newStatus);
        refreshColumn(fromCol);
        refreshColumn(col);

        fetch(moveUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({
                csrf_token: csrf,
                id: Number(card.getAttribute('data-id')),
                status: newStatus
            })
        }).then(function (response) {
            return response.json().then(function (data) {
                return { ok: response.ok && data && data.ok, data: data };
            });
        }).then(function (result) {
            if (result.ok) return;
            if (fromCol) fromCol.appendChild(card);
            card.setAttribute('data-status', oldStatus);
            refreshColumn(fromCol);
            refreshColumn(col);
            window.alert(failMsg);
        }).catch(function () {
            if (fromCol) fromCol.appendChild(card);
            card.setAttribute('data-status', oldStatus);
            refreshColumn(fromCol);
            refreshColumn(col);
            window.alert(failMsg);
        });
    });
})();
</script>
<?php rp_footer();
