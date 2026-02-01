<?php
/**
 * Firebase-based QR Code Generation API for Vending Machine
 * File: api/generate_qr_firebase.php
 * 
 * This version uses Firebase Realtime Database instead of MySQL
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Firebase Configuration
$firebaseUrl = "https://vendo-machine-c75fb-default-rtdb.asia-southeast1.firebasedatabase.app";

// PayMongo API configuration
$paymongo_secret_key = 'sk_live_WLCpGs66PbqcMjBaMVsuK5k6'; // CHANGE THIS
$paymongo_api_url = 'https://api.paymongo.com/v1';

/**
 * Main function to handle API requests
 */
function handleRequest() {
    global $firebaseUrl, $paymongo_secret_key, $paymongo_api_url;
    
    // Check if this is an API call
    $content_type = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
    $is_api_call = (strpos($content_type, 'application/json') !== false);
    
    // Only accept POST requests for API
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$is_api_call) {
        sendErrorResponse('Invalid request method or content type', 405);
        return;
    }
    
    // Get POST data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    // Validate input
    if (!$data || !isset($data['payment_id']) || !isset($data['amount'])) {
        sendErrorResponse('Missing required fields: payment_id and amount', 400);
        return;
    }
    
    $payment_id = $data['payment_id'];
    $amount = intval($data['amount']); // Amount in centavos
    
    // Validate amount
    if ($amount < 100) {
        sendErrorResponse('Amount must be at least 100 centavos (1 PHP)', 400);
        return;
    }
    
    try {
        // Check if payment ID already exists in Firebase
        $existingPayment = getFromFirebase($firebaseUrl, "payments/$payment_id");
        
        if ($existingPayment !== null) {
            sendErrorResponse('Payment ID already exists', 409);
            return;
        }
        
        // Create PayMongo QR code
        $qr_result = createPayMongoQR($payment_id, $amount, $paymongo_secret_key, $paymongo_api_url);
        
        if (!$qr_result['success']) {
            sendErrorResponse('Failed to create QR code: ' . $qr_result['error'], 500);
            return;
        }
        
        // Store payment in Firebase
        $paymentData = [
            'payment_id' => $payment_id,
            'paymongo_source_id' => $qr_result['source_id'],
            'amount' => $amount,
            'currency' => 'PHP',
            'status' => 'pending',
            'qr_url' => $qr_result['qr_url'],
            'timestamp' => time(),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $saved = saveToFirebase($firebaseUrl, "payments/$payment_id", $paymentData);
        
        if (!$saved) {
            sendErrorResponse('Failed to save payment to Firebase', 500);
            return;
        }
        
        // Return success response
        sendSuccessResponse([
            'payment_id' => $payment_id,
            'source_id' => $qr_result['source_id'],
            'qr_url' => $qr_result['qr_url'],
            'amount' => $amount,
            'currency' => 'PHP',
            'status' => 'pending'
        ]);
        
    } catch (Exception $e) {
        error_log("Error: " . $e->getMessage());
        sendErrorResponse('Internal server error: ' . $e->getMessage(), 500);
    }
}

/**
 * Create PayMongo QR code payment source
 */
function createPayMongoQR($payment_id, $amount, $secret_key, $api_url) {
    $endpoint = $api_url . '/sources';
    
    $payload = [
        'data' => [
            'attributes' => [
                'type' => 'gcash',
                'amount' => $amount,
                'currency' => 'PHP',
                'redirect' => [
                    'success' => 'https://rain-shoe-cover-vendo.onrender.com/payment_success.php?payment_id=' . $payment_id,
                    'failed' => 'https://rain-shoe-cover-vendo.onrender.com/payment_failed.php?payment_id=' . $payment_id
                ],
                'billing' => [
                    'name' => 'Vending Machine Customer',
                    'email' => 'customer@vendingmachine.com'
                ]
            ]
        ]
    ];
    
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode($secret_key . ':')
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        return [
            'success' => false,
            'error' => 'cURL error: ' . $error
        ];
    }
    
    curl_close($ch);
    
    if ($http_code !== 200 && $http_code !== 201) {
        error_log("PayMongo API error: " . $response);
        return [
            'success' => false,
            'error' => 'PayMongo API returned status ' . $http_code
        ];
    }
    
    $result = json_decode($response, true);
    
    if (!$result || !isset($result['data']['id'])) {
        return [
            'success' => false,
            'error' => 'Invalid PayMongo response'
        ];
    }
    
    $qr_url = $result['data']['attributes']['checkout_url'] ?? null;
    
    if (!$qr_url) {
        return [
            'success' => false,
            'error' => 'No checkout URL in response'
        ];
    }
    
    return [
        'success' => true,
        'source_id' => $result['data']['id'],
        'qr_url' => $qr_url
    ];
}

/**
 * Save data to Firebase
 */
function saveToFirebase($firebaseUrl, $path, $data) {
    $ch = curl_init("$firebaseUrl/$path.json");
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ($httpCode === 200);
}

/**
 * Get data from Firebase
 */
function getFromFirebase($firebaseUrl, $path) {
    $ch = curl_init("$firebaseUrl/$path.json");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return null;
    }
    
    return json_decode($response, true);
}

/**
 * Send success response
 */
function sendSuccessResponse($data) {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $data
    ] + $data);
    exit();
}

/**
 * Send error response
 */
function sendErrorResponse($message, $code = 400) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => $message
    ]);
    exit();
}

// Execute main function
handleRequest();
?>
