<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function auth_user(): ?array
{
    return $_SESSION['auth_user'] ?? null;
}

function auth_login(string $username, string $password): array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, username, password_hash, role FROM users WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();
    if (!$user) {
        throw new RuntimeException('INVALID_CREDENTIALS');
    }
    if (!password_verify($password, $user['password_hash'])) {
        throw new RuntimeException('INVALID_CREDENTIALS');
    }
    unset($user['password_hash']);
    session_regenerate_id(true);
    $_SESSION['auth_user'] = $user;
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $user;
}

function auth_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function require_login(): void
{
    if (!auth_user()) {
        json_response(['ok' => false, 'msg' => 'UNAUTHORIZED'], 401);
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function require_csrf(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($token === '' || !hash_equals(csrf_token(), $token)) {
        json_response(['ok' => false, 'msg' => 'BAD_CSRF'], 403);
    }
}

