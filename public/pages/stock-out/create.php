<?php
require dirname(__DIR__, 3) . '/bootstrap/init.php';
require_once APP_PATH . '/controllers/StockOutController.php';

$controller = new StockOutController();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller->store();
}

$controller->create();
