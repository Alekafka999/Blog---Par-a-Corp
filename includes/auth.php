<?php
declare(strict_types=1);

function blog_is_logged_in(): bool
{
    return !empty($_SESSION['blog_logged_in']) && $_SESSION['blog_logged_in'] === true;
}

function blog_attempt_login(string $username, string $password): bool
{
    $userOk = hash_equals(BLOG_ADMIN_USER, trim($username));
    $passOk = hash_equals(BLOG_ADMIN_PASS, $password);

    if (!$userOk || !$passOk) {
        return false;
    }

    $_SESSION['blog_logged_in'] = true;
    $_SESSION['blog_login_time'] = time();

    return true;
}

function blog_logout(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, (string) ($params['path'] ?? '/'));
    }

    session_destroy();
}

function blog_require_login(): void
{
    if (!blog_is_logged_in()) {
        blog_redirect('login.php');
    }
}

