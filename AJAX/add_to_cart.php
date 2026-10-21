<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../secure_helpers.php';
require_once __DIR__ . '/../includes/cart.php';
header('Content-Type: text/plain; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('invalid'); }
try {
    echo sip_add_cart_item($conn, ecom_user_id(), (int)($_POST['product_id'] ?? 0), (string)($_POST['item_type'] ?? 'product'), (int)($_POST['qty'] ?? 1));
} catch (Throwable $e) {
    http_response_code(503);
    echo 'Cart is temporarily unavailable. Please try again.';
}
