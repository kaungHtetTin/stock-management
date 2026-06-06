<?php
require dirname(__DIR__, 3) . '/bootstrap/init.php';
require_once APP_PATH . '/controllers/CategoryController.php';

$controller = new CategoryController();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller->store();
}

$controller->create();
