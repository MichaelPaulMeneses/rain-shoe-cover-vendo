<?php
/**
 * PayMongo QR Code Generation for ESP32
 * Returns QR code image directly to ESP32 display
 */

header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, no-store, must-revalidate');

$secretKey = "sk_live_WLCpGs66PbqcMjBaMVsuK5k6";

// Get payment ID from ESP32
$espPaymentId = isset($_GET['id']) ? $_GET['id'] : '';

if (empty($espPaymentId)) {
    http_response_code(400);
    exit('Missing payment ID');
}

/* -------------------------------
 STEP 1: Create Payment Intent
-------------------------------- */
$intentData = [
    "data" => [
        "attributes" => [
            "amount" => 1000, // ₱20.00 (amount in centavos)
            "currency" => "PHP",
            "payment_method_allowed" => ["qrph"],
            "capture_type" => "automatic",
            "description" => "Vendo Machine - Rain Shoe Cover"
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
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode != 200 && $httpCode != 201) {
    error_log("PayMongo Intent Error: " . $intentResponse);
    http_response_code(500);
    exit('Payment Intent creation failed');
}

$intent = json_decode($intentResponse, true);
$intentId = $intent["data"]["id"] ?? null;

if (!$intentId) {
    error_log("No intent ID received");
    http_response_code(500);
    exit('Invalid intent response');
}

/* -------------------------------
 STEP 2: Create QR Ph Payment Method
-------------------------------- */
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

$methodResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode != 200 && $httpCode != 201) {
    error_log("PayMongo Method Error: " . $methodResponse);
    http_response_code(500);
    exit('Payment Method creation failed');
}

$method = json_decode($methodResponse, true);
$methodId = $method["data"]["id"] ?? null;

if (!$methodId) {
    error_log("No method ID received");
    http_response_code(500);
    exit('Invalid method response');
}

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
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode != 200 && $httpCode != 201) {
    error_log("PayMongo Attach Error: " . $attachResponse);
    http_response_code(500);
    exit('Payment Method attachment failed');
}

$result = json_decode($attachResponse, true);

/* -------------------------------
 STEP 4: Save to Firebase
-------------------------------- */
$firebaseUrl = "https://vendo-machine-c75fb-default-rtdb.asia-southeast1.firebasedatabase.app/payments/$intentId.json";

$firebaseData = [
    "esp_id" => $espPaymentId,
    "datetime" => date("Y-m-d H:i:s"),
    "status" => "pending",
    "amount" => 2000,
    "description" => "Vendo Machine - Rain Shoe Cover",
    "payment_intent_id" => $intentId,
    "payment_method_id" => $methodId
];

$ch = curl_init($firebaseUrl);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($firebaseData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);

$resultFirebase = curl_exec($ch);
curl_close($ch);

/* -------------------------------
 STEP 5: Return QR Image to ESP32
-------------------------------- */
if (isset($result["data"]["attributes"]["next_action"]["code"]["image_url"])) {
    
    $qrBase64 = $result["data"]["attributes"]["next_action"]["code"]["image_url"];
    
    // Extract base64 data (remove "data:image/png;base64," prefix)
    if (strpos($qrBase64, 'base64,') !== false) {
        $qrBase64Data = explode('base64,', $qrBase64)[1];
    } else {
        $qrBase64Data = $qrBase64;
    }
    
    // Decode base64 image
    $imageData = base64_decode($qrBase64Data);
    
    if ($imageData === false) {
        error_log("Failed to decode base64 QR image");
        http_response_code(500);
        exit('QR decode failed');
    }
    
    // Create image from PNG data
    $sourceImage = imagecreatefromstring($imageData);
    
    if ($sourceImage === false) {
        error_log("Failed to create image from string");
        http_response_code(500);
        exit('Image creation failed');
    }
    
    // Resize to 145x145 for ESP32 screen
    $resizedImage = imagecreatetruecolor(145, 145);
    
    // Make background white
    $white = imagecolorallocate($resizedImage, 255, 255, 255);
    imagefill($resizedImage, 0, 0, $white);
    
    // Get original dimensions
    $origWidth = imagesx($sourceImage);
    $origHeight = imagesy($sourceImage);
    
    // Resize and copy
    imagecopyresampled(
        $resizedImage, $sourceImage,
        0, 0, 0, 0,
        145, 145,
        $origWidth, $origHeight
    );
    
    // Output as JPEG
    header('Content-Type: image/jpeg');
    imagejpeg($resizedImage, null, 85);
    
    // Clean up
    imagedestroy($sourceImage);
    imagedestroy($resizedImage);
    
    // Log success
    error_log("QR generated successfully for ESP32 payment ID: $espPaymentId, PayMongo Intent: $intentId");
    
} else {
    error_log("No QR image in PayMongo response: " . json_encode($result));
    http_response_code(500);
    exit('No QR image in response');
}
?>
