<?php
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../includes/transactions.php';
$id=(int)($_POST['id'] ?? 0);
$customerId=(int)($_POST['customer_id'] ?? 0);
if ($id <= 0 || $customerId <= 0) { http_response_code(400); exit('0'); }
try {
    sip_require_transactional($conn,['customer_addresses','users']);
    mysqli_begin_transaction($conn);
    mysqli_query($conn,"SELECT id FROM users WHERE id=$customerId FOR UPDATE");
    if (mysqli_num_rows(mysqli_query($conn,"SELECT id FROM customer_addresses WHERE id=$id AND customer_id=$customerId FOR UPDATE")) !== 1) {
        mysqli_rollback($conn); http_response_code(404); exit('0');
    }
    mysqli_query($conn,"UPDATE customer_addresses SET is_default=0 WHERE customer_id=$customerId");
    mysqli_query($conn,"UPDATE customer_addresses SET is_default=1 WHERE id=$id AND customer_id=$customerId");
    mysqli_commit($conn); echo '1';
} catch (Throwable $e) {
    mysqli_rollback($conn); http_response_code(503); echo '0';
}
