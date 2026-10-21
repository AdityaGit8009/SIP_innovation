<?php
session_start();
include "config/db.php";
include "order_helpers.php";
require_once "secure_helpers.php";
$user_id = ecom_require_user();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("Location: orders.php");
  exit;
}
$order_id = (int)($_POST['order_id'] ?? 0);
$reason = trim($_POST['cancel_reason'] ?? '');
if ($order_id <= 0) {
  header("Location: orders.php?msg=invalid");
  exit;
}
require_once __DIR__ . '/includes/transactions.php';
try {
sip_require_transactional($conn, ['orders','order_items','products','transactions','order_status_history']);
mysqli_begin_transaction($conn);
$orderQuery = mysqli_query($conn, "SELECT * FROM orders WHERE id='$order_id' AND user_id='$user_id' LIMIT 1 FOR UPDATE");
if (!$orderQuery || mysqli_num_rows($orderQuery) == 0) {
  header("Location: orders.php?msg=not_found");
  exit;
}
$order = mysqli_fetch_assoc($orderQuery);
$currentStatus = $order['order_status'] ?? 'Pending';
$paymentMethod = strtoupper(trim($order['payment_method'] ?? ''));
$paymentStatus = $order['payment_status'] ?? 'Pending';
if (!sip_can_customer_cancel($currentStatus)) {
  header("Location: order-details.php?order_id=" . $order_id . "&msg=cannot_cancel");
  exit;
}
$newPaymentStatus = $paymentStatus;
if ($paymentMethod === 'COD') {
  $newPaymentStatus = 'Pending';
} elseif ($paymentStatus === 'Paid') {
  // Payment remains Paid until a provider or recorded manual refund succeeds.
  $newPaymentStatus = 'Paid';
}
$cancelNote = $reason !== '' ? "Cancelled by customer: " . $reason : "Cancelled by customer";
$cancelReason = mysqli_real_escape_string($conn, $reason ?: 'Cancelled by customer');
$update = mysqli_query($conn, "UPDATE orders SET order_status='Cancelled', payment_status='$newPaymentStatus', cancelled_by='Customer', cancel_reason='$cancelReason', cancelled_at=IFNULL(cancelled_at,NOW()), refund_status=IF(payment_status='Paid' AND refund_status='None','Requested',refund_status) WHERE id='$order_id' AND user_id='$user_id'");
if ($update) {
  sip_restore_order_stock_once($conn, $order_id, true);
  mysqli_query($conn, "UPDATE transactions SET payment_status='$newPaymentStatus' WHERE order_id='$order_id'");
}
if (!$update) {
  die("Cancel failed: " . mysqli_error($conn));
}
sip_history_insert_once($conn, $order_id, 'Cancelled', $cancelNote);
mysqli_commit($conn);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    http_response_code(503);
    exit('Cancellation could not be completed. Please try again.');
}
header("Location: order-details.php?order_id=" . $order_id . "&msg=cancelled");
exit;
