<?php
/**
 * Encrypt / decrypt config.php for storing production credentials in git.
 *
 * The ciphertext (config.enc) is committed. The passphrase is not:
 * keep it in .config-pass (gitignored) or in RP_CONFIG_PASSPHRASE.
 *
 *   php tools/config_crypt.php encrypt
 *   php tools/config_crypt.php decrypt
 *   php tools/config_crypt.php encrypt --in /path/to/config.php
 *   php tools/config_crypt.php decrypt --force
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script runs from the command line only.\n");
}

if (!extension_loaded('openssl')) {
    fwrite(STDERR, "The openssl PHP extension is required.\n");
    exit(1);
}

const RP_ENC_MAGIC = 'RPENC1';
const RP_ENC_CIPHER = 'aes-256-gcm';
const RP_ENC_ITERATIONS = 600000;
const RP_ENC_SALT_LEN = 16;
const RP_ENC_NONCE_LEN = 12;
const RP_ENC_KEY_LEN = 32;

$root = dirname(__DIR__);
$action = $argv[1] ?? '';
$opts = rp_crypt_parse_opts(array_slice($argv, 2));

$inDefault = $action === 'decrypt' ? $root . '/config.enc' : $root . '/config.php';
$outDefault = $action === 'decrypt' ? $root . '/config.php' : $root . '/config.enc';
$inFile = $opts['in'] ?? $inDefault;
$outFile = $opts['out'] ?? $outDefault;

if ($action !== 'encrypt' && $action !== 'decrypt') {
    fwrite(STDERR, "Usage:\n"
        . "  php tools/config_crypt.php encrypt [--in FILE] [--out FILE]\n"
        . "  php tools/config_crypt.php decrypt [--in FILE] [--out FILE] [--force]\n"
        . "\n"
        . "Passphrase (first match):\n"
        . "  --pass FILE                 passphrase file\n"
        . "  RP_CONFIG_PASSPHRASE        environment variable\n"
        . "  .config-pass                in the project root (gitignored)\n");
    exit(1);
}

if (!is_file($inFile)) {
    fwrite(STDERR, "Input not found: {$inFile}\n");
    exit(1);
}

if (is_file($outFile) && empty($opts['force'])) {
    fwrite(STDERR, "Refusing to overwrite {$outFile} (pass --force).\n");
    exit(1);
}

$passphrase = rp_crypt_passphrase($root, $opts);
$plaintextOrCipher = (string) file_get_contents($inFile);

try {
    if ($action === 'encrypt') {
        if (!str_contains($plaintextOrCipher, 'return [')) {
            fwrite(STDERR, "Does not look like a PHP config file: {$inFile}\n");
            exit(1);
        }
        $out = rp_crypt_encrypt($plaintextOrCipher, $passphrase);
    } else {
        $out = rp_crypt_decrypt($plaintextOrCipher, $passphrase);
        if (!str_contains($out, 'return [')) {
            fwrite(STDERR, "Decrypted data does not look like config.php. Wrong passphrase?\n");
            exit(1);
        }
    }
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

if (file_put_contents($outFile, $out) === false) {
    fwrite(STDERR, "Could not write {$outFile}\n");
    exit(1);
}
@chmod($outFile, 0600);

echo ($action === 'encrypt' ? 'Encrypted' : 'Decrypted') . ": {$inFile} → {$outFile}\n";
exit(0);

/**
 * @param list<string> $args
 * @return array{in?:string,out?:string,pass?:string,force?:bool}
 */
function rp_crypt_parse_opts(array $args): array
{
    $opts = [];
    $n = count($args);
    for ($i = 0; $i < $n; $i++) {
        $a = $args[$i];
        if ($a === '--force') {
            $opts['force'] = true;
            continue;
        }
        if ($a === '--in' || $a === '--out' || $a === '--pass') {
            $key = substr($a, 2);
            $i++;
            if ($i >= $n) {
                fwrite(STDERR, "Missing value for {$a}\n");
                exit(1);
            }
            $opts[$key] = $args[$i];
            continue;
        }
        fwrite(STDERR, "Unknown option: {$a}\n");
        exit(1);
    }
    return $opts;
}

/**
 * @param array{pass?:string} $opts
 */
function rp_crypt_passphrase(string $root, array $opts): string
{
    $file = $opts['pass'] ?? '';
    if ($file === '' && getenv('RP_CONFIG_PASSPHRASE') !== false && getenv('RP_CONFIG_PASSPHRASE') !== '') {
        return (string) getenv('RP_CONFIG_PASSPHRASE');
    }
    if ($file === '') {
        $file = $root . '/.config-pass';
    }
    if (!is_file($file)) {
        fwrite(STDERR, "Passphrase not found. Create {$root}/.config-pass or set RP_CONFIG_PASSPHRASE.\n");
        exit(1);
    }
    $pass = trim((string) file_get_contents($file));
    if ($pass === '') {
        fwrite(STDERR, "Passphrase file is empty: {$file}\n");
        exit(1);
    }
    return $pass;
}

function rp_crypt_key(string $passphrase, string $salt): string
{
    return hash_pbkdf2('sha256', $passphrase, $salt, RP_ENC_ITERATIONS, RP_ENC_KEY_LEN, true);
}

function rp_crypt_encrypt(string $plaintext, string $passphrase): string
{
    $salt = random_bytes(RP_ENC_SALT_LEN);
    $nonce = random_bytes(RP_ENC_NONCE_LEN);
    $tag = '';
    $cipher = openssl_encrypt(
        $plaintext,
        RP_ENC_CIPHER,
        rp_crypt_key($passphrase, $salt),
        OPENSSL_RAW_DATA,
        $nonce,
        $tag
    );
    if ($cipher === false || $tag === '') {
        throw new RuntimeException('Encryption failed.');
    }

    return RP_ENC_MAGIC . "\n"
        . "# AES-256-GCM / PBKDF2-SHA256 / " . RP_ENC_ITERATIONS . " iterations\n"
        . "# Decrypt: php tools/config_crypt.php decrypt\n"
        . rp_crypt_b64($salt) . "\n"
        . rp_crypt_b64($nonce) . "\n"
        . rp_crypt_b64($tag) . "\n"
        . rp_crypt_b64($cipher) . "\n";
}

function rp_crypt_decrypt(string $payload, string $passphrase): string
{
    $lines = preg_split("/\R/", trim($payload)) ?: [];
    $data = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $data[] = $line;
    }
    if (count($data) < 5 || $data[0] !== RP_ENC_MAGIC) {
        throw new RuntimeException('Not a Request Portal config.enc file.');
    }

    $salt = rp_crypt_unb64($data[1]);
    $nonce = rp_crypt_unb64($data[2]);
    $tag = rp_crypt_unb64($data[3]);
    $cipher = rp_crypt_unb64($data[4]);

    if (strlen($salt) !== RP_ENC_SALT_LEN || strlen($nonce) !== RP_ENC_NONCE_LEN) {
        throw new RuntimeException('config.enc is corrupt.');
    }

    $plain = openssl_decrypt(
        $cipher,
        RP_ENC_CIPHER,
        rp_crypt_key($passphrase, $salt),
        OPENSSL_RAW_DATA,
        $nonce,
        $tag
    );
    if ($plain === false) {
        throw new RuntimeException('Decryption failed. Wrong passphrase or corrupt file.');
    }
    return $plain;
}

function rp_crypt_b64(string $bin): string
{
    $b64 = base64_encode($bin);
    return $b64 !== false ? $b64 : '';
}

function rp_crypt_unb64(string $b64): string
{
    $bin = base64_decode($b64, true);
    if ($bin === false) {
        throw new RuntimeException('config.enc contains invalid base64.');
    }
    return $bin;
}
