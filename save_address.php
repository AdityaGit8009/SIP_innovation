<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/secure_helpers.php';
require_once __DIR__ . '/includes/addresses.php';
header('Content-Type: application/json');
$userId=ecom_user_id();
if ($userId <= 0) { http_response_code(401); exit(json_encode(['status'=>'error','message'=>'Login required'])); }
try {
    sip_save_address($conn,$userId,$_POST);
    echo json_encode(['status'=>'success']);
} catch (InvalidArgumentException $e) {
    http_response_code(400); echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(503); echo json_encode(['status'=>'error','message'=>'Address could not be saved.']);
}
