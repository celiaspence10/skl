<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/LinkService.php';
require_once __DIR__ . '/../app/RedirectService.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$path = trim((string) $path, '/');

if ($path === '' || strpos($path, '/') !== false) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Shortlink not found';
    exit;
}

$pdo = db();
$linkService = new LinkService($pdo);
$redirect = new RedirectService($linkService);

$redirect->handle($path, $_GET, $_SERVER);
