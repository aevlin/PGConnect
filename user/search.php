<?php
require_once __DIR__ . '/../backend/bootstrap.php';

$query = $_SERVER['QUERY_STRING'] ?? '';
$target = base_url('user/pg-listings.php');
if ($query !== '') {
    $target .= '?' . $query;
}
header('Location: ' . $target);
exit;
