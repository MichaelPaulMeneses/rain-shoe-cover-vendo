<?php
header('Content-Type: application/json');

// Log errors to help debug
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in response
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/mobile_payment_errors.log');

$secretKey = "sk_live_WLCpGs66PbqcMjBaMVsuK5k6";

// Get POST data
$input = file_get_contents("php://input");
$data = json_decode($input, true);

// Log the incoming request
file_put_contents(
    __DIR__ . "/mobile_payment_log.txt",
    date("Y-m-d H:i:s") . " Request: " . $input . PHP_EOL,
    FILE_APPEND
);

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
        'message' => 'Invalid mobile number format. Must be +639XXXXXXXXX'
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
        $errorResponse = json_decode($intentResponse, true);
        $errorMsg = $errorResponse['errors'][0]['detail'] ?? 'Failed to create payment intent';
        
        file_put_contents(
            __DIR__ . "/mobile_payment_log.txt",
            date("Y-m-d H:i:s") . " Intent Error: " . $intentResponse . PHP_EOL,
            FILE_APPEND
        );
        
        throw new Exception($errorMsg);
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
        $errorResponse = json_decode($methodResponse, true);
        $errorMsg = $errorResponse['errors'][0]['detail'] ?? 'Failed to create payment method';
        
        file_put_contents(
            __DIR__ . "/mobile_payment_log.txt",
            date("Y-m-d H:i:s") . " Method Error: " . $methodResponse . PHP_EOL,
            FILE_APPEND
        );
        
        throw new Exception($errorMsg);
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
        $errorResponse = json_decode($attachResponse, true);
        $errorMsg = $errorResponse['errors'][0]['detail'] ?? 'Failed to attach payment method';
        
        file_put_contents(
            __DIR__ . "/mobile_payment_log.txt",
            date("Y-m-d H:i:s") . " Attach Error: " . $attachResponse . PHP_EOL,
            FILE_APPEND
        );
        
        throw new Exception($errorMsg);
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
    
    $response = [
        'success' => true,
        'intent_id' => $intentId,
        'status' => $result["data"]["attributes"]["status"],
        'next_action' => $nextAction,
        'message' => 'Payment request sent successfully. Please check your ' . ($wallet === 'gcash' ? 'GCash' : 'Maya') . ' app.'
    ];
    
    file_put_contents(
        __DIR__ . "/mobile_payment_log.txt",
        date("Y-m-d H:i:s") . " Success Response: " . json_encode($response) . PHP_EOL,
        FILE_APPEND
    );
    
    echo json_encode($response);

} catch (Exception $e) {
    $errorResponse = [
        'success' => false,
        'message' => $e->getMessage()
    ];
    
    file_put_contents(
        __DIR__ . "/mobile_payment_log.txt",
        date("Y-m-d H:i:s") . " Error Response: " . json_encode($errorResponse) . PHP_EOL,
        FILE_APPEND
    );
    
    echo json_encode($errorResponse);
}
?>
