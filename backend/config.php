<?php
// Configuration for PGConnect
// Set DEV_MODE to true while developing to enable seed button and other helpers
if (!defined('DEV_MODE')) define('DEV_MODE', false);

// Listing visibility / approval behavior
// Set to true if you want new listings to be visible immediately (no admin approval step).
if (!defined('AUTO_APPROVE_LISTINGS')) define('AUTO_APPROVE_LISTINGS', true);
// Set to true if search/listings pages should include pending listings.
if (!defined('SHOW_PENDING_LISTINGS')) define('SHOW_PENDING_LISTINGS', true);

// Admin email for alerts (set to a real email in production)
if (!defined('ADMIN_EMAIL')) define('ADMIN_EMAIL', 'admin@localhost');

// Notify admin about new booking requests (best-effort mail + log)
if (!defined('BOOKING_ADMIN_NOTIFY')) define('BOOKING_ADMIN_NOTIFY', true);
// Threshold: number of repeated identical errors before sending an alert
if (!defined('ERROR_ALERT_THRESHOLD')) define('ERROR_ALERT_THRESHOLD', 3);

// Window in seconds for counting repeated errors (default 1 hour)
if (!defined('ERROR_ALERT_WINDOW')) define('ERROR_ALERT_WINDOW', 3600);

// Path to store error counts (writable by webserver)
if (!defined('ERROR_COUNTER_FILE')) define('ERROR_COUNTER_FILE', __DIR__ . '/getPgs_error_counts.json');

// Path to admin alerts log
if (!defined('ADMIN_ALERT_LOG')) define('ADMIN_ALERT_LOG', __DIR__ . '/admin_alerts.log');

// Helper: attempt to send an email alert (best-effort)
function send_admin_alert($subject, $body) {
    if (empty(ADMIN_EMAIL)) return false;
    $headers = 'From: noreply@pgconnect.local' . "\r\n" . 'Content-Type: text/plain; charset=utf-8';
    // best-effort mail; may not work on local dev
    @mail(ADMIN_EMAIL, $subject, $body, $headers);
    @file_put_contents(ADMIN_ALERT_LOG, date('Y-m-d H:i:s') . " ALERT: $subject\n$body\n\n", FILE_APPEND);
    return true;
}
