<?php
require_once __DIR__ . '/../check_admin.php';
require_once __DIR__ . '/../../includes/transactions.php';
header('Content-Type: application/json');
$id=(int)($_POST['order_id'] ?? 0);
$status=(string)($_POST['status'] ?? '');
$reference=trim((string)($_POST['refund_reference'] ?? ''));
if ($id <= 0 || !in_array($status,['Approved','Rejected','Refunded'],true) || ($status === 'Refunded' && ($reference === '' || strlen($reference)>190))) {
    http_response_code(400); exit(json_encode(['status'=>'error','message'=>'A completed refund requires its provider or bank reference.']));
}
try {
    sip_require_transactional($conn,['orders','transactions']);
    mysqli_begin_transaction($conn);
    $order=mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM orders WHERE id=$id FOR UPDATE"));
    if (!$order || $order['refund_status'] === 'Refunded' || !in_array($order['refund_status'],['Requested','Approved'],true)) {
        throw new RuntimeException('Refund cannot be changed.');
    }
    if ($status === 'Refunded' && $order['payment_status'] !== 'Paid') throw new RuntimeException('Payment must be paid before refunding.');
    $ref=mysqli_real_escape_string($conn,$reference);
    $extra=$status === 'Refunded' ? ",payment_status='Refunded',refund_reference='$ref'" : '';
    mysqli_query($conn,"UPDATE orders SET refund_status='$status'$extra WHERE id=$id");
    if ($status === 'Refunded') mysqli_query($conn,"UPDATE transactions SET payment_status='Refunded' WHERE order_id=$id");
    mysqli_commit($conn);
    sip_log_admin_activity($conn,'REFUND_UPDATE','ORDERS','Refund status updated','order',$id,['status'=>$status,'reference'=>$reference]);
    echo json_encode(['status'=>'success']);
} catch (Throwable $e) {
    mysqli_rollback($conn); http_response_code(400);
    echo json_encode(['status'=>'error','message'=>'Refund update failed. Check the payment and current refund status.']);
}
