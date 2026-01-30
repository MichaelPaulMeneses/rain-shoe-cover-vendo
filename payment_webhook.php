<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Firebase Configuration
$firebaseUrl = "https://vendo-machine-c75fb-default-rtdb.asia-southeast1.firebasedatabase.app";

// PayMongo webhook secret (get this from PayMongo dashboard)
$webhookSecret = "whsec_your_webhook_secret_here"; // Replace with your actual webhook secret

/* ========================================
   Verify PayMongo Webhook Signature
======================================== */
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '';

// Log webhook for debugging
file_put_contents('webhook_log.txt', date('Y-m-d H:i:s') . "\n" . $payload . "\n\n", FILE_APPEND);

// Optional: Verify signature (recommended for production)
// $computedSignature = hash_hmac('sha256', $payload, $webhookSecret);
// if (!hash_equals($signature, $computedSignature)) {
//     http_response_code(401);
//     echo json_encode(["error" => "Invalid signature"]);
//     exit;
// }

/* ========================================
   Process Webhook Event
======================================== */
$event = json_decode($payload, true);

if (!isset($event['data']['attributes']['type'])) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid webhook payload"]);
    exit;
}

$eventType = $event['data']['attributes']['type'];
$eventData = $event['data']['attributes']['data'];

// Only process payment.paid events
if ($eventType === 'payment.paid') {
    
    $paymentIntentId = $eventData['attributes']['payment_intent_id'] ?? null;
    $status = $eventData['attributes']['status'] ?? 'unknown';
    $amount = $eventData['attributes']['amount'] ?? 0;
    
    if ($paymentIntentId) {
        // Update Firebase with payment status
        $updateData = [
            "status" => "paid",
            "paid_at" => date("Y-m-d H:i:s"),
            "paid_timestamp" => time(),
            "amount_paid" => $amount,
            "webhook_received" => true
        ];
        
        $ch = curl_init("$firebaseUrl/payments/$paymentIntentId.json");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($updateData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        
        $result = curl_exec($ch);
        curl_close($ch);
        
        // Log success
        file_put_contents('webhook_log.txt', 
            "✓ Payment confirmed: $paymentIntentId\n\n", 
            FILE_APPEND
        );
    }
}

// Handle payment.failed events
if ($eventType === 'payment.failed') {
    $paymentIntentId = $eventData['attributes']['payment_intent_id'] ?? null;
    
    if ($paymentIntentId) {
        $updateData = [
            "status" => "failed",
            "failed_at" => date("Y-m-d H:i:s"),
            "webhook_received" => true
        ];
        
        $ch = curl_init("$firebaseUrl/payments/$paymentIntentId.json");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($updateData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        
        curl_exec($ch);
        curl_close($ch);
    }
}

// Respond to PayMongo
http_response_code(200);
echo json_encode(["received" => true]);
?>
