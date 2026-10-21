<?php
function sip_csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

function sip_mutating_get(string $script, array $query): bool
{
    $name = basename($script);
    if ($name === 'remove_address.php' || $name === 'delete_supplier.php') return true;
    if ($name === 'cart.php' && isset($query['add'])) return true;
    if (strpos(str_replace('\\', '/', $script), '/admin/') !== false) {
        foreach (['delete', 'toggle', 'toggle_status', 'status'] as $key) {
            if (isset($query[$key])) return true;
        }
    }
    return false;
}

function sip_check_request(): void
{
    if (PHP_SAPI === 'cli') return;
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $mutation = sip_mutating_get($_SERVER['SCRIPT_NAME'] ?? '', $_GET);
    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
        if (!$mutation) return;
        http_response_code(405);
        header('Allow: POST');
    } else {
        $expected = sip_csrf_token();
        $actual = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? '';
        if (is_string($actual) && hash_equals($expected, $actual)) return;
        http_response_code(403);
    }
    header('Content-Type: application/json; charset=utf-8');
    exit(json_encode(['status' => 'error', 'message' => 'Request rejected. Reload the page and try again.']));
}
