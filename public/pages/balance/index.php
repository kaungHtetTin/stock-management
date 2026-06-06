<?php
require dirname(__DIR__, 3) . '/bootstrap/init.php';
require_once APP_PATH . '/controllers/BalanceController.php';

(new BalanceController())->index();
