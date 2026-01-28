<?php
/**
 * Payment Status Check for ESP32
 * Checks Firebase for payment status
 * 
 * Usage: /check?id=ESP_PAYMENT_ID
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Access-Control-Allow-Origin: *');

// Get ESP32 payment ID
$espPaymentId = isset($_GET['id']) ? $_GET['id'] : '';

if (empty($espPaymentId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing payment ID']);
    exit();
}

// Query Firebase to find payment by ESP ID
$firebaseUrl = "https://vendo-machine-c75fb-default-rtdb.asia-southeast1.firebasedatabase.app/payments.json";

$ch = curl_init($firebaseUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode != 200) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Firebase connection failed'
    ]);
    exit();
}

$allPayments = json_decode($response, true);

if (!$allPayments || !is_array($allPayments)) {
    echo json_encode([
        'status' => 'pending',
        'id' => $espPaymentId
    ]);
    exit();
}

// Find payment by ESP ID
$paymentFound = null;
foreach ($allPayments as $intentId => $payment) {
    if (isset($payment['esp_id']) && $payment['esp_id'] === $espPaymentId) {
        $paymentFound = $payment;
        break;
    }
}

if (!$paymentFound) {
    echo json_encode([
        'status' => 'pending',
        'id' => $espPaymentId,
        'message' => 'Payment not found'
    ]);
    exit();
}

// Check payment status
$status = $paymentFound['status'] ?? 'pending';

if ($status === 'paid') {
    echo json_encode([
        'status' => 'paid',
        'id' => $espPaymentId,
        'amount' => $paymentFound['amount'] ?? 0,
        'datetime' => $paymentFound['datetime'] ?? ''
    ]);
} elseif ($status === 'expired') {
    echo json_encode([
        'status' => 'expired',
        'id' => $espPaymentId
    ]);
} else {
    echo json_encode([
        'status' => 'pending',
        'id' => $espPaymentId
    ]);
}
?>
