<?php

declare(strict_types=1);

/**
 * Ukrainian national holidays and commemorative dates for a calendar year.
 *
 * Public days off follow the current Labour Code list (Easter and Trinity are
 * Orthodox / Julian, converted to Gregorian). Other entries are widely marked
 * national remembrance and cultural dates.
 *
 * @return list<array{slug:string,kind:string,uk:string,en:string,rule:array}>
 */
function rp_ukrainian_holiday_defs(): array
{
    return [
        ['slug' => 'new-year', 'kind' => 'public', 'uk' => 'Новий рік', 'en' => 'New Year', 'rule' => ['fixed', 1, 1]],
        ['slug' => 'christmas-julian', 'kind' => 'date', 'uk' => 'Різдво Христове (7 січня)', 'en' => 'Christmas (Julian)', 'rule' => ['fixed', 1, 7]],
        ['slug' => 'unity-day', 'kind' => 'date', 'uk' => 'День Соборності України', 'en' => 'Unity Day of Ukraine', 'rule' => ['fixed', 1, 22]],
        ['slug' => 'krut', 'kind' => 'date', 'uk' => 'День пам’яті Героїв Крут', 'en' => 'Kruty Heroes Remembrance Day', 'rule' => ['fixed', 1, 29]],
        ['slug' => 'combatants-abroad', 'kind' => 'date', 'uk' => 'День вшанування учасників бойових дій на території інших держав', 'en' => 'Day of honouring combatants who served abroad', 'rule' => ['fixed', 2, 15]],
        ['slug' => 'heavenly-hundred', 'kind' => 'date', 'uk' => 'День Героїв Небесної Сотні', 'en' => 'Heavenly Hundred Heroes Day', 'rule' => ['fixed', 2, 20]],
        ['slug' => 'womens-day', 'kind' => 'public', 'uk' => 'Міжнародний жіночий день', 'en' => 'International Women’s Day', 'rule' => ['fixed', 3, 8]],
        ['slug' => 'shevchenko', 'kind' => 'date', 'uk' => 'День народження Тараса Шевченка', 'en' => 'Taras Shevchenko’s birthday', 'rule' => ['fixed', 3, 9]],
        ['slug' => 'volunteer-day', 'kind' => 'date', 'uk' => 'День українського добровольця', 'en' => 'Ukrainian Volunteer Day', 'rule' => ['fixed', 3, 14]],
        ['slug' => 'palm-sunday', 'kind' => 'date', 'uk' => 'Вербна неділя', 'en' => 'Palm Sunday', 'rule' => ['easter', -7]],
        ['slug' => 'annunciation', 'kind' => 'date', 'uk' => 'Благовіщення Пресвятої Богородиці', 'en' => 'Annunciation', 'rule' => ['fixed', 4, 7]],
        ['slug' => 'easter', 'kind' => 'public', 'uk' => 'Великдень', 'en' => 'Orthodox Easter', 'rule' => ['easter', 0]],
        ['slug' => 'chornobyl', 'kind' => 'date', 'uk' => 'День Чорнобильської трагедії', 'en' => 'Chornobyl Disaster Remembrance Day', 'rule' => ['fixed', 4, 26]],
        ['slug' => 'labour-day', 'kind' => 'public', 'uk' => 'День праці', 'en' => 'Labour Day', 'rule' => ['fixed', 5, 1]],
        ['slug' => 'victory-day', 'kind' => 'public', 'uk' => 'День пам’яті та перемоги над нацизмом', 'en' => 'Day of Remembrance and Victory over Nazism', 'rule' => ['fixed', 5, 8]],
        ['slug' => 'europe-day', 'kind' => 'date', 'uk' => 'День Європи', 'en' => 'Europe Day', 'rule' => ['fixed', 5, 9]],
        ['slug' => 'mothers-day', 'kind' => 'date', 'uk' => 'День матері', 'en' => 'Mother’s Day', 'rule' => ['nth', 5, 7, 2]],
        ['slug' => 'vyshyvanka', 'kind' => 'date', 'uk' => 'День вишиванки', 'en' => 'Vyshyvanka Day', 'rule' => ['nth', 5, 4, 3]],
        ['slug' => 'crimean-tatars', 'kind' => 'date', 'uk' => 'День пам’яті жертв геноциду кримськотатарського народу', 'en' => 'Crimean Tatar Genocide Remembrance Day', 'rule' => ['fixed', 5, 18]],
        ['slug' => 'trinity', 'kind' => 'public', 'uk' => 'Трійця', 'en' => 'Holy Trinity (Pentecost)', 'rule' => ['easter', 49]],
        ['slug' => 'childrens-day', 'kind' => 'date', 'uk' => 'День захисту дітей', 'en' => 'Children’s Day', 'rule' => ['fixed', 6, 1]],
        ['slug' => 'children-war', 'kind' => 'date', 'uk' => 'День вшанування пам’яті дітей, загиблих унаслідок збройної агресії РФ', 'en' => 'Remembrance Day for children killed by Russian aggression', 'rule' => ['fixed', 6, 4]],
        ['slug' => 'sorrow-day', 'kind' => 'date', 'uk' => 'День скорботи і вшанування пам’яті жертв війни', 'en' => 'Day of Sorrow and commemoration of war victims', 'rule' => ['fixed', 6, 22]],
        ['slug' => 'constitution-day', 'kind' => 'public', 'uk' => 'День Конституції України', 'en' => 'Constitution Day of Ukraine', 'rule' => ['fixed', 6, 28]],
        ['slug' => 'statehood-day', 'kind' => 'public', 'uk' => 'День Української Державності', 'en' => 'Day of Ukrainian Statehood', 'rule' => ['fixed', 7, 15]],
        ['slug' => 'baptism-rus', 'kind' => 'date', 'uk' => 'День хрещення Київської Русі-України', 'en' => 'Baptism of Kyivan Rus’-Ukraine Day', 'rule' => ['fixed', 7, 28]],
        ['slug' => 'flag-day', 'kind' => 'date', 'uk' => 'День Державного Прапора України', 'en' => 'National Flag Day of Ukraine', 'rule' => ['fixed', 8, 23]],
        ['slug' => 'independence-day', 'kind' => 'public', 'uk' => 'День Незалежності України', 'en' => 'Independence Day of Ukraine', 'rule' => ['fixed', 8, 24]],
        ['slug' => 'fallen-defenders', 'kind' => 'date', 'uk' => 'День пам’яті захисників України', 'en' => 'Remembrance Day of Ukraine’s Defenders', 'rule' => ['fixed', 8, 29]],
        ['slug' => 'knowledge-day', 'kind' => 'date', 'uk' => 'День знань', 'en' => 'Knowledge Day', 'rule' => ['fixed', 9, 1]],
        ['slug' => 'sport-day', 'kind' => 'date', 'uk' => 'День фізичної культури і спорту', 'en' => 'Physical Culture and Sport Day', 'rule' => ['nth', 9, 6, 2]],
        ['slug' => 'peace-day', 'kind' => 'date', 'uk' => 'Міжнародний день миру', 'en' => 'International Day of Peace', 'rule' => ['fixed', 9, 21]],
        ['slug' => 'babyn-yar', 'kind' => 'date', 'uk' => 'День пам’яті жертв Бабиного Яру', 'en' => 'Babyn Yar Remembrance Day', 'rule' => ['fixed', 9, 29]],
        ['slug' => 'defenders-day', 'kind' => 'public', 'uk' => 'День захисників і захисниць України', 'en' => 'Defenders of Ukraine Day', 'rule' => ['fixed', 10, 1]],
        ['slug' => 'teachers-day', 'kind' => 'date', 'uk' => 'День працівників освіти', 'en' => 'Teachers’ Day', 'rule' => ['nth', 10, 7, 1]],
        ['slug' => 'pokrova', 'kind' => 'date', 'uk' => 'Покрова Пресвятої Богородиці / День українського козацтва', 'en' => 'Pokrova / Ukrainian Cossacks Day', 'rule' => ['fixed', 10, 14]],
        ['slug' => 'ukrainian-language', 'kind' => 'date', 'uk' => 'День української писемності та мови', 'en' => 'Ukrainian Writing and Language Day', 'rule' => ['fixed', 10, 27]],
        ['slug' => 'liberation-day', 'kind' => 'date', 'uk' => 'День визволення України від нацистських загарбників', 'en' => 'Ukraine Liberation Day', 'rule' => ['fixed', 10, 28]],
        ['slug' => 'students-day', 'kind' => 'date', 'uk' => 'День студента', 'en' => 'Students’ Day', 'rule' => ['fixed', 11, 17]],
        ['slug' => 'dignity-day', 'kind' => 'date', 'uk' => 'День Гідності та Свободи', 'en' => 'Dignity and Freedom Day', 'rule' => ['fixed', 11, 21]],
        ['slug' => 'holodomor', 'kind' => 'date', 'uk' => 'День пам’яті жертв голодоморів', 'en' => 'Holodomor Remembrance Day', 'rule' => ['nth', 11, 6, 4]],
        ['slug' => 'armed-forces', 'kind' => 'date', 'uk' => 'День Збройних Сил України', 'en' => 'Armed Forces of Ukraine Day', 'rule' => ['fixed', 12, 6]],
        ['slug' => 'st-nicholas', 'kind' => 'date', 'uk' => 'День святого Миколая', 'en' => 'St Nicholas Day', 'rule' => ['fixed', 12, 6]],
        ['slug' => 'christmas', 'kind' => 'public', 'uk' => 'Різдво Христове', 'en' => 'Christmas', 'rule' => ['fixed', 12, 25]],
    ];
}

/** Orthodox (Julian) Easter as a Gregorian date in the app timezone. */
function rp_orthodox_easter(int $year, DateTimeZone $tz): DateTimeImmutable
{
    $a = $year % 4;
    $b = $year % 7;
    $c = $year % 19;
    $d = (19 * $c + 15) % 30;
    $e = (2 * $a + 4 * $b - $d + 34) % 7;
    $month = intdiv($d + $e + 114, 31);
    $day   = (($d + $e + 114) % 31) + 1;
    $julian = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day), $tz);

    return $julian->modify('+13 days');
}

/** ISO weekday: 1 = Monday … 7 = Sunday. $nth is 1-based. */
function rp_nth_iso_weekday(int $year, int $month, int $isoWeekday, int $nth, DateTimeZone $tz): DateTimeImmutable
{
    $first = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month), $tz);
    $shift = ($isoWeekday - (int) $first->format('N') + 7) % 7;
    $date  = $first->modify('+' . $shift . ' days');
    if ($nth > 1) {
        $date = $date->modify('+' . (7 * ($nth - 1)) . ' days');
    }

    return $date;
}

/**
 * Visible Ukrainian holidays for a year (hidden keys already removed).
 *
 * @return list<array{key:string,date:string,kind:string,title:string}>
 */
function rp_ukrainian_holidays(int $year): array
{
    $tz     = rp_app_timezone();
    $easter = rp_orthodox_easter($year, $tz);
    $out    = [];

    foreach (rp_ukrainian_holiday_defs() as $def) {
        $rule = $def['rule'];
        $type = $rule[0];
        if ($type === 'fixed') {
            $date = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $rule[1], $rule[2]), $tz);
        } elseif ($type === 'easter') {
            $date = $easter->modify(sprintf('%+d days', (int) $rule[1]));
        } elseif ($type === 'nth') {
            $date = rp_nth_iso_weekday($year, (int) $rule[1], (int) $rule[2], (int) $rule[3], $tz);
            if ((int) $date->format('n') !== (int) $rule[1]) {
                continue;
            }
        } else {
            continue;
        }

        $ymd = $date->format('Y-m-d');
        $out[] = [
            'key'   => $ymd . ':' . $def['slug'],
            'date'  => $ymd,
            'kind'  => $def['kind'],
            'title' => rp_lang() === 'en' ? $def['en'] : $def['uk'],
        ];
    }

    usort($out, static fn (array $a, array $b): int => strcmp($a['date'] . $a['key'], $b['date'] . $b['key']));

    return $out;
}

/**
 * @return array<string,true>
 */
function rp_hidden_holiday_keys(PDO $pdo): array
{
    $rows = $pdo->query('SELECT holiday_key FROM ' . RP_TABLE_HIDDEN_HOLIDAYS)->fetchAll();
    $out  = [];
    foreach ($rows as $row) {
        $key = (string) ($row['holiday_key'] ?? '');
        if ($key !== '') {
            $out[$key] = true;
        }
    }

    return $out;
}

function rp_holiday_key_valid(string $key): bool
{
    return (bool) preg_match('/^\d{4}-\d{2}-\d{2}:[a-z0-9-]{1,60}$/', $key);
}

function rp_hide_holiday(PDO $pdo, string $key, string $by): bool
{
    if (!rp_holiday_key_valid($key)) {
        return false;
    }
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO ' . RP_TABLE_HIDDEN_HOLIDAYS . '
            (holiday_key, hidden_by, hidden_at) VALUES (:k, :by, NOW())'
    );
    $stmt->execute(['k' => $key, 'by' => $by]);

    return true;
}

function rp_restore_holiday(PDO $pdo, string $key): bool
{
    if (!rp_holiday_key_valid($key)) {
        return false;
    }
    $stmt = $pdo->prepare('DELETE FROM ' . RP_TABLE_HIDDEN_HOLIDAYS . ' WHERE holiday_key = :k');
    $stmt->execute(['k' => $key]);

    return $stmt->rowCount() > 0;
}

function rp_restore_all_holidays(PDO $pdo): int
{
    return (int) $pdo->exec('DELETE FROM ' . RP_TABLE_HIDDEN_HOLIDAYS);
}

/**
 * @return array{date:string,slug:string}|null
 */
function rp_parse_holiday_key(string $key): ?array
{
    if (!preg_match('/^(\d{4}-\d{2}-\d{2}):([a-z0-9-]{1,60})$/', $key, $match)) {
        return null;
    }

    return ['date' => $match[1], 'slug' => $match[2]];
}

/**
 * Rebuild a holiday row from a stored hide-key (for the restore list).
 *
 * @return array{key:string,date:string,kind:string,title:string}|null
 */
function rp_holiday_from_key(string $key): ?array
{
    $parsed = rp_parse_holiday_key($key);
    if ($parsed === null) {
        return null;
    }

    $year = (int) substr($parsed['date'], 0, 4);
    foreach (rp_ukrainian_holidays($year) as $holiday) {
        if ($holiday['key'] === $key) {
            return $holiday;
        }
    }

    foreach (rp_ukrainian_holiday_defs() as $def) {
        if ($def['slug'] !== $parsed['slug']) {
            continue;
        }

        return [
            'key'   => $key,
            'date'  => $parsed['date'],
            'kind'  => $def['kind'],
            'title' => rp_lang() === 'en' ? $def['en'] : $def['uk'],
        ];
    }

    return [
        'key'   => $key,
        'date'  => $parsed['date'],
        'kind'  => 'date',
        'title' => $parsed['slug'],
    ];
}

/**
 * Hidden holidays, newest first.
 *
 * @return list<array{key:string,date:string,kind:string,title:string,hidden_by:string,hidden_at:string}>
 */
function rp_hidden_holidays_list(PDO $pdo): array
{
    $rows = $pdo->query(
        'SELECT holiday_key, hidden_by, hidden_at FROM ' . RP_TABLE_HIDDEN_HOLIDAYS . '
         ORDER BY hidden_at DESC, holiday_key ASC'
    )->fetchAll();

    $out = [];
    foreach ($rows as $row) {
        $holiday = rp_holiday_from_key((string) ($row['holiday_key'] ?? ''));
        if ($holiday === null) {
            continue;
        }
        $holiday['hidden_by'] = (string) ($row['hidden_by'] ?? '');
        $holiday['hidden_at'] = (string) ($row['hidden_at'] ?? '');
        $out[] = $holiday;
    }

    return $out;
}

function rp_holiday_kind_label(string $kind): string
{
    return $kind === 'public' ? __('content.holiday.public') : __('content.holiday.date');
}

/**
 * Holidays in [fromYmd, toYmd], minus hidden ones.
 *
 * @return list<array{key:string,date:string,kind:string,title:string}>
 */
function rp_visible_holidays(PDO $pdo, string $fromYmd, string $toYmd): array
{
    $fromYear = (int) substr($fromYmd, 0, 4);
    $toYear   = (int) substr($toYmd, 0, 4);
    $hidden   = rp_hidden_holiday_keys($pdo);
    $out      = [];

    for ($year = $fromYear; $year <= $toYear; $year++) {
        foreach (rp_ukrainian_holidays($year) as $holiday) {
            if ($holiday['date'] < $fromYmd || $holiday['date'] > $toYmd) {
                continue;
            }
            if (isset($hidden[$holiday['key']])) {
                continue;
            }
            $out[] = $holiday;
        }
    }

    return $out;
}

/**
 * Holidays for the visible month, minus hidden ones.
 *
 * @return list<array{key:string,date:string,kind:string,title:string}>
 */
function rp_holidays_for_month(PDO $pdo, DateTimeImmutable $monthStart): array
{
    $prefix = $monthStart->format('Y-m');
    $from   = $monthStart->format('Y-m-01');
    $to     = $monthStart->modify('last day of this month')->format('Y-m-d');
    $out    = [];
    foreach (rp_visible_holidays($pdo, $from, $to) as $holiday) {
        if (str_starts_with($holiday['date'], $prefix)) {
            $out[] = $holiday;
        }
    }

    return $out;
}

/**
 * @param list<array{key:string,date:string,kind:string,title:string}> $holidays
 * @return array<string,list<array{key:string,date:string,kind:string,title:string}>>
 */
function rp_holidays_by_day(array $holidays): array
{
    $byDay = [];
    foreach ($holidays as $holiday) {
        $byDay[$holiday['date']][] = $holiday;
    }

    return $byDay;
}
