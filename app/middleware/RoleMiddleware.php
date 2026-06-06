<?php
/**
 * Ensures user has required role
 */

require_once APP_PATH . '/helpers/session.php';

class RoleMiddleware
{
    public static function admin(): void
    {
        require_admin();
    }

    public static function staff(): void
    {
        require_login();
    }
}
