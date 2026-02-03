<?php
header('Content-Type: application/json');

$secretKey = "sk_live_WLCpGs66PbqcMjBaMVsuK5k6";

// Get POST data
$input = file_get_contents("php://input");
$data = json_decode($input, true);

$mobileNumber = $data['mobile'] ?? null;
$wallet = $data['wallet'] ?? 'gcash';

// Validation
if (!$mobileNumber) {
    echo json_encode([
        'success' => false,
        'message' => 'Mobile number is required'
    ]);
    exit;
}

// Validate Philippine mobile number format
if (!preg_match('/^\+639\d{9}$/', $mobileNumber)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid Philippine mobile number format'
    ]);
    exit;
}

try {
    /* -------------------------------
     STEP 1: Create Payment Intent
    -------------------------------- */
    $intentData = [
        "data" => [
            "attributes" => [
                "amount" => 100, // ₱1.00
                "currency" => "PHP",
                "payment_method_allowed" => ["gcash", "paymaya"], // Allow both wallets
                "capture_type" => "automatic",
                "description" => "Vendo Machine Payment"
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

    if ($httpCode !== 200) {
        throw new Exception("Failed to create payment intent");
    }

    $intent = json_decode($intentResponse, true);
    $intentId = $intent["data"]["id"];
    $clientKey = $intent["data"]["attributes"]["client_key"];

    /* -------------------------------
     STEP 2: Create GCash/Maya Payment Method with Mobile Number
    -------------------------------- */
    $paymentType = $wallet === 'gcash' ? 'gcash' : 'paymaya';
    
    $methodData = [
        "data" => [
            "attributes" => [
                "type" => $paymentType,
                "billing" => [
                    "phone" => $mobileNumber
                ]
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

    if ($httpCode !== 200) {
        throw new Exception("Failed to create payment method");
    }

    $method = json_decode($methodResponse, true);
    $methodId = $method["data"]["id"];

    /* -------------------------------
     STEP 3: Attach Payment Method to Intent
    -------------------------------- */
    $attachData = [
        "data" => [
            "attributes" => [
                "payment_method" => $methodId,
                "client_key" => $clientKey,
                "return_url" => "https://yourdomain.com/payment_success.php" // Optional: redirect after payment
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

    if ($httpCode !== 200) {
        throw new Exception("Failed to attach payment method");
    }

    $result = json_decode($attachResponse, true);
    
    /* -------------------------------
     STEP 4: Store in Firebase
    -------------------------------- */
    $firebaseUrl = "https://vendo-machine-c75fb-default-rtdb.asia-southeast1.firebasedatabase.app/payments/$intentId.json";

    $firebaseData = [
        "datetime" => date("Y-m-d H:i:s"),
        "status" => "pending",
        "amount" => 100,
        "description" => "Vendo Machine Payment",
        "payment_method" => $paymentType,
        "mobile_number" => $mobileNumber
    ];

    $ch = curl_init($firebaseUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($firebaseData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    
    curl_exec($ch);
    curl_close($ch);

    /* -------------------------------
     STEP 5: Return Success Response
    -------------------------------- */
    $nextAction = $result["data"]["attributes"]["next_action"] ?? null;
    
    echo json_encode([
        'success' => true,
        'intent_id' => $intentId,
        'status' => $result["data"]["attributes"]["status"],
        'next_action' => $nextAction,
        'message' => 'Payment request sent successfully'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
