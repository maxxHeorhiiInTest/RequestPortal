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
    <link rel="stylesheet" href="<?= e(rp_url('assets/style.css')) ?>?v=8">
</head>
<body class="<?= e($context) ?><?= in_array($script, ['board.php', 'plan.php', 'feedback-board.php'], true) ? ' board-page' : '' ?>">
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
    <?php if ($context !== 'admin'): ?>
        <nav class="admin-tabs" aria-label="<?= e(__('nav.new_request')) ?>">
            <a class="admin-tab<?= $script === 'index.php' ? ' active' : '' ?>"
               href="<?= e(rp_url('index.php')) ?>"><?= e(__('public.tab.requests')) ?></a>
            <a class="admin-tab<?= $script === 'feedback.php' ? ' active' : '' ?>"
               href="<?= e(rp_url('feedback.php')) ?>"><?= e(__('public.tab.feedback')) ?></a>
        </nav>
    <?php endif; ?>
    <?php if ($context === 'admin' && $admin !== null): ?>
        <?php
        $isRequests = in_array($script, ['index.php', 'view.php', 'board.php'], true);
        $isPlan     = in_array($script, ['plan.php', 'plan-edit.php'], true);
        $isFeedback = in_array($script, ['feedback.php', 'feedback-view.php', 'feedback-board.php'], true);
        ?>
        <nav class="admin-tabs" aria-label="<?= e(__('nav.admin')) ?>">
            <a class="admin-tab<?= $isRequests ? ' active' : '' ?>"
               href="<?= e(rp_url('admin/index.php')) ?>"><?= e(__('admin.section.requests')) ?></a>
            <a class="admin-tab<?= $isPlan ? ' active' : '' ?>"
               href="<?= e(rp_url('admin/plan.php')) ?>"><?= e(__('admin.section.plan')) ?></a>
            <a class="admin-tab<?= $isFeedback ? ' active' : '' ?>"
               href="<?= e(rp_url('admin/feedback.php')) ?>"><?= e(__('admin.section.feedback')) ?></a>
        </nav>
        <?php if ($isRequests): ?>
            <nav class="admin-subtabs">
                <a class="admin-tab<?= in_array($script, ['index.php', 'view.php'], true) ? ' active' : '' ?>"
                   href="<?= e(rp_url('admin/index.php')) ?>"><?= e(__('admin.list_title')) ?></a>
                <a class="admin-tab<?= $script === 'board.php' ? ' active' : '' ?>"
                   href="<?= e(rp_url('admin/board.php')) ?>"><?= e(__('admin.board_title')) ?></a>
            </nav>
        <?php elseif ($isPlan): ?>
            <nav class="admin-subtabs">
                <a class="admin-tab<?= $script === 'plan.php' ? ' active' : '' ?>"
                   href="<?= e(rp_url('admin/plan.php')) ?>"><?= e(__('content.calendar')) ?></a>
                <a class="admin-tab<?= $script === 'plan-edit.php' && empty($_GET['id']) ? ' active' : '' ?>"
                   href="<?= e(rp_url('admin/plan-edit.php')) ?>"><?= e(__('content.new')) ?></a>
            </nav>
        <?php elseif ($isFeedback): ?>
            <nav class="admin-subtabs">
                <a class="admin-tab<?= in_array($script, ['feedback.php', 'feedback-view.php'], true) ? ' active' : '' ?>"
                   href="<?= e(rp_url('admin/feedback.php')) ?>"><?= e(__('admin.list_title')) ?></a>
                <a class="admin-tab<?= $script === 'feedback-board.php' ? ' active' : '' ?>"
                   href="<?= e(rp_url('admin/feedback-board.php')) ?>"><?= e(__('admin.board_title')) ?></a>
            </nav>
        <?php endif; ?>
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
<script>
(function () {
    function closeTip(tip) {
        var box = tip.querySelector('.page-tip-box');
        var button = tip.querySelector('.page-tip-btn');
        if (!box || !button) {
            return;
        }
        box.hidden = true;
        button.setAttribute('aria-expanded', 'false');
        tip.classList.remove('open');
        var video = tip.querySelector('video');
        if (video) {
            video.pause();
        }
    }

    function toggleTip(tip) {
        var box = tip.querySelector('.page-tip-box');
        var button = tip.querySelector('.page-tip-btn');
        if (!box || !button) {
            return;
        }
        var open = box.hidden;
        document.querySelectorAll('.page-tip').forEach(function (other) {
            if (other !== tip) {
                closeTip(other);
            }
        });
        box.hidden = !open;
        button.setAttribute('aria-expanded', open ? 'true' : 'false');
        tip.classList.toggle('open', open);
        var video = tip.querySelector('video');
        if (video) {
            if (open) {
                try {
                    video.currentTime = 0;
                    video.play();
                } catch (err) {}
            } else {
                video.pause();
            }
        }
    }

    document.addEventListener('click', function (event) {
        var tip = event.target.closest ? event.target.closest('.page-tip') : null;
        var closeBtn = event.target.closest ? event.target.closest('.page-tip-close') : null;
        var button = event.target.closest ? event.target.closest('.page-tip-btn') : null;
        if (closeBtn && tip) {
            event.preventDefault();
            closeTip(tip);
            return;
        }
        if (button && tip) {
            event.preventDefault();
            toggleTip(tip);
            return;
        }
        document.querySelectorAll('.page-tip.open').forEach(function (openTip) {
            if (!openTip.contains(event.target)) {
                closeTip(openTip);
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }
        document.querySelectorAll('.page-tip.open').forEach(closeTip);
    });
})();
</script>
</body>
</html>
    <?php
}

/** Clickable help control that opens the current public page description. */
function rp_page_tip(string $bodyKey, ?string $videoPath = null, ?string $posterPath = null): void
{
    $paragraphs = preg_split("/\n{2,}/", trim((string) __($bodyKey))) ?: [];
    ?>
    <div class="page-tip">
        <button type="button" class="page-tip-btn" aria-expanded="false" aria-controls="page-tip-box">
            <span class="page-tip-mark" aria-hidden="true">?</span>
            <span><?= e(__('page.tip.label')) ?></span>
        </button>
        <div class="page-tip-box" id="page-tip-box" hidden>
            <div class="page-tip-scroll">
            <strong><?= e(__('page.tip.label')) ?></strong>
            <?php if ($videoPath): ?>
                <div class="page-tip-video-wrap">
                    <video class="page-tip-video" controls playsinline muted preload="metadata"
                           poster="<?= $posterPath ? e(rp_url($posterPath)) : '' ?>">
                        <source src="<?= e(rp_url($videoPath)) ?>" type="video/mp4">
                    </video>
                    <p class="page-tip-video-cap"><?= e(__('page.tip.video')) ?></p>
                </div>
            <?php endif; ?>
            <?php foreach ($paragraphs as $block): ?>
                <?php
                $block = trim($block);
                if ($block === '') {
                    continue;
                }
                if (str_starts_with($block, '## ')) {
                    echo '<h3>' . e(substr($block, 3)) . '</h3>';
                    continue;
                }
                $lines    = preg_split("/\n/", $block) ?: [];
                $steps    = [];
                $allSteps = $lines !== [];
                foreach ($lines as $line) {
                    if (!preg_match('/^\d+\.\s+(.+)$/u', trim($line), $match)) {
                        $allSteps = false;
                        break;
                    }
                    $steps[] = $match[1];
                }
                if ($allSteps && $steps !== []) {
                    echo '<ol>';
                    foreach ($steps as $step) {
                        echo '<li>' . e($step) . '</li>';
                    }
                    echo '</ol>';
                    continue;
                }
                ?>
                <p><?= nl2br(e($block), false) ?></p>
            <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-primary page-tip-close"><?= e(__('page.tip.close')) ?></button>
        </div>
    </div>
    <?php
}
