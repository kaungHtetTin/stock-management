<?php
require dirname(__DIR__, 3) . '/bootstrap/init.php';
require_once APP_PATH . '/controllers/StockInController.php';
require_login();

$controller = new StockInController();
$controller->index();
