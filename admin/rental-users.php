<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/auth.php';
require dirname(__DIR__) . '/includes/layout.php';

$admin = rp_rental_require('users');
$pdo   = rp_db();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && rp_csrf_valid($_POST['csrf_token'] ?? null)) {
    try {
        rp_rental_save_user(
            $pdo,
            rp_clean_string($_POST['username'] ?? '', 80),
            (string) ($_POST['password'] ?? ''),
            rp_clean_string($_POST['role'] ?? '', 20),
            rp_rental_actor($admin)
        );
        rp_flash('success', __('rental.saved'));
    } catch (Throwable $e) {
        rp_flash('error', $e->getMessage());
    }
    rp_redirect('admin/rental-users.php');
}

$users = rp_rental_users($pdo);
rp_header(__('rental.nav.users'), 'admin');
?>
<div class="card">
    <h1><?= e(__('rental.nav.users')) ?></h1>
    <ul>
        <?php foreach ($users as $user): ?>
            <li><?= e((string) $user['username']) ?> · <?= e(__('admin.role.' . $user['role'])) ?></li>
        <?php endforeach; ?>
    </ul>
    <h2><?= e(__('rental.user.new')) ?></h2>
    <form method="post">
        <?= rp_csrf_field() ?>
        <div class="field">
            <label><?= e(__('admin.username')) ?></label>
            <input name="username" required>
        </div>
        <div class="field">
            <label><?= e(__('rental.password')) ?></label>
            <input type="password" name="password" required minlength="10">
        </div>
        <div class="field">
            <label><?= e(__('rental.role')) ?></label>
            <select name="role">
                <?php foreach (rp_rental_roles() as $role): ?>
                    <option value="<?= e($role) ?>"><?= e(__('admin.role.' . $role)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-primary"><?= e(__('common.save')) ?></button>
    </form>
</div>
<?php rp_footer();