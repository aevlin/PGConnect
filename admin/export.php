<?php
require_once '../backend/auth.php';
require_role('admin');
require_once '../backend/connect.php';

$type = $_GET['type'] ?? 'bookings';
$filename = 'export_' . $type . '_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);
$out = fopen('php://output', 'w');

if ($type === 'bookings') {
    fputcsv($out, ['booking_id','pg_id','user_id','status','payment_status','payment_amount','created_at','paid_at']);
    $rows = $pdo->query('SELECT id, pg_id, user_id, status, payment_status, payment_amount, created_at, paid_at FROM bookings ORDER BY created_at DESC');
    foreach ($rows as $r) fputcsv($out, $r);
} elseif ($type === 'owners') {
    fputcsv($out, ['owner_id','name','email','phone','verification','created_at']);
    $rows = $pdo->query("SELECT id, name, email, phone, owner_verification_status, created_at FROM users WHERE role = 'owner' ORDER BY created_at DESC");
    foreach ($rows as $r) fputcsv($out, $r);
} elseif ($type === 'revenue') {
    fputcsv($out, ['pg_id','pg_name','city','paid_bookings','total_paid_amount']);
    $sql = "SELECT p.id, p.pg_name, p.city,
                   SUM(CASE WHEN b.payment_status='paid' THEN 1 ELSE 0 END) as paid_bookings,
                   SUM(CASE WHEN b.payment_status='paid' THEN b.payment_amount ELSE 0 END) as total_paid_amount
            FROM pg_listings p
            LEFT JOIN bookings b ON b.pg_id = p.id
            GROUP BY p.id, p.pg_name, p.city
            ORDER BY total_paid_amount DESC";
    $rows = $pdo->query($sql);
    foreach ($rows as $r) fputcsv($out, $r);
} else {
    fputcsv($out, ['error']);
    fputcsv($out, ['Unknown export type']);
}
fclose($out);
exit;

