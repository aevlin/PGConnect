<?php
if (!defined('BASE_URL')) define('BASE_URL', '/PGConnect');

$query = $_SERVER['QUERY_STRING'] ?? '';
$target = BASE_URL . '/user/pg-listings.php';
if ($query !== '') {
    $target .= '?' . $query;
}
header('Location: ' . $target);
exit;
