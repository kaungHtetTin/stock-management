<?php
/**
 * Session helpers
 */

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_admin(): bool
{
    $user = current_user();
    return $user && ($user['role'] ?? '') === 'admin';
}

function require_login(): void
{
    if (!is_logged_in()) {
        flash('error', 'Please sign in to continue.');
        redirect('login.php');
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        flash('error', 'You do not have permission to access that page.');
        redirect('pages/dashboard.php');
    }
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message === null) {
        $text = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $text;
    }

    $_SESSION['flash'][$key] = $message;
    return null;
}

function has_flash(string $key): bool
{
    return !empty($_SESSION['flash'][$key]);
}

function enforce_session_timeout(): void
{
    if (empty($_SESSION['user_id'])) {
        return;
    }

    $limit = SESSION_TIMEOUT * 60;
    $now = time();

    if (!empty($_SESSION['last_activity']) && ($now - $_SESSION['last_activity']) > $limit) {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
        session_start();
        flash('error', 'Your session has expired. Please sign in again.');
        redirect('login.php');
    }

    $_SESSION['last_activity'] = $now;
}
