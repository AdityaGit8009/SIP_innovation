<?php
session_start();
require_once __DIR__ . '/includes/security.php';
sip_check_request();
header('Content-Type: application/json');

if (isset($_SESSION['applied_coupon'])) {
    unset($_SESSION['applied_coupon']);
    echo json_encode(['status' => 'success', 'message' => 'Coupon removed successfully.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'No coupon is currently applied.']);
}
