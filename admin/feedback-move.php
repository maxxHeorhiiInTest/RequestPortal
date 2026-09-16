<?php
/**
 * Admin: change a feedback status from the Kanban board.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$admin = rp_admin_user();
if ($admin === null) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'auth']);
    exit;
}

$raw  = (string) file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

$id     = (int) ($data['id'] ?? 0);
$status = is_string($data['status'] ?? null) ? (string) $data['status'] : '';
$token  = $data['csrf_token'] ?? null;

if (!rp_csrf_valid($token) || $id <= 0 || !in_array($status, rp_statuses(), true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid']);
    exit;
}

$pdo      = rp_db();
$feedback = rp_find_feedback($pdo, $id);
if ($feedback === null) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'not_found']);
    exit;
}

$oldStatus = (string) $feedback['status'];
if ($oldStatus === $status) {
    echo json_encode(['ok' => true, 'unchanged' => true]);
    exit;
}

try {
    $pdo->beginTransaction();
    $update = $pdo->prepare(
        'UPDATE ' . RP_TABLE_FEEDBACK . ' SET status = :status, updated_at = NOW() WHERE id = :id'
    );
    $update->execute(['status' => $status, 'id' => $id]);
    rp_log_feedback_history($pdo, $id, 'status_changed', $oldStatus, $status, null, $admin['username']);
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[request-portal] feedback move failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'save']);
    exit;
}

echo json_encode(['ok' => true]);
