<?php
require dirname(__DIR__, 3) . '/bootstrap/init.php';
require_once APP_PATH . '/controllers/ItemController.php';
require_login();

(new ItemController())->index();
