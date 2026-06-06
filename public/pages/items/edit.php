<?php
require dirname(__DIR__, 3) . '/bootstrap/init.php';
require_once APP_PATH . '/controllers/ItemController.php';

$id = (int) ($_GET['id'] ?? 0);
$controller = new ItemController();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller->update($id);
}

$controller->edit($id);
