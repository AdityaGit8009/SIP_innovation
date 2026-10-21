<?php
function sip_verify_captured_payment(string $paymentId, string $orderId, int $amount): bool
{
    if (!preg_match('/^pay_[A-Za-z0-9]+$/', $paymentId) || !RAZORPAY_KEY_SECRET) return false;
    $ch = curl_init('https://api.razorpay.com/v1/payments/' . rawurlencode($paymentId));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_USERPWD=>RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET,
        CURLOPT_TIMEOUT=>20, CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2]);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($response === false || $status !== 200) return false;
    $data=json_decode($response,true);
    return is_array($data) && ($data['id'] ?? '') === $paymentId && ($data['order_id'] ?? '') === $orderId
        && ($data['status'] ?? '') === 'captured' && ($data['currency'] ?? '') === 'INR'
        && (int)($data['amount'] ?? 0) === $amount;
}
