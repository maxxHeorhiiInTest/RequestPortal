<?php
/**
 * Public landing: NUFVSU site under construction.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$lang = rp_lang();

header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e(__('home.doc_title')) ?></title>
    <link rel="icon" type="image/png" href="<?= e(rp_url('assets/logo-nufvsu.png')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(rp_url('assets/home.css')) ?>?v=7">
</head>
<body class="home">
    <div class="home-stage">
        <div class="home-photo" aria-hidden="true"></div>

        <header class="home-wrap home-top">
            <a class="home-brand" href="<?= e(rp_url('index.php')) ?>">
                <img src="<?= e(rp_url('assets/logo-nufvsu.png')) ?>" alt="<?= e(__('home.logo_alt')) ?>">
            </a>
            <nav class="home-nav" aria-label="<?= e(__('nav.home')) ?>">
                <a href="<?= e(rp_url('addData.php')) ?>"><?= e(__('home.nav.request')) ?></a>
                <a href="<?= e(rp_url('planAdd.php')) ?>"><?= e(__('home.nav.plan')) ?></a>
                <a href="<?= e(rp_url('feedback.php')) ?>"><?= e(__('home.nav.feedback')) ?></a>
                <a href="<?= e(rp_url('itAdd.php')) ?>"><?= e(__('home.nav.it')) ?></a>
            </nav>
            <div class="home-tools">
                <nav class="home-langs" aria-label="Language">
                    <?php foreach (RP_LANGUAGES as $code): ?>
                        <a class="<?= $code === $lang ? 'active' : '' ?>"
                           href="<?= e(rp_lang_switch_url($code)) ?>"><?= e(strtoupper($code)) ?></a>
                    <?php endforeach; ?>
                </nav>
               
            </div>
        </header>

        <main class="home-wrap home-main">
            <section class="home-hero">
                <p class="home-kicker"><?= e(__('home.banner')) ?></p>
                <h1>
                    <?= e(__('home.title.line1')) ?><br>
                    <?= e(__('home.title.line2')) ?><br>
                    <span class="home-title-accent"><?= e(__('home.title.accent')) ?></span>
                </h1>
                <p class="home-lead"><?= e(__('home.lead')) ?></p>
            </section>

            <section class="home-cards">
                <a class="home-card" href="<?= e(rp_url('addData.php')) ?>">
                    <span class="home-card-copy">
                        <strong><?= e(__('home.card.request.title')) ?></strong>
                        <small><?= e(__('home.card.request.text')) ?></small>
                    </span>
                    <span class="home-card-go" aria-hidden="true">→</span>
                </a>
                <a class="home-card" href="<?= e(rp_url('planAdd.php')) ?>">
                    <span class="home-card-copy">
                        <strong><?= e(__('home.card.plan.title')) ?></strong>
                        <small><?= e(__('home.card.plan.text')) ?></small>
                    </span>
                    <span class="home-card-go" aria-hidden="true">→</span>
                </a>
                <a class="home-card" href="<?= e(rp_url('feedback.php')) ?>">
                    <span class="home-card-copy">
                        <strong><?= e(__('home.card.feedback.title')) ?></strong>
                        <small><?= e(__('home.card.feedback.text')) ?></small>
                    </span>
                    <span class="home-card-go" aria-hidden="true">→</span>
                </a>
                <a class="home-card" href="<?= e(rp_url('itAdd.php')) ?>">
                    <span class="home-card-copy">
                        <strong><?= e(__('home.card.it.title')) ?></strong>
                        <small><?= e(__('home.card.it.text')) ?></small>
                    </span>
                    <span class="home-card-go" aria-hidden="true">→</span>
                </a>
            </section>
        </main>

        <footer class="home-wrap home-foot">
            <span><?= e(__('home.footer')) ?></span>
            <a href="<?= e(rp_url('admin/login.php')) ?>"><?= e(__('nav.admin')) ?></a>
        </footer>
    </div>
</body>
</html>
