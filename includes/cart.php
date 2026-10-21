<?php
require_once __DIR__ . '/transactions.php';
function sip_add_cart_item($conn, int $userId, int $productId, string $type, int $quantity): string
{
    if ($userId <= 0) return 'login_required';
    if ($productId <= 0 || $quantity < 1 || $quantity > 999999 || !in_array($type, ['product','project'], true)) return 'invalid';
    sip_require_transactional($conn, ['cart','products','projects']);
    mysqli_begin_transaction($conn);
    try {
        $table = $type === 'project' ? 'projects' : 'products';
        $q = mysqli_query($conn, "SELECT * FROM $table WHERE id=$productId AND status=1 FOR UPDATE");
        $item = mysqli_fetch_assoc($q);
        if (!$item) { mysqli_rollback($conn); return 'invalid'; }
        $q = mysqli_query($conn, "SELECT id,qty FROM cart WHERE user_id=$userId AND product_id=$productId AND item_type='$type' FOR UPDATE");
        $cart = mysqli_fetch_assoc($q);
        $newQty = (int)($cart['qty'] ?? 0) + $quantity;
        if ($newQty > ($type === 'product' ? (int)$item['stock'] : 999999)) {
            mysqli_rollback($conn); return 'out_of_stock';
        }
        if ($cart) {
            mysqli_query($conn, "UPDATE cart SET qty=$newQty WHERE id=" . (int)$cart['id']);
        } else {
            mysqli_query($conn, "INSERT INTO cart (user_id,product_id,item_type,qty) VALUES ($userId,$productId,'$type',$newQty)");
        }
        mysqli_commit($conn);
        return 'success:' . $newQty;
    } catch (Throwable $e) { mysqli_rollback($conn); throw $e; }
}
