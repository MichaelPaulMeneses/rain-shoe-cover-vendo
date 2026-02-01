<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
// Firebase Configuration
$firebaseUrl = "https://vendo-machine-c75fb-default-rtdb.asia-southeast1.firebasedatabase.app";
// Get intent_id from query parameter
$intentId = $_GET['intent_id'] ?? '';
if (empty($intentId)) {
    echo json_encode([
        "success" => false,
        "error" => "Missing intent_id parameter"
    ]);
    exit;
}
// Fetch payment status from Firebase
$ch = curl_init("$firebaseUrl/payments/$intentId.json");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
if ($httpCode !== 200) {
    echo json_encode([
        "success" => false,
        "error" => "Failed to fetch payment status",
        "http_code" => $httpCode
    ]);
    exit;
}
$paymentData = json_decode($response, true);
if (!$paymentData) {
    echo json_encode([
        "success" => false,
        "error" => "Payment not found"
    ]);
    exit;
}
// Check if payment has expired (2 minutes)
$timestamp = $paymentData['timestamp'] ?? 0;
$currentTime = time();
$elapsed = $currentTime - $timestamp;
if ($elapsed > 120 && $paymentData['status'] !== 'paid') {
    // Update status to expired in Firebase
    $updateData = ["status" => "expired"];

    $ch = curl_init("$firebaseUrl/payments/$intentId.json");
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($updateData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_exec($ch);
    curl_close($ch);

    $paymentData['status'] = 'expired';
}
// Return payment status
echo json_encode([
    "success" => true,
    "intent_id" => $intentId,
    "status" => $paymentData['status'],
    "amount" => $paymentData['amount'] ?? 0,
    "timestamp" => $paymentData['timestamp'] ?? 0,
    "elapsed_seconds" => $elapsed,
    "paid" => ($paymentData['status'] === 'paid'),
    "expired" => ($paymentData['status'] === 'expired')
]);
?>
