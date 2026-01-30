<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// PayMongo API Configuration
$secretKey = "sk_live_WLCpGs66PbqcMjBaMVsuK5k6";
$firebaseUrl = "https://vendo-machine-c75fb-default-rtdb.asia-southeast1.firebasedatabase.app";

// Amount in centavos (2000 = ₱20.00)
$amount = 2000;

/* ========================================
   STEP 1: Create Payment Intent
======================================== */
$intentData = [
    "data" => [
        "attributes" => [
            "amount" => $amount,
            "currency" => "PHP",
            "payment_method_allowed" => ["qrph"],
            "capture_type" => "automatic",
            "description" => "Rain Shoe Cover - Vending Machine"
        ]
    ]
];

$ch = curl_init("https://api.paymongo.com/v1/payment_intents");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Basic " . base64_encode($secretKey . ":"),
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($intentData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

$intentResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 && $httpCode !== 201) {
    echo json_encode([
        "success" => false,
        "error" => "Failed to create payment intent",
        "http_code" => $httpCode,
        "response" => $intentResponse
    ]);
    exit;
}

$intent = json_decode($intentResponse, true);

if (!isset($intent["data"]["id"])) {
    echo json_encode([
        "success" => false,
        "error" => "Invalid intent response",
        "response" => $intent
    ]);
    exit;
}

$intentId = $intent["data"]["id"];

/* ========================================
   STEP 2: Create QR Ph Payment Method
======================================== */
$methodData = [
    "data" => [
        "attributes" => [
            "type" => "qrph",
            "expiry_seconds" => 120 // 2 minutes
        ]
    ]
];

$ch = curl_init("https://api.paymongo.com/v1/payment_methods");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Basic " . base64_encode($secretKey . ":"),
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($methodData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

$methodResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 && $httpCode !== 201) {
    echo json_encode([
        "success" => false,
        "error" => "Failed to create payment method",
        "http_code" => $httpCode,
        "response" => $methodResponse
    ]);
    exit;
}

$method = json_decode($methodResponse, true);

if (!isset($method["data"]["id"])) {
    echo json_encode([
        "success" => false,
        "error" => "Invalid method response",
        "response" => $method
    ]);
    exit;
}

$methodId = $method["data"]["id"];

/* ========================================
   STEP 3: Attach Payment Method to Intent
======================================== */
$attachData = [
    "data" => [
        "attributes" => [
            "payment_method" => $methodId
        ]
    ]
];

$ch = curl_init("https://api.paymongo.com/v1/payment_intents/$intentId/attach");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Basic " . base64_encode($secretKey . ":"),
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($attachData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

$attachResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 && $httpCode !== 201) {
    echo json_encode([
        "success" => false,
        "error" => "Failed to attach payment method",
        "http_code" => $httpCode,
        "response" => $attachResponse
    ]);
    exit;
}

$result = json_decode($attachResponse, true);

/* ========================================
   STEP 4: Extract QR Code Image
======================================== */
if (!isset($result["data"]["attributes"]["next_action"]["code"]["image_url"])) {
    echo json_encode([
        "success" => false,
        "error" => "No QR code generated",
        "response" => $result
    ]);
    exit;
}

$qrBase64Url = $result["data"]["attributes"]["next_action"]["code"]["image_url"];
$intentId = $result["data"]["id"];
$status = $result["data"]["attributes"]["status"];

/* ========================================
   STEP 5: Save to Firebase
======================================== */
$firebaseData = [
    "intent_id" => $intentId,
    "payment_method_id" => $methodId,
    "datetime" => date("Y-m-d H:i:s"),
    "timestamp" => time(),
    "status" => $status,
    "amount" => $amount,
    "currency" => "PHP",
    "description" => "Rain Shoe Cover - Vending Machine",
    "qr_expires_at" => date("Y-m-d H:i:s", time() + 120)
];

$ch = curl_init("$firebaseUrl/payments/$intentId.json");
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($firebaseData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);

$firebaseResponse = curl_exec($ch);
curl_close($ch);

/* ========================================
   STEP 6: Return Response
======================================== */
echo json_encode([
    "success" => true,
    "intent_id" => $intentId,
    "payment_method_id" => $methodId,
    "amount" => $amount,
    "currency" => "PHP",
    "status" => $status,
    "qr_code_url" => $qrBase64Url,
    "expires_in" => 120,
    "firebase_saved" => ($firebaseResponse !== false)
]);
?>
