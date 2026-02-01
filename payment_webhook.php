<?php
$firebaseBase = "https://vendo-machine-c75fb-default-rtdb.asia-southeast1.firebasedatabase.app/payments/";

// Read webhook payload
$payload = file_get_contents("php://input");
$event = json_decode($payload, true);

// Log for debugging (KEEP THIS)
file_put_contents(
    __DIR__ . "/webhook_log.txt",
    date("Y-m-d H:i:s") . " " . $payload . PHP_EOL,
    FILE_APPEND
);

if (!$event || !isset($event["data"]["attributes"]["type"])) {
    http_response_code(400);
    exit;
}

$type = $event["data"]["attributes"]["type"];


if ($type === 'payment.paid') {

    // 🔑 THIS IS THE IMPORTANT PART
    $intentId = $event['data']['attributes']['data']['attributes']['payment_intent_id'] ?? null;


    if ($intentId) {
        $firebaseUrl = $firebaseBase . $intentId . ".json";

        $ch = curl_init($firebaseUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            "status" => "paid"
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        curl_exec($ch);
        curl_close($ch);
    } else {
        echo "No intent ID found in webhook data.";
    }
} else{
    // Handle other event types if needed
    echo "Unhandled event type:";
}

http_response_code(200);
echo json_encode(["ok" => true]);
