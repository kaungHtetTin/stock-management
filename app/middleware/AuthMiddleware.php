<?php
/**
 * Ensures user is authenticated
 */

require_once APP_PATH . '/helpers/session.php';

class AuthMiddleware
{
    public static function handle(): void
    {
        require_login();
    }
}
