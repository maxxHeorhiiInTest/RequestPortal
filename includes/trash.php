<?php
/**
 * Soft-delete and restore for public submissions. Full admin only.
 */

declare(strict_types=1);

/** @return list<string> */
function rp_trash_kinds(): array
{
    return ['request', 'plan', 'feedback', 'it', 'cartridge'];
}

function rp_sql_alive(string $alias = ''): string
{
    $column = $alias === '' ? 'deleted_at' : $alias . '.deleted_at';

    return $column . ' IS NULL';
}

function rp_item_is_deleted(array $row): bool
{
    return trim((string) ($row['deleted_at'] ?? '')) !== '';
}

function rp_trash_table(string $kind): ?string
{
    return match ($kind) {
        'request'  => RP_TABLE_REQUESTS,
        'plan'     => RP_TABLE_CONTENT,
        'feedback' => RP_TABLE_FEEDBACK,
        'it'        => RP_TABLE_IT,
        'cartridge' => RP_TABLE_CARTRIDGE,
        default    => null,
    };
}

function rp_trash_view_url(string $kind, int $id): string
{
    return match ($kind) {
        'request'  => 'admin/view.php?id=' . $id,
        'plan'     => 'admin/plan-edit.php?id=' . $id,
        'feedback' => 'admin/feedback-view.php?id=' . $id,
        'it'        => 'admin/it-view.php?id=' . $id,
        'cartridge' => 'admin/cartridge-view.php?id=' . $id,
        default    => 'admin/trash.php',
    };
}

function rp_trash_list_url(string $kind): string
{
    return match ($kind) {
        'request'  => 'admin/index.php',
        'plan'     => 'admin/plan.php',
        'feedback' => 'admin/feedback.php',
        'it'        => 'admin/it.php',
        'cartridge' => 'admin/cartridge.php',
        default    => 'admin/trash.php',
    };
}

function rp_trash_find(PDO $pdo, string $kind, int $id): ?array
{
    if ($kind === 'plan') {
        require_once __DIR__ . '/content.php';
    }

    return match ($kind) {
        'request'  => rp_find_request($pdo, $id),
        'plan'     => rp_find_content($pdo, $id),
        'feedback' => rp_find_feedback($pdo, $id),
        'it'        => rp_find_it_ticket($pdo, $id),
        'cartridge' => rp_find_cartridge($pdo, $id),
        default    => null,
    };
}

function rp_trash_title(string $kind, array $row): string
{
    return match ($kind) {
        'request', 'feedback', 'it', 'cartridge' => (string) ($row['public_code'] ?? ''),
        'plan' => (string) ($row['title'] ?? ''),
        default => '#' . (int) ($row['id'] ?? 0),
    };
}

function rp_soft_delete(PDO $pdo, string $kind, int $id, string $actor): bool
{
    $table = rp_trash_table($kind);
    $row   = $table !== null ? rp_trash_find($pdo, $kind, $id) : null;
    if ($table === null || $row === null || rp_item_is_deleted($row)) {
        return false;
    }

    $stmt = $pdo->prepare(
        'UPDATE `' . $table . '`
         SET deleted_at = NOW(), deleted_by = :by, updated_at = NOW()
         WHERE id = :id AND deleted_at IS NULL'
    );
    $stmt->execute(['by' => $actor, 'id' => $id]);
    if ($stmt->rowCount() < 1) {
        return false;
    }

    rp_trash_log($pdo, $kind, $id, 'deleted', $actor);

    return true;
}

function rp_soft_restore(PDO $pdo, string $kind, int $id, string $actor): bool
{
    $table = rp_trash_table($kind);
    $row   = $table !== null ? rp_trash_find($pdo, $kind, $id) : null;
    if ($table === null || $row === null || !rp_item_is_deleted($row)) {
        return false;
    }

    $stmt = $pdo->prepare(
        'UPDATE `' . $table . '`
         SET deleted_at = NULL, deleted_by = NULL, updated_at = NOW()
         WHERE id = :id AND deleted_at IS NOT NULL'
    );
    $stmt->execute(['id' => $id]);
    if ($stmt->rowCount() < 1) {
        return false;
    }

    rp_trash_log($pdo, $kind, $id, 'restored', $actor);

    return true;
}

function rp_trash_log(PDO $pdo, string $kind, int $id, string $action, string $actor): void
{
    if ($kind === 'request') {
        rp_log_history($pdo, $id, $action, null, null, null, $actor);
    } elseif ($kind === 'feedback') {
        rp_log_feedback_history($pdo, $id, $action, null, null, null, $actor);
    } elseif ($kind === 'it') {
        rp_log_it_history($pdo, $id, $action, null, null, null, $actor);
    } elseif ($kind === 'cartridge') {
        rp_log_cartridge_history($pdo, $id, $action, null, null, null, $actor);
    }
}

/**
 * @return list<array<string,mixed>>
 */
function rp_trash_rows(PDO $pdo, string $kind, int $limit = 100): array
{
    $table = rp_trash_table($kind);
    if ($table === null) {
        return [];
    }
    $limit = max(1, min(200, $limit));
    $stmt  = $pdo->query(
        'SELECT * FROM `' . $table . '`
         WHERE deleted_at IS NOT NULL
         ORDER BY deleted_at DESC, id DESC
         LIMIT ' . $limit
    );

    return $stmt->fetchAll();
}

function rp_admin_guard_deleted(array $row, string $listUrl): bool
{
    $deleted = rp_item_is_deleted($row);
    if ($deleted && !rp_admin_is_full()) {
        rp_flash('error', __('admin.not_found'));
        rp_redirect($listUrl);
    }

    return $deleted;
}

function rp_admin_trash_box(string $kind, int $id, bool $deleted): void
{
    if (!rp_admin_is_full() || $id <= 0) {
        return;
    }

    if ($deleted) {
        ?>
        <div class="card">
            <h2><?= e(__('admin.trash.deleted_title')) ?></h2>
            <p class="muted"><?= e(__('admin.trash.deleted_hint')) ?></p>
            <form method="post" action="<?= e(rp_url('admin/soft-restore.php')) ?>">
                <?= rp_csrf_field() ?>
                <input type="hidden" name="kind" value="<?= e($kind) ?>">
                <input type="hidden" name="id" value="<?= (int) $id ?>">
                <button type="submit" class="btn btn-primary"><?= e(__('admin.trash.restore')) ?></button>
            </form>
        </div>
        <?php
        return;
    }

    ?>
    <div class="card">
        <h2><?= e(__('admin.trash.delete_title')) ?></h2>
        <p class="muted"><?= e(__('admin.trash.delete_hint')) ?></p>
        <form method="post" action="<?= e(rp_url('admin/soft-delete.php')) ?>" class="delete-block"
              onsubmit='return confirm(<?= json_encode(__('admin.trash.delete_confirm'), JSON_UNESCAPED_UNICODE) ?>);'>
            <?= rp_csrf_field() ?>
            <input type="hidden" name="kind" value="<?= e($kind) ?>">
            <input type="hidden" name="id" value="<?= (int) $id ?>">
            <button type="submit" class="btn btn-danger"><?= e(__('admin.trash.delete')) ?></button>
        </form>
    </div>
    <?php
}
