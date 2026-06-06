<?php
require dirname(__DIR__, 3) . '/bootstrap/init.php';
require_once APP_PATH . '/controllers/CustomerController.php';
require_login();

(new CustomerController())->index();
