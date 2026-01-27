<?php
header('Access-Control-Allow-Origin: https://vendo-machine-dbb9a.web.app');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$secretKey = "sk_live_WLCpGs66PbqcMjBaMVsuK5k6";

/* -------------------------------
 STEP 1: Create Payment Intent
-------------------------------- */
$intentData = [
    "data" => [
        "attributes" => [
            "amount" => 1, // ₱100.00
            "currency" => "PHP",
            "payment_method_allowed" => ["qrph"],
            "capture_type" => "automatic",
            "description" => "Vendo Machine Purchase"
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

$intentResponse = curl_exec($ch);
curl_close($ch);

$intent = json_decode($intentResponse, true);
$intentId = $intent["data"]["id"];

/* -------------------------------
 STEP 2: Create QR Ph Payment Method
-------------------------------- */
$methodData = [
    "data" => [
        "attributes" => [
            "type" => "qrph",
            "expiry_seconds" => 60
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

$methodResponse = curl_exec($ch);
curl_close($ch);

$method = json_decode($methodResponse, true);
$methodId = $method["data"]["id"];

/* -------------------------------
 STEP 3: Attach Payment Method
-------------------------------- */
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

$attachResponse = curl_exec($ch);
curl_close($ch);

$result = json_decode($attachResponse, true);

/* -------------------------------
 STEP 4: Show QR
-------------------------------- */
$intentId = $result["data"]["id"]; // payment intent ID

// -------------------------------
// Add Firebase record for ESP32
// -------------------------------

$firebaseUrl = "https://vendo-machine-dbb9a-default-rtdb.asia-southeast1.firebasedatabase.app//payments/$intentId.json";

$firebaseData = [
    "datetime" => date("Y-m-d H:i:s"),
    "status" => "pending",
    "amount" => 10000, 
    "description" => "Vendo Machine Purchase"
];

$ch = curl_init($firebaseUrl);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT"); // create/update
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($firebaseData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);

$resultFirebase = curl_exec($ch);
curl_close($ch);

// -------------------------------
// Show QR to user
// -------------------------------
if (isset($result["data"]["attributes"]["next_action"]["code"]["image_url"])) {
    
    $qrBase64 = $result["data"]["attributes"]["next_action"]["code"]["image_url"];
    echo '<div id="qrWrapper" data-intent-id="'.$intentId.'">
            <img src="'.$qrBase64.'" alt="QR Code" width="300">
        </div>';

} else {
    echo "<pre>";
    print_r($result);
    echo "</pre>";
    exit;
}

?>

