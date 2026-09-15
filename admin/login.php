<?php
/**
 * Administrator login.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/auth.php';
require dirname(__DIR__) . '/includes/layout.php';

if (rp_admin_user() !== null) {
    rp_redirect('admin/index.php');
}

$error    = '';
$username = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $username = rp_clean_string($_POST['username'] ?? '', 80);
    $password = is_string($_POST['password'] ?? null) ? (string) $_POST['password'] : '';
    $locked   = rp_login_lock_remaining();

    if ($locked > 0) {
        $error = __('admin.login_throttled', $locked);
    } elseif (!rp_csrf_valid($_POST['csrf_token'] ?? null)) {
        $error = __('error.csrf');
    } elseif ($username === '' || $password === '') {
        $error = __('admin.login_failed');
    } elseif (rp_attempt_login($username, $password)) {
        rp_redirect('admin/index.php');
    } else {
        $error = __('admin.login_failed');
    }
}

rp_header(__('admin.login_title'), 'admin');
?>
<div class="card login-card">
    <h1><?= e(__('admin.login_title')) ?></h1>

    <?php if ($error !== ''): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= e(rp_url('admin/login.php')) ?>">
        <?= rp_csrf_field() ?>
        <div class="field">
            <label for="username"><?= e(__('admin.username')) ?></label>
            <input type="text" id="username" name="username" maxlength="80" autocomplete="username"
                   value="<?= e($username) ?>" required>
        </div>
        <div class="field">
            <label for="password"><?= e(__('admin.password')) ?></label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn btn-primary"><?= e(__('admin.login')) ?></button>
    </form>
</div>
<?php rp_footer();
