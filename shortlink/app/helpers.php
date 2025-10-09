<?php

declare(strict_types=1);

function base_path(string $path = ''): string
{
    $base = dirname(__DIR__);
    if ($path === '') {
        return $base;
    }
    return $base . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
}

function storage_path(string $path = ''): string
{
    $base = base_path('storage');
    if (!is_dir($base)) {
        mkdir($base, 0775, true);
    }
    if ($path === '') {
        return $base;
    }
    $full = $base . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    $dir = dirname($full);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $full;
}

function load_env(string $file): void
{
    if (!file_exists($file)) {
        return;
    }
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$name, $value] = array_map('trim', explode('=', $line, 2));
        if (strlen($value) > 1 && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
            $value = substr($value, 1, -1);
        }
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
        putenv($name . '=' . $value);
    }
}

function env(string $key, mixed $default = null): mixed
{
    return $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: $default;
}

function app_config(): array
{
    static $config;
    if ($config === null) {
        $config = require base_path('config/config.php');
    }
    return $config;
}

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $config = require base_path('config/database.php');
    $pdo = $config();
    return $pdo;
}

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (!empty($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
        return true;
    }
    return false;
}

function validate_slug(string $slug): bool
{
    $slug = trim($slug);
    $config = app_config();
    if (strlen($slug) < $config['slug_min_length'] || strlen($slug) > $config['slug_max_length']) {
        return false;
    }
    if (!preg_match('/^[A-Za-z0-9_-]+$/', $slug)) {
        return false;
    }
    if (in_array(strtolower($slug), array_map('strtolower', $config['reserved_slugs']), true)) {
        return false;
    }
    return true;
}

function generate_slug(?int $length = null): string
{
    $config = app_config();
    $length = $length ?: $config['default_slug_length'];
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_-';
    $max = strlen($alphabet) - 1;
    $slug = '';
    for ($i = 0; $i < $length; $i++) {
        $slug .= $alphabet[random_int(0, $max)];
    }
    return $slug;
}

function validate_url(string $url): bool
{
    $url = trim($url);
    if ($url === '') {
        return false;
    }
    $parsed = parse_url($url);
    if (!$parsed || empty($parsed['scheme']) || empty($parsed['host'])) {
        return false;
    }
    $scheme = strtolower($parsed['scheme']);
    if (!in_array($scheme, ['http', 'https'], true)) {
        return false;
    }
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

function parse_utm_from_array(array $params): array
{
    $keys = ['utm_source','utm_medium','utm_campaign','utm_content','utm_term'];
    $result = [];
    foreach ($keys as $key) {
        if (isset($params[$key]) && $params[$key] !== '') {
            $result[$key] = (string) $params[$key];
        }
    }
    return $result;
}

function merge_utm_into_url(string $url, array $utm): string
{
    if (empty($utm)) {
        return $url;
    }
    $parts = parse_url($url);
    $query = [];
    if (isset($parts['query'])) {
        parse_str($parts['query'], $query);
    }
    $updated = $query;
    foreach ($utm as $key => $value) {
        if (!isset($updated[$key])) {
            $updated[$key] = $value;
        }
    }
    if ($updated === $query) {
        return $url;
    }
    $parts['query'] = http_build_query($updated);
    $rebuilt = rebuild_url($parts);
    return $rebuilt;
}

function rebuild_url(array $parts): string
{
    $scheme   = $parts['scheme'] ?? '';
    $host     = $parts['host'] ?? '';
    $port     = isset($parts['port']) ? ':' . $parts['port'] : '';
    $user     = $parts['user'] ?? '';
    $pass     = isset($parts['pass']) ? ':' . $parts['pass']  : '';
    $pass     = ($user || $pass) ? "$pass@" : '';
    $path     = $parts['path'] ?? '';
    $query    = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
    $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
    return ($scheme ? "$scheme://" : '') . $user . $pass . $host . $port . $path . $query . $fragment;
}

function get_client_ip(array $server): string
{
    $keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    foreach ($keys as $key) {
        if (!empty($server[$key])) {
            $value = $server[$key];
            if ($key === 'HTTP_X_FORWARDED_FOR') {
                $value = explode(',', $value)[0];
            }
            return trim($value);
        }
    }
    return '0.0.0.0';
}

function sanitize_csv_cell(string $value): string
{
    if ($value === '') {
        return '';
    }
    $first = $value[0];
    if (in_array($first, ['=', '+', '-', '@'], true)) {
        return "\t" . $value;
    }
    return $value;
}

function format_datetime(string $datetime): string
{
    $dt = new DateTime($datetime);
    return $dt->format('Y-m-d H:i:s');
}

function pagination(int $page, int $size): array
{
    $page = max(1, $page);
    $size = max(1, min(100, $size));
    $offset = ($page - 1) * $size;
    return [$offset, $size];
}

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function csv_response(string $filename, array $rows): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fprintf($output, "\xEF\xBB\xBF");
    foreach ($rows as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

function handle_exception(Throwable $e): void
{
    error_log($e->getMessage() . "\n" . $e->getTraceAsString());
    json_response(['ok' => false, 'msg' => 'INTERNAL_ERROR'], 500);
}

function ensure_storage_dirs(): void
{
    storage_path('logs');
    storage_path('cache');
}
