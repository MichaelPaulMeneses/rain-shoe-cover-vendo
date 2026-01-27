<?php

$firebaseUrl = "https://vendo-machine-dbb9a-default-rtdb.asia-southeast1.firebasedatabase.app/vendo.json";


$ch = curl_init($firebaseUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            "state" => 1
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        curl_exec($ch);
        curl_close($ch);

http_response_code(200);
echo "Intent $intentId cancelled";
