<?php
/**
 * PayMongo Webhook Handler for Vending Machine (Firebase Version)
 * File: api/webhook_firebase.php
 * 
 * This receives webhook notifications from PayMongo and updates Firebase
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 1 for debugging

// Log file
$log_file = __DIR__ . '/webhook_log.txt';
file_put_contents($log_file, date('Y-m-d H:i:s') . " - Webhook received\n", FILE_APPEND);

// Firebase Configuration
$firebaseUrl = "https://vendo-machine-c75fb-default-rtdb.asia-southeast1.firebasedatabase.app";

// PayMongo webhook secret (for signature verification)
$webhook_secret = 'whsec_YOUR_WEBHOOK_SECRET_HERE'; // CHANGE THIS

/**
 * Main webhook handler
 */
function handleWebhook() {
    global $firebaseUrl, $webhook_secret, $log_file;
    
    // Only accept POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Method not allowed');
    }
    
    // Get raw POST data
    $payload = file_get_contents('php://input');
    
    // Log the payload
    file_put_contents($log_file, "Payload: " . $payload . "\n", FILE_APPEND);
    
    // Verify webhook signature (recommended for production)
    $signature = isset($_SERVER['HTTP_PAYMONGO_SIGNATURE']) ? $_SERVER['HTTP_PAYMONGO_SIGNATURE'] : '';
    
    if ($webhook_secret && $signature) {
        $computed_signature = hash_hmac('sha256', $payload, $webhook_secret);
        if (!hash_equals($computed_signature, $signature)) {
            file_put_contents($log_file, "Invalid signature\n", FILE_APPEND);
            http_response_code(401);
            exit('Invalid signature');
        }
    }
    
    // Parse JSON payload
    $data = json_decode($payload, true);
    
    if (!$data) {
        file_put_contents($log_file, "Invalid JSON\n", FILE_APPEND);
        http_response_code(400);
        exit('Invalid JSON');
    }
    
    // Log event type
    $event_type = $data['data']['attributes']['type'] ?? 'unknown';
    file_put_contents($log_file, "Event type: $event_type\n", FILE_APPEND);
    
    try {
        // Handle different event types
        switch ($event_type) {
            case 'source.chargeable':
                handleSourceChargeable($data, $firebaseUrl, $log_file);
                break;
                
            case 'payment.paid':
                handlePaymentPaid($data, $firebaseUrl, $log_file);
                break;
                
            case 'payment.failed':
                handlePaymentFailed($data, $firebaseUrl, $log_file);
                break;
                
            default:
                file_put_contents($log_file, "Unhandled event type: $event_type\n", FILE_APPEND);
        }
        
        // Log the webhook event to Firebase
        logWebhookEvent($firebaseUrl, $event_type, $payload);
        
        // Return success
        http_response_code(200);
        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        file_put_contents($log_file, "Error: " . $e->getMessage() . "\n", FILE_APPEND);
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Handle source.chargeable event (payment successful)
 */
function handleSourceChargeable($data, $firebaseUrl, $log_file) {
    $source_id = $data['data']['attributes']['data']['id'] ?? null;
    
    if (!$source_id) {
        file_put_contents($log_file, "No source_id in payload\n", FILE_APPEND);
        return;
    }
    
    file_put_contents($log_file, "Source chargeable: $source_id\n", FILE_APPEND);
    
    // Find payment by source_id in Firebase
    $payments = getFromFirebase($firebaseUrl, "payments");
    
    if ($payments) {
        foreach ($payments as $payment_id => $payment) {
            if (isset($payment['paymongo_source_id']) && $payment['paymongo_source_id'] === $source_id) {
                // Update payment status
                $updateData = [
                    "status" => "paid",
                    "paid_at" => time()
                ];
                
                updateFirebase($firebaseUrl, "payments/$payment_id", $updateData);
                file_put_contents($log_file, "Updated payment $payment_id to paid\n", FILE_APPEND);
                break;
            }
        }
    }
}

/**
 * Handle payment.paid event
 */
function handlePaymentPaid($data, $firebaseUrl, $log_file) {
    // Try to get payment_id from metadata or attributes
    $payment_id = $data['data']['attributes']['data']['attributes']['metadata']['payment_id'] ?? null;
    
    if (!$payment_id) {
        // Try alternative path
        $payment_id = $data['data']['attributes']['data']['id'] ?? null;
    }
    
    if (!$payment_id) {
        file_put_contents($log_file, "No payment_id found in payload\n", FILE_APPEND);
        return;
    }
    
    file_put_contents($log_file, "Payment paid: $payment_id\n", FILE_APPEND);
    
    $updateData = [
        "status" => "paid",
        "paid_at" => time()
    ];
    
    updateFirebase($firebaseUrl, "payments/$payment_id", $updateData);
    file_put_contents($log_file, "Updated payment $payment_id to paid\n", FILE_APPEND);
}

/**
 * Handle payment.failed event
 */
function handlePaymentFailed($data, $firebaseUrl, $log_file) {
    $payment_id = $data['data']['attributes']['data']['attributes']['metadata']['payment_id'] ?? null;
    
    if (!$payment_id) {
        $payment_id = $data['data']['attributes']['data']['id'] ?? null;
    }
    
    if (!$payment_id) {
        file_put_contents($log_file, "No payment_id found in payload\n", FILE_APPEND);
        return;
    }
    
    file_put_contents($log_file, "Payment failed: $payment_id\n", FILE_APPEND);
    
    $updateData = [
        "status" => "failed",
        "failed_at" => time()
    ];
    
    updateFirebase($firebaseUrl, "payments/$payment_id", $updateData);
    file_put_contents($log_file, "Updated payment $payment_id to failed\n", FILE_APPEND);
}

/**
 * Update data in Firebase
 */
function updateFirebase($firebaseUrl, $path, $data) {
    $ch = curl_init("$firebaseUrl/$path.json");
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
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
 * Log webhook event to Firebase
 */
function logWebhookEvent($firebaseUrl, $event_type, $payload) {
    $logData = [
        'event_type' => $event_type,
        'payload' => $payload,
        'timestamp' => time(),
        'date' => date('Y-m-d H:i:s')
    ];
    
    $logId = 'webhook_' . time() . '_' . rand(1000, 9999);
    
    $ch = curl_init("$firebaseUrl/webhook_logs/$logId.json");
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($logData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_exec($ch);
    curl_close($ch);
}

// Execute main function
handleWebhook();
?>
