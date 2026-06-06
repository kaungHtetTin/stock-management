<?php
require dirname(__DIR__) . '/bootstrap/init.php';
require_once APP_PATH . '/controllers/AuthController.php';

$auth = new AuthController();
$auth->logout();
