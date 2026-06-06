<?php
/**
 * CSRF protection helpers
 */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(?string $token = null): bool
{
    $token = $token ?? ($_POST['csrf_token'] ?? '');

    return isset($_SESSION['csrf_token'])
        && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

function regenerate_csrf_token(): void
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function require_csrf(string $fallback = 'pages/dashboard.php'): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || verify_csrf()) {
        return;
    }

    flash('error', 'Invalid security token. Please try again.');
    redirect($fallback);
}
