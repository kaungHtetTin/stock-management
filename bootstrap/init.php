<?php
/**
 * Application bootstrap — load config, helpers, start session
 */

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('STORAGE_PATH', ROOT_PATH . '/storage');

require APP_PATH . '/config/app.php';
require APP_PATH . '/config/database.php';
require APP_PATH . '/helpers/functions.php';
require APP_PATH . '/helpers/session.php';
require APP_PATH . '/helpers/csrf.php';
require APP_PATH . '/helpers/logger.php';
require APP_PATH . '/helpers/pagination.php';
require APP_PATH . '/helpers/Database.php';

register_app_error_handlers();

session_start();

enforce_session_timeout();
