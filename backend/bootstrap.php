<?php

if (!function_exists('pgconnect_path_ends_with')) {
    function pgconnect_path_ends_with($haystack, $needle) {
        $haystack = (string)$haystack;
        $needle = (string)$needle;
        if ($needle === '') return true;
        if (strlen($needle) > strlen($haystack)) return false;
        return substr($haystack, -strlen($needle)) === $needle;
    }
}

if (!function_exists('pgconnect_normalize_base_url')) {
    function pgconnect_normalize_base_url($path) {
        $path = str_replace('\\', '/', (string)$path);
        $path = trim($path);
        if ($path === '' || $path === '/') {
            return '';
        }
        return '/' . trim($path, '/');
    }
}

if (!function_exists('pgconnect_detect_base_url')) {
    function pgconnect_detect_base_url() {
        $override = getenv('PGCONNECT_BASE_URL') ?: getenv('APP_BASE_URL');
        if ($override !== false && $override !== '') {
            return pgconnect_normalize_base_url($override);
        }

        $appRoot = realpath(dirname(__DIR__));
        $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
        if ($appRoot && $docRoot && strpos($appRoot, $docRoot) === 0) {
            $relative = substr($appRoot, strlen($docRoot));
            return pgconnect_normalize_base_url($relative);
        }

        $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        $scriptDir = str_replace('\\', '/', dirname($scriptName));
        if ($scriptDir === '.' || $scriptDir === '/') {
            $scriptDir = '';
        }

        foreach (['/backend', '/user', '/owner', '/admin', '/includes', '/uploads'] as $suffix) {
            if (pgconnect_path_ends_with($scriptDir, $suffix)) {
                $scriptDir = substr($scriptDir, 0, -strlen($suffix));
                break;
            }
        }

        return pgconnect_normalize_base_url($scriptDir);
    }
}

if (!defined('BASE_URL')) {
    define('BASE_URL', pgconnect_detect_base_url());
}

if (!function_exists('base_url')) {
    function base_url($path = '') {
        $path = ltrim((string)$path, '/');
        if ($path === '') {
            return BASE_URL !== '' ? BASE_URL : '/';
        }
        return (BASE_URL !== '' ? BASE_URL : '') . '/' . $path;
    }
}
