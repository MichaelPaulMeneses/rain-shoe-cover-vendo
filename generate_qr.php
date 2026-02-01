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
            <img src="'.$qrBase64.'" alt="QR Code" width="300" id="qrCodeImage">
            <div class="mt-3">
                <p class="text-muted small mb-2">Scan with camera or:</p>
                <div class="d-grid gap-2">
                    <button class="btn btn-primary btn-sm download-qr-btn">
                        <svg width="16" height="16" fill="currentColor" class="me-1" viewBox="0 0 16 16">
                            <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
                            <path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/>
                        </svg>
                        Download QR Code
                    </button>
                    <div class="text-muted small mt-1 text-start" style="font-size: 0.8rem;">
                        <strong>To pay with GCash/Maya:</strong><br>
                        1. Click "Download QR Code" above<br>
                        2. Open GCash or Maya app<br>
                        3. Tap "Scan QR" → "Upload from Gallery"<br>
                        4. Select the downloaded QR code
                    </div>
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

    // Download QR functionality
    document.addEventListener('DOMContentLoaded', function() {
        const downloadBtn = document.querySelector('.download-qr-btn');
        if (downloadBtn) {
            downloadBtn.addEventListener('click', function() {
                const qrImg = document.getElementById('qrCodeImage');
                const link = document.createElement('a');
                link.href = qrImg.src;
                link.download = 'payment-qr-code.png';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                // Show success message
                downloadBtn.innerHTML = '<svg width="16" height="16" fill="currentColor" class="me-1" viewBox="0 0 16 16"><path d="M10.97 4.97a.75.75 0 0 1 1.07 1.05l-3.99 4.99a.75.75 0 0 1-1.08.02L4.324 8.384a.75.75 0 1 1 1.06-1.06l2.094 2.093 3.473-4.425a.267.267 0 0 1 .02-.022z"/></svg> Downloaded!';
                
                setTimeout(() => {
                    downloadBtn.innerHTML = '<svg width="16" height="16" fill="currentColor" class="me-1" viewBox="0 0 16 16"><path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/><path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/></svg> Download QR Code';
                }, 2000);
            });
        }
    });

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
