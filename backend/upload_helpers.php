<?php

function pg_prepare_upload_dir($dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return is_dir($dir) && is_writable($dir);
}

function pg_validate_upload(array $file, array $allowedExtensions, array $allowedMimePrefixes, $maxBytes, &$error = null) {
    $error = null;
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $error = 'Upload failed.';
        return false;
    }
    if (($file['size'] ?? 0) <= 0 || ($file['size'] ?? 0) > $maxBytes) {
        $error = 'File size is invalid.';
        return false;
    }
    $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExtensions, true)) {
        $error = 'File type is not allowed.';
        return false;
    }
    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = (string)finfo_file($finfo, (string)$file['tmp_name']);
            finfo_close($finfo);
        }
    }
    if ($mime !== '') {
        $ok = false;
        foreach ($allowedMimePrefixes as $prefix) {
            if (strpos($mime, $prefix) === 0) {
                $ok = true;
                break;
            }
        }
        if (!$ok) {
            $error = 'File content is not allowed.';
            return false;
        }
    }
    return true;
}

function pg_store_upload(array $file, $targetDir, $prefix, array $allowedExtensions, array $allowedMimePrefixes, $maxBytes, &$error = null) {
    if (!pg_prepare_upload_dir($targetDir)) {
        $error = 'Upload directory is not writable.';
        return null;
    }
    if (!pg_validate_upload($file, $allowedExtensions, $allowedMimePrefixes, $maxBytes, $error)) {
        return null;
    }
    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    $filename = $prefix . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $target = rtrim($targetDir, '/') . '/' . $filename;
    if (!is_uploaded_file((string)$file['tmp_name']) || !move_uploaded_file((string)$file['tmp_name'], $target)) {
        $error = 'Failed to move uploaded file.';
        return null;
    }
    return $filename;
}
