<?php
// Global error/exception/shutdown handler
// Logs full details to backend/error.log and shows a friendly error page to users.
// Respects DEV_MODE from backend/config.php — when DEV_MODE is true it will display details.
@ini_set('display_errors', 0);
@error_reporting(E_ALL);

// Try to load config to read DEV_MODE and ADMIN_EMAIL
if (file_exists(__DIR__ . '/../backend/config.php')) {
    require_once __DIR__ . '/../backend/config.php';
}

// prefer project log file, but fall back to system temp if it's not writable
$logFile = __DIR__ . '/../backend/error.log';
// try to ensure backend dir exists
@mkdir(dirname($logFile), 0755, true);
if (!file_exists($logFile)) {
    // attempt to create the file
    @file_put_contents($logFile, "");
}
// if still not writable, use temp
if (!is_writable($logFile)) {
    $tmp = sys_get_temp_dir() . '/pgconnect_error.log';
    if (!file_exists($tmp)) @file_put_contents($tmp, "");
    $logFile = $tmp;
}

function render_friendly_error($title = 'Something went wrong', $msg = '') {
    $home = defined('BASE_URL') ? BASE_URL : '/PGConnect';
    http_response_code(500);
    echo '<!doctype html><html><head><meta charset="utf-8"><title>' . htmlspecialchars($title) . '</title>' .
        '<meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="font-family:system-ui, -apple-system, sans-serif;padding:40px;background:#fff;color:#111">';
    echo '<div style="max-width:760px;margin:0 auto;text-align:left">';
    echo '<h1 style="font-size:1.6rem;margin-bottom:8px;">' . htmlspecialchars($title) . '</h1>';
    echo '<p style="color:#6b7280;margin-bottom:16px">We logged the error and our team has been notified. Please <a href="' . htmlspecialchars($home) . '">return to the homepage</a> or try again.</p>';
    echo '<p style="color:#6b7280;font-size:0.9rem">If you are the site owner, check the log at <code>' . htmlspecialchars($logFile) . '</code> for details.</p>';
    if (defined('DEV_MODE') && DEV_MODE && $msg) {
        echo '<pre style="background:#111;color:#e6fffa;padding:12px;border-radius:6px;overflow:auto">' . htmlspecialchars($msg) . '</pre>';
    }
    echo '</div></body></html>';
}

set_error_handler(function($severity, $message, $file, $line) use ($logFile) {
    $text = date('Y-m-d H:i:s') . " ERROR: [$severity] $message in $file:$line\n";
    @file_put_contents($logFile, $text, FILE_APPEND);
    // Render friendly page unless in DEV_MODE where we show details
    if (defined('DEV_MODE') && DEV_MODE) {
        render_friendly_error('Runtime error', $text);
    } else {
        render_friendly_error();
    }
    exit;
});

set_exception_handler(function($e) use ($logFile) {
    $text = date('Y-m-d H:i:s') . ' EXCEPTION: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString() . "\n";
    @file_put_contents($logFile, $text, FILE_APPEND);
    if (defined('DEV_MODE') && DEV_MODE) {
        render_friendly_error('Unhandled exception', $text);
    } else {
        render_friendly_error();
    }
    exit;
});

register_shutdown_function(function() use ($logFile) {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $text = date('Y-m-d H:i:s') . ' FATAL: ' . $err['message'] . ' in ' . $err['file'] . ':' . $err['line'] . "\n";
        @file_put_contents($logFile, $text, FILE_APPEND);
        if (defined('DEV_MODE') && DEV_MODE) {
            render_friendly_error('Fatal error', $text);
        } else {
            render_friendly_error();
        }
    }
});

?>
