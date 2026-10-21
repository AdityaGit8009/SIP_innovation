<?php
header('Content-Type: application/json');
require 'config/db.php';
require_once __DIR__ . '/includes/Mailer.php';
require_once __DIR__ . '/includes/NotificationService.php';

$data = json_decode(file_get_contents("php://input"), true) ?: [];

$name   = trim($data['username'] ?? '');
$email  = trim($data['email'] ?? '');
$mobile = trim($data['mobile'] ?? '');
$pass   = (string)($data['password'] ?? '');

$mobileDigits = preg_replace('/\D/', '', $mobile);

if ($name === '' || $email === '' || $mobileDigits === '' || $pass === '') {
    echo json_encode(['status' => 'error', 'message' => 'All fields required']);
    exit;
}

if (!preg_match('/^[a-zA-Z\s]{2,60}$/', $name)) {
    echo json_encode(['status' => 'error', 'message' => 'Enter a valid name']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Enter a valid email address']);
    exit;
}

if (!preg_match('/^[0-9]{10}$/', $mobileDigits)) {
    echo json_encode(['status' => 'error', 'message' => 'Enter a valid 10 digit mobile number']);
    exit;
}

if (strlen($pass) < 6) {
    echo json_encode(['status' => 'error', 'message' => 'Password must be at least 6 characters']);
    exit;
}

$nameEsc = mysqli_real_escape_string($conn, $name);
$emailEsc = mysqli_real_escape_string($conn, $email);
$mobileEsc = mysqli_real_escape_string($conn, $mobileDigits);

require_once __DIR__ . '/includes/transactions.php';
try {
    sip_require_transactional($conn, ['users','login']);
    mysqli_begin_transaction($conn);
    $exists = mysqli_query($conn, "SELECT id FROM users WHERE email='$emailEsc' OR mobile='$mobileEsc' LIMIT 1");
    $loginExists = mysqli_query($conn, "SELECT id FROM login WHERE login_id='$emailEsc' OR mobile='$mobileEsc' LIMIT 1");
    if (mysqli_num_rows($exists) || mysqli_num_rows($loginExists)) {
        mysqli_rollback($conn);
        exit(json_encode(['status'=>'error','message'=>'User already exists']));
    }
    $hashEsc = mysqli_real_escape_string($conn, password_hash($pass, PASSWORD_DEFAULT));
    mysqli_query($conn, "INSERT INTO users (name,email,mobile) VALUES ('$nameEsc','$emailEsc','$mobileEsc')");
    mysqli_query($conn, "INSERT INTO login (login_id,password,mobile,role,status) VALUES ('$emailEsc','$hashEsc','$mobileEsc','user',1)");
    mysqli_commit($conn);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    http_response_code($e instanceof mysqli_sql_exception && $e->getCode() === 1062 ? 409 : 503);
    exit(json_encode(['status'=>'error','message'=>'Registration could not be completed. Please try again.']));
}

try {
    $mailer = new SipMailer();
    $ns = new NotificationService();

    // User welcome email
    $ns->sendWelcomeEmail($email, $name);

    // Admin registration alert
    $mailer->send(
        $mailer->adminEmail(),
        'New User Registered',
        '<p>A new user has registered on the website.</p>'
        . '<p><b>Name:</b> ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '<br>'
        . '<b>Email:</b> ' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '<br>'
        . '<b>Mobile:</b> ' . htmlspecialchars($mobileDigits, ENT_QUOTES, 'UTF-8') . '</p>',
        $email
    );
} catch (Exception $e) {
    error_log('Registration mail failed: ' . $e->getMessage());
}

echo json_encode(['status' => 'success']);
