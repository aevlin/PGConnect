<?php

$projectRoot = realpath(__DIR__ . '/..');
if ($projectRoot === false) {
    http_response_code(500);
    exit('Project root not found.');
}

$route = isset($_GET['route']) ? (string)$_GET['route'] : 'index.php';
$route = trim($route, '/');
if ($route === '') {
    $route = 'index.php';
}

$route = str_replace("\0", '', $route);
$target = realpath($projectRoot . '/' . $route);

if ($target === false || strpos($target, $projectRoot . DIRECTORY_SEPARATOR) !== 0 || !is_file($target)) {
    http_response_code(404);
    exit('Not Found');
}

if (strtolower(pathinfo($target, PATHINFO_EXTENSION)) !== 'php') {
    http_response_code(403);
    exit('Forbidden');
}

$publicScript = '/' . ltrim(str_replace('\\', '/', $route), '/');
$requestUri = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : $publicScript;

$_SERVER['SCRIPT_FILENAME'] = $target;
$_SERVER['SCRIPT_NAME'] = $publicScript;
$_SERVER['PHP_SELF'] = $publicScript;
$_SERVER['DOCUMENT_ROOT'] = $projectRoot;
$_SERVER['REQUEST_URI'] = $requestUri;

chdir(dirname($target));
require $target;
