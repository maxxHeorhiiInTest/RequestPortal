<?php
/**
 * One-off installer: checks the environment, creates the MySQL tables and the
 * first administrator account.
 *
 * UA: Після встановлення видаліть цей файл з сервера.
 * EN: Delete this file from the server once the installation is done.
 */

declare(strict_types=1);

$configFile = __DIR__ . '/config.php';

if (!is_file($configFile)) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="uk"><meta charset="utf-8"><title>config.php</title>'
        . '<body style="font:15px/1.6 system-ui;max-width:640px;margin:40px auto;padding:0 16px">'
        . '<h1>config.php</h1>'
        . '<p><strong>UA:</strong> Розшифруйте прод-конфіг: <code>php tools/config_crypt.php decrypt</code> '
        . '(потрібен файл <code>.config-pass</code> або змінна <code>RP_CONFIG_PASSPHRASE</code>). '
        . 'Або скопіюйте <code>config.sample.php</code> у <code>config.php</code> і вкажіть дані MySQL.</p>'
        . '<p><strong>EN:</strong> Decrypt the production config: <code>php tools/config_crypt.php decrypt</code> '
        . '(needs <code>.config-pass</code> or <code>RP_CONFIG_PASSPHRASE</code>). '
        . 'Or copy <code>config.sample.php</code> to <code>config.php</code> and fill in MySQL credentials.</p>';
    exit;
}

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/uploads.php';

/** @var list<array{label:string,ok:bool,hint:string}> $checks */
$checks     = [];
$addCheck   = static function (string $label, bool $ok, string $hint = '') use (&$checks): void {
    $checks[] = ['label' => $label, 'ok' => $ok, 'hint' => $hint];
};

$addCheck('PHP >= ' . RP_MIN_PHP . ' (' . PHP_VERSION . ')', version_compare(PHP_VERSION, RP_MIN_PHP, '>='));
$addCheck('PDO MySQL', extension_loaded('pdo_mysql'), 'php.ini: extension=pdo_mysql');
$addCheck('fileinfo', extension_loaded('fileinfo'), 'php.ini: extension=fileinfo');
$addCheck('mbstring', extension_loaded('mbstring'), 'php.ini: extension=mbstring');

$uploadDir = rp_uploads_dir();
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}
$addCheck(
    'Uploads dir: ' . $uploadDir,
    is_dir($uploadDir) && is_writable($uploadDir),
    'chmod 755 storage/uploads'
);
$addCheck(
    'upload_max_filesize / post_max_size: ' . ini_get('upload_max_filesize') . ' / ' . ini_get('post_max_size'),
    rp_php_upload_limit() >= (int) rp_config('uploads.max_file_size', 0),
    'UA: ліміт PHP менший за налаштований у config.php — великі файли не пройдуть. '
        . 'EN: the PHP limit is below the configured one — large files will be rejected.'
);

$dbError = '';
$pdo     = null;
try {
    $pdo = new PDO(
        sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            (string) rp_config('db.host'),
            (int) rp_config('db.port', 3306),
            (string) rp_config('db.name'),
            (string) rp_config('db.charset', 'utf8mb4')
        ),
        (string) rp_config('db.user'),
        (string) rp_config('db.pass'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    rp_apply_db_timezone($pdo);
} catch (PDOException $exception) {
    $dbError = $exception->getMessage();
}
$addCheck('MySQL: ' . rp_config('db.name') . '@' . rp_config('db.host'), $pdo instanceof PDO, $dbError);

$hasBlocking  = $pdo === null || !extension_loaded('pdo_mysql');
$alreadySetUp = $pdo instanceof PDO && rp_tables_exist($pdo) && rp_has_admin_users($pdo);

$messages = [];
$errors   = [];
$done     = false;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !$hasBlocking) {
    $username = rp_clean_string($_POST['username'] ?? '', 80);
    $password = is_string($_POST['password'] ?? null) ? (string) $_POST['password'] : '';
    $confirm  = is_string($_POST['password_confirm'] ?? null) ? (string) $_POST['password_confirm'] : '';

    if (!rp_csrf_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'CSRF token mismatch — reload the page.';
    }
    if ($alreadySetUp) {
        $errors[] = 'UA: Портал уже встановлено. EN: The portal is already installed.';
    }
    if (!preg_match('/^[A-Za-z0-9._-]{3,80}$/', $username)) {
        $errors[] = 'UA: Логін: 3–80 символів (літери, цифри, . _ -). EN: Username: 3–80 chars (letters, digits, . _ -).';
    }
    if (strlen($password) < 10) {
        $errors[] = 'UA: Пароль має містити щонайменше 10 символів. EN: The password must be at least 10 characters long.';
    }
    if ($password !== $confirm) {
        $errors[] = 'UA: Паролі не співпадають. EN: Passwords do not match.';
    }

    if (!$errors) {
        try {
            foreach (rp_schema() as $table => $ddl) {
                $pdo->exec($ddl);
                $messages[] = 'Table ready: ' . $table;
            }

            $stmt = $pdo->prepare(
                'INSERT INTO ' . RP_TABLE_ADMINS . ' (username, password_hash, created_at)
                 VALUES (:username, :hash, NOW())'
            );
            $stmt->execute([
                'username' => $username,
                'hash'     => password_hash($password, PASSWORD_DEFAULT),
            ]);

            $messages[] = 'Administrator created: ' . $username;
            $done       = true;
        } catch (PDOException $exception) {
            $errors[] = 'MySQL: ' . $exception->getMessage();
        }
    }
}

header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Request Portal — install</title>
    <link rel="stylesheet" href="<?= e(rp_url('assets/style.css')) ?>">
</head>
<body>
<main class="wrap" style="padding-top:28px">
    <div class="card">
        <h1>Request Portal — встановлення / installation</h1>

        <h2>1. Перевірка сервера / Environment</h2>
        <ul class="files">
            <?php foreach ($checks as $check): ?>
                <li>
                    <?= $check['ok'] ? '✅' : '⚠️' ?> <?= e($check['label']) ?>
                    <?php if (!$check['ok'] && $check['hint'] !== ''): ?>
                        <span class="meta"><?= e($check['hint']) ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-error">
            <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <?php if ($messages): ?>
        <div class="alert alert-success">
            <ul><?php foreach ($messages as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2>2. Адміністратор / Administrator</h2>

        <?php if ($done): ?>
            <p><strong>UA:</strong> Готово. Видаліть файл <code>install.php</code> з сервера, після чого користуйтеся
                <a href="<?= e(rp_url('index.php')) ?>">головною сторінкою</a>,
                <a href="<?= e(rp_url('addData.php')) ?>">формою заявок</a> та
                <a href="<?= e(rp_url('admin/login.php')) ?>">панеллю адміністратора</a>.</p>
            <p><strong>EN:</strong> Done. Delete <code>install.php</code> from the server, then use the
                <a href="<?= e(rp_url('index.php')) ?>">home page</a>,
                <a href="<?= e(rp_url('addData.php')) ?>">request form</a> and the
                <a href="<?= e(rp_url('admin/login.php')) ?>">admin panel</a>.</p>
        <?php elseif ($alreadySetUp): ?>
            <p><strong>UA:</strong> Портал уже встановлено, адміністратор існує. Видаліть <code>install.php</code>.</p>
            <p><strong>EN:</strong> Already installed, an administrator exists. Delete <code>install.php</code>.</p>
            <p><a class="btn" href="<?= e(rp_url('admin/login.php')) ?>">Admin panel</a></p>
        <?php elseif ($hasBlocking): ?>
            <p><strong>UA:</strong> Спочатку виправте помилки у перевірці вище (підключення до MySQL / розширення PHP).</p>
            <p><strong>EN:</strong> Fix the failed checks above first (MySQL connection / PHP extensions).</p>
        <?php else: ?>
            <p class="muted">
                UA: створюються таблиці та перший адміністратор.<br>
                EN: this creates the tables and the first administrator account.
            </p>
            <form method="post" action="<?= e(rp_url('install.php')) ?>">
                <?= rp_csrf_field() ?>
                <div class="field">
                    <label for="username">Логін / Username</label>
                    <input type="text" id="username" name="username" maxlength="80" required>
                </div>
                <div class="field">
                    <label for="password">Пароль / Password (min 10)</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="field">
                    <label for="password_confirm">Повторіть пароль / Repeat password</label>
                    <input type="password" id="password_confirm" name="password_confirm" required>
                </div>
                <button type="submit" class="btn btn-primary">Встановити / Install</button>
            </form>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
