<?php
session_start();
header('Content-Type: application/json');
include 'config/db.php';
require_once 'ecom_pricing.php';
require_once __DIR__ . '/secure_helpers.php';
$user_id = ecom_user_id();
if ($user_id <= 0) { http_response_code(401); exit(json_encode(['status'=>'error','message'=>'Login required'])); }
$pincode = preg_replace('/\D/', '', $_GET['pincode'] ?? $_POST['pincode'] ?? '');
$rows = [];
$cart = mysqli_query($conn, "SELECT c.qty,c.item_type,IF(c.item_type='project',pr.price,p.price) AS price,
    IF(c.item_type='project',pr.tax_percentage,p.tax_percentage) AS tax_percentage,
    IF(c.item_type='project',0,p.delivery_charge) AS delivery_charge
    FROM cart c LEFT JOIN products p ON p.id=c.product_id AND c.item_type='product'
    LEFT JOIN projects pr ON pr.id=c.product_id AND c.item_type='project'
    WHERE c.user_id=$user_id AND ((c.item_type='product' AND p.status=1) OR (c.item_type='project' AND pr.status=1))");
while ($cart && $r = mysqli_fetch_assoc($cart)) {
    $rows[] = $r;
}
$totals = ecom_cart_totals($rows);
$quote = ['serviceable'=>true,'charge'=>$totals['delivery_total'],'partner'=>'Product Delivery Charge','message'=>'Product-wise delivery charge'];
if (strlen($pincode) === 6) {
    $api = @file_get_contents('https://api.postalpincode.in/pincode/' . $pincode, false, stream_context_create(['http'=>['timeout'=>5]]));
    if ($api) {
        $decoded = json_decode($api, true);
        if (!empty($decoded[0]['Status']) && $decoded[0]['Status'] === 'Success') {
            $quote['city'] = $decoded[0]['PostOffice'][0]['District'] ?? '';
            $quote['state'] = $decoded[0]['PostOffice'][0]['State'] ?? '';
        } else {
            $quote['serviceable'] = false;
            $quote['message'] = 'Pincode not serviceable';
        }
    }
}
echo json_encode(['status' => 'success', 'totals' => $totals, 'delivery' => $quote]);
