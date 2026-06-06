<?php
require dirname(__DIR__) . '/bootstrap/init.php';
require_once APP_PATH . '/controllers/AuthController.php';

$auth = new AuthController();

if (is_logged_in()) {
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth->login();
}

$auth->showLogin();
