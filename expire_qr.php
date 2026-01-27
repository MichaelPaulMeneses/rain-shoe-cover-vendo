<?php
$payload = file_get_contents("php://input");
$event = json_decode($payload, true);

if (!$event || !isset($event["data"]["attributes"]["type"])) {
    http_response_code(400);
    exit;
}

$type = $event["data"]["attributes"]["type"];

if ($type === "qrph.expired") {

    $intentId = $event['data']['attributes']['data']['attributes']['payment_intent_id'] ?? null;

    if (!$intentId) {
        http_response_code(200);
        exit;
    }

    $firebaseUrl = "https://vendo-machine-c75fb-default-rtdb.asia-southeast1.firebasedatabase.app/payments/$intentId.json";

    /* -------------------------
       1. GET current status
    --------------------------*/
    $ch = curl_init($firebaseUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $currentData = json_decode($response, true);
    $currentStatus = $currentData['status'] ?? null;

    // 🚫 Do NOT overwrite these states
    if (in_array($currentStatus, ['paid', 'dispensing'])) {
        http_response_code(200);
        exit;
    }

    /* -------------------------
       2. Update to expired
    --------------------------*/
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
}

http_response_code(200);
