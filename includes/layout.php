<?php

declare(strict_types=1);

/**
 * Render the page head, header bar and open the main container.
 *
 * @param 'public'|'admin' $context
 */
function rp_header(string $title, string $context = 'public'): void
{
    $appName  = (string) rp_config('app.name', 'Request Portal');
    $siteUrl  = trim((string) rp_config('app.site_url', ''));
    $lang     = rp_lang();
    $admin    = function_exists('rp_admin_user') ? rp_admin_user() : null;
    $script   = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));

    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');

    ?><!DOCTYPE html>
<html lang="<?= e($lang) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title . ' — ' . $appName) ?></title>
    <link rel="stylesheet" href="<?= e(rp_url('assets/style.css')) ?>?v=3">
</head>
<body class="<?= e($context) ?><?= $script === 'board.php' ? ' board-page' : '' ?>">
<header class="topbar">
    <div class="wrap topbar-inner">
        <div class="brand">
            <a href="<?= e(rp_url('index.php')) ?>"><?= e($appName) ?></a>
            <span class="tagline"><?= e(__('app.tagline')) ?></span>
        </div>
        <nav class="topnav">
            <?php if ($context === 'admin'): ?>
                <?php if ($admin !== null): ?>
                    <a href="<?= e(rp_url('admin/logout.php')) ?>"><?= e(__('nav.logout')) ?> (<?= e((string) $admin['username']) ?>)</a>
                <?php endif; ?>
            <?php else: ?>
                <a href="<?= e(rp_url('index.php')) ?>"><?= e(__('nav.new_request')) ?></a>
                <a href="<?= e(rp_url('admin/index.php')) ?>"><?= e(__('nav.admin')) ?></a>
            <?php endif; ?>
            <?php if ($siteUrl !== ''): ?>
                <a href="<?= e($siteUrl) ?>" target="_blank" rel="noopener"><?= e(__('nav.site')) ?></a>
            <?php endif; ?>
            <span class="langs">
                <?php foreach (RP_LANGUAGES as $code): ?>
                    <a class="lang<?= $code === $lang ? ' active' : '' ?>"
                       href="<?= e(rp_lang_switch_url($code)) ?>"><?= e(strtoupper($code)) ?></a>
                <?php endforeach; ?>
            </span>
        </nav>
    </div>
</header>
<main class="wrap">
    <?php if ($context === 'admin' && $admin !== null): ?>
        <nav class="admin-tabs" aria-label="<?= e(__('nav.admin')) ?>">
            <a class="admin-tab<?= in_array($script, ['index.php', 'view.php'], true) ? ' active' : '' ?>"
               href="<?= e(rp_url('admin/index.php')) ?>"><?= e(__('admin.list_title')) ?></a>
            <a class="admin-tab<?= $script === 'board.php' ? ' active' : '' ?>"
               href="<?= e(rp_url('admin/board.php')) ?>"><?= e(__('admin.board_title')) ?></a>
        </nav>
    <?php endif; ?>
    <?php foreach (rp_take_flashes() as $flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endforeach; ?>
    <?php
}

function rp_footer(): void
{
    ?>
</main>
<footer class="footer wrap">
    <span><?= e(__('common.footer')) ?> · <?= e(date('Y')) ?></span>
</footer>
</body>
</html>
    <?php
}
