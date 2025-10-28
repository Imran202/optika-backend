<?php

// Konfiguracija
$expoPushToken = "ExponentPushToken[YOUR_TOKEN_HERE]"; // Zamenite sa vašim token-om
$title = "Optika Loyalty";
$body = "Ovo je test notifikacija sa računara! 🚀";

// Expo Push API endpoint
$url = 'https://exp.host/--/api/v2/push/send';

// Podaci za notifikaciju
$data = [
    'to' => $expoPushToken,
    'title' => $title,
    'body' => $body,
    'sound' => 'default',
    'data' => [
        'screen' => 'profile',
        'timestamp' => time()
    ]
];

// cURL zahtev
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Accept-encoding: gzip, deflate',
        'Content-Type: application/json',
    ],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// Rezultat
echo "=== PUSH NOTIFICATION TEST ===\n";
echo "Token: " . substr($expoPushToken, 0, 20) . "...\n";
echo "Title: $title\n";
echo "Body: $body\n";
echo "HTTP Code: $httpCode\n";

if ($error) {
    echo "Error: $error\n";
} else {
    echo "Response: $response\n";
    
    $responseData = json_decode($response, true);
    if (isset($responseData['data']['status']) && $responseData['data']['status'] === 'ok') {
        echo "✅ Notifikacija uspešno poslana!\n";
    } else {
        echo "❌ Greška prilikom slanja notifikacije.\n";
        if (isset($responseData['errors'])) {
            foreach ($responseData['errors'] as $error) {
                echo "Error: " . $error['message'] . "\n";
            }
        }
    }
}

echo "==============================\n";
?>
