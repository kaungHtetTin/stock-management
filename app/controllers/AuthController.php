<?php
/**
 * Login / logout
 */

require_once APP_PATH . '/models/User.php';

class AuthController
{
    public function showLogin(): void
    {
        $username = $_SESSION['login_username'] ?? '';
        unset($_SESSION['login_username']);
        require APP_PATH . '/views/auth/login.php';
    }

    public function login(): void
    {
        require_csrf('login.php');

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $this->failLogin($username);
        }

        $user = User::findByUsername($username);

        if (!$user || $user['status'] !== 'active') {
            $this->failLogin($username);
        }

        if (!User::verifyPassword($password, $user['password_hash'])) {
            $this->failLogin($username);
        }

        session_regenerate_id(true);
        regenerate_csrf_token();

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user'] = User::toSessionUser($user);
        $_SESSION['last_activity'] = time();

        flash('success', 'Welcome back, ' . $user['display_name'] . '!');
        redirect('index.php');
    }

    public function logout(): void
    {
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

        flash('success', 'You have been signed out.');
        redirect('login.php');
    }

    private function failLogin(string $username): void
    {
        $_SESSION['login_username'] = $username;
        flash('error', 'Invalid username or password.');
        redirect('login.php');
    }
}
