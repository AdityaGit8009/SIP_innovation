<?php
if (!function_exists('ecom_h')) {
function ecom_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
}

if (!function_exists('ecom_user_id')) {
function ecom_user_id() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    global $conn;
    require_once __DIR__ . '/jwt.php';
    if (!isset($conn)) return 0;
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if ($uid > 0) {
        $q = mysqli_query($conn, "SELECT u.id,l.password FROM users u JOIN login l ON (l.login_id=u.email OR l.mobile=u.mobile) WHERE u.id=$uid AND l.role='user' AND l.status=1 LIMIT 1");
        if ($q && ($row = mysqli_fetch_assoc($q)) && JWT::matchesAccount(['ver'=>$_SESSION['auth_version'] ?? null], $row['password'])) return $uid;
        unset($_SESSION['user_id']);
    }
    if (!empty($_COOKIE['auth_token'])) {
        require_once __DIR__ . '/jwt.php';
        $payload = JWT::decode($_COOKIE['auth_token']);
        if (is_array($payload) && ($payload['role'] ?? '') === 'user' && is_string($payload['login_id'] ?? null)) {
            $loginId = mysqli_real_escape_string($conn, $payload['login_id']);
            $loginUid = (int)($payload['uid'] ?? 0);
            $q = mysqli_query($conn, "SELECT u.id,l.password FROM users u JOIN login l ON (l.login_id=u.email OR l.mobile=u.mobile) WHERE l.id=$loginUid AND l.login_id='$loginId' AND l.role='user' AND l.status=1 LIMIT 1");
            if ($q && ($row = mysqli_fetch_assoc($q)) && JWT::matchesAccount($payload, $row['password'])) {
                $_SESSION['auth_version'] = $payload['ver'];
                $_SESSION['user_id'] = (int)$row['id'];
                return (int)$row['id'];
            }
        }
    }
    return 0;
}
}

if (!function_exists('ecom_require_user')) {
function ecom_require_user() {
    $uid = ecom_user_id();
    if ($uid <= 0) {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest') {
            http_response_code(401); header('Content-Type: application/json');
            exit(json_encode(['status'=>'error','message'=>'Please login first']));
        }
        require_once __DIR__ . '/includes/url.php';
        $target = sip_base_path() . '/index.php';
        if (!headers_sent()) header('Location: ' . $target);
        else echo '<script>location.href=' . json_encode($target, JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>';
        exit;
    }
    return $uid;
}
}

if (!function_exists('ecom_get_user')) {
function ecom_get_user($conn, $user_id) {
    $user_id = (int)$user_id;
    $q = mysqli_query($conn, "SELECT * FROM users WHERE id='$user_id' LIMIT 1");
    return $q ? mysqli_fetch_assoc($q) : null;
}
}

if (!function_exists('ecom_table_has_column')) {
function ecom_table_has_column($conn, $table, $column) {
    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);
    $q = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $q && mysqli_num_rows($q) > 0;
}
}

if (!function_exists('ecom_order_badge')) {
function ecom_order_badge($status) {
    $status = strtolower((string)$status);
    if ($status === 'delivered') return 'success';
    if ($status === 'shipped' || $status === 'packed') return 'warning text-dark';
    if ($status === 'cancelled' || $status === 'returned') return 'danger';
    if ($status === 'confirmed' || $status === 'processing') return 'info text-dark';
    return 'secondary';
}
}

if (!function_exists('ecom_set_auth_cookie')) {
    function ecom_set_auth_cookie($token, $lifetimeSeconds = 86400)
    {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        setcookie('auth_token', $token, [
            'expires' => time() + (int) $lifetimeSeconds,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

if (!function_exists('ecom_clear_auth_cookie')) {
    function ecom_clear_auth_cookie()
    {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        setcookie('auth_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
?>
