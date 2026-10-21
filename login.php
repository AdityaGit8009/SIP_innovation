<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/jwt.php';
// Validate before establishing any authenticated session state.
try {
    JWT::assertConfigured();
} catch (RuntimeException $e) {
    http_response_code(503);
    echo json_encode(['status' => 'error', 'message' => 'Authentication is temporarily unavailable.']);
    exit;
}
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/secure_helpers.php';

$data = json_decode(file_get_contents("php://input"), true) ?: [];
$loginId = mysqli_real_escape_string($conn, trim($data['loginId'] ?? ''));
$pass = (string)($data['password'] ?? '');
if ($loginId === '' || $pass === '') {
    echo json_encode(['status' => 'error', 'message' => 'All fields required']);
    exit;
}
$q = "SELECT * FROM login WHERE login_id='$loginId' OR mobile='$loginId' LIMIT 1";
$r = mysqli_query($conn, $q);
if (!$r || mysqli_num_rows($r) == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid login']);
    exit;
}
$user = mysqli_fetch_assoc($r);
if ((int)$user['status'] !== 1) {
    echo json_encode(['status' => 'error', 'message' => 'Account inactive']);
    exit;
}
if (!password_verify($pass, $user['password'])) {
    echo json_encode(['status' => 'error', 'message' => 'Wrong password']);
    exit;
}
session_regenerate_id(true);
$_SESSION['auth_version'] = JWT::accountVersion($user['password']);
$uid = (int)$user['id'];
if ($user['role'] === 'user') {
    $uRes = mysqli_query($conn, "SELECT id FROM users WHERE email='" . mysqli_real_escape_string($conn, $user['login_id']) . "' OR mobile='" . mysqli_real_escape_string($conn, $user['mobile']) . "' LIMIT 1");
    $uRow = $uRes ? mysqli_fetch_assoc($uRes) : null;
    $uid = (int)($uRow['id'] ?? 0);
    if ($uid <= 0) { http_response_code(409); exit(json_encode(['status'=>'error','message'=>'Account needs support before login.'])); }
    unset($_SESSION['admin_id']);
    $_SESSION['user_id'] = $uid;
} else {
    unset($_SESSION['user_id']);
    $_SESSION['admin_id'] = (int)$user['id'];
    $_SESSION['admin_name'] = $user['name'] ?: $user['login_id'];
}
$token = JWT::encode(['ver' => $_SESSION['auth_version'], 'uid' => (int)$user['id'], 'login_id' => $user['login_id'], 'role' => $user['role'], 'role_id' => (int)($user['role_id'] ?? 0), 'time' => time(), 'exp' => time() + 86400]);
ecom_set_auth_cookie($token, 86400);
echo json_encode(['status' => 'success', 'redirect' => ($user['role'] === 'admin' ? 'admin/dashboard.php' : 'index.php')]);
