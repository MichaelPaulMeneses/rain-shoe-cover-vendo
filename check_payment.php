<?php
/**
 * Payment Status Check API for Vending Machine (Firebase Version)
 * File: api/check_payment_firebase.php
 * 
 * This checks payment status from Firebase with expiration logic
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Firebase Configuration
$firebaseUrl = "https://vendo-machine-c75fb-default-rtdb.asia-southeast1.firebasedatabase.app";

// PayMongo API configuration (for status verification)
$paymongo_secret_key = 'sk_live_WLCpGs66PbqcMjBaMVsuK5k6'; // CHANGE THIS
$paymongo_api_url = 'https://api.paymongo.com/v1';

// Get payment_id from query parameter (support both payment_id and intent_id)
$paymentId = $_GET['payment_id'] ?? $_GET['intent_id'] ?? '';

if (empty($paymentId)) {
    echo json_encode([
        "success" => false,
        "error" => "Missing payment_id or intent_id parameter"
    ]);
    exit;
}

try {
    // Fetch payment status from Firebase
    $ch = curl_init("$firebaseUrl/payments/$paymentId.json");
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
    
    // Check if payment has expired (2 minutes = 120 seconds)
    $timestamp = $paymentData['timestamp'] ?? 0;
    $currentTime = time();
    $elapsed = $currentTime - $timestamp;
    
    // If payment is still pending and has expired, mark as expired
    if ($elapsed > 120 && $paymentData['status'] !== 'paid') {
        // Update status to expired in Firebase
        $updateData = ["status" => "expired"];
        
        $ch = curl_init("$firebaseUrl/payments/$paymentId.json");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($updateData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_exec($ch);
        curl_close($ch);
        
        $paymentData['status'] = 'expired';
    }
    
    // If still pending and not expired, check with PayMongo API
    if ($paymentData['status'] === 'pending' && $elapsed <= 120) {
        $sourceId = $paymentData['paymongo_source_id'] ?? null;
        
        if ($sourceId) {
            $paymongoStatus = checkPayMongoStatus($sourceId, $paymongo_secret_key, $paymongo_api_url);
            
            // If PayMongo says it's chargeable/paid, update Firebase
            if ($paymongoStatus['success'] && $paymongoStatus['status'] === 'chargeable') {
                $updateData = [
                    "status" => "paid",
                    "paid_at" => time()
                ];
                
                $ch = curl_init("$firebaseUrl/payments/$paymentId.json");
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($updateData));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_exec($ch);
                curl_close($ch);
                
                $paymentData['status'] = 'paid';
            }
        }
    }
    
    // Return payment status
    echo json_encode([
        "success" => true,
        "payment_id" => $paymentId,
        "intent_id" => $paymentId, // Compatibility
        "status" => $paymentData['status'],
        "amount" => $paymentData['amount'] ?? 0,
        "currency" => $paymentData['currency'] ?? 'PHP',
        "timestamp" => $paymentData['timestamp'] ?? 0,
        "elapsed_seconds" => $elapsed,
        "paid" => ($paymentData['status'] === 'paid'),
        "expired" => ($paymentData['status'] === 'expired'),
        "pending" => ($paymentData['status'] === 'pending')
    ]);
    
} catch (Exception $e) {
    error_log("Error in check_payment: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "error" => "Internal server error: " . $e->getMessage()
    ]);
}

/**
 * Check payment status with PayMongo API
 */
function checkPayMongoStatus($source_id, $secret_key, $api_url) {
    $endpoint = $api_url . '/sources/' . $source_id;
    
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
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
    
    if ($http_code !== 200) {
        return [
            'success' => false,
            'error' => 'PayMongo API returned status ' . $http_code
        ];
    }
    
    $result = json_decode($response, true);
    
    if (!$result || !isset($result['data']['attributes']['status'])) {
        return [
            'success' => false,
            'error' => 'Invalid PayMongo response'
        ];
    }
    
    $status = $result['data']['attributes']['status'];
    
    return [
        'success' => true,
        'status' => $status
    ];
}
?>
