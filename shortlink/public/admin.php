<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/LinkService.php';
require_once __DIR__ . '/../app/ExportService.php';
require_once __DIR__ . '/../app/auth.php';

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($action === '') {
    header('Content-Type: text/html; charset=utf-8');
    $appName = htmlspecialchars(app_config()['app_name'], ENT_QUOTES, 'UTF-8');
    $tailwindCdn = 'https://cdn.jsdelivr.net/npm/tailwindcss@3.4.4/dist/tailwind.min.css';
    $chartCdn = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js';
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$appName} Admin</title>
    <link rel="stylesheet" href="{$tailwindCdn}" onerror="this.remove();">
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="bg-gray-50 text-gray-900">
    <div id="app"></div>
    <script src="{$chartCdn}" onerror="loadLocalChart()"></script>
    <script>
    function loadLocalChart() {
        var script = document.createElement('script');
        script.src = '/assets/chart.umd.js';
        document.body.appendChild(script);
    }
    </script>
    <script src="/assets/app.js"></script>
</body>
</html>
HTML;
    exit;
}

$pdo = db();
$linkService = new LinkService($pdo);
$exportService = new ExportService($pdo);

try {
    switch ($action) {
        case 'login':
            if ($method !== 'POST') {
                json_response(['ok' => false, 'msg' => 'METHOD_NOT_ALLOWED'], 405);
            }
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            if ($username === '' || $password === '') {
                json_response(['ok' => false, 'msg' => '用户名或密码错误'], 401);
            }
            try {
                $user = auth_login($username, $password);
            } catch (RuntimeException $e) {
                json_response(['ok' => false, 'msg' => '用户名或密码错误'], 401);
            }
            json_response(['ok' => true, 'data' => ['user' => $user, 'csrf_token' => csrf_token()]]);
            break;
        case 'logout':
            require_login();
            if ($method !== 'POST') {
                json_response(['ok' => false, 'msg' => 'METHOD_NOT_ALLOWED'], 405);
            }
            require_csrf();
            auth_logout();
            json_response(['ok' => true, 'data' => []]);
            break;
        case 'me':
            require_login();
            json_response(['ok' => true, 'data' => ['user' => auth_user(), 'csrf_token' => csrf_token()]]);
            break;
        case 'link.create':
            require_login();
            if ($method !== 'POST') {
                json_response(['ok' => false, 'msg' => 'METHOD_NOT_ALLOWED'], 405);
            }
            require_csrf();
            $data = [
                'title' => $_POST['title'] ?? '',
                'default_url' => $_POST['default_url'] ?? '',
                'slug' => $_POST['slug'] ?? '',
            ];
            try {
                $link = $linkService->create($data, (int) auth_user()['id']);
                json_response(['ok' => true, 'data' => $link]);
            } catch (InvalidArgumentException $e) {
                json_response(['ok' => false, 'msg' => 'VALIDATION_ERROR: ' . $e->getMessage()], 422);
            }
            break;
        case 'link.update':
            require_login();
            if ($method !== 'POST') {
                json_response(['ok' => false, 'msg' => 'METHOD_NOT_ALLOWED'], 405);
            }
            require_csrf();
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                json_response(['ok' => false, 'msg' => 'VALIDATION_ERROR: id'], 422);
            }
            $payload = [];
            if (isset($_POST['title'])) {
                $payload['title'] = $_POST['title'];
            }
            if (isset($_POST['default_url'])) {
                $payload['default_url'] = $_POST['default_url'];
            }
            try {
                $link = $linkService->update($id, $payload);
                json_response(['ok' => true, 'data' => $link]);
            } catch (InvalidArgumentException $e) {
                json_response(['ok' => false, 'msg' => 'VALIDATION_ERROR: ' . $e->getMessage()], 422);
            }
            break;
        case 'link.toggle':
            require_login();
            if ($method !== 'POST') {
                json_response(['ok' => false, 'msg' => 'METHOD_NOT_ALLOWED'], 405);
            }
            require_csrf();
            $id = (int) ($_POST['id'] ?? 0);
            $isActive = (int) ($_POST['is_active'] ?? 0);
            if ($id <= 0) {
                json_response(['ok' => false, 'msg' => 'VALIDATION_ERROR: id'], 422);
            }
            try {
                $link = $linkService->toggle($id, $isActive);
                json_response(['ok' => true, 'data' => $link]);
            } catch (InvalidArgumentException $e) {
                json_response(['ok' => false, 'msg' => 'VALIDATION_ERROR: ' . $e->getMessage()], 422);
            }
            break;
        case 'link.delete':
            require_login();
            if ($method !== 'POST') {
                json_response(['ok' => false, 'msg' => 'METHOD_NOT_ALLOWED'], 405);
            }
            require_csrf();
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                json_response(['ok' => false, 'msg' => 'VALIDATION_ERROR: id'], 422);
            }
            $linkService->delete($id);
            json_response(['ok' => true, 'data' => []]);
            break;
        case 'link.list':
            require_login();
            $page = (int) ($_GET['page'] ?? 1);
            $size = (int) ($_GET['size'] ?? 20);
            $q = isset($_GET['q']) ? trim((string) $_GET['q']) : null;
            $list = $linkService->list($page, $size, $q);
            json_response(['ok' => true, 'data' => $list]);
            break;
        case 'target.add':
            require_login();
            if ($method !== 'POST') {
                json_response(['ok' => false, 'msg' => 'METHOD_NOT_ALLOWED'], 405);
            }
            require_csrf();
            $linkId = (int) ($_POST['link_id'] ?? 0);
            if ($linkId <= 0) {
                json_response(['ok' => false, 'msg' => 'VALIDATION_ERROR: link_id'], 422);
            }
            $payload = [
                'target_url' => $_POST['target_url'] ?? '',
                'weight' => $_POST['weight'] ?? 0,
            ];
            try {
                $target = $linkService->addTarget($linkId, $payload);
                json_response(['ok' => true, 'data' => $target]);
            } catch (InvalidArgumentException $e) {
                json_response(['ok' => false, 'msg' => 'VALIDATION_ERROR: ' . $e->getMessage()], 422);
            }
            break;
        case 'target.update':
            require_login();
            if ($method !== 'POST') {
                json_response(['ok' => false, 'msg' => 'METHOD_NOT_ALLOWED'], 405);
            }
            require_csrf();
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                json_response(['ok' => false, 'msg' => 'VALIDATION_ERROR: id'], 422);
            }
            $payload = [];
            if (isset($_POST['target_url'])) {
                $payload['target_url'] = $_POST['target_url'];
            }
            if (isset($_POST['weight'])) {
                $payload['weight'] = $_POST['weight'];
            }
            try {
                $target = $linkService->updateTarget($id, $payload);
                json_response(['ok' => true, 'data' => $target]);
            } catch (InvalidArgumentException $e) {
                json_response(['ok' => false, 'msg' => 'VALIDATION_ERROR: ' . $e->getMessage()], 422);
            }
            break;
        case 'target.toggle':
            require_login();
            if ($method !== 'POST') {
                json_response(['ok' => false, 'msg' => 'METHOD_NOT_ALLOWED'], 405);
            }
            require_csrf();
            $id = (int) ($_POST['id'] ?? 0);
            $isActive = (int) ($_POST['is_active'] ?? 0);
            if ($id <= 0) {
                json_response(['ok' => false, 'msg' => 'VALIDATION_ERROR: id'], 422);
            }
            try {
                $target = $linkService->toggleTarget($id, $isActive);
                json_response(['ok' => true, 'data' => $target]);
            } catch (InvalidArgumentException $e) {
                json_response(['ok' => false, 'msg' => 'VALIDATION_ERROR: ' . $e->getMessage()], 422);
            }
            break;
        case 'target.delete':
            require_login();
            if ($method !== 'POST') {
                json_response(['ok' => false, 'msg' => 'METHOD_NOT_ALLOWED'], 405);
            }
            require_csrf();
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                json_response(['ok' => false, 'msg' => 'VALIDATION_ERROR: id'], 422);
            }
            $linkService->deleteTarget($id);
            json_response(['ok' => true, 'data' => []]);
            break;
        case 'target.list':
            require_login();
            $linkId = (int) ($_GET['link_id'] ?? 0);
            if ($linkId <= 0) {
                json_response(['ok' => false, 'msg' => 'VALIDATION_ERROR: link_id'], 422);
            }
            $targets = $linkService->listTargets($linkId);
            json_response(['ok' => true, 'data' => $targets]);
            break;
        case 'stats.overview':
            require_login();
            $stats = $linkService->statsOverview();
            json_response(['ok' => true, 'data' => $stats]);
            break;
        case 'stats.by_day':
            require_login();
            $days = (int) ($_GET['days'] ?? 7);
            if (!in_array($days, [7, 30], true)) {
                json_response(['ok' => false, 'msg' => 'VALIDATION_ERROR: days'], 422);
            }
            $rows = $linkService->statsByDay($days);
            json_response(['ok' => true, 'data' => $rows]);
            break;
        case 'stats.by_target':
            require_login();
            $linkId = isset($_GET['link_id']) ? (int) $_GET['link_id'] : null;
            if ($linkId !== null && $linkId <= 0) {
                json_response(['ok' => false, 'msg' => 'VALIDATION_ERROR: link_id'], 422);
            }
            $rows = $linkService->statsByTarget($linkId);
            json_response(['ok' => true, 'data' => $rows]);
            break;
        case 'stats.recent_clicks':
            require_login();
            $page = (int) ($_GET['page'] ?? 1);
            $size = (int) ($_GET['size'] ?? 20);
            $filters = [
                'slug' => $_GET['slug'] ?? null,
                'date_from' => $_GET['date_from'] ?? null,
                'date_to' => $_GET['date_to'] ?? null,
                'utm_source' => $_GET['utm_source'] ?? null,
                'utm_content' => $_GET['utm_content'] ?? null,
            ];
            $list = $linkService->recentClicks($filters, $page, $size);
            json_response(['ok' => true, 'data' => $list]);
            break;
        case 'export.csv':
            require_login();
            $filters = [
                'slug' => $_GET['slug'] ?? null,
                'date_from' => $_GET['date_from'] ?? null,
                'date_to' => $_GET['date_to'] ?? null,
                'utm_source' => $_GET['utm_source'] ?? null,
                'utm_content' => $_GET['utm_content'] ?? null,
            ];
            $rows = $exportService->export($filters);
            $filename = 'shortlink_export_' . date('Ymd_His') . '.csv';
            csv_response($filename, $rows);
            break;
        default:
            json_response(['ok' => false, 'msg' => 'NOT_FOUND'], 404);
    }
} catch (Throwable $e) {
    handle_exception($e);
}
