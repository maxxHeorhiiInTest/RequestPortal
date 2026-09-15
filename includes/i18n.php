<?php

declare(strict_types=1);

const RP_LANGUAGES = ['uk', 'en'];

/**
 * Resolve the active language from ?lang=, cookie or config, and remember it.
 */
function rp_init_language(): void
{
    $default = in_array((string) rp_config('app.default_lang', 'uk'), RP_LANGUAGES, true)
        ? (string) rp_config('app.default_lang', 'uk')
        : 'uk';

    $lang = $default;

    if (isset($_COOKIE['rp_lang']) && in_array($_COOKIE['rp_lang'], RP_LANGUAGES, true)) {
        $lang = (string) $_COOKIE['rp_lang'];
    }

    if (isset($_GET['lang']) && in_array($_GET['lang'], RP_LANGUAGES, true)) {
        $lang = (string) $_GET['lang'];
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            setcookie('rp_lang', $lang, [
                'expires'  => time() + 31536000,
                'path'     => rp_base_url() !== '' ? rp_base_url() : '/',
                'secure'   => rp_is_https(),
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        }
    }

    $GLOBALS['RP_LANG']         = $lang;
    $GLOBALS['RP_TRANSLATIONS'] = require __DIR__ . '/lang/' . $lang . '.php';

    if ($lang !== 'uk') {
        // Ukrainian doubles as the fallback dictionary for missing keys.
        $GLOBALS['RP_FALLBACK'] = require __DIR__ . '/lang/uk.php';
    } else {
        $GLOBALS['RP_FALLBACK'] = [];
    }
}

function rp_lang(): string
{
    return (string) ($GLOBALS['RP_LANG'] ?? 'uk');
}

/**
 * Translate a key. Extra arguments are passed to sprintf().
 */
function __(string $key, int|float|string ...$args): string
{
    $text = $GLOBALS['RP_TRANSLATIONS'][$key]
        ?? $GLOBALS['RP_FALLBACK'][$key]
        ?? $key;

    return $args ? vsprintf($text, $args) : $text;
}

/** Current URL with ?lang= replaced, used by the language switcher. */
function rp_lang_switch_url(string $lang): string
{
    $query         = $_GET;
    $query['lang'] = $lang;
    $path          = strtok((string) ($_SERVER['REQUEST_URI'] ?? '/'), '?');

    return $path . '?' . http_build_query($query);
}
