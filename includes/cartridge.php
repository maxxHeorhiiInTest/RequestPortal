<?php

declare(strict_types=1);

/** Absolute path of the cartridge PDF directory, without a trailing slash. */
function rp_cartridges_dir(): string
{
    $dir = (string) rp_config('cartridges.dir', RP_ROOT . '/storage/cartridges');

    return rtrim($dir, '/\\');
}

function rp_cartridge_status_url(string $code): string
{
    return rp_absolute_url('cartridge.php?code=' . rawurlencode($code));
}

function rp_cartridge_edit_url(int $id): string
{
    return rp_absolute_url('admin/cartridge-view.php?id=' . $id);
}

function rp_cartridge_clean_barcode(string $raw): string
{
    $value = trim(preg_replace('/\s+/u', ' ', $raw) ?? $raw);
    $value = rp_clean_string($value, 80);
    if ($value === '') {
        return '';
    }
    if (preg_match('/[^\x20-\x7E]/', $value) === 1) {
        return '';
    }

    return $value;
}

/** User-entered barcode, or the request number when the field is empty. */
function rp_cartridge_scan_code(array $row): string
{
    $entered = rp_cartridge_clean_barcode((string) ($row['barcode'] ?? ''));

    return $entered !== '' ? $entered : (string) ($row['public_code'] ?? '');
}

/**
 * Code 128-B modules (true = black), or empty if the text cannot be encoded.
 *
 * @return list<bool>
 */
function rp_code128_modules(string $text): array
{
    if ($text === '' || strlen($text) > 80 || preg_match('/[^\x20-\x7E]/', $text) === 1) {
        return [];
    }
    $patterns = [
        '11011001100', '11001101100', '11001100110', '10010011000', '10010001100',
        '10001001100', '10011001000', '10011000100', '10001100100', '11001001000',
        '11001000100', '11000100100', '10110011100', '10011011100', '10011001110',
        '10111001100', '10011101100', '10011100110', '11001110010', '11001011100',
        '11001001110', '11011100100', '11001110100', '11101101110', '11101001100',
        '11100101100', '11100100110', '11101100100', '11100110100', '11100110010',
        '11011011000', '11011000110', '11000110110', '10100011000', '10001011000',
        '10001000110', '10110001000', '10001101000', '10001100010', '11010001000',
        '11000101000', '11000100010', '10110111000', '10110001110', '10001101110',
        '10111011000', '10111000110', '10001110110', '11101110110', '11010001110',
        '11000101110', '11011101000', '11011100010', '11011101110', '11101011000',
        '11101000110', '11100010110', '11101101000', '11101100010', '11100011010',
        '11101111010', '11001000010', '11110001010', '10100110000', '10100001100',
        '10010110000', '10010000110', '10000101100', '10000100110', '10110010000',
        '10110000100', '10011010000', '10011000010', '10000110100', '10000110010',
        '11000010010', '11001010000', '11110111010', '11000010100', '10001111010',
        '10100111100', '10010111100', '10010011110', '10111100100', '10011110100',
        '10011110010', '11110100100', '11110010100', '11110010010', '11011011110',
        '11011110110', '11110110110', '10101111000', '10100011110', '10001011110',
        '10111101000', '10111100010', '11110101000', '11110100010', '10111011110',
        '10111101110', '11101011110', '11110101110', '11010000100', '11010010000',
        '11010011100', '1100011101011',
    ];
    $values = [104];
    $len    = strlen($text);
    for ($i = 0; $i < $len; $i++) {
        $values[] = ord($text[$i]) - 32;
    }
    $sum = 104;
    for ($i = 1, $n = count($values); $i < $n; $i++) {
        $sum += $values[$i] * $i;
    }
    $values[] = $sum % 103;
    $values[] = 106;
    $modules  = [];
    for ($i = 0; $i < 10; $i++) {
        $modules[] = false;
    }
    foreach ($values as $value) {
        $pattern = $patterns[$value] ?? '';
        $count   = strlen($pattern);
        for ($i = 0; $i < $count; $i++) {
            $modules[] = $pattern[$i] === '1';
        }
    }
    for ($i = 0; $i < 10; $i++) {
        $modules[] = false;
    }

    return $modules;
}

function rp_code128_svg(string $text, int $width = 240, int $height = 52): string
{
    $modules = rp_code128_modules($text);
    $n       = count($modules);
    if ($n < 1) {
        return '';
    }
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $n . ' ' . $height . '"'
        . ' width="' . $width . '" height="' . $height . '" shape-rendering="crispEdges" role="img"'
        . ' aria-label="' . e(__('cartridge.barcode_label')) . '">';
    $svg .= '<rect width="' . $n . '" height="' . $height . '" fill="#ffffff"/>';
    foreach ($modules as $x => $on) {
        if ($on) {
            $svg .= '<rect x="' . (int) $x . '" y="0" width="1" height="' . $height . '" fill="#111111"/>';
        }
    }

    return $svg . '</svg>';
}

/** @return list<string> */
function rp_cartridge_comment_preset_ids(): array
{
    return ['stripe', 'not_detected', 'drum', 'faint', 'leak'];
}

function rp_cartridge_comment_preset_label(string $id): string
{
    $key = 'cartridge.comment.' . $id;

    return __($key) === $key ? $id : __($key);
}

/** @return list<string> */
function rp_cartridge_posted_comment_keys(mixed $posted): array
{
    $allowed = rp_cartridge_comment_preset_ids();
    $raw     = is_array($posted) ? $posted : [];
    $keys    = [];
    foreach ($raw as $item) {
        if (!is_string($item) || !in_array($item, $allowed, true) || in_array($item, $keys, true)) {
            continue;
        }
        $keys[] = $item;
    }

    return $keys;
}

/** @param list<string> $keys */
function rp_cartridge_compose_comment(array $keys, string $note): string
{
    $parts = [];
    foreach ($keys as $id) {
        $parts[] = rp_cartridge_comment_preset_label($id);
    }
    $note = trim($note);
    if ($note !== '') {
        $parts[] = $note;
    }

    return rp_clean_string(implode('; ', $parts), 1000);
}

/** @return list<string> */
function rp_cartridge_qr_modules(string $text): array
{
    $lib = RP_ROOT . '/lib/phpqrcode/phpqrcode.php';
    $level = error_reporting();
    error_reporting($level & ~E_DEPRECATED);
    try {
        if (!class_exists('QRcode', false) && is_file($lib)) {
            require_once $lib;
        }
        if (!class_exists('QRcode', false)) {
            return [];
        }
        $rows = QRcode::text($text, false, QR_ECLEVEL_M, 3, 1);
    } finally {
        error_reporting($level);
    }
    if (!is_array($rows)) {
        return [];
    }
    $modules = [];
    foreach ($rows as $row) {
        $line = [];
        $len  = strlen((string) $row);
        for ($i = 0; $i < $len; $i++) {
            $line[] = $row[$i] === '1';
        }
        $modules[] = $line;
    }

    return $modules;
}

function rp_cartridge_qr_svg(string $text, int $size = 168): string
{
    $modules = rp_cartridge_qr_modules($text);
    $n       = count($modules);
    if ($n < 1) {
        return '';
    }
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $n . ' ' . $n . '"'
        . ' width="' . $size . '" height="' . $size . '" shape-rendering="crispEdges" role="img"'
        . ' aria-label="' . e(__('cartridge.qr_label')) . '">';
    $svg .= '<rect width="' . $n . '" height="' . $n . '" fill="#ffffff"/>';
    foreach ($modules as $y => $row) {
        foreach ($row as $x => $on) {
            if ($on) {
                $svg .= '<rect x="' . (int) $x . '" y="' . (int) $y . '" width="1" height="1" fill="#111111"/>';
            }
        }
    }

    return $svg . '</svg>';
}

function rp_resolve_cartridge_pdf(string $relPath): ?string
{
    if ($relPath === '' || str_contains($relPath, "\0")) {
        return null;
    }
    $baseDir = realpath(rp_cartridges_dir());
    if ($baseDir === false) {
        return null;
    }
    $absPath = realpath($baseDir . '/' . ltrim($relPath, '/\\'));
    if ($absPath === false || !is_file($absPath)) {
        return null;
    }

    return str_starts_with($absPath, $baseDir . DIRECTORY_SEPARATOR) ? $absPath : null;
}

/**
 * Build a stored PDF for the request and return the relative path.
 *
 * @param array<string,mixed> $row
 */
function rp_cartridge_write_pdf(array $row): string
{
    $code    = (string) ($row['public_code'] ?? '');
    $created = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) ($row['created_at'] ?? ''));
    $year    = $created ? $created->format('Y') : date('Y');
    $month   = $created ? $created->format('m') : date('m');
    $rel     = $year . '/' . $month . '/' . $code . '.pdf';
    $dir     = rp_cartridges_dir() . '/' . $year . '/' . $month;
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('Cannot create cartridge storage');
    }
    $abs = $dir . '/' . $code . '.pdf';
    rp_cartridge_render_pdf($row, $abs);

    return $rel;
}

/**
 * @param array<string,mixed> $row
 */
function rp_cartridge_render_pdf(array $row, string $absPath): void
{
    $tfpdf = RP_ROOT . '/lib/tfpdf/tFPDF.php';
    $level = error_reporting();
    error_reporting($level & ~E_DEPRECATED);
    try {
    if (!class_exists('tFPDF', false)) {
        if (!defined('FPDF_FONTPATH')) {
            define('FPDF_FONTPATH', RP_ROOT . '/lib/tfpdf/font/');
        }
        require_once $tfpdf;
    }

    $code   = (string) ($row['public_code'] ?? '');
    $status = rp_cartridge_status_label((string) ($row['status'] ?? ''));
    $url    = rp_cartridge_edit_url((int) ($row['id'] ?? 0));
    $date   = rp_format_datetime((string) ($row['created_at'] ?? ''));
    $uni    = __('home.logo_alt');
    $title  = __('cartridge.blank_title');

    $pdf = new tFPDF('P', 'mm', 'A4');
    $pdf->SetAutoPageBreak(false);
    $pdf->AddPage();
    $pdf->AddFont('DejaVu', '', 'DejaVuSans.ttf', true);
    $pdf->AddFont('DejaVu', 'B', 'DejaVuSans-Bold.ttf', true);

    $pdf->SetDrawColor(21, 93, 255);
    $pdf->SetFillColor(5, 11, 47);
    $pdf->Rect(0, 0, 210, 28, 'F');

    $logo = RP_ROOT . '/assets/logo-nufvsu.png';
    if (is_file($logo)) {
        $pdf->Image($logo, 12, 6, 38);
    }
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('DejaVu', 'B', 11);
    $pdf->SetXY(56, 7);
    $pdf->Cell(142, 7, $uni, 0, 2, 'L');
    $pdf->SetFont('DejaVu', '', 10);
    $pdf->Cell(142, 6, $title, 0, 0, 'L');

    $pdf->SetTextColor(17, 17, 17);
    $pdf->SetFont('DejaVu', 'B', 18);
    $pdf->SetXY(16, 38);
    $pdf->Cell(178, 10, $title, 0, 1, 'L');
    $pdf->SetFont('DejaVu', 'B', 16);
    $pdf->SetX(16);
    $pdf->SetTextColor(21, 93, 255);
    $pdf->Cell(178, 9, $code, 0, 1, 'L');
    $pdf->SetTextColor(17, 17, 17);

    $scan = rp_cartridge_scan_code($row);
    $fields = [
        __('cartridge.building')  => (string) ($row['building'] ?? ''),
        __('cartridge.room')      => (string) ($row['room'] ?? ''),
        __('cartridge.printer')   => (string) ($row['printer_model'] ?? ''),
        __('cartridge.cartridge') => (string) ($row['cartridge_model'] ?? ''),
        __('cartridge.barcode')   => $scan,
        __('cartridge.date')      => $date,
        __('cartridge.status')    => $status,
    ];
    $name  = trim((string) ($row['requester_name'] ?? ''));
    $phone = trim((string) ($row['requester_phone'] ?? ''));
    if ($name !== '') {
        $fields[__('cartridge.name')] = $name;
    }
    if ($phone !== '') {
        $fields[__('cartridge.phone')] = $phone;
    }
    $comment = trim((string) ($row['comment'] ?? ''));
    if ($comment !== '') {
        $fields[__('cartridge.comment')] = $comment;
    }

    $y = 58;
    foreach ($fields as $label => $value) {
        $pdf->SetFont('DejaVu', '', 8);
        $pdf->SetTextColor(107, 118, 128);
        $pdf->SetXY(16, $y);
        $pdf->Cell(178, 4, $label, 0, 1, 'L');
        $pdf->SetFont('DejaVu', 'B', 11);
        $pdf->SetTextColor(17, 17, 17);
        $pdf->SetX(16);
        $pdf->MultiCell(178, 5, $value !== '' ? $value : '—', 0, 'L');
        $y = $pdf->GetY() + 2;
    }

    $qrModules = rp_cartridge_qr_modules($url);
    $qrN       = count($qrModules);
    $barModules = rp_code128_modules($scan);
    $barN      = count($barModules);
    $qrSize    = 36.0;
    $barH      = 22.0;
    $barW      = 92.0;
    $y0        = max($y + 6, 210);
    if ($y0 + $qrSize + 16 > 285) {
        $y0 = 285 - $qrSize - 16;
    }
    $qrX = 28.0;
    $barX = 210 - 16 - $barW;
    if ($qrN > 0) {
        $cell = $qrSize / $qrN;
        $pdf->SetFillColor(17, 17, 17);
        $pdf->SetDrawColor(17, 17, 17);
        foreach ($qrModules as $my => $line) {
            foreach ($line as $mx => $on) {
                if ($on) {
                    $pdf->Rect($qrX + $mx * $cell, $y0 + $my * $cell, $cell + 0.05, $cell + 0.05, 'F');
                }
            }
        }
    }
    if ($barN > 0) {
        $cell = $barW / $barN;
        $barY = $y0 + ($qrSize - $barH) / 2;
        $pdf->SetFillColor(17, 17, 17);
        foreach ($barModules as $mx => $on) {
            if ($on) {
                $pdf->Rect($barX + $mx * $cell, $barY, $cell + 0.02, $barH, 'F');
            }
        }
        $pdf->SetFont('DejaVu', 'B', 9);
        $pdf->SetTextColor(17, 17, 17);
        $pdf->SetXY($barX, $barY + $barH + 2);
        $pdf->Cell($barW, 5, $scan, 0, 0, 'C');
    }

    $pdf->SetFont('DejaVu', 'B', 12);
    $pdf->SetTextColor(17, 17, 17);
    $pdf->SetXY(16, $y0 + $qrSize + 3);
    $pdf->Cell(50, 6, $code, 0, 0, 'L');

    $pdf->Output($absPath, 'F');
    } finally {
        error_reporting($level);
    }
}
