<?php
require dirname(__DIR__, 3) . '/bootstrap/init.php';
require_once APP_PATH . '/controllers/CategoryController.php';

(new CategoryController())->index();
