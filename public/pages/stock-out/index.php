<?php
require dirname(__DIR__, 3) . '/bootstrap/init.php';
require_once APP_PATH . '/controllers/StockOutController.php';
require_login();

$controller = new StockOutController();
$controller->index();
