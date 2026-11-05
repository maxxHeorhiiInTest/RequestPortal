<?php

declare(strict_types=1);

/**
 * Normalise $_FILES['attachments'] into a flat list, dropping empty slots.
 *
 * @return list<array{name:string,tmp_name:string,size:int,error:int}>
 */
function rp_collect_uploads(string $field = 'attachments'): array
{
    if (empty($_FILES[$field]) || !is_array($_FILES[$field]['name'])) {
        return [];
    }

    $raw   = $_FILES[$field];
    $files = [];

    foreach ($raw['name'] as $index => $name) {
        $error = (int) ($raw['error'][$index] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE || ($name === '' && $error === UPLOAD_ERR_OK)) {
            continue;
        }

        $files[] = [
            'name'     => basename((string) $name),
            'tmp_name' => (string) ($raw['tmp_name'][$index] ?? ''),
            'size'     => (int) ($raw['size'][$index] ?? 0),
            'error'    => $error,
        ];
    }

    return $files;
}

function rp_file_extension(string $name): string
{
    return strtolower(pathinfo($name, PATHINFO_EXTENSION));
}

/**
 * Validate uploaded files against the configured limits.
 *
 * @param list<array{name:string,tmp_name:string,size:int,error:int}> $files
 * @return list<string> translated error messages
 */
function rp_validate_uploads(array $files): array
{
    $errors    = [];
    $maxFiles  = (int) rp_config('uploads.max_files', 10);
    $maxSize   = rp_effective_file_limit();
    /** @var array<string,list<string>> $allowed */
    $allowed   = (array) rp_config('uploads.allowed', []);

    if ($maxFiles > 0 && count($files) > $maxFiles) {
        $errors[] = __('error.too_many_files', $maxFiles);
    }

    $finfo = class_exists('finfo') ? new finfo(FILEINFO_MIME_TYPE) : null;

    foreach ($files as $file) {
        $label = $file['name'] !== '' ? $file['name'] : '?';

        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
            $errors[] = __('error.file_too_large', $label, rp_format_bytes($maxSize));
            continue;
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            $errors[] = __('error.file_upload', $label);
            continue;
        }
        if ($maxSize > 0 && $file['size'] > $maxSize) {
            $errors[] = __('error.file_too_large', $label, rp_format_bytes($maxSize));
            continue;
        }

        $ext = rp_file_extension($file['name']);
        if ($ext === '' || !isset($allowed[$ext])) {
            $errors[] = __('error.file_type', $label);
            continue;
        }

        if ($finfo instanceof finfo) {
            $mime = rp_normalize_mime((string) $finfo->file($file['tmp_name']));
            if ($mime !== '' && !rp_upload_mime_allowed($ext, $mime)) {
                $errors[] = __('error.file_type', $label);
            }
        }
    }

    return $errors;
}

/**
 * Move validated uploads into storage and record them in the database.
 *
 * @param list<array{name:string,tmp_name:string,size:int,error:int}> $files
 * @return list<string> stored relative paths (for cleanup on rollback)
 * @throws RuntimeException when a file cannot be stored
 */
function rp_store_uploads(PDO $pdo, int $requestId, string $publicCode, array $files): array
{
    if (!$files) {
        return [];
    }

    $baseDir   = rp_uploads_dir();
    $subDir    = date('Y/m');
    $targetDir = $baseDir . '/' . $subDir;

    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Cannot create upload directory: ' . $targetDir);
    }

    $finfo  = class_exists('finfo') ? new finfo(FILEINFO_MIME_TYPE) : null;
    $stored = [];

    $insert = $pdo->prepare(
        'INSERT INTO ' . RP_TABLE_FILES . '
            (request_id, original_name, stored_path, mime_type, size_bytes, created_at)
         VALUES (:request_id, :original_name, :stored_path, :mime_type, :size_bytes, NOW())'
    );

    foreach ($files as $file) {
        $ext        = rp_file_extension($file['name']);
        $storedName = $publicCode . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
        $relPath    = $subDir . '/' . $storedName;
        $absPath    = $baseDir . '/' . $relPath;

        if (!move_uploaded_file($file['tmp_name'], $absPath)) {
            throw new RuntimeException('Cannot move uploaded file to ' . $absPath);
        }
        @chmod($absPath, 0644);
        $stored[] = $relPath;

        $mime = $finfo instanceof finfo ? (string) $finfo->file($absPath) : 'application/octet-stream';

        $insert->execute([
            'request_id'    => $requestId,
            'original_name' => mb_substr($file['name'], 0, 255, 'UTF-8'),
            'stored_path'   => $relPath,
            'mime_type'     => $mime !== '' ? $mime : 'application/octet-stream',
            'size_bytes'    => filesize($absPath) ?: $file['size'],
        ]);

        rp_log_history(
            $pdo,
            $requestId,
            'file_added',
            null,
            mb_substr($file['name'], 0, 255, 'UTF-8'),
            null,
            __('history.visitor')
        );
    }

    return $stored;
}

/**
 * Store uploads for a question / suggestion (no request-history side effects).
 *
 * @param list<array{name:string,tmp_name:string,size:int,error:int}> $files
 * @return list<string>
 */
function rp_store_feedback_uploads(PDO $pdo, int $feedbackId, string $publicCode, array $files): array
{
    if (!$files) {
        return [];
    }

    $baseDir   = rp_uploads_dir();
    $subDir    = date('Y/m');
    $targetDir = $baseDir . '/' . $subDir;

    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Cannot create upload directory: ' . $targetDir);
    }

    $finfo  = class_exists('finfo') ? new finfo(FILEINFO_MIME_TYPE) : null;
    $stored = [];
    $insert = $pdo->prepare(
        'INSERT INTO ' . RP_TABLE_FEEDBACK_FILES . '
            (feedback_id, original_name, stored_path, mime_type, size_bytes, created_at)
         VALUES (:feedback_id, :original_name, :stored_path, :mime_type, :size_bytes, NOW())'
    );

    foreach ($files as $file) {
        $ext        = rp_file_extension($file['name']);
        $storedName = $publicCode . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
        $relPath    = $subDir . '/' . $storedName;
        $absPath    = $baseDir . '/' . $relPath;

        if (!move_uploaded_file($file['tmp_name'], $absPath)) {
            throw new RuntimeException('Cannot move uploaded file to ' . $absPath);
        }
        @chmod($absPath, 0644);
        $stored[] = $relPath;

        $mime = $finfo instanceof finfo ? (string) $finfo->file($absPath) : 'application/octet-stream';
        $insert->execute([
            'feedback_id'   => $feedbackId,
            'original_name' => mb_substr($file['name'], 0, 255, 'UTF-8'),
            'stored_path'   => $relPath,
            'mime_type'     => $mime !== '' ? $mime : 'application/octet-stream',
            'size_bytes'    => filesize($absPath) ?: $file['size'],
        ]);

        rp_log_feedback_history(
            $pdo,
            $feedbackId,
            'file_added',
            null,
            mb_substr($file['name'], 0, 255, 'UTF-8'),
            null,
            __('history.visitor')
        );
    }

    return $stored;
}

/**
 * Store uploads for a content-plan announcement (no request-history side effects).
 *
 * @param list<array{name:string,tmp_name:string,size:int,error:int}> $files
 * @return list<string>
 */
function rp_store_content_uploads(PDO $pdo, int $contentId, string $codePrefix, array $files): array
{
    if (!$files) {
        return [];
    }

    $baseDir   = rp_uploads_dir();
    $subDir    = date('Y/m');
    $targetDir = $baseDir . '/' . $subDir;

    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Cannot create upload directory: ' . $targetDir);
    }

    $finfo  = class_exists('finfo') ? new finfo(FILEINFO_MIME_TYPE) : null;
    $stored = [];
    $insert = $pdo->prepare(
        'INSERT INTO ' . RP_TABLE_CONTENT_FILES . '
            (content_id, original_name, stored_path, mime_type, size_bytes, created_at)
         VALUES (:content_id, :original_name, :stored_path, :mime_type, :size_bytes, NOW())'
    );

    foreach ($files as $file) {
        $ext        = rp_file_extension($file['name']);
        $storedName = $codePrefix . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
        $relPath    = $subDir . '/' . $storedName;
        $absPath    = $baseDir . '/' . $relPath;

        if (!move_uploaded_file($file['tmp_name'], $absPath)) {
            throw new RuntimeException('Cannot move uploaded file to ' . $absPath);
        }
        @chmod($absPath, 0644);
        $stored[] = $relPath;

        $mime = $finfo instanceof finfo ? (string) $finfo->file($absPath) : 'application/octet-stream';
        $insert->execute([
            'content_id'    => $contentId,
            'original_name' => mb_substr($file['name'], 0, 255, 'UTF-8'),
            'stored_path'   => $relPath,
            'mime_type'     => $mime !== '' ? $mime : 'application/octet-stream',
            'size_bytes'    => filesize($absPath) ?: $file['size'],
        ]);
    }

    return $stored;
}

/** MIME type without a charset suffix, lowercased. */
function rp_normalize_mime(string $mime): string
{
    $mime = strtolower(trim($mime));
    if ($mime === '') {
        return '';
    }
    $semi = strpos($mime, ';');

    return $semi === false ? $mime : trim(substr($mime, 0, $semi));
}

/**
 * Extra MIME types that real office files often have, beyond config.sample.php.
 *
 * @return list<string>
 */
function rp_upload_extra_mimes(string $ext): array
{
    $ole = [
        'application/vnd.ms-office',
        'application/x-ole-storage',
        'application/cdfv2',
        'application/cdfv2-encrypted',
        'application/cdfv2-unknown',
        'application/octet-stream',
    ];
    $ooxml = [
        'application/zip',
        'application/x-zip',
        'application/x-zip-compressed',
        'application/octet-stream',
    ];

    return match ($ext) {
        'xls' => array_merge($ole, [
            'application/vnd.ms-excel',
            'application/msexcel',
            'application/x-msexcel',
            'application/x-ms-excel',
            'application/x-excel',
            'application/excel',
            'application/xls',
            'application/x-xls',
        ]),
        'xlsx' => array_merge($ooxml, [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel.sheet.macroenabled.12',
            'application/vnd.ms-excel.sheet.binary.macroenabled.12',
            'application/haansofthxlsx',
            'application/wps-office.xlsx',
        ]),
        'doc' => array_merge($ole, [
            'application/msword',
        ]),
        'docx' => array_merge($ooxml, [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]),
        'ppt' => array_merge($ole, [
            'application/vnd.ms-powerpoint',
        ]),
        'pptx' => array_merge($ooxml, [
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ]),
        default => [],
    };
}

function rp_upload_mime_allowed(string $ext, string $mime): bool
{
    $mime = rp_normalize_mime($mime);
    if ($mime === '') {
        return true;
    }

    /** @var array<string,list<string>> $allowed */
    $allowed = (array) rp_config('uploads.allowed', []);
    $configured = array_map('strtolower', (array) ($allowed[$ext] ?? []));
    if (in_array($mime, $configured, true)) {
        return true;
    }

    return in_array($mime, rp_upload_extra_mimes($ext), true);
}

function rp_file_kind(string $name): string
{
    return match (rp_file_extension($name)) {
        'xls', 'xlsx' => 'excel',
        'doc', 'docx' => 'word',
        'ppt', 'pptx' => 'powerpoint',
        'pdf' => 'pdf',
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'heic' => 'image',
        'mp4', 'mov', 'avi', 'mkv', 'webm' => 'video',
        'txt', 'csv' => 'text',
        'zip' => 'archive',
        default => 'file',
    };
}

function rp_file_kind_label(string $name): string
{
    return __('file.kind.' . rp_file_kind($name));
}

function rp_download_content_type(string $originalName, string $storedMime = ''): string
{
    $byExt = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'bmp'  => 'image/bmp',
        'heic' => 'image/heic',
        'mp4'  => 'video/mp4',
        'mov'  => 'video/quicktime',
        'avi'  => 'video/x-msvideo',
        'mkv'  => 'video/x-matroska',
        'webm' => 'video/webm',
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt'  => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'txt'  => 'text/plain',
        'csv'  => 'text/csv',
        'zip'  => 'application/zip',
    ];
    $ext = rp_file_extension($originalName);
    if (isset($byExt[$ext])) {
        return $byExt[$ext];
    }
    $stored = rp_normalize_mime($storedMime);

    return $stored !== '' ? $stored : 'application/octet-stream';
}

/** @param array<string,mixed> $file */
function rp_admin_file_li(array $file, string $downloadUrl): void
{
    $name   = (string) $file['original_name'];
    $kind   = rp_file_kind($name);
    $exists = rp_resolve_stored_file((string) $file['stored_path']) !== null;

    echo '<li>';
    echo '<span class="file-kind file-kind-' . e($kind) . '">' . e(rp_file_kind_label($name)) . '</span> ';
    if ($exists) {
        echo '<a href="' . e($downloadUrl) . '">' . e($name) . '</a>';
    } else {
        echo e($name);
        echo ' <span class="badge status-rejected">' . e(__('admin.file_missing')) . '</span>';
    }
    echo '<span class="meta">';
    echo e(rp_format_bytes((int) $file['size_bytes']));
    if (!empty($file['created_at'])) {
        echo ' · ' . e(rp_format_datetime((string) $file['created_at']));
    }
    echo '</span></li>';
}

function rp_send_stored_download(string $absPath, string $originalName, string $storedMime = ''): never
{
    $downloadName = preg_replace('/[^\p{L}\p{N}\.\-_ ]+/u', '_', $originalName) ?: 'attachment';

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: ' . rp_download_content_type($originalName, $storedMime));
    header('Content-Length: ' . (string) filesize($absPath));
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadName) . '"; '
        . "filename*=UTF-8''" . rawurlencode($originalName));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    readfile($absPath);
    exit;
}

/** Absolute path of the uploads directory, without a trailing slash. */
function rp_uploads_dir(): string
{
    $dir = (string) rp_config('uploads.dir', RP_ROOT . '/storage/uploads');

    return rtrim($dir, '/\\');
}

/**
 * Resolve a stored relative path to an absolute path inside the uploads directory.
 * Returns null when the path escapes the uploads directory or does not exist.
 */
function rp_resolve_stored_file(string $relPath): ?string
{
    if ($relPath === '' || str_contains($relPath, "\0")) {
        return null;
    }

    $baseDir = realpath(rp_uploads_dir());
    if ($baseDir === false) {
        return null;
    }

    $absPath = realpath($baseDir . '/' . ltrim($relPath, '/\\'));
    if ($absPath === false || !is_file($absPath)) {
        return null;
    }

    return str_starts_with($absPath, $baseDir . DIRECTORY_SEPARATOR) ? $absPath : null;
}

/** @param list<string> $relPaths */
function rp_delete_stored_files(array $relPaths): void
{
    foreach ($relPaths as $relPath) {
        $absPath = rp_resolve_stored_file($relPath);
        if ($absPath !== null) {
            @unlink($absPath);
        }
    }
}
