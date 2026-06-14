<?php
require dirname(__DIR__, 3) . '/bootstrap/init.php';
require_once APP_PATH . '/controllers/ExpiryController.php';

(new ExpiryController())->index();
