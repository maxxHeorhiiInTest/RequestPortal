<?php

declare(strict_types=1);

require_once __DIR__ . '/holidays.php';

/** @return list<string> */
function rp_rental_caps(string $role): array
{
    return match ($role) {
        'rental' => [
            'calendar', 'clients', 'deals', 'pay', 'cancel', 'move', 'refund',
            'classes', 'confirm_month', 'approve', 'stats', 'tariffs', 'rooms', 'users', 'audit',
            'finance',
        ],
        'rental_manager' => [
            'calendar', 'clients', 'deals', 'pay', 'cancel', 'move', 'refund', 'stats', 'finance',
        ],
        'rental_schedule' => [
            'calendar', 'classes', 'confirm_month', 'approve',
        ],
        default => [],
    };
}

function rp_rental_can(string $cap, ?array $admin = null): bool
{
    $admin ??= function_exists('rp_admin_user') ? rp_admin_user() : null;
    if ($admin === null) {
        return false;
    }

    return in_array($cap, rp_rental_caps((string) ($admin['role'] ?? '')), true);
}

function rp_rental_require(string $cap): array
{
    $admin = rp_require_admin('rental');
    if (!rp_rental_can($cap, $admin)) {
        rp_flash('error', __('rental.error.forbidden'));
        rp_redirect(rp_admin_home());
    }

    return $admin;
}

function rp_rental_actor(?array $admin = null): string
{
    $admin ??= function_exists('rp_admin_user') ? rp_admin_user() : null;

    return $admin !== null ? (string) $admin['username'] : 'system';
}

function rp_rental_audit(
    PDO $pdo,
    string $action,
    string $entity,
    ?int $entityId,
    ?string $oldValue,
    ?string $newValue,
    ?string $note,
    string $actor
): void {
    $stmt = $pdo->prepare(
        'INSERT INTO ' . RP_TABLE_RENTAL_AUDIT . '
            (action, entity, entity_id, old_value, new_value, note, actor, actor_ip, created_at)
         VALUES (:action, :entity, :entity_id, :old_value, :new_value, :note, :actor, :ip, UTC_TIMESTAMP())'
    );
    $stmt->execute([
        'action'    => $action,
        'entity'    => $entity,
        'entity_id' => $entityId,
        'old_value' => $oldValue,
        'new_value' => $newValue,
        'note'      => $note,
        'actor'     => $actor,
        'ip'        => function_exists('rp_client_ip') ? rp_client_ip() : null,
    ]);
}

/** @return list<array{slug:string,building:string,name:string,room_number:?string,type:string,area:?float,tariffs:list<array{min:int,max:?int,price:string}>}> */
function rp_rental_catalog(): array
{
    return [
        ['slug' => 'b6-sports', 'building' => '6', 'name' => 'Зал спортивний', 'room_number' => null, 'type' => 'hall', 'area' => null,
            'tariffs' => [['min' => 1, 'max' => 19, 'price' => '1700.00'], ['min' => 20, 'max' => 99, 'price' => '2600.00'], ['min' => 100, 'max' => null, 'price' => '4000.00']]],
        ['slug' => 'b5-sports', 'building' => '5', 'name' => 'Зал спортивний', 'room_number' => null, 'type' => 'hall', 'area' => null,
            'tariffs' => [['min' => 1, 'max' => 19, 'price' => '2300.00'], ['min' => 20, 'max' => 99, 'price' => '3600.00'], ['min' => 100, 'max' => null, 'price' => '5500.00']]],
        ['slug' => 'b3-fencing', 'building' => '3', 'name' => 'Зал фехтування', 'room_number' => null, 'type' => 'hall', 'area' => null,
            'tariffs' => [['min' => 1, 'max' => null, 'price' => '500.00']]],
        ['slug' => 'b3-dance', 'building' => '3', 'name' => 'Зал спортивного танцю', 'room_number' => null, 'type' => 'hall', 'area' => null,
            'tariffs' => [['min' => 1, 'max' => null, 'price' => '700.00']]],
        ['slug' => 'b3-box', 'building' => '3', 'name' => 'Зал боксу', 'room_number' => null, 'type' => 'hall', 'area' => null,
            'tariffs' => [['min' => 1, 'max' => null, 'price' => '500.00']]],
        ['slug' => 'b3-weights', 'building' => '3', 'name' => 'Зал важкої атлетики', 'room_number' => null, 'type' => 'hall', 'area' => null,
            'tariffs' => [['min' => 1, 'max' => 14, 'price' => '400.00'], ['min' => 15, 'max' => null, 'price' => '700.00']]],
        ['slug' => 'b3-gymn', 'building' => '3', 'name' => 'Зал гімнастики', 'room_number' => null, 'type' => 'hall', 'area' => null,
            'tariffs' => [['min' => 1, 'max' => null, 'price' => '1100.00']]],
        ['slug' => 'b1-wrestle', 'building' => '1', 'name' => 'Зал боротьби', 'room_number' => null, 'type' => 'hall', 'area' => null,
            'tariffs' => [['min' => 1, 'max' => 19, 'price' => '500.00'], ['min' => 20, 'max' => null, 'price' => '1300.00']]],
        ['slug' => 'b9-wrestle', 'building' => '9', 'name' => 'Зал боротьби', 'room_number' => null, 'type' => 'hall', 'area' => null,
            'tariffs' => [['min' => 1, 'max' => null, 'price' => '300.00']]],
        ['slug' => 'b1-fitness', 'building' => '1', 'name' => 'Зал фітнесу', 'room_number' => null, 'type' => 'hall', 'area' => null,
            'tariffs' => [['min' => 1, 'max' => 19, 'price' => '600.00'], ['min' => 20, 'max' => null, 'price' => '700.00']]],
        ['slug' => 'b1-hall', 'building' => '1', 'name' => 'Хол', 'room_number' => null, 'type' => 'lobby', 'area' => 177.0,
            'tariffs' => [['min' => 1, 'max' => null, 'price' => '300.00']]],
        ['slug' => 'b6-func', 'building' => '6', 'name' => 'Зал тренажерний, зона функціонального тренінгу', 'room_number' => null, 'type' => 'hall', 'area' => null,
            'tariffs' => [['min' => 1, 'max' => null, 'price' => '400.00']]],
        ['slug' => 'b6-mental', 'building' => '6', 'name' => 'Зал ментального фітнесу', 'room_number' => null, 'type' => 'hall', 'area' => 88.0,
            'tariffs' => [['min' => 1, 'max' => null, 'price' => '500.00']]],
        ['slug' => 'b6-fitness', 'building' => '6', 'name' => 'Зал фітнесу', 'room_number' => null, 'type' => 'hall', 'area' => 177.1,
            'tariffs' => [['min' => 1, 'max' => null, 'price' => '500.00']]],
        ['slug' => 'b4-billiard', 'building' => '4', 'name' => 'Зал більярду', 'room_number' => null, 'type' => 'hall', 'area' => null,
            'tariffs' => [['min' => 1, 'max' => null, 'price' => '900.00']]],
        ['slug' => 'b8-fitness', 'building' => '8', 'name' => 'Зал фітнесу', 'room_number' => null, 'type' => 'hall', 'area' => null,
            'tariffs' => [['min' => 1, 'max' => null, 'price' => '400.00']]],
        ['slug' => 'b5-choreo-401', 'building' => '5', 'name' => 'Зал хореографії', 'room_number' => '401', 'type' => 'hall', 'area' => null,
            'tariffs' => [['min' => 1, 'max' => null, 'price' => '500.00']]],
        ['slug' => 'b8-sports', 'building' => '8', 'name' => 'Зал спортивний', 'room_number' => null, 'type' => 'hall', 'area' => 198.0,
            'tariffs' => [['min' => 1, 'max' => null, 'price' => '700.00']]],
        ['slug' => 'b1-assembly', 'building' => '1', 'name' => 'Актовий зал', 'room_number' => null, 'type' => 'hall', 'area' => null,
            'tariffs' => [['min' => 1, 'max' => null, 'price' => '2400.00']]],
        ['slug' => 'b8-pool-25', 'building' => '8', 'name' => 'Басейн 25 м', 'room_number' => null, 'type' => 'pool', 'area' => null,
            'tariffs' => [['min' => 1, 'max' => 59, 'price' => '4800.00'], ['min' => 60, 'max' => 99, 'price' => '9600.00'], ['min' => 100, 'max' => null, 'price' => '12000.00']]],
        ['slug' => 'b8-pool-12', 'building' => '8', 'name' => 'Басейн 12 м', 'room_number' => null, 'type' => 'pool', 'area' => null,
            'tariffs' => [['min' => 1, 'max' => 9, 'price' => '1500.00'], ['min' => 10, 'max' => null, 'price' => '2000.00']]],
        ['slug' => 'b6-pool-20', 'building' => '6', 'name' => 'Басейн 20 м', 'room_number' => null, 'type' => 'pool', 'area' => null,
            'tariffs' => [['min' => 1, 'max' => 19, 'price' => '2000.00'], ['min' => 20, 'max' => null, 'price' => '2500.00']]],
        ['slug' => 'b1-a104', 'building' => '1', 'name' => 'Аудиторія 104', 'room_number' => '104', 'type' => 'auditorium', 'area' => null,
            'tariffs' => [['min' => 1, 'max' => null, 'price' => '2100.00']]],
        ['slug' => 'b1-a303', 'building' => '1', 'name' => 'Аудиторія 303', 'room_number' => '303', 'type' => 'auditorium', 'area' => null,
            'tariffs' => [['min' => 1, 'max' => null, 'price' => '1700.00']]],
        ['slug' => 'b1-a209', 'building' => '1', 'name' => 'Аудиторія 209', 'room_number' => '209', 'type' => 'auditorium', 'area' => null,
            'tariffs' => [['min' => 1, 'max' => null, 'price' => '1400.00']]],
        ['slug' => 'b2-a90', 'building' => '2', 'name' => 'Аудиторія 90', 'room_number' => '90', 'type' => 'auditorium', 'area' => null,
            'tariffs' => [['min' => 1, 'max' => null, 'price' => '600.00']]],
        ['slug' => 'b4-a411', 'building' => '4', 'name' => 'Аудиторія 411', 'room_number' => '411', 'type' => 'auditorium', 'area' => null,
            'tariffs' => [['min' => 1, 'max' => null, 'price' => '440.00']]],
        ['slug' => 'b8-a1', 'building' => '8', 'name' => 'Аудиторія 1', 'room_number' => '1', 'type' => 'auditorium', 'area' => null,
            'tariffs' => [['min' => 1, 'max' => null, 'price' => '300.00']]],
        ['slug' => 'b8-a101', 'building' => '8', 'name' => 'Аудиторія 101', 'room_number' => '101', 'type' => 'auditorium', 'area' => null,
            'tariffs' => [['min' => 1, 'max' => null, 'price' => '400.00']]],
        ['slug' => 'b3-a101', 'building' => '3', 'name' => 'Аудиторія 101', 'room_number' => '101', 'type' => 'auditorium', 'area' => null,
            'tariffs' => [['min' => 1, 'max' => null, 'price' => '400.00']]],
        ['slug' => 'b5-a203', 'building' => '5', 'name' => 'Аудиторія 203', 'room_number' => '203', 'type' => 'auditorium', 'area' => null,
            'tariffs' => [['min' => 1, 'max' => null, 'price' => '400.00']]],
        ['slug' => 'b4-a213', 'building' => '4', 'name' => 'Аудиторія 213', 'room_number' => '213', 'type' => 'auditorium', 'area' => null,
            'tariffs' => [['min' => 1, 'max' => null, 'price' => '400.00']]],
        ['slug' => 'b5-a303', 'building' => '5', 'name' => 'Аудиторія 303', 'room_number' => '303', 'type' => 'auditorium', 'area' => null,
            'tariffs' => [['min' => 1, 'max' => null, 'price' => '500.00']]],
        ['slug' => 'b5-a310', 'building' => '5', 'name' => 'Аудиторія 310', 'room_number' => '310', 'type' => 'auditorium', 'area' => null,
            'tariffs' => [['min' => 1, 'max' => null, 'price' => '400.00']]],
        ['slug' => 'b5-a403', 'building' => '5', 'name' => 'Аудиторія 403', 'room_number' => '403', 'type' => 'auditorium', 'area' => null,
            'tariffs' => [['min' => 1, 'max' => null, 'price' => '400.00']]],
        ['slug' => 'b1-a206', 'building' => '1', 'name' => 'Аудиторія 206', 'room_number' => '206', 'type' => 'auditorium', 'area' => null,
            'tariffs' => [['min' => 1, 'max' => null, 'price' => '800.00']]],
    ];
}

function rp_rental_seed_rooms(PDO $pdo): void
{
    $count = (int) $pdo->query('SELECT COUNT(*) FROM ' . RP_TABLE_RENTAL_ROOMS)->fetchColumn();
    if ($count > 0) {
        return;
    }

    $insertRoom = $pdo->prepare(
        'INSERT INTO ' . RP_TABLE_RENTAL_ROOMS . '
            (slug, building, name, room_number, type, area_m2, description, is_active, created_at, updated_at)
         VALUES (:slug, :building, :name, :room_number, :type, :area_m2, NULL, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
    );
    $insertTariff = $pdo->prepare(
        'INSERT INTO ' . RP_TABLE_RENTAL_TARIFFS . '
            (room_id, min_participants, max_participants, price)
         VALUES (:room_id, :min_participants, :max_participants, :price)'
    );

    foreach (rp_rental_catalog() as $room) {
        $insertRoom->execute([
            'slug'        => $room['slug'],
            'building'    => $room['building'],
            'name'        => $room['name'],
            'room_number' => $room['room_number'],
            'type'        => $room['type'],
            'area_m2'     => $room['area'],
        ]);
        $roomId = (int) $pdo->lastInsertId();
        foreach ($room['tariffs'] as $tier) {
            $insertTariff->execute([
                'room_id'           => $roomId,
                'min_participants'  => $tier['min'],
                'max_participants'  => $tier['max'],
                'price'             => $tier['price'],
            ]);
        }
    }
}

function rp_rental_money(string|float|int $value): string
{
    return number_format((float) $value, 2, '.', '');
}

function rp_rental_format_money(string|float|int $value): string
{
    return number_format((float) $value, 2, ',', ' ') . ' грн';
}

/** @return list<array<string,mixed>> */
function rp_rental_rooms(PDO $pdo, bool $activeOnly = false): array
{
    $sql = 'SELECT * FROM ' . RP_TABLE_RENTAL_ROOMS;
    if ($activeOnly) {
        $sql .= ' WHERE is_active = 1';
    }
    $sql .= ' ORDER BY building+0, name, room_number';

    return $pdo->query($sql)->fetchAll();
}

/** @return array<string,mixed>|null */
function rp_rental_room(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM ' . RP_TABLE_RENTAL_ROOMS . ' WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function rp_rental_room_label(array $room): string
{
    $label = 'Корпус ' . $room['building'] . ' · ' . $room['name'];
    if (!empty($room['room_number'])) {
        $label .= ' (' . $room['room_number'] . ')';
    }

    return $label;
}

/** @return list<array<string,mixed>> */
function rp_rental_tariffs(PDO $pdo, int $roomId): array
{
    $stmt = $pdo->prepare(
        'SELECT * FROM ' . RP_TABLE_RENTAL_TARIFFS . ' WHERE room_id = :id ORDER BY min_participants'
    );
    $stmt->execute(['id' => $roomId]);

    return $stmt->fetchAll();
}

function rp_rental_needs_participants(PDO $pdo, int $roomId): bool
{
    return count(rp_rental_tariffs($pdo, $roomId)) > 1;
}

function rp_rental_hourly_price(PDO $pdo, int $roomId, ?int $participants, ?string $localYmd = null): string
{
    if ($localYmd !== null) {
        $holiday = $pdo->prepare(
            'SELECT price FROM ' . RP_TABLE_RENTAL_HOLIDAY_RATES . '
             WHERE room_id = :room AND holiday_date = :d'
        );
        $holiday->execute(['room' => $roomId, 'd' => $localYmd]);
        $custom = $holiday->fetchColumn();
        if ($custom !== false && $custom !== null) {
            return rp_rental_money((string) $custom);
        }
    }

    $tiers = rp_rental_tariffs($pdo, $roomId);
    if (!$tiers) {
        throw new RuntimeException('No tariff for room ' . $roomId);
    }
    if (count($tiers) === 1) {
        return rp_rental_money((string) $tiers[0]['price']);
    }
    if ($participants === null || $participants < 1) {
        throw new InvalidArgumentException('participants_required');
    }
    foreach ($tiers as $tier) {
        $min = (int) $tier['min_participants'];
        $max = $tier['max_participants'] !== null ? (int) $tier['max_participants'] : null;
        if ($participants >= $min && ($max === null || $participants <= $max)) {
            return rp_rental_money((string) $tier['price']);
        }
    }
    throw new InvalidArgumentException('participants_out_of_range');
}

function rp_rental_is_public_holiday(string $ymd): bool
{
    $year = (int) substr($ymd, 0, 4);
    foreach (rp_ukrainian_holidays($year) as $item) {
        if ($item['kind'] === 'public' && $item['date'] === $ymd) {
            return true;
        }
    }

    return false;
}

function rp_rental_public_holiday_title(string $ymd): ?string
{
    $year = (int) substr($ymd, 0, 4);
    foreach (rp_ukrainian_holidays($year) as $item) {
        if ($item['kind'] === 'public' && $item['date'] === $ymd) {
            return (string) $item['title'];
        }
    }

    return null;
}

function rp_rental_local_dt(string $utc): DateTimeImmutable
{
    return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))
        ->setTimezone(rp_app_timezone());
}

/** @return array{start:DateTimeImmutable,end:DateTimeImmutable}|null */
function rp_rental_parse_local_range(string $ymd, string $startHm, string $endHm): ?array
{
    $tz = rp_app_timezone();
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
        return null;
    }
    $start = DateTimeImmutable::createFromFormat('Y-m-d H:i', $ymd . ' ' . $startHm, $tz);
    $end   = DateTimeImmutable::createFromFormat('Y-m-d H:i', $ymd . ' ' . $endHm, $tz);
    if (!$start || !$end || $start->format('Y-m-d H:i') !== $ymd . ' ' . $startHm) {
        return null;
    }
    if ($end <= $start) {
        return null;
    }

    return ['start' => $start, 'end' => $end];
}

function rp_rental_is_weekend_like(DateTimeImmutable $local): bool
{
    $n = (int) $local->format('N');
    if ($n >= 6) {
        return true;
    }

    return rp_rental_is_public_holiday($local->format('Y-m-d'));
}

function rp_rental_is_morning_slot(DateTimeImmutable $start, DateTimeImmutable $end): bool
{
    return $start->format('H:i') === '07:45'
        && $end->format('H:i') === '08:45'
        && $start->format('Y-m-d') === $end->format('Y-m-d');
}

function rp_rental_hours(DateTimeImmutable $start, DateTimeImmutable $end): string
{
    if (rp_rental_is_morning_slot($start, $end)) {
        return '1.00';
    }
    $minutes = (int) (($end->getTimestamp() - $start->getTimestamp()) / 60);

    return rp_rental_money($minutes / 60);
}

function rp_rental_month_confirmed(PDO $pdo, string $yearMonth): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM ' . RP_TABLE_RENTAL_MONTHS . ' WHERE schedule_month = :m');
    $stmt->execute(['m' => $yearMonth]);

    return (bool) $stmt->fetchColumn();
}

function rp_rental_confirm_month(PDO $pdo, string $yearMonth, string $actor): void
{
    if (!preg_match('/^\d{4}-\d{2}$/', $yearMonth)) {
        throw new InvalidArgumentException('bad_month');
    }
    $stmt = $pdo->prepare(
        'INSERT INTO ' . RP_TABLE_RENTAL_MONTHS . ' (schedule_month, confirmed_by, confirmed_at)
         VALUES (:m, :by, UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE confirmed_by = VALUES(confirmed_by), confirmed_at = VALUES(confirmed_at)'
    );
    $stmt->execute(['m' => $yearMonth, 'by' => $actor]);
    rp_rental_audit($pdo, 'schedule_confirmed', 'month', null, null, $yearMonth, null, $actor);
}

/** @return array<string,mixed>|null */
function rp_rental_matching_approval(PDO $pdo, int $roomId, DateTimeImmutable $startUtc, DateTimeImmutable $endUtc): ?array
{
    $stmt = $pdo->prepare(
        'SELECT * FROM ' . RP_TABLE_RENTAL_APPROVALS . '
         WHERE room_id = :room AND starts_at = :start AND ends_at = :end AND status = \'approved\''
    );
    $stmt->execute([
        'room'  => $roomId,
        'start' => $startUtc->format('Y-m-d H:i:s'),
        'end'   => $endUtc->format('Y-m-d H:i:s'),
    ]);
    $row = $stmt->fetch();

    return $row ?: null;
}

/**
 * @return list<string> error keys / messages
 */
function rp_rental_validate_slot(PDO $pdo, DateTimeImmutable $localStart, DateTimeImmutable $localEnd, int $roomId): array
{
    $errors = [];
    if ($localStart->format('Y-m-d') !== $localEnd->format('Y-m-d')) {
        $errors[] = __('rental.error.same_day');

        return $errors;
    }

    $morning = rp_rental_is_morning_slot($localStart, $localEnd);
    $weekend = rp_rental_is_weekend_like($localStart);
    $weekday = !$weekend;

    if ($morning) {
        if ($weekend) {
            $errors[] = __('rental.error.morning_weekday');
        }
    } else {
        if ($localStart->format('i') !== '00' || $localEnd->format('i') !== '00') {
            $errors[] = __('rental.error.whole_hour');
        }
        $minutes = (int) (($localEnd->getTimestamp() - $localStart->getTimestamp()) / 60);
        if ($minutes < 60 || $minutes % 60 !== 0) {
            $errors[] = __('rental.error.whole_hours');
        }
    }

    $startHm = $localStart->format('H:i');
    $endHm   = $localEnd->format('H:i');

    if ($weekend) {
        if ($startHm < '08:00' || $endHm > '21:00') {
            $errors[] = __('rental.error.weekend_hours');
        }
    } else {
        if ($startHm < '07:45' || $endHm > '21:00') {
            $errors[] = __('rental.error.weekday_hours');
        }
        $prepStart = $localStart->setTime(8, 45);
        $prepEnd   = $localStart->setTime(9, 0);
        if ($localStart < $prepEnd && $localEnd > $prepStart) {
            $errors[] = __('rental.error.prep');
        }
    }

    $dayStart = $localStart->setTime(9, 0);
    $dayEnd   = $localStart->setTime(17, 0);
    $needsDay = $weekday && $localStart < $dayEnd && $localEnd > $dayStart;
    if ($needsDay && !rp_rental_month_confirmed($pdo, $localStart->format('Y-m'))) {
        $startUtc = $localStart->setTimezone(new DateTimeZone('UTC'));
        $endUtc   = $localEnd->setTimezone(new DateTimeZone('UTC'));
        if (rp_rental_matching_approval($pdo, $roomId, $startUtc, $endUtc) === null) {
            $errors[] = __('rental.error.needs_approval');
        }
    }

    return $errors;
}

function rp_rental_needs_approval_message(PDO $pdo, DateTimeImmutable $localStart, DateTimeImmutable $localEnd): bool
{
    if (rp_rental_is_weekend_like($localStart)) {
        return false;
    }
    $dayStart = $localStart->setTime(9, 0);
    $dayEnd   = $localStart->setTime(17, 0);
    $needsDay = $localStart < $dayEnd && $localEnd > $dayStart;

    return $needsDay && !rp_rental_month_confirmed($pdo, $localStart->format('Y-m'));
}

function rp_rental_intervals_overlap(string $aStart, string $aEnd, string $bStart, string $bEnd): bool
{
    return $aStart < $bEnd && $aEnd > $bStart;
}

/** @return list<array{kind:string,title:string,starts_at:string,ends_at:string}> */
function rp_rental_conflicts(
    PDO $pdo,
    int $roomId,
    string $startUtc,
    string $endUtc,
    ?int $ignoreSessionId = null,
    ?int $ignoreClassId = null,
    ?int $ignoreBlockId = null
): array {
    $out = [];

    $sql = 'SELECT id, starts_at, ends_at, status, deal_id FROM ' . RP_TABLE_RENTAL_SESSIONS . '
            WHERE room_id = :room AND status IN (\'hold\', \'booked\', \'no_show\')
              AND starts_at < :end AND ends_at > :start';
    $params = ['room' => $roomId, 'start' => $startUtc, 'end' => $endUtc];
    if ($ignoreSessionId !== null) {
        $sql .= ' AND id <> :sid';
        $params['sid'] = $ignoreSessionId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $row) {
        $out[] = [
            'kind'      => 'session',
            'title'     => __('rental.event.booking') . ' #' . $row['deal_id'],
            'starts_at' => (string) $row['starts_at'],
            'ends_at'   => (string) $row['ends_at'],
        ];
    }

    $sql = 'SELECT id, title, starts_at, ends_at FROM ' . RP_TABLE_RENTAL_CLASSES . '
            WHERE room_id = :room AND starts_at < :end AND ends_at > :start';
    $params = ['room' => $roomId, 'start' => $startUtc, 'end' => $endUtc];
    if ($ignoreClassId !== null) {
        $sql .= ' AND id <> :cid';
        $params['cid'] = $ignoreClassId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $row) {
        $out[] = [
            'kind'      => 'class',
            'title'     => (string) $row['title'],
            'starts_at' => (string) $row['starts_at'],
            'ends_at'   => (string) $row['ends_at'],
        ];
    }

    $sql = 'SELECT id, title, starts_at, ends_at FROM ' . RP_TABLE_RENTAL_BLOCKS . '
            WHERE room_id = :room AND starts_at < :end AND ends_at > :start';
    $params = ['room' => $roomId, 'start' => $startUtc, 'end' => $endUtc];
    if ($ignoreBlockId !== null) {
        $sql .= ' AND id <> :bid';
        $params['bid'] = $ignoreBlockId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $row) {
        $out[] = [
            'kind'      => 'block',
            'title'     => (string) $row['title'],
            'starts_at' => (string) $row['starts_at'],
            'ends_at'   => (string) $row['ends_at'],
        ];
    }

    return $out;
}

function rp_rental_lock_room(PDO $pdo, int $roomId): void
{
    $stmt = $pdo->prepare('SELECT id FROM ' . RP_TABLE_RENTAL_ROOMS . ' WHERE id = :id FOR UPDATE');
    $stmt->execute(['id' => $roomId]);
    if (!$stmt->fetch()) {
        throw new RuntimeException('room_missing');
    }
}

/** @return list<array<string,mixed>> */
function rp_rental_clients(PDO $pdo, string $q = ''): array
{
    if ($q === '') {
        return $pdo->query(
            'SELECT * FROM ' . RP_TABLE_RENTAL_CLIENTS . ' ORDER BY name LIMIT 200'
        )->fetchAll();
    }
    $stmt = $pdo->prepare(
        'SELECT * FROM ' . RP_TABLE_RENTAL_CLIENTS . '
         WHERE name LIKE :q OR phone LIKE :q OR email LIKE :q
         ORDER BY name LIMIT 200'
    );
    $like = '%' . $q . '%';
    $stmt->execute(['q' => $like]);

    return $stmt->fetchAll();
}

/** @return array<string,mixed>|null */
function rp_rental_client(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM ' . RP_TABLE_RENTAL_CLIENTS . ' WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function rp_rental_save_client(PDO $pdo, array $data, string $actor, ?int $id = null): int
{
    $type  = in_array($data['type'] ?? '', ['person', 'fop', 'org'], true) ? (string) $data['type'] : 'person';
    $name  = rp_clean_string($data['name'] ?? '', 255);
    $phone = rp_clean_string($data['phone'] ?? '', 80);
    if ($name === '' || $phone === '') {
        throw new InvalidArgumentException('client_required');
    }
    $fields = [
        'type'           => $type,
        'name'           => $name,
        'phone'          => $phone,
        'email'          => rp_clean_string($data['email'] ?? '', 255) ?: null,
        'contact_person' => rp_clean_string($data['contact_person'] ?? '', 160) ?: null,
        'tax_id'         => rp_clean_string($data['tax_id'] ?? '', 32) ?: null,
        'comment'        => rp_clean_string($data['comment'] ?? '', 2000) ?: null,
    ];
    if ($id !== null && $id > 0) {
        $stmt = $pdo->prepare(
            'UPDATE ' . RP_TABLE_RENTAL_CLIENTS . '
             SET type=:type, name=:name, phone=:phone, email=:email, contact_person=:contact_person,
                 tax_id=:tax_id, comment=:comment, updated_at=UTC_TIMESTAMP()
             WHERE id=:id'
        );
        $stmt->execute($fields + ['id' => $id]);
        rp_rental_audit($pdo, 'client_updated', 'client', $id, null, $name, null, $actor);

        return $id;
    }
    $stmt = $pdo->prepare(
        'INSERT INTO ' . RP_TABLE_RENTAL_CLIENTS . '
            (type, name, phone, email, contact_person, tax_id, comment, created_by, created_at, updated_at)
         VALUES (:type, :name, :phone, :email, :contact_person, :tax_id, :comment, :created_by, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
    );
    $stmt->execute($fields + ['created_by' => $actor]);
    $newId = (int) $pdo->lastInsertId();
    rp_rental_audit($pdo, 'client_created', 'client', $newId, null, $name, null, $actor);

    return $newId;
}

/**
 * @param list<array{room_id:int,ymd:string,start:string,end:string,participants:?int}> $rows
 * @return array{ok:bool,errors:list<string>,conflicts:list<array<string,mixed>>,preview:list<array<string,mixed>>}
 */
function rp_rental_preview_sessions(PDO $pdo, array $rows): array
{
    $errors    = [];
    $conflicts = [];
    $preview   = [];
    foreach ($rows as $index => $row) {
        $n     = $index + 1;
        $range = rp_rental_parse_local_range($row['ymd'], $row['start'], $row['end']);
        if ($range === null) {
            $errors[] = __('rental.error.session_time', $n);
            continue;
        }
        $room = rp_rental_room($pdo, $row['room_id']);
        if ($room === null || !(int) $room['is_active']) {
            $errors[] = __('rental.error.session_room', $n);
            continue;
        }
        $slotErrors = rp_rental_validate_slot($pdo, $range['start'], $range['end'], $row['room_id']);
        foreach ($slotErrors as $err) {
            $errors[] = '#' . $n . ': ' . $err;
        }
        try {
            $price = rp_rental_hourly_price($pdo, $row['room_id'], $row['participants'], $row['ymd']);
        } catch (InvalidArgumentException $e) {
            $errors[] = '#' . $n . ': ' . __('rental.error.participants');
            $price    = '0.00';
        }
        $hours  = rp_rental_hours($range['start'], $range['end']);
        $amount = rp_rental_money((float) $price * (float) $hours);
        $startUtc = $range['start']->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $endUtc   = $range['end']->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $hits     = rp_rental_conflicts($pdo, $row['room_id'], $startUtc, $endUtc);
        foreach ($hits as $hit) {
            $conflicts[] = $hit + ['row' => $n, 'room' => rp_rental_room_label($room)];
            $errors[]    = __('rental.error.conflict', $n, rp_rental_room_label($room));
        }
        $approval = null;
        if (rp_rental_needs_approval_message($pdo, $range['start'], $range['end'])) {
            $approval = rp_rental_matching_approval(
                $pdo,
                $row['room_id'],
                $range['start']->setTimezone(new DateTimeZone('UTC')),
                $range['end']->setTimezone(new DateTimeZone('UTC'))
            );
        }
        $preview[] = [
            'room_id'      => $row['room_id'],
            'room_label'   => rp_rental_room_label($room),
            'ymd'          => $row['ymd'],
            'start'        => $row['start'],
            'end'          => $row['end'],
            'starts_at'    => $startUtc,
            'ends_at'      => $endUtc,
            'hours'        => $hours,
            'participants' => $row['participants'],
            'hourly_price' => $price,
            'amount'       => $amount,
            'approval_id'  => $approval['id'] ?? null,
        ];
    }

    return [
        'ok'        => $errors === [] && $preview !== [],
        'errors'    => $errors,
        'conflicts' => $conflicts,
        'preview'   => $preview,
    ];
}

function rp_rental_pay_deadline(DateTimeImmutable $createdLocal, DateTimeImmutable $firstStartLocal): DateTimeImmutable
{
    $plus72 = $createdLocal->modify('+72 hours');

    return $plus72 <= $firstStartLocal ? $plus72 : $firstStartLocal;
}

/**
 * @param list<array{room_id:int,ymd:string,start:string,end:string,participants:?int}> $rows
 * @return array{ok:bool,id?:int,code?:string,errors:list<string>,conflicts:list<array<string,mixed>>}
 */
function rp_rental_create_deal(PDO $pdo, int $clientId, array $rows, string $actor): array
{
    $preview = rp_rental_preview_sessions($pdo, $rows);
    if (!$preview['ok']) {
        return ['ok' => false, 'errors' => $preview['errors'], 'conflicts' => $preview['conflicts']];
    }
    if (rp_rental_client($pdo, $clientId) === null) {
        return ['ok' => false, 'errors' => [__('rental.error.client')], 'conflicts' => []];
    }

    $pdo->beginTransaction();
    try {
        $roomIds = array_unique(array_map(static fn (array $s): int => (int) $s['room_id'], $preview['preview']));
        sort($roomIds);
        foreach ($roomIds as $roomId) {
            rp_rental_lock_room($pdo, $roomId);
        }

        $recheck = rp_rental_preview_sessions($pdo, $rows);
        if (!$recheck['ok']) {
            $pdo->rollBack();

            return ['ok' => false, 'errors' => $recheck['errors'], 'conflicts' => $recheck['conflicts']];
        }

        $total = '0.00';
        $firstStart = null;
        foreach ($recheck['preview'] as $session) {
            $total = rp_rental_money((float) $total + (float) $session['amount']);
            $start = new DateTimeImmutable($session['starts_at'], new DateTimeZone('UTC'));
            if ($firstStart === null || $start < $firstStart) {
                $firstStart = $start;
            }
        }

        $createdUtc   = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $createdLocal = $createdUtc->setTimezone(rp_app_timezone());
        $firstLocal   = $firstStart->setTimezone(rp_app_timezone());
        $deadline     = rp_rental_pay_deadline($createdLocal, $firstLocal)->setTimezone(new DateTimeZone('UTC'));

        $code = '';
        for ($attempt = 1; ; $attempt++) {
            $code = rp_generate_code('RNT');
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO ' . RP_TABLE_RENTAL_DEALS . '
                        (public_code, client_id, created_by, created_at, updated_at, total_amount, pay_deadline, payment_status)
                     VALUES (:code, :client_id, :created_by, UTC_TIMESTAMP(), UTC_TIMESTAMP(), :total, :deadline, \'unpaid\')'
                );
                $stmt->execute([
                    'code'       => $code,
                    'client_id'  => $clientId,
                    'created_by' => $actor,
                    'total'      => $total,
                    'deadline'   => $deadline->format('Y-m-d H:i:s'),
                ]);
                break;
            } catch (PDOException $e) {
                if ($attempt >= 5 || $e->getCode() !== '23000') {
                    throw $e;
                }
            }
        }

        $dealId = (int) $pdo->lastInsertId();
        $insert = $pdo->prepare(
            'INSERT INTO ' . RP_TABLE_RENTAL_SESSIONS . '
                (deal_id, room_id, starts_at, ends_at, hours, participants, hourly_price, amount, status, approval_id)
             VALUES (:deal_id, :room_id, :starts_at, :ends_at, :hours, :participants, :hourly_price, :amount, \'hold\', :approval_id)'
        );
        foreach ($recheck['preview'] as $session) {
            $insert->execute([
                'deal_id'      => $dealId,
                'room_id'      => $session['room_id'],
                'starts_at'    => $session['starts_at'],
                'ends_at'      => $session['ends_at'],
                'hours'        => $session['hours'],
                'participants' => $session['participants'],
                'hourly_price' => $session['hourly_price'],
                'amount'       => $session['amount'],
                'approval_id'  => $session['approval_id'],
            ]);
        }

        rp_rental_audit($pdo, 'deal_created', 'deal', $dealId, null, $code, $total, $actor);
        $pdo->commit();

        return ['ok' => true, 'id' => $dealId, 'code' => $code, 'errors' => [], 'conflicts' => []];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[rental] create deal failed: ' . $e->getMessage());

        return ['ok' => false, 'errors' => [__('error.save_failed')], 'conflicts' => []];
    }
}

/** @return array<string,mixed>|null */
function rp_rental_deal(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT d.*, c.name AS client_name, c.phone AS client_phone, c.type AS client_type
         FROM ' . RP_TABLE_RENTAL_DEALS . ' d
         JOIN ' . RP_TABLE_RENTAL_CLIENTS . ' c ON c.id = d.client_id
         WHERE d.id = :id'
    );
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    return $row ?: null;
}

/** @return list<array<string,mixed>> */
function rp_rental_deal_sessions(PDO $pdo, int $dealId): array
{
    $stmt = $pdo->prepare(
        'SELECT s.*, r.building, r.name AS room_name, r.room_number
         FROM ' . RP_TABLE_RENTAL_SESSIONS . ' s
         JOIN ' . RP_TABLE_RENTAL_ROOMS . ' r ON r.id = s.room_id
         WHERE s.deal_id = :id ORDER BY s.starts_at, s.id'
    );
    $stmt->execute(['id' => $dealId]);

    return $stmt->fetchAll();
}

/** @return list<array<string,mixed>> */
function rp_rental_deal_receipts(PDO $pdo, int $dealId): array
{
    $stmt = $pdo->prepare(
        'SELECT * FROM ' . RP_TABLE_RENTAL_RECEIPTS . ' WHERE deal_id = :id ORDER BY id'
    );
    $stmt->execute(['id' => $dealId]);

    return $stmt->fetchAll();
}

function rp_rental_expire_overdue(PDO $pdo): int
{
    $stmt = $pdo->prepare(
        'SELECT id FROM ' . RP_TABLE_RENTAL_DEALS . '
         WHERE payment_status = \'unpaid\' AND pay_deadline < UTC_TIMESTAMP()'
    );
    $stmt->execute();
    $ids = array_map(static fn (array $r): int => (int) $r['id'], $stmt->fetchAll());
    foreach ($ids as $id) {
        rp_rental_expire_deal($pdo, $id);
    }

    return count($ids);
}

function rp_rental_expire_deal(PDO $pdo, int $dealId): void
{
    $pdo->beginTransaction();
    try {
        $deal = rp_rental_deal($pdo, $dealId);
        if ($deal === null || $deal['payment_status'] !== 'unpaid') {
            $pdo->rollBack();

            return;
        }
        $pdo->prepare(
            'UPDATE ' . RP_TABLE_RENTAL_DEALS . '
             SET payment_status = \'expired\', updated_at = UTC_TIMESTAMP() WHERE id = :id'
        )->execute(['id' => $dealId]);
        $pdo->prepare(
            'UPDATE ' . RP_TABLE_RENTAL_SESSIONS . '
             SET status = \'cancelled\', cancelled_at = UTC_TIMESTAMP(), cancelled_by = \'system\',
                 cancel_reason = \'expired\'
             WHERE deal_id = :id AND status = \'hold\''
        )->execute(['id' => $dealId]);
        rp_rental_audit($pdo, 'deal_expired', 'deal', $dealId, 'unpaid', 'expired', __('rental.reason.expired'), 'system');
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function rp_rental_confirm_payment(PDO $pdo, int $dealId, string $actor): void
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM ' . RP_TABLE_RENTAL_DEALS . ' WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $dealId]);
        $deal = $stmt->fetch();
        if (!$deal) {
            throw new RuntimeException('deal_missing');
        }
        if ($deal['payment_status'] !== 'unpaid') {
            throw new RuntimeException('not_unpaid');
        }
        if (rp_rental_deal_receipts($pdo, $dealId) === []) {
            throw new RuntimeException('no_receipt');
        }
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $deadline = new DateTimeImmutable((string) $deal['pay_deadline'], new DateTimeZone('UTC'));
        if ($now > $deadline) {
            $pdo->rollBack();
            rp_rental_expire_deal($pdo, $dealId);
            throw new RuntimeException('expired');
        }
        $pdo->prepare(
            'UPDATE ' . RP_TABLE_RENTAL_DEALS . '
             SET payment_status = \'paid\', paid_at = UTC_TIMESTAMP(), paid_by = :by,
                 paid_amount = total_amount, updated_at = UTC_TIMESTAMP()
             WHERE id = :id'
        )->execute(['by' => $actor, 'id' => $dealId]);
        $pdo->prepare(
            'UPDATE ' . RP_TABLE_RENTAL_SESSIONS . '
             SET status = \'booked\' WHERE deal_id = :id AND status = \'hold\''
        )->execute(['id' => $dealId]);
        rp_rental_audit($pdo, 'payment_confirmed', 'deal', $dealId, 'unpaid', 'paid', (string) $deal['total_amount'], $actor);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function rp_rental_sign_contract(PDO $pdo, int $dealId, string $actor): void
{
    $pdo->prepare(
        'UPDATE ' . RP_TABLE_RENTAL_DEALS . '
         SET contract_signed = 1, contract_signed_at = UTC_TIMESTAMP(), contract_signed_by = :by,
             updated_at = UTC_TIMESTAMP()
         WHERE id = :id'
    )->execute(['by' => $actor, 'id' => $dealId]);
    rp_rental_audit($pdo, 'contract_signed', 'deal', $dealId, '0', '1', null, $actor);
}

function rp_rental_mark_no_show(PDO $pdo, int $sessionId, string $actor): void
{
    $stmt = $pdo->prepare('SELECT * FROM ' . RP_TABLE_RENTAL_SESSIONS . ' WHERE id = :id');
    $stmt->execute(['id' => $sessionId]);
    $session = $stmt->fetch();
    if (!$session || $session['status'] !== 'booked') {
        throw new RuntimeException('bad_session');
    }
    $pdo->prepare(
        'UPDATE ' . RP_TABLE_RENTAL_SESSIONS . ' SET status = \'no_show\' WHERE id = :id'
    )->execute(['id' => $sessionId]);
    rp_rental_audit($pdo, 'no_show', 'session', $sessionId, 'booked', 'no_show', null, $actor);
}

function rp_rental_hours_until(string $utcStart): float
{
    $start = new DateTimeImmutable($utcStart, new DateTimeZone('UTC'));
    $now   = new DateTimeImmutable('now', new DateTimeZone('UTC'));

    return ($start->getTimestamp() - $now->getTimestamp()) / 3600;
}

function rp_rental_cancel_session(PDO $pdo, int $sessionId, string $actor): void
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM ' . RP_TABLE_RENTAL_SESSIONS . ' WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $sessionId]);
        $session = $stmt->fetch();
        if (!$session || !in_array($session['status'], ['hold', 'booked'], true)) {
            throw new RuntimeException('bad_session');
        }
        $dealStmt = $pdo->prepare('SELECT * FROM ' . RP_TABLE_RENTAL_DEALS . ' WHERE id = :id FOR UPDATE');
        $dealStmt->execute(['id' => $session['deal_id']]);
        $deal = $dealStmt->fetch();
        if (!$deal) {
            throw new RuntimeException('deal_missing');
        }

        $eligible = false;
        $reason   = 'cancelled';
        if ($session['status'] === 'booked') {
            $eligible = rp_rental_hours_until((string) $session['starts_at']) >= 72;
            $reason   = $eligible ? 'cancelled_refund' : 'cancelled_late';
        }

        $pdo->prepare(
            'UPDATE ' . RP_TABLE_RENTAL_SESSIONS . '
             SET status = \'cancelled\', cancelled_at = UTC_TIMESTAMP(), cancelled_by = :by,
                 cancel_reason = :reason, refund_eligible = :elig
             WHERE id = :id'
        )->execute([
            'by'     => $actor,
            'reason' => $reason,
            'elig'   => $eligible ? 1 : 0,
            'id'     => $sessionId,
        ]);

        if ($eligible) {
            $pdo->prepare(
                'INSERT INTO ' . RP_TABLE_RENTAL_REFUNDS . '
                    (deal_id, amount, status, session_ids, created_by, created_at)
                 VALUES (:deal_id, :amount, \'pending\', :sids, :by, UTC_TIMESTAMP())'
            )->execute([
                'deal_id' => $session['deal_id'],
                'amount'  => $session['amount'],
                'sids'    => (string) $sessionId,
                'by'      => $actor,
            ]);
        }

        $left = $pdo->prepare(
            'SELECT COUNT(*) FROM ' . RP_TABLE_RENTAL_SESSIONS . '
             WHERE deal_id = :id AND status IN (\'hold\', \'booked\', \'no_show\')'
        );
        $left->execute(['id' => $session['deal_id']]);
        if ((int) $left->fetchColumn() === 0 && $deal['payment_status'] === 'unpaid') {
            $pdo->prepare(
                'UPDATE ' . RP_TABLE_RENTAL_DEALS . '
                 SET payment_status = \'cancelled\', updated_at = UTC_TIMESTAMP() WHERE id = :id'
            )->execute(['id' => $session['deal_id']]);
        }

        rp_rental_audit($pdo, 'session_cancelled', 'session', $sessionId, (string) $session['status'], 'cancelled', $reason, $actor);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function rp_rental_move_session(PDO $pdo, int $sessionId, string $ymd, string $startHm, string $endHm, string $actor): void
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM ' . RP_TABLE_RENTAL_SESSIONS . ' WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $sessionId]);
        $session = $stmt->fetch();
        if (!$session || !in_array($session['status'], ['hold', 'booked'], true)) {
            throw new RuntimeException('bad_session');
        }
        if (rp_rental_hours_until((string) $session['starts_at']) < 72) {
            throw new RuntimeException('too_late');
        }
        $range = rp_rental_parse_local_range($ymd, $startHm, $endHm);
        if ($range === null) {
            throw new RuntimeException('bad_time');
        }
        $newHours = rp_rental_hours($range['start'], $range['end']);
        if ($newHours !== rp_rental_money((string) $session['hours'])) {
            throw new RuntimeException('duration');
        }
        rp_rental_lock_room($pdo, (int) $session['room_id']);
        $slotErrors = rp_rental_validate_slot($pdo, $range['start'], $range['end'], (int) $session['room_id']);
        if ($slotErrors) {
            throw new RuntimeException(implode(' ', $slotErrors));
        }
        $startUtc = $range['start']->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $endUtc   = $range['end']->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        if (rp_rental_conflicts($pdo, (int) $session['room_id'], $startUtc, $endUtc, $sessionId) !== []) {
            throw new RuntimeException('conflict');
        }
        $old = (string) $session['starts_at'] . '/' . $session['ends_at'];
        $approval = null;
        if (rp_rental_needs_approval_message($pdo, $range['start'], $range['end'])) {
            $approval = rp_rental_matching_approval(
                $pdo,
                (int) $session['room_id'],
                $range['start']->setTimezone(new DateTimeZone('UTC')),
                $range['end']->setTimezone(new DateTimeZone('UTC'))
            );
            if ($approval === null) {
                throw new RuntimeException('needs_approval');
            }
        }
        $pdo->prepare(
            'UPDATE ' . RP_TABLE_RENTAL_SESSIONS . '
             SET starts_at = :s, ends_at = :e, approval_id = :a WHERE id = :id'
        )->execute([
            's'  => $startUtc,
            'e'  => $endUtc,
            'a'  => $approval['id'] ?? null,
            'id' => $sessionId,
        ]);
        rp_rental_audit($pdo, 'session_moved', 'session', $sessionId, $old, $startUtc . '/' . $endUtc, null, $actor);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function rp_rental_confirm_refund(PDO $pdo, int $refundId, string $actor): void
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM ' . RP_TABLE_RENTAL_REFUNDS . ' WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $refundId]);
        $row = $stmt->fetch();
        if (!$row || $row['status'] !== 'pending') {
            throw new RuntimeException('bad_refund');
        }
        $pdo->prepare(
            'UPDATE ' . RP_TABLE_RENTAL_REFUNDS . '
             SET status = \'done\', done_by = :by, done_at = UTC_TIMESTAMP() WHERE id = :id'
        )->execute(['by' => $actor, 'id' => $refundId]);
        rp_rental_audit($pdo, 'refund_done', 'refund', $refundId, 'pending', 'done', (string) $row['amount'], $actor);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function rp_rental_add_class(PDO $pdo, int $roomId, string $ymd, string $startHm, string $endHm, string $title, string $actor): void
{
    $range = rp_rental_parse_local_range($ymd, $startHm, $endHm);
    if ($range === null) {
        throw new InvalidArgumentException('bad_time');
    }
    $title = rp_clean_string($title, 255);
    if ($title === '') {
        throw new InvalidArgumentException('title');
    }
    $pdo->beginTransaction();
    try {
        rp_rental_lock_room($pdo, $roomId);
        $startUtc = $range['start']->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $endUtc   = $range['end']->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        if (rp_rental_conflicts($pdo, $roomId, $startUtc, $endUtc) !== []) {
            throw new RuntimeException('conflict');
        }
        $pdo->prepare(
            'INSERT INTO ' . RP_TABLE_RENTAL_CLASSES . '
                (room_id, starts_at, ends_at, title, created_by, created_at)
             VALUES (:room_id, :s, :e, :title, :by, UTC_TIMESTAMP())'
        )->execute([
            'room_id' => $roomId,
            's'       => $startUtc,
            'e'       => $endUtc,
            'title'   => $title,
            'by'      => $actor,
        ]);
        rp_rental_audit($pdo, 'class_added', 'class', (int) $pdo->lastInsertId(), null, $title, $ymd . ' ' . $startHm, $actor);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function rp_rental_add_block(PDO $pdo, int $roomId, string $ymd, string $startHm, string $endHm, string $title, string $actor): void
{
    $range = rp_rental_parse_local_range($ymd, $startHm, $endHm);
    if ($range === null) {
        throw new InvalidArgumentException('bad_time');
    }
    $title = rp_clean_string($title, 255) ?: __('rental.event.block');
    $pdo->beginTransaction();
    try {
        rp_rental_lock_room($pdo, $roomId);
        $startUtc = $range['start']->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $endUtc   = $range['end']->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        if (rp_rental_conflicts($pdo, $roomId, $startUtc, $endUtc) !== []) {
            throw new RuntimeException('conflict');
        }
        $pdo->prepare(
            'INSERT INTO ' . RP_TABLE_RENTAL_BLOCKS . '
                (room_id, starts_at, ends_at, title, created_by, created_at)
             VALUES (:room_id, :s, :e, :title, :by, UTC_TIMESTAMP())'
        )->execute([
            'room_id' => $roomId, 's' => $startUtc, 'e' => $endUtc, 'title' => $title, 'by' => $actor,
        ]);
        rp_rental_audit($pdo, 'block_added', 'block', (int) $pdo->lastInsertId(), null, $title, null, $actor);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function rp_rental_request_approval(PDO $pdo, int $roomId, string $ymd, string $startHm, string $endHm, string $actor): int
{
    $range = rp_rental_parse_local_range($ymd, $startHm, $endHm);
    if ($range === null) {
        throw new InvalidArgumentException('bad_time');
    }
    if (!rp_rental_needs_approval_message($pdo, $range['start'], $range['end'])) {
        throw new RuntimeException('not_needed');
    }
    $startUtc = $range['start']->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $endUtc   = $range['end']->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $pdo->prepare(
        'INSERT INTO ' . RP_TABLE_RENTAL_APPROVALS . '
            (room_id, starts_at, ends_at, status, requested_by, requested_at)
         VALUES (:room_id, :s, :e, \'pending\', :by, UTC_TIMESTAMP())'
    )->execute(['room_id' => $roomId, 's' => $startUtc, 'e' => $endUtc, 'by' => $actor]);
    $id = (int) $pdo->lastInsertId();
    rp_rental_audit($pdo, 'approval_requested', 'approval', $id, null, $ymd . ' ' . $startHm . '-' . $endHm, null, $actor);

    return $id;
}

function rp_rental_decide_approval(PDO $pdo, int $id, bool $approve, string $actor, string $note = ''): void
{
    $stmt = $pdo->prepare('SELECT * FROM ' . RP_TABLE_RENTAL_APPROVALS . ' WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    if (!$row || $row['status'] !== 'pending') {
        throw new RuntimeException('bad_approval');
    }
    $status = $approve ? 'approved' : 'rejected';
    $pdo->prepare(
        'UPDATE ' . RP_TABLE_RENTAL_APPROVALS . '
         SET status = :st, decided_by = :by, decided_at = UTC_TIMESTAMP(), note = :note
         WHERE id = :id'
    )->execute(['st' => $status, 'by' => $actor, 'note' => rp_clean_string($note, 500) ?: null, 'id' => $id]);
    rp_rental_audit($pdo, 'approval_' . $status, 'approval', $id, 'pending', $status, $note, $actor);
}

function rp_rental_store_receipts(PDO $pdo, int $dealId, array $files, string $actor): array
{
    if (!$files) {
        return [];
    }
    $baseDir   = rp_uploads_dir() . '/rental';
    $subDir    = date('Y/m');
    $targetDir = $baseDir . '/' . $subDir;
    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Cannot create upload directory');
    }
    $finfo  = class_exists('finfo') ? new finfo(FILEINFO_MIME_TYPE) : null;
    $stored = [];
    $insert = $pdo->prepare(
        'INSERT INTO ' . RP_TABLE_RENTAL_RECEIPTS . '
            (deal_id, original_name, stored_path, mime_type, size_bytes, created_by, created_at)
         VALUES (:deal_id, :original_name, :stored_path, :mime_type, :size_bytes, :created_by, UTC_TIMESTAMP())'
    );
    $deal = rp_rental_deal($pdo, $dealId);
    $code = $deal['public_code'] ?? ('RNT-' . $dealId);
    foreach ($files as $file) {
        $ext        = rp_file_extension($file['name']);
        $storedName = $code . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
        $relPath    = 'rental/' . $subDir . '/' . $storedName;
        $absPath    = rp_uploads_dir() . '/' . $relPath;
        if (!move_uploaded_file($file['tmp_name'], $absPath)) {
            throw new RuntimeException('Cannot move uploaded file');
        }
        @chmod($absPath, 0644);
        $mime = $finfo instanceof finfo ? (string) $finfo->file($absPath) : 'application/octet-stream';
        $insert->execute([
            'deal_id'       => $dealId,
            'original_name' => mb_substr($file['name'], 0, 255, 'UTF-8'),
            'stored_path'   => $relPath,
            'mime_type'     => $mime !== '' ? $mime : 'application/octet-stream',
            'size_bytes'    => filesize($absPath) ?: $file['size'],
            'created_by'    => $actor,
        ]);
        $stored[] = $relPath;
        rp_rental_audit($pdo, 'receipt_added', 'deal', $dealId, null, $file['name'], null, $actor);
    }

    return $stored;
}

/**
 * @return list<array<string,mixed>>
 */
function rp_rental_events(PDO $pdo, string $fromUtc, string $toUtc, ?int $roomId = null, ?string $building = null): array
{
    $roomFilter = '';
    $params     = ['from' => $fromUtc, 'to' => $toUtc];
    if ($roomId) {
        $roomFilter = ' AND room_id = :room';
        $params['room'] = $roomId;
    }

    $events = [];
    $sql = 'SELECT s.*, r.building, r.name AS room_name, r.room_number, d.public_code, d.pay_deadline,
                   d.payment_status, c.name AS client_name
            FROM ' . RP_TABLE_RENTAL_SESSIONS . ' s
            JOIN ' . RP_TABLE_RENTAL_ROOMS . ' r ON r.id = s.room_id
            JOIN ' . RP_TABLE_RENTAL_DEALS . ' d ON d.id = s.deal_id
            JOIN ' . RP_TABLE_RENTAL_CLIENTS . ' c ON c.id = d.client_id
            WHERE s.status IN (\'hold\', \'booked\', \'no_show\')
              AND s.starts_at < :to AND s.ends_at > :from' . str_replace('room_id', 's.room_id', $roomFilter);
    if ($building !== null && $building !== '') {
        $sql .= ' AND r.building = :b';
        $params['b'] = $building;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $row) {
        $kind = $row['status'] === 'booked' || $row['status'] === 'no_show' ? 'paid' : 'unpaid';
        $events[] = [
            'kind'      => $kind,
            'label'     => $kind === 'paid' ? __('rental.event.paid') : __('rental.event.unpaid'),
            'title'     => (string) $row['public_code'],
            'room_id'   => (int) $row['room_id'],
            'room'      => rp_rental_room_label($row + ['name' => $row['room_name']]),
            'starts_at' => (string) $row['starts_at'],
            'ends_at'   => (string) $row['ends_at'],
            'deal_id'   => (int) $row['deal_id'],
            'client'    => (string) $row['client_name'],
            'deadline'  => (string) $row['pay_deadline'],
            'amount'    => (string) $row['amount'],
            'status'    => (string) $row['status'],
        ];
    }

    $classSql = 'SELECT cl.*, r.building, r.name AS room_name, r.room_number
                 FROM ' . RP_TABLE_RENTAL_CLASSES . ' cl
                 JOIN ' . RP_TABLE_RENTAL_ROOMS . ' r ON r.id = cl.room_id
                 WHERE cl.starts_at < :to AND cl.ends_at > :from' . str_replace('room_id', 'cl.room_id', $roomFilter);
    $cparams = ['from' => $fromUtc, 'to' => $toUtc];
    if ($roomId) {
        $cparams['room'] = $roomId;
    }
    if ($building !== null && $building !== '') {
        $classSql .= ' AND r.building = :b';
        $cparams['b'] = $building;
    }
    $stmt = $pdo->prepare($classSql);
    $stmt->execute($cparams);
    foreach ($stmt->fetchAll() as $row) {
        $events[] = [
            'kind'      => 'class',
            'label'     => __('rental.event.class'),
            'title'     => (string) $row['title'],
            'room_id'   => (int) $row['room_id'],
            'room'      => rp_rental_room_label($row + ['name' => $row['room_name']]),
            'starts_at' => (string) $row['starts_at'],
            'ends_at'   => (string) $row['ends_at'],
            'class_id'  => (int) $row['id'],
        ];
    }

    $blockSql = 'SELECT bl.*, r.building, r.name AS room_name, r.room_number
                 FROM ' . RP_TABLE_RENTAL_BLOCKS . ' bl
                 JOIN ' . RP_TABLE_RENTAL_ROOMS . ' r ON r.id = bl.room_id
                 WHERE bl.starts_at < :to AND bl.ends_at > :from' . str_replace('room_id', 'bl.room_id', $roomFilter);
    $stmt = $pdo->prepare($blockSql);
    $stmt->execute($cparams);
    foreach ($stmt->fetchAll() as $row) {
        $events[] = [
            'kind'      => 'block',
            'label'     => __('rental.event.block'),
            'title'     => (string) $row['title'],
            'room_id'   => (int) $row['room_id'],
            'room'      => rp_rental_room_label($row + ['name' => $row['room_name']]),
            'starts_at' => (string) $row['starts_at'],
            'ends_at'   => (string) $row['ends_at'],
            'block_id'  => (int) $row['id'],
        ];
    }

    $apSql = 'SELECT a.*, r.building, r.name AS room_name, r.room_number
              FROM ' . RP_TABLE_RENTAL_APPROVALS . ' a
              JOIN ' . RP_TABLE_RENTAL_ROOMS . ' r ON r.id = a.room_id
              WHERE a.status = \'pending\' AND a.starts_at < :to AND a.ends_at > :from'
              . str_replace('room_id', 'a.room_id', $roomFilter);
    $stmt = $pdo->prepare($apSql);
    $stmt->execute($cparams);
    foreach ($stmt->fetchAll() as $row) {
        $events[] = [
            'kind'      => 'approval',
            'label'     => __('rental.event.approval'),
            'title'     => __('rental.event.approval'),
            'room_id'   => (int) $row['room_id'],
            'room'      => rp_rental_room_label($row + ['name' => $row['room_name']]),
            'starts_at' => (string) $row['starts_at'],
            'ends_at'   => (string) $row['ends_at'],
            'approval_id' => (int) $row['id'],
        ];
    }

    usort($events, static fn (array $a, array $b): int => strcmp($a['starts_at'], $b['starts_at']));

    return $events;
}

function rp_rental_day_fund_hours(DateTimeImmutable $localDay): float
{
    $weekend = rp_rental_is_weekend_like($localDay);
    if ($weekend) {
        return 13.0; // 08:00–21:00
    }

    return 13.0; // 07:45–08:45 (1h) + 09:00–21:00 (12h); 08:45–09:00 excluded
}

/** @return array<string,mixed> */
function rp_rental_dashboard(PDO $pdo, string $yearMonth): array
{
    $tz    = rp_app_timezone();
    $start = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $yearMonth . '-01 00:00:00', $tz);
    if (!$start) {
        $start = new DateTimeImmutable('first day of this month', $tz);
    }
    $end     = $start->modify('first day of next month');
    $fromUtc = $start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $toUtc   = $end->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

    $paid = $pdo->prepare(
        'SELECT COALESCE(SUM(paid_amount),0) FROM ' . RP_TABLE_RENTAL_DEALS . '
         WHERE payment_status = \'paid\' AND paid_at >= :from AND paid_at < :to'
    );
    $paid->execute(['from' => $fromUtc, 'to' => $toUtc]);
    $confirmed = rp_rental_money((string) $paid->fetchColumn());

    $due = $pdo->prepare(
        'SELECT COALESCE(SUM(total_amount),0) FROM ' . RP_TABLE_RENTAL_DEALS . '
         WHERE payment_status = \'unpaid\' AND pay_deadline >= :from AND pay_deadline < :to'
    );
    $due->execute(['from' => $fromUtc, 'to' => $toUtc]);
    $expected = rp_rental_money((string) $due->fetchColumn());

    $ref = $pdo->prepare(
        'SELECT COALESCE(SUM(amount),0) FROM ' . RP_TABLE_RENTAL_REFUNDS . '
         WHERE status = \'done\' AND done_at >= :from AND done_at < :to'
    );
    $ref->execute(['from' => $fromUtc, 'to' => $toUtc]);
    $refunds = rp_rental_money((string) $ref->fetchColumn());

    $income = rp_rental_money((float) $confirmed - (float) $refunds);
    $total  = rp_rental_money((float) $confirmed + (float) $expected - (float) $refunds);

    $newClients = $pdo->prepare(
        'SELECT COUNT(*) FROM (
            SELECT client_id, MIN(paid_at) first_paid
            FROM ' . RP_TABLE_RENTAL_DEALS . '
            WHERE payment_status = \'paid\' AND paid_at IS NOT NULL
            GROUP BY client_id
         ) t WHERE first_paid >= :from AND first_paid < :to'
    );
    $newClients->execute(['from' => $fromUtc, 'to' => $toUtc]);

    $hoursPaid = $pdo->prepare(
        'SELECT COALESCE(SUM(hours),0) FROM ' . RP_TABLE_RENTAL_SESSIONS . '
         WHERE status IN (\'booked\', \'no_show\') AND starts_at >= :from AND starts_at < :to'
    );
    $hoursPaid->execute(['from' => $fromUtc, 'to' => $toUtc]);
    $paidHoursVal = (float) $hoursPaid->fetchColumn();
    $hoursHold = $pdo->prepare(
        'SELECT COALESCE(SUM(hours),0) FROM ' . RP_TABLE_RENTAL_SESSIONS . '
         WHERE status = \'hold\' AND starts_at >= :from AND starts_at < :to'
    );
    $hoursHold->execute(['from' => $fromUtc, 'to' => $toUtc]);
    $holdHoursVal = (float) $hoursHold->fetchColumn();

    $hoursClass = $pdo->prepare(
        'SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, starts_at, ends_at))/60,0)
         FROM ' . RP_TABLE_RENTAL_CLASSES . '
         WHERE starts_at >= :from AND starts_at < :to'
    );
    $hoursClass->execute(['from' => $fromUtc, 'to' => $toUtc]);
    $classHoursVal = (float) $hoursClass->fetchColumn();

    $rooms = rp_rental_rooms($pdo, true);
    $fund = 0.0;
    $cursor = $start;
    while ($cursor < $end) {
        $fund += rp_rental_day_fund_hours($cursor) * count($rooms);
        $cursor = $cursor->modify('+1 day');
    }
    $busy = $paidHoursVal + $holdHoursVal + $classHoursVal;
    $free = max(0, $fund - $busy);

    $needApprove = 0.0;
    if (!rp_rental_month_confirmed($pdo, $yearMonth)) {
        $weekday = $start;
        while ($weekday < $end) {
            if (!rp_rental_is_weekend_like($weekday)) {
                $needApprove += 8.0 * count($rooms); // 09:00–17:00
            }
            $weekday = $weekday->modify('+1 day');
        }
        $needApprove = min($needApprove, $free);
    }
    $freeBookable = max(0, $free - $needApprove);

    return [
        'month'          => $start->format('Y-m'),
        'confirmed'      => $confirmed,
        'expected'       => $expected,
        'refunds'        => $refunds,
        'income'         => $income,
        'total_expected' => $total,
        'new_clients'    => (int) $newClients->fetchColumn(),
        'all_clients'    => (int) $pdo->query('SELECT COUNT(*) FROM ' . RP_TABLE_RENTAL_CLIENTS)->fetchColumn(),
        'hours_paid'     => rp_rental_money($paidHoursVal),
        'hours_hold'     => rp_rental_money($holdHoursVal),
        'hours_class'    => rp_rental_money($classHoursVal),
        'hours_free'     => rp_rental_money($freeBookable),
        'hours_approve'  => rp_rental_money($needApprove),
    ];
}

function rp_rental_update_room(PDO $pdo, int $id, array $data, string $actor): void
{
    $room = rp_rental_room($pdo, $id);
    if ($room === null) {
        throw new RuntimeException('room_missing');
    }
    $name = rp_clean_string($data['name'] ?? '', 255);
    if ($name === '') {
        throw new InvalidArgumentException('name');
    }
    $areaRaw = trim((string) ($data['area_m2'] ?? ''));
    $area    = $areaRaw === '' ? null : rp_rental_money($areaRaw);
    $pdo->prepare(
        'UPDATE ' . RP_TABLE_RENTAL_ROOMS . '
         SET name = :name, building = :building, room_number = :room_number, type = :type,
             area_m2 = :area, description = :description, is_active = :active, updated_at = UTC_TIMESTAMP()
         WHERE id = :id'
    )->execute([
        'name'        => $name,
        'building'    => rp_clean_string($data['building'] ?? '', 20),
        'room_number' => rp_clean_string($data['room_number'] ?? '', 40) ?: null,
        'type'        => rp_clean_string($data['type'] ?? 'hall', 40),
        'area'        => $area,
        'description' => rp_clean_string($data['description'] ?? '', 2000) ?: null,
        'active'      => !empty($data['is_active']) ? 1 : 0,
        'id'          => $id,
    ]);
    rp_rental_audit($pdo, 'room_updated', 'room', $id, null, $name, null, $actor);
}

function rp_rental_replace_tariffs(PDO $pdo, int $roomId, array $tiers, string $actor): void
{
    $pdo->prepare('DELETE FROM ' . RP_TABLE_RENTAL_TARIFFS . ' WHERE room_id = :id')->execute(['id' => $roomId]);
    $ins = $pdo->prepare(
        'INSERT INTO ' . RP_TABLE_RENTAL_TARIFFS . ' (room_id, min_participants, max_participants, price)
         VALUES (:room_id, :min_participants, :max_participants, :price)'
    );
    foreach ($tiers as $tier) {
        $min = (int) ($tier['min'] ?? 1);
        $max = isset($tier['max']) && $tier['max'] !== '' ? (int) $tier['max'] : null;
        $ins->execute([
            'room_id'          => $roomId,
            'min_participants' => $min,
            'max_participants' => $max,
            'price'            => rp_rental_money($tier['price'] ?? 0),
        ]);
    }
    rp_rental_audit($pdo, 'tariffs_updated', 'room', $roomId, null, (string) count($tiers), null, $actor);
}

function rp_rental_set_holiday_rate(PDO $pdo, int $roomId, string $ymd, string $price, string $actor): void
{
    if (!rp_rental_is_public_holiday($ymd)) {
        throw new InvalidArgumentException('not_holiday');
    }
    $pdo->prepare(
        'INSERT INTO ' . RP_TABLE_RENTAL_HOLIDAY_RATES . ' (room_id, holiday_date, price)
         VALUES (:room, :d, :p)
         ON DUPLICATE KEY UPDATE price = VALUES(price)'
    )->execute(['room' => $roomId, 'd' => $ymd, 'p' => rp_rental_money($price)]);
    rp_rental_audit($pdo, 'holiday_rate', 'room', $roomId, $ymd, $price, null, $actor);
}

function rp_rental_save_user(PDO $pdo, string $username, string $password, string $role, string $actor): void
{
    if (!preg_match('/^[A-Za-z0-9._-]{3,80}$/', $username) || !rp_is_rental_role($role)) {
        throw new InvalidArgumentException('bad_user');
    }
    if (strlen($password) < 10) {
        throw new InvalidArgumentException('bad_password');
    }
    $stmt = $pdo->prepare(
        'INSERT INTO ' . RP_TABLE_ADMINS . ' (username, password_hash, role, created_at)
         VALUES (:username, :hash, :role, UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), role = VALUES(role)'
    );
    $stmt->execute([
        'username' => $username,
        'hash'     => password_hash($password, PASSWORD_DEFAULT),
        'role'     => $role,
    ]);
    rp_rental_audit($pdo, 'user_saved', 'user', null, null, $username, $role, $actor);
}

/** @return list<array<string,mixed>> */
function rp_rental_users(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT id, username, role, created_at, last_login_at FROM ' . RP_TABLE_ADMINS . '
         WHERE role IN (\'rental\', \'rental_manager\', \'rental_schedule\')
         ORDER BY username'
    );

    return $stmt->fetchAll();
}

function rp_rental_type_label(string $type): string
{
    return match ($type) {
        'pool'       => __('rental.type.pool'),
        'auditorium' => __('rental.type.auditorium'),
        'lobby'      => __('rental.type.lobby'),
        default      => __('rental.type.hall'),
    };
}

function rp_rental_client_type_label(string $type): string
{
    return match ($type) {
        'fop' => __('rental.client.fop'),
        'org' => __('rental.client.org'),
        default => __('rental.client.person'),
    };
}

function rp_rental_session_status_label(string $status): string
{
    return match ($status) {
        'hold'      => __('rental.session.hold'),
        'booked'    => __('rental.session.booked'),
        'cancelled' => __('rental.session.cancelled'),
        'no_show'   => __('rental.session.no_show'),
        default     => $status,
    };
}

function rp_rental_payment_label(string $status): string
{
    return match ($status) {
        'paid'      => __('rental.pay.paid'),
        'expired'   => __('rental.pay.expired'),
        'cancelled' => __('rental.pay.cancelled'),
        default     => __('rental.pay.unpaid'),
    };
}
