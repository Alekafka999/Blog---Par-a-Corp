<?php
declare(strict_types=1);

function blog_is_logged_in(): bool
{
    return !empty($_SESSION['blog_logged_in']) && $_SESSION['blog_logged_in'] === true;
}

function blog_attempt_login(string $username, string $password): bool
{
    $normalized = blog_normalize_username($username);

    $users = blog_load_users();
    $user = blog_find_user_by_username($users, $normalized);

    if ($user !== null) {
        $hash = (string) ($user['password_hash'] ?? '');
        if ($hash !== '' && password_verify($password, $hash)) {
            $_SESSION['blog_logged_in'] = true;
            $_SESSION['blog_login_time'] = time();
            $_SESSION['blog_user_name'] = (string) ($user['name'] ?? '');
            $_SESSION['blog_user_username'] = (string) ($user['username'] ?? '');
            $_SESSION['blog_user_nickname'] = (string) ($user['nickname'] ?? '');
            return true;
        }
    }

    $userOk = hash_equals(BLOG_ADMIN_USER, trim($username));
    $passOk = false;

    $adminData = blog_load_admin_credentials();
    $overrideHash = (string) ($adminData['password_hash'] ?? '');

    if ($overrideHash !== '') {
        $passOk = password_verify($password, $overrideHash);
    } elseif (defined('BLOG_ADMIN_PASS_HASH')) {
        $passOk = password_verify($password, BLOG_ADMIN_PASS_HASH);
    } elseif (defined('BLOG_ADMIN_PASS')) {
        $passOk = hash_equals(BLOG_ADMIN_PASS, $password);
    }

    if (!$userOk || !$passOk) {
        return false;
    }

    $_SESSION['blog_logged_in'] = true;
    $_SESSION['blog_login_time'] = time();
    $_SESSION['blog_user_name'] = BLOG_ADMIN_USER;
    $_SESSION['blog_user_username'] = BLOG_ADMIN_USER;
    $_SESSION['blog_user_nickname'] = BLOG_ADMIN_USER;

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
