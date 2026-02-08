<?php
require_once 'config.php';
header('Content-Type: application/json');
echo json_encode(['dev_mode' => !!DEV_MODE]);
