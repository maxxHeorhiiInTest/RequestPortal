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
            $mime = (string) $finfo->file($file['tmp_name']);
            if ($mime !== '' && !in_array($mime, $allowed[$ext], true)) {
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
