<?php

// Vaš pravi push token
$token = "ExponentPushToken[zS57SCCDYEuijaE4a46PTA]";

$data = [
    'to' => $token,
    'title' => 'Optika Loyalty',
    'body' => 'Ovo je notifikacija sa računara! 🚀',
    'sound' => 'default'
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://exp.host/--/api/v2/push/send',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
]);

$response = curl_exec($ch);
curl_close($ch);

echo "Notifikacija poslana!\n";
echo "Response: $response\n";
?>
