<?php
// Get intent ID (POST or JSON)
$intentId = $_POST['intent_id'] ?? null;

if (!$intentId) {
    $raw = file_get_contents("php://input");
    $json = json_decode($raw, true);
    $intentId = $json['intent_id'] ?? null;
}

if (!$intentId) {
    http_response_code(400);
    echo "Missing intent_id";
    exit;
}

$firebaseUrl = "https://vendo-machine-dbb9a-default-rtdb.asia-southeast1.firebasedatabase.app/payments/$intentId.json";

/* -------------------------
   GET current status
--------------------------*/
$ch = curl_init($firebaseUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$currentData = json_decode($response, true);
$currentStatus = $currentData['status'] ?? null;

// 🚫 Protect final states
if (in_array($currentStatus, ['expired', 'paid', 'dispensing'])) {
    http_response_code(200);
    echo "Status protected: $currentStatus";
    exit;
}

$update = [
    "status" => "expired"
];

$ch = curl_init($firebaseUrl);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($update));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
curl_exec($ch);
curl_close($ch);

http_response_code(200);
echo "Intent $intentId cancelled";
