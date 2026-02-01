<?php
$secretKey = "sk_live_WLCpGs66PbqcMjBaMVsuK5k6";

/* -------------------------------
 STEP 1: Create Payment Intent
-------------------------------- */
$intentData = [
    "data" => [
        "attributes" => [
            "amount" => 100, // ₱1.00
            "currency" => "PHP",
            "payment_method_allowed" => ["qrph"],
            "capture_type" => "automatic",
            "description" => "Vendo Machine QR Test"
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

$firebaseUrl = "https://vendo-machine-c75fb-default-rtdb.asia-southeast1.firebasedatabase.app/payments/$intentId.json";


$firebaseData = [
    "datetime" => date("Y-m-d H:i:s"),
    "status" => "pending",
    "amount" => 100, 
    "description" => "Vendo Machine QR Test"
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
    $qrCodeUrl = $result["data"]["attributes"]["next_action"]["code"]["value"] ?? '';
    
    echo '<div id="qrWrapper" data-intent-id="'.$intentId.'" data-qr-url="'.htmlspecialchars($qrCodeUrl, ENT_QUOTES).'">
            <img src="'.$qrBase64.'" alt="QR Code" width="300">
            <div class="mt-3">
                <p class="text-muted small mb-2">Or open directly in your app:</p>
                <div class="d-flex gap-2 justify-content-center">
                    <button class="btn btn-outline-primary btn-sm open-gcash" data-qr-url="'.htmlspecialchars($qrCodeUrl, ENT_QUOTES).'">
                        <svg width="16" height="16" fill="currentColor" class="me-1" viewBox="0 0 16 16">
                            <path d="M8 0a8 8 0 1 0 0 16A8 8 0 0 0 8 0zM4.5 7.5a.5.5 0 0 1 0-1h5.793L8.146 4.354a.5.5 0 1 1 .708-.708l3 3a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708-.708L10.293 7.5H4.5z"/>
                        </svg>
                        Open GCash
                    </button>
                    <button class="btn btn-outline-success btn-sm open-maya" data-qr-url="'.htmlspecialchars($qrCodeUrl, ENT_QUOTES).'">
                        <svg width="16" height="16" fill="currentColor" class="me-1" viewBox="0 0 16 16">
                            <path d="M8 0a8 8 0 1 0 0 16A8 8 0 0 0 8 0zM4.5 7.5a.5.5 0 0 1 0-1h5.793L8.146 4.354a.5.5 0 1 1 .708-.708l3 3a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708-.708L10.293 7.5H4.5z"/>
                        </svg>
                        Open Maya
                    </button>
                </div>
            </div>
        </div>';

} else {
    echo "<pre>";
    print_r($result);
    echo "</pre>";
    exit;
}

?>


<script>
    // check payment status every 3 seconds
    const intentId = "<?= $intentId ?>";

    function checkStatus() {
        fetch(`https://vendo-machine-c75fb-default-rtdb.asia-southeast1.firebasedatabase.app/payments/${intentId}.json`)
            .then(res => res.json())
            .then(data => {
                if (!data) return;

                if (data.status === "expired") {
                    const expiredModal = new bootstrap.Modal(document.getElementById('expiredModal'));
                    expiredModal.show();

                    // Reload the QR image
                    setTimeout(() => {
                        location.reload();
                    }, 500);
                }

                if (data.status === "paid") {
                    // Show success modal
                    const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                    successModal.show();
                    
                    // Reload after 2 seconds
                    setTimeout(() => {
                        location.reload();
                    }, 500);
                }
            });
    }
    setInterval(checkStatus, 3000); // every 3 seconds
</script>
