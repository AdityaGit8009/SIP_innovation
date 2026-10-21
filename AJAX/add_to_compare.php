<?php
session_start();
include __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../jwt.php';
require_once __DIR__ . '/../secure_helpers.php';
$user_id = ecom_user_id();
if ($user_id <= 0) { echo "login_required"; exit; }
$product_id = (int)($_POST['product_id'] ?? 0);
if ($product_id <= 0) { echo "invalid"; exit; }
require_once __DIR__ . '/../includes/transactions.php';
try {
    sip_require_transactional($conn, ['users','compare']);
    mysqli_begin_transaction($conn);
    mysqli_query($conn, "SELECT id FROM users WHERE id=$user_id FOR UPDATE");
    if (mysqli_num_rows(mysqli_query($conn, "SELECT id FROM products WHERE id=$product_id AND status=1")) !== 1) { mysqli_rollback($conn); exit('invalid'); }
$count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM compare WHERE user_id='$user_id'"))['cnt'] ?? 0;
$exists = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM compare WHERE user_id='$user_id' AND product_id='$product_id' LIMIT 1"));
if (!$exists && $count >= 4) { mysqli_rollback($conn); echo "limit"; exit; }
mysqli_query($conn, "INSERT IGNORE INTO compare (user_id, product_id) VALUES ('$user_id', '$product_id')");
mysqli_commit($conn);
echo "success";
} catch (Throwable $e) { mysqli_rollback($conn); http_response_code(503); echo 'Comparison is temporarily unavailable.'; }
