<?php
/**
 * CLI: seed university-relevant holidays into rp_content_items (Sep–Dec 2026).
 *
 *   php tools/seed_calendar_2026.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script runs from the command line only.\n");
}

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/content.php';

$pdo = rp_db();

$existing = $pdo->query(
    'SELECT id, title, event_at, created_by FROM ' . RP_TABLE_CONTENT . '
     WHERE event_at >= \'2026-09-21 00:00:00\' AND event_at < \'2027-01-01 00:00:00\'
     ORDER BY event_at ASC'
)->fetchAll();

echo "Existing events Sep–Dec 2026: " . count($existing) . "\n";
foreach ($existing as $row) {
    echo sprintf("  #%s %s | %s | %s\n", $row['id'], $row['event_at'], $row['title'], $row['created_by'] ?? '');
}

$seeded = $pdo->query(
    'SELECT COUNT(*) FROM ' . RP_TABLE_CONTENT . " WHERE created_by = 'system:calendar-2026'"
)->fetchColumn();

if ((int) $seeded > 0) {
    fwrite(STDERR, "Seed system:calendar-2026 already present ({$seeded} rows). Aborting.\n");
    exit(1);
}

$channelsDefault = ['website', 'facebook', 'instagram', 'telegram'];

$items = [
    [
        'title' => 'Міжнародний день миру',
        'event_at' => '2026-09-21 06:00:00',
        'department' => 'Навчально-науковий олімпійський інститут',
        'description' => 'Спорт як мова миру: команда, інклюзія, ветерани. Показати тренування студентів і те, як університет об’єднує людей різного досвіду. Без політичних гасел.',
        'extra_info' => 'UN International Day of Peace; zakon.rada.gov.ua',
        'channels' => $channelsDefault,
    ],
    [
        'title' => 'День без автомобіля',
        'event_at' => '2026-09-22 06:00:00',
        'department' => 'Факультет здоровʼя, фізичного виховання та туризму',
        'description' => 'Заклик дістатися університету пішки, бігом або велосипедом. Коротке відео/сторіз маршруту до вул. Фізкультури, 1. Хештег #BeActive.',
        'extra_info' => 'World Car-Free Day; European Week of Sport 23–30.09.2026',
        'channels' => ['instagram', 'telegram', 'facebook'],
    ],
    [
        'title' => 'European Week of Sport — старт тижня',
        'event_at' => '2026-09-23 06:00:00',
        'department' => 'Факультет спорту та менеджменту',
        'description' => 'Тиждень 23–30 вересня. Челендж кафедр «будь активним щодня»: 1 тренування, 1 сторіз, підсумок у п’ятницю. Запросити студентів і співробітників.',
        'extra_info' => 'https://sport.ec.europa.eu/european-week-of-sport',
        'channels' => ['website', 'facebook', 'instagram', 'telegram', 'tiktok'],
    ],
    [
        'title' => 'Всесвітній день туризму',
        'event_at' => '2026-09-27 06:00:00',
        'department' => 'Факультет здоровʼя, фізичного виховання та туризму',
        'description' => 'Спеціальність «Туризм і рекреація»: активний туризм, рекреація спортсменів, маршрути Києва. Показати практику студентів, не стокові фото.',
        'extra_info' => 'UNWTO World Tourism Day; 27 вересня також День туризму в календарі ВРУ',
        'channels' => $channelsDefault,
    ],
    [
        'title' => 'Всесвітній день серця',
        'event_at' => '2026-09-29 06:00:00',
        'department' => 'Факультет здоровʼя, фізичного виховання та туризму',
        'description' => 'Кампанія WHF «Donʼt Miss a Beat». 1 факт: рух знижує ризик серцево-судинних хвороб. 1 проста вправа від викладача. Заклик перевірити тиск.',
        'extra_info' => 'https://world-heart-federation.org/world-heart-day/',
        'channels' => $channelsDefault,
    ],
    [
        'title' => 'День памʼяті жертв Бабиного Яру',
        'event_at' => '2026-09-29 07:00:00',
        'department' => 'Ректорат',
        'description' => 'Стриманий пост вшанування. Без розважального контенту цього дня. Коротко: історія, памʼять, посилання на офіційні заходи Києва — якщо підтверджені.',
        'extra_info' => 'zakon.rada.gov.ua — 29 вересня',
        'channels' => ['website', 'facebook', 'telegram'],
    ],
    [
        'title' => 'Всеукраїнський день бібліотек',
        'event_at' => '2026-09-30 06:00:00',
        'department' => 'Бібліотека НУФВСУ',
        'description' => 'Показати бібліотеку університету, видавництво «Олімпійська література», 3 книжки для студента-спортсмена. Запрошення на консультацію.',
        'extra_info' => 'zakon.rada.gov.ua — 30 вересня',
        'channels' => ['website', 'facebook', 'instagram'],
    ],
    [
        'title' => 'День захисників і захисниць України',
        'event_at' => '2026-10-01 06:00:00',
        'department' => 'Ректорат',
        'description' => 'Головне державне свято осені (також Покрова і День українського козацтва). Вшанувати студентів, викладачів і випускників на службі, ветеранів. Тон гідний, без пафосу. За потреби — окрема історія героя університету.',
        'extra_info' => 'Офіційний вихідний за КЗпП; під час воєнного стану перенесення не діє. https://zakon.rada.gov.ua/laws/main/days',
        'channels' => ['website', 'facebook', 'instagram', 'telegram', 'youtube'],
    ],
    [
        'title' => 'День працівників освіти',
        'event_at' => '2026-10-04 06:00:00',
        'department' => 'Усі факультети',
        'description' => 'Перша неділя жовтня. Привітання викладачів НУФВСУ. Карусель: 4–5 облич кафедр. 5 жовтня — Міжнародний день вчителя UNESCO: можна вести кампанію 4–5.10.',
        'extra_info' => 'Перша неділя жовтня; UNESCO World Teachers’ Day — 5.10',
        'channels' => $channelsDefault,
    ],
    [
        'title' => 'Всесвітній день психічного здоровʼя',
        'event_at' => '2026-10-10 06:00:00',
        'department' => 'Центр спортивної травматології та відновлювальної медицини',
        'description' => 'Тема WHO 2026: Lived experiences heard. Тиск змагань, відновлення, вигорання. Куди звернутися в університеті. Без порад «просто помисли позитивно».',
        'extra_info' => 'https://www.who.int/campaigns/world-mental-health-day/2026',
        'channels' => $channelsDefault,
    ],
    [
        'title' => 'Всесвітній день продовольства',
        'event_at' => '2026-10-16 06:00:00',
        'department' => 'Кафедра спортивної дієтології',
        'description' => 'Їжа як частина підготовки, не «дієти». Три правила тарілки спортсмена + вода. Залучити магістерську спеціалізацію зі спортивної дієтології.',
        'extra_info' => 'FAO World Food Day — 16 жовтня',
        'channels' => ['instagram', 'facebook', 'telegram'],
    ],
    [
        'title' => 'Всеукраїнський день боротьби з раком молочної залози',
        'event_at' => '2026-10-20 06:00:00',
        'department' => 'Центр спортивної травматології та відновлювальної медицини',
        'description' => 'Скринінг, рух під час і після лікування. Тон підтримки, не залякування. Контакти медцентру університету, якщо погодять.',
        'extra_info' => 'zakon.rada.gov.ua — 20 жовтня',
        'channels' => $channelsDefault,
    ],
    [
        'title' => 'День української писемності та мови',
        'event_at' => '2026-10-27 07:00:00',
        'department' => 'Пресслужба',
        'description' => 'Мова в спорті: гімн, коментарі, імена чемпіонів. Короткий словник правильних спорттермінів українською. Запросити студентів написати слово «перемога» у сторіз.',
        'extra_info' => 'zakon.rada.gov.ua — 27 жовтня',
        'channels' => $channelsDefault,
    ],
    [
        'title' => 'День визволення України від нацизму',
        'event_at' => '2026-10-28 07:00:00',
        'department' => 'Ректорат',
        'description' => 'Памʼятна дата. Коротка історична довідка, без святкової стилістики. За потреби — звʼязок із ветеранами Другої світової / сучасними захисниками.',
        'extra_info' => 'zakon.rada.gov.ua — 28 жовтня',
        'channels' => ['website', 'facebook', 'telegram'],
    ],
    [
        'title' => 'Всесвітній день боротьби з інсультом',
        'event_at' => '2026-10-29 07:00:00',
        'department' => 'Спеціальність «Терапія та реабілітація»',
        'description' => 'Профілактика рухом. Роль фізичної терапії після інсульту. 1 ознака інсульту (FAST) + як університет готує реабілітологів.',
        'extra_info' => 'World Stroke Day — 29 жовтня',
        'channels' => $channelsDefault,
    ],
    [
        'title' => 'День працівників культури та майстрів народного мистецтва',
        'event_at' => '2026-11-09 07:00:00',
        'department' => 'Кафедра хореографії',
        'description' => 'НУФВСУ готує хореографів: сцена, постановки, спорт як культура тіла. Показати репетицію або уривок виступу студентів.',
        'extra_info' => 'zakon.rada.gov.ua — 9 листопада',
        'channels' => ['website', 'facebook', 'instagram', 'youtube'],
    ],
    [
        'title' => 'Всесвітній день науки заради миру та розвитку',
        'event_at' => '2026-11-10 07:00:00',
        'department' => 'Науково-дослідний інститут НУФВСУ',
        'description' => 'UNESCO World Science Day. Один конкретний кейс досліджень університету: відновлення, навантаження, олімпійська освіта. Запросити на відкриту лекцію, якщо буде.',
        'extra_info' => 'https://unesco.org/en/days/science-peace-development',
        'channels' => ['website', 'facebook', 'telegram'],
    ],
    [
        'title' => 'Всесвітній день боротьби з діабетом',
        'event_at' => '2026-11-14 07:00:00',
        'department' => 'Факультет здоровʼя, фізичного виховання та туризму',
        'description' => 'Рух і контроль глюкози. Адаптивний спорт. Коротко: міфи про «спорт і діабет» від викладача.',
        'extra_info' => 'WHO World Diabetes Day — 14 листопада',
        'channels' => ['website', 'facebook', 'instagram'],
    ],
    [
        'title' => 'Міжнародний день толерантності',
        'event_at' => '2026-11-16 07:00:00',
        'department' => 'Студентський парламент',
        'description' => 'Команда без дискримінації: інклюзія, іноземні студенти, адаптивний спорт. Правила поваги в залі та гуртожитку.',
        'extra_info' => 'UNESCO International Day for Tolerance — 16 листопада',
        'channels' => ['instagram', 'facebook', 'telegram'],
    ],
    [
        'title' => 'День студента',
        'event_at' => '2026-11-17 07:00:00',
        'department' => 'Студентський парламент',
        'description' => 'Теплий обовʼязковий контент: гуртожиток, збірні, закулісся тренувань, 3 обличчя студентів різних факультетів. Не лише «вітаємо», а живі історії.',
        'extra_info' => 'zakon.rada.gov.ua — 17 листопада (Міжнародний день студента)',
        'channels' => ['website', 'facebook', 'instagram', 'telegram', 'tiktok'],
    ],
    [
        'title' => 'День Гідності та Свободи',
        'event_at' => '2026-11-21 07:00:00',
        'department' => 'Ректорат',
        'description' => 'Євромайдан, гідність, свобода. Згадати студентів 2013–14. Стриманий тон, архівні факти, без мемів.',
        'extra_info' => 'zakon.rada.gov.ua — 21 листопада',
        'channels' => $channelsDefault,
    ],
    [
        'title' => 'Міжнародний день боротьби з насильством щодо жінок',
        'event_at' => '2026-11-25 07:00:00',
        'department' => 'Пресслужба',
        'description' => 'Безпека і повага в залі, гуртожитку, на зборах. Короткі правила + куди звертатися. Без жертв у візуалі.',
        'extra_info' => 'UN International Day for the Elimination of Violence against Women',
        'channels' => $channelsDefault,
    ],
    [
        'title' => 'День памʼяті жертв голодоморів',
        'event_at' => '2026-11-28 07:00:00',
        'department' => 'Ректорат',
        'description' => 'Четверта субота листопада. Свічка памʼяті. Цього дня не планувати розважальний і промо-контент.',
        'extra_info' => 'zakon.rada.gov.ua — 28 листопада 2026',
        'channels' => ['website', 'facebook', 'telegram'],
    ],
    [
        'title' => 'Всесвітній день боротьби зі СНІДом',
        'event_at' => '2026-12-01 07:00:00',
        'department' => 'Центр спортивної травматології та відновлювальної медицини',
        'description' => 'Факти, тестування, без стигми. Спорт і ВІЛ: можна тренуватися, потрібна підтримка. Контакти, якщо медцентр погодить.',
        'extra_info' => 'UNAIDS World AIDS Day — 1 грудня',
        'channels' => ['website', 'facebook', 'telegram'],
    ],
    [
        'title' => 'Міжнародний день людей з інвалідністю',
        'event_at' => '2026-12-03 07:00:00',
        'department' => 'Спеціальність «Терапія та реабілітація»',
        'description' => 'Паралімпійці-вихованці НУФВСУ, ерготерапія, спорт без барʼєрів. Показати інфраструктуру / адаптивні програми, не «натхненну» риторику.',
        'extra_info' => 'UN International Day of Persons with Disabilities; також у календарі ВРУ',
        'channels' => $channelsDefault,
    ],
    [
        'title' => 'Міжнародний день волонтера',
        'event_at' => '2026-12-05 07:00:00',
        'department' => 'Студентський парламент',
        'description' => 'Волонтери на стартах, допомога ЗСУ, донорство. Подяка конкретним людям з іменами і фото (за згодою).',
        'extra_info' => 'UN International Volunteer Day — 5 грудня',
        'channels' => $channelsDefault,
    ],
    [
        'title' => 'День Збройних Сил України',
        'event_at' => '2026-12-06 07:00:00',
        'department' => 'Ректорат',
        'description' => 'Вшанування ЗСУ. Студенти програми офіцерів запасу, викладачі-ветерани. Окремо від Миколая — ранок, гідний тон.',
        'extra_info' => 'zakon.rada.gov.ua — 6 грудня',
        'channels' => ['website', 'facebook', 'instagram', 'telegram', 'youtube'],
    ],
    [
        'title' => 'День святого Миколая',
        'event_at' => '2026-12-06 15:00:00',
        'department' => 'Студентський парламент',
        'description' => 'Теплий денний/вечірній пост: діти захисників, гуртожиток, подарунки від студпарламенту. Не змішувати зі стилістикою Дня ЗСУ.',
        'extra_info' => '6 грудня; час 17:00 Київ = 15:00 UTC',
        'channels' => ['instagram', 'facebook', 'telegram'],
    ],
    [
        'title' => 'День прав людини',
        'event_at' => '2026-12-10 07:00:00',
        'department' => 'Пресслужба',
        'description' => 'Право на освіту, спорт, інклюзію. Коротко: як університет відкриває спорт для різних груп.',
        'extra_info' => 'UN Human Rights Day — 10 грудня',
        'channels' => ['website', 'facebook', 'telegram'],
    ],
    [
        'title' => 'Всесвітній день баскетболу',
        'event_at' => '2026-12-21 07:00:00',
        'department' => 'Факультет спорту та менеджменту',
        'description' => 'UN / FIBA: 21 грудня — річниця першої гри 1891. Показати збірну університету, челендж кидків, коротке відео з зали. Сильний профільний день для НУФВСУ.',
        'extra_info' => 'https://www.un.org/en/observances/world-basketball-day',
        'channels' => ['website', 'facebook', 'instagram', 'telegram', 'tiktok', 'youtube'],
    ],
    [
        'title' => 'Різдво Христове',
        'event_at' => '2026-12-25 07:00:00',
        'department' => 'Пресслужба',
        'description' => 'Офіційне державне свято. Привітання громаді університету. Якщо відомий графік зимових канікул — додати в extra_info перед публікацією.',
        'extra_info' => 'zakon.rada.gov.ua — 25 грудня, пʼятниця',
        'channels' => $channelsDefault,
    ],
    [
        'title' => 'Новий рік',
        'event_at' => '2026-12-31 07:00:00',
        'department' => 'Пресслужба',
        'description' => 'Підсумки 2026 для НУФВСУ: 3 цифри, 3 імена, подяка викладачам і студентам. Візуал без зайвого пафосу. Графік роботи після свят — якщо є.',
        'extra_info' => 'Наступне офіційне свято — 1 січня 2027',
        'channels' => $channelsDefault,
    ],
];

$stmt = $pdo->prepare(
    'INSERT INTO ' . RP_TABLE_CONTENT . '
        (title, event_at, department, description, responsible, extra_info,
         channels, status, created_by, created_at, updated_at)
     VALUES
        (:title, :event_at, :department, :description, :responsible, :extra_info,
         :channels, :status, :created_by, NOW(), NOW())'
);

$pdo->beginTransaction();
try {
    foreach ($items as $item) {
        $stmt->execute([
            'title'       => $item['title'],
            'event_at'    => $item['event_at'],
            'department'  => $item['department'],
            'description' => $item['description'],
            'responsible' => 'Пресслужба',
            'extra_info'  => $item['extra_info'],
            'channels'    => json_encode($item['channels'], JSON_UNESCAPED_UNICODE),
            'status'      => 'planned',
            'created_by'  => 'system:calendar-2026',
        ]);
        echo '+ ' . $item['event_at'] . '  ' . $item['title'] . "\n";
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Insert failed: ' . $e->getMessage() . "\n");
    exit(1);
}

$count = $pdo->query(
    'SELECT COUNT(*) FROM ' . RP_TABLE_CONTENT . " WHERE created_by = 'system:calendar-2026'"
)->fetchColumn();

echo "Inserted {$count} planned calendar items.\n";
