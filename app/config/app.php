<?php
/**
 * Application settings
 */

define('APP_NAME', 'Stock Management');
define('APP_COMPANY', 'YUKIOH MYANMAR CO.,LTD');
define('APP_COMPANY_ID', '119751578');

define('APP_URL', '/stock-manage');
define('APP_TIMEZONE', 'Asia/Yangon');

date_default_timezone_set(APP_TIMEZONE);

define('SESSION_TIMEOUT', 30);
define('LOW_STOCK_THRESHOLD', 15);
