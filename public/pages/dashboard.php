<?php
require dirname(__DIR__, 2) . '/bootstrap/init.php';
require_once APP_PATH . '/controllers/DashboardController.php';

(new DashboardController())->index();
