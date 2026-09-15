# Портал заявок / Request Portal

Проста сторінка для подачі заявок на **розміщення** або **оновлення** інформації на сайті (Joomla або будь-якому іншому) + панель адміністратора. Чистий PHP 8 + MySQL, без Composer і без зовнішніх залежностей.

A simple portal for submitting requests to **publish** or **update** information on a website, plus an admin panel. Plain PHP 8 + MySQL, no Composer, no external dependencies.

---

## Що вміє / Features

* Публічна форма без реєстрації: тип заявки (нове розміщення / оновлення), місце розміщення (посилання **або** назва розділу), опис, додатковий коментар, ім'я та контакт заявника.
* Вкладення: фото, відео, документи (`pdf`, `doc(x)`, `xls(x)`, `ppt(x)`, `txt`, `csv`, `zip`) — до 10 файлів по 200 МБ.
* Кожна заявка отримує номер (`REQ-2026-XXXXXX`), який показується заявнику.
* Панель адміністратора: список із фільтрами (статус, тип, дати), пошуком і пагінацією; картка заявки; зміна статусу (нова → в роботі → виконано / відхилено); внутрішня примітка; завантаження вкладень.
* Повна історія по кожній заявці в БД: створення, додані файли, зміни статусу з коментарем, редагування примітки — з автором, IP та часом.
* Двомовний інтерфейс: українська / English (перемикач у шапці).
* Захист: prepared statements, CSRF-токени, honeypot і ліміт заявок з однієї IP, хешування паролів (`password_hash`), файли поза вебдоступом і віддаються лише авторизованому адміністратору.

---

## Встановлення / Installation

### 1. Файли

Завантажте вміст цієї папки на сервер, наприклад у `public_html/requests/` (портал працює як у підпапці, так і на окремому домені/субдомені).

Upload the contents of this folder to the server, e.g. to `public_html/requests/`.

### 2. База даних

Створіть базу (або скористайтеся наявною) та користувача MySQL з правами на цю базу.

```sql
CREATE DATABASE request_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'request_portal'@'localhost' IDENTIFIED BY 'СКЛАДНИЙ_ПАРОЛЬ';
GRANT ALL PRIVILEGES ON request_portal.* TO 'request_portal'@'localhost';
FLUSH PRIVILEGES;
```

### 3. Конфігурація

```bash
cp config.sample.php config.php
```

У `config.php` заповніть блок `db` (host, name, user, pass). За бажанням змініть `app.name`, `app.site_url` (посилання на основний сайт), `app.default_lang`, ліміти в `uploads` і `security.rate_limit_per_hour`.

### 4. Права на теку вкладень

```bash
mkdir -p storage/uploads
chmod 755 storage storage/uploads
```

На деяких хостингах потрібен `775` або власник `www-data` — залежить від конфігурації.

### 5. Запуск встановлення

Відкрийте в браузері `https://ваш-сайт/requests/install.php`:

* сторінка перевірить версію PHP, розширення (`pdo_mysql`, `fileinfo`, `mbstring`), права на теку та ліміти завантаження;
* створить таблиці `rp_requests`, `rp_request_files`, `rp_request_history`, `rp_admin_users`;
* створить першого адміністратора (логін + пароль ≥ 10 символів).

**Після успішного встановлення видаліть `install.php` з сервера.**

Альтернатива без браузера: імпортувати `sql/schema.sql` і створити адміністратора з командного рядка:

```bash
php tools/admin_user.php admin 'СКЛАДНИЙ_ПАРОЛЬ'
```

(цей самий скрипт скидає пароль наявного адміністратора).

### 6. Готово

* Форма заявок: `https://ваш-сайт/requests/`
* Панель адміністратора: `https://ваш-сайт/requests/admin/`

---

## Налаштування сервера / Server notes

### Apache

`.htaccess` у корені та в теках `includes/`, `sql/`, `storage/` уже налаштовані (заборона доступу до коду, конфігу та вкладень). Потрібен `AllowOverride All` для теки порталу.

Ліміти завантаження (200 МБ на файл) задані у `.htaccess` для `mod_php` і в `.user.ini` для PHP-FPM/CGI. Обидва також вимикають показ попереджень PHP, щоб перевищення ліміту не світило технічним `Warning:` на сторінці.

### Nginx

`.htaccess` не діє — додайте до server-блоку:

```nginx
client_max_body_size 220m;

location ~* ^/requests/(includes|storage|sql|tools)/ { deny all; return 404; }
location ~* ^/requests/(config\.php|config\.sample\.php|\.user\.ini) { deny all; return 404; }
```

І збільште у `php.ini` (або пулі FPM): `upload_max_filesize = 200M`, `post_max_size = 220M`, `max_execution_time = 300`.

---

## Структура / Structure

```
index.php                 публічна форма + обробка відправки
install.php               одноразовий установник (видалити після встановлення)
config.sample.php         зразок конфігурації → скопіювати в config.php
admin/
  login.php  logout.php   вхід / вихід адміністратора
  index.php               список заявок: фільтри, пошук, пагінація
  view.php                картка заявки: деталі, статус, примітка, історія
  board.php               канбан заявок
  board-move.php          зміна статусу з канбану
  plan.php                контент-план: календар анонсів
  plan-edit.php           створення / редагування анонсу
  download.php            віддача вкладень (лише для адміністратора)
includes/
  bootstrap.php           конфіг, сесія, підключення хелперів
  db.php                  PDO, DDL таблиць, запити, лог історії
  uploads.php             валідація та збереження вкладень
  auth.php                автентифікація адміністратора
  helpers.php i18n.php    хелпери, переклади
  layout.php              шапка / підвал
  lang/uk.php lang/en.php рядки інтерфейсу
sql/schema.sql            схема БД для ручного імпорту
storage/uploads/          вкладення (поза вебдоступом)
tools/admin_user.php      CLI: створити адміністратора / скинути пароль
```

## Таблиці / Tables

| Таблиця | Призначення |
| --- | --- |
| `rp_requests` | заявки: тип, місце, опис, коментар, заявник, статус, примітка, IP, дати |
| `rp_request_files` | вкладення: оригінальна назва, шлях у `storage/uploads`, MIME, розмір |
| `rp_request_history` | історія: створення, файли, зміни статусу з коментарем, правки примітки |
| `rp_admin_users` | адміністратори: логін і хеш пароля |
| `rp_content_items` | анонси контент-плану: подія, дата, підрозділ, канали, статус |

## Обслуговування / Maintenance

* Бекап: дамп бази + тека `storage/uploads`.
* Вкладення видаляються з БД каскадно разом із заявкою (`ON DELETE CASCADE`), файли на диску — вручну.
* Для діагностики встановіть `app.debug => true` у `config.php`, після перевірки обов'язково поверніть `false`.
