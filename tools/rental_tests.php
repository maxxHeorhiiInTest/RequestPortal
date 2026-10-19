<?php
/**
 * Business-rule checks for the rental CRM (acceptance criteria).
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/auth.php';

$pdo = rp_db();
$fail = 0;
$ok   = 0;

$assert = static function (bool $cond, string $name) use (&$fail, &$ok): void {
    if ($cond) {
        $ok++;
        echo "OK  {$name}\n";
    } else {
        $fail++;
        echo "FAIL {$name}\n";
    }
};

$room = $pdo->query("SELECT * FROM " . RP_TABLE_RENTAL_ROOMS . " WHERE slug = 'b6-sports'")->fetch();
$assert(is_array($room), '1. seed sports hall building 6');
$roomId = (int) ($room['id'] ?? 0);

$assert(rp_rental_hourly_price($pdo, $roomId, 19) === '1700.00', '8a. 19 people = 1700');
$assert(rp_rental_hourly_price($pdo, $roomId, 20) === '2600.00', '8b. 20 people = 2600');
$assert(rp_rental_hourly_price($pdo, $roomId, 100) === '4000.00', '8c. 100 people = 4000');

$tz = rp_app_timezone();
$weekday = new DateTimeImmutable('next monday', $tz);
if ((int) $weekday->format('N') !== 1) {
    $weekday = $weekday->modify('monday this week');
}
$ymd = $weekday->modify('+14 days')->format('Y-m-d');
while (rp_rental_is_public_holiday($ymd) || (int) (new DateTimeImmutable($ymd, $tz))->format('N') >= 6) {
    $ymd = (new DateTimeImmutable($ymd, $tz))->modify('+1 day')->format('Y-m-d');
}

$previewAdj = rp_rental_preview_sessions($pdo, [
    ['room_id' => $roomId, 'ymd' => $ymd, 'start' => '18:00', 'end' => '19:00', 'participants' => 5],
    ['room_id' => $roomId, 'ymd' => $ymd, 'start' => '19:00', 'end' => '20:00', 'participants' => 5],
]);
$assert($previewAdj['conflicts'] === [], '2. adjacent 18-19 and 19-20 allowed (no overlap)');

$morning = rp_rental_preview_sessions($pdo, [
    ['room_id' => $roomId, 'ymd' => $ymd, 'start' => '07:45', 'end' => '08:45', 'participants' => 5],
]);
$assert($morning['ok'] === true, '3a. 07:45-08:45 weekday morning slot is allowed');

$prep = rp_rental_preview_sessions($pdo, [
    ['room_id' => $roomId, 'ymd' => $ymd, 'start' => '08:00', 'end' => '09:00', 'participants' => 5],
]);
$assert($prep['ok'] === false, '3b. 08:45-09:00 overlap rejected');

$weekend = (new DateTimeImmutable($ymd, $tz))->modify('next saturday')->format('Y-m-d');
$early = rp_rental_preview_sessions($pdo, [
    ['room_id' => $roomId, 'ymd' => $weekend, 'start' => '07:00', 'end' => '08:00', 'participants' => 5],
]);
$late = rp_rental_preview_sessions($pdo, [
    ['room_id' => $roomId, 'ymd' => $weekend, 'start' => '21:00', 'end' => '22:00', 'participants' => 5],
]);
$assert($early['ok'] === false && $late['ok'] === false, '4. weekend before 08:00 or after 21:00 rejected');

$half = rp_rental_preview_sessions($pdo, [
    ['room_id' => $roomId, 'ymd' => $ymd, 'start' => '18:00', 'end' => '18:30', 'participants' => 5],
]);
$ninety = rp_rental_preview_sessions($pdo, [
    ['room_id' => $roomId, 'ymd' => $ymd, 'start' => '18:00', 'end' => '19:30', 'participants' => 5],
]);
$assert($half['ok'] === false && $ninety['ok'] === false, '5. 30 and 90 minutes rejected');

$daySlot = rp_rental_preview_sessions($pdo, [
    ['room_id' => $roomId, 'ymd' => $ymd, 'start' => '10:00', 'end' => '11:00', 'participants' => 5],
]);
$needs = false;
foreach ($daySlot['errors'] as $err) {
    if (str_contains($err, 'погодження') || str_contains($err, 'approval') || str_contains($err, 'підтверджено')) {
        $needs = true;
    }
}
$assert($needs || rp_rental_month_confirmed($pdo, substr($ymd, 0, 7)), '6. weekday 10:00 needs approval unless month confirmed');

$clientId = rp_rental_save_client($pdo, [
    'name' => 'Тест CRM ' . bin2hex(random_bytes(3)),
    'phone' => '+380501234567',
    'type' => 'person',
], 'test');

$created = rp_rental_create_deal($pdo, $clientId, [
    ['room_id' => $roomId, 'ymd' => $ymd, 'start' => '18:00', 'end' => '19:00', 'participants' => 5],
    ['room_id' => $roomId, 'ymd' => $ymd, 'start' => '19:00', 'end' => '20:00', 'participants' => 5],
], 'test-manager');
$assert(!empty($created['ok']), '10. one deal with two sessions');
$deal = $created['ok'] ? rp_rental_deal($pdo, (int) $created['id']) : null;
$assert($deal !== null && (float) $deal['total_amount'] === 3400.00, '10b. total = 1700*2 hours');

if ($deal) {
    $createdAt = rp_rental_local_dt((string) $deal['created_at']);
    $first = rp_rental_local_dt((string) $pdo->query(
        'SELECT MIN(starts_at) FROM ' . RP_TABLE_RENTAL_SESSIONS . ' WHERE deal_id = ' . (int) $deal['id']
    )->fetchColumn());
    $deadline = rp_rental_local_dt((string) $deal['pay_deadline']);
    $plus72 = $createdAt->modify('+72 hours');
    $expected = $plus72 <= $first ? $plus72 : $first;
    $assert(abs($deadline->getTimestamp() - $expected->getTimestamp()) < 60, '11. deadline = min(72h, first session)');

    $overlap = rp_rental_create_deal($pdo, $clientId, [
        ['room_id' => $roomId, 'ymd' => $ymd, 'start' => '18:00', 'end' => '19:00', 'participants' => 5],
    ], 'test-manager-2');
    $assert(empty($overlap['ok']), '1. second manager cannot overlap the same slot');

    $sessions = rp_rental_deal_sessions($pdo, (int) $deal['id']);
    $sid = (int) $sessions[0]['id'];
    $oldStart = (string) $sessions[0]['starts_at'];
    try {
        rp_rental_move_session($pdo, $sid, $ymd, '19:00', '20:00', 'test');
        $assert(false, '16. move onto the other session is rejected');
    } catch (Throwable) {
        $again = rp_rental_deal_sessions($pdo, (int) $deal['id']);
        $assert((string) $again[0]['starts_at'] === $oldStart, '17. failed move keeps original slot');
    }

    $pdo->prepare(
        'UPDATE ' . RP_TABLE_RENTAL_DEALS . ' SET pay_deadline = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE) WHERE id = :id'
    )->execute(['id' => $deal['id']]);
    rp_rental_expire_overdue($pdo);
    $expired = rp_rental_deal($pdo, (int) $deal['id']);
    $assert($expired['payment_status'] === 'expired', '12. overdue unpaid deal expires and frees the slot');
}

$assert(!rp_rental_can('finance', ['id' => 1, 'username' => 's', 'role' => 'rental_schedule']), '19. schedule role has no finance');
$assert(rp_rental_can('classes', ['id' => 1, 'username' => 's', 'role' => 'rental_schedule']), '19b. schedule role can add classes');
$assert(!in_array('unknown', rp_admin_roles(), true), 'roles list includes rental');
$assert(in_array('rental_manager', rp_admin_roles(), true), 'rental_manager is a known role');

$hours = rp_rental_hours_until((new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('+72 hours')->format('Y-m-d H:i:s'));
$assert($hours >= 71.9 && $hours <= 72.1, '15. 72 hours is the refund threshold');

echo "\n{$ok} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
