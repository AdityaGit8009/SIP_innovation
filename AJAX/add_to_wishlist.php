<?php
session_start();
include __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../jwt.php';
require_once __DIR__ . '/../secure_helpers.php';
$user_id = ecom_user_id();
if ($user_id <= 0) { echo "login_required"; exit; }
$product_id = (int)($_POST['product_id'] ?? 0);
if ($product_id <= 0) { echo "invalid"; exit; }
if (mysqli_num_rows(mysqli_query($conn, "SELECT id FROM products WHERE id=$product_id AND status=1")) !== 1) exit("invalid");
mysqli_query($conn, "INSERT IGNORE INTO wishlist (user_id, product_id) VALUES ('$user_id', '$product_id')");
echo "success";
