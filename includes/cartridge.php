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
    $url    = rp_cartridge_status_url($code);
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

    $fields = [
        __('cartridge.building')  => (string) ($row['building'] ?? ''),
        __('cartridge.room')      => (string) ($row['room'] ?? ''),
        __('cartridge.printer')   => (string) ($row['printer_model'] ?? ''),
        __('cartridge.cartridge') => (string) ($row['cartridge_model'] ?? ''),
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

    $y = 62;
    foreach ($fields as $label => $value) {
        $pdf->SetFont('DejaVu', '', 9);
        $pdf->SetTextColor(107, 118, 128);
        $pdf->SetXY(16, $y);
        $pdf->Cell(178, 5, $label, 0, 1, 'L');
        $pdf->SetFont('DejaVu', 'B', 12);
        $pdf->SetTextColor(17, 17, 17);
        $pdf->SetX(16);
        $pdf->MultiCell(178, 6, $value !== '' ? $value : '—', 0, 'L');
        $y = $pdf->GetY() + 3;
    }

    $modules = rp_cartridge_qr_modules($url);
    $n       = count($modules);
    $qrSize  = 42.0;
    $x0      = (210 - $qrSize) / 2;
    $y0      = min(230, max($y + 8, 200));
    if ($n > 0) {
        $cell = $qrSize / $n;
        $pdf->SetFillColor(17, 17, 17);
        $pdf->SetDrawColor(17, 17, 17);
        foreach ($modules as $my => $line) {
            foreach ($line as $mx => $on) {
                if ($on) {
                    $pdf->Rect($x0 + $mx * $cell, $y0 + $my * $cell, $cell + 0.05, $cell + 0.05, 'F');
                }
            }
        }
    }

    $pdf->SetFont('DejaVu', 'B', 16);
    $pdf->SetXY(16, $y0 + $qrSize + 4);
    $pdf->Cell(178, 8, $code, 0, 1, 'C');
    $pdf->SetFont('DejaVu', '', 8);
    $pdf->SetTextColor(107, 118, 128);
    $pdf->SetX(16);
    $pdf->Cell(178, 5, $url, 0, 1, 'C');

    $pdf->Output($absPath, 'F');
    } finally {
        error_reporting($level);
    }
}
