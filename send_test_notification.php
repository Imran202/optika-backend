#!/usr/bin/env php
<?php

require __DIR__.'/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n🚀 BRZO TESTIRANJE NOTIFIKACIJA\n";
echo "════════════════════════════════\n\n";

// Funkcija za slanje notifikacije
function sendPushNotification($token, $title, $body, $type = 'general') {
    $url = 'https://exp.host/--/api/v2/push/send';
    
    $data = [
        'to' => $token,
        'title' => $title,
        'body' => $body,
        'sound' => 'default',
        'priority' => 'high',
        'channelId' => 'default',
        'badge' => 1,
        'data' => [
            'type' => $type,
            'timestamp' => time(),
            'screen' => 'notifications'
        ]
    ];

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

    return [
        'success' => !$error && $httpCode === 200,
        'response' => $response,
        'httpCode' => $httpCode,
        'error' => $error
    ];
}

// Pronađi prvog korisnika sa push tokenom (najverovatnije ti)
$user = DB::table('users')
    ->whereNotNull('push_token')
    ->where('push_token', '!=', '')
    ->orderBy('updated_at', 'desc')
    ->select('id', 'username', 'push_token', 'userphone')
    ->first();

if (!$user) {
    echo "❌ Nema registrovanih push tokena.\n";
    echo "💡 Prvo se prijavite u aplikaciji da biste registrovali push token.\n\n";
    exit(1);
}

echo "📱 Korisnik: {$user->username} ({$user->userphone})\n";
echo "🔑 Token: " . substr($user->push_token, 0, 30) . "...\n\n";

// Pošalji test notifikaciju
$title = "🎉 Test Notifikacija";
$body = "Ovo je test sa tvog laptopa! Radi savršeno! ✨";
$type = "general";

echo "📤 Šaljem test notifikaciju...\n";

$result = sendPushNotification($user->push_token, $title, $body, $type);

if ($result['success']) {
    $responseData = json_decode($result['response'], true);
    
    echo "📥 Expo API Odgovor:\n";
    echo json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    // Proveri da li je response array ili objekat
    $status = $responseData['data']['status'] ?? ($responseData['data'][0]['status'] ?? null);
    
    if ($status === 'ok') {
        echo "✅ USPEH! Notifikacija je poslata!\n";
        echo "📱 Proveri svoj telefon - trebalo bi da je stigla.\n\n";
        
        // Snimi u bazu
        DB::table('notifications')->insert([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'message' => $body,
            'read' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        echo "💾 Notifikacija je takođe sačuvana u bazi podataka.\n\n";
    } else {
        $errorDetails = $responseData['data']['details'] ?? ($responseData['data'][0]['details'] ?? []);
        $errorMsg = $responseData['data']['message'] ?? ($responseData['data'][0]['message'] ?? 'Unknown');
        echo "❌ Expo API greška: {$errorMsg}\n";
        if (!empty($errorDetails)) {
            echo "Detalji: " . json_encode($errorDetails, JSON_UNESCAPED_UNICODE) . "\n";
        }
        echo "\n";
    }
} else {
    echo "❌ GREŠKA prilikom slanja!\n";
    echo "HTTP Code: {$result['httpCode']}\n";
    if ($result['error']) {
        echo "Error: {$result['error']}\n";
    }
    echo "Response: {$result['response']}\n\n";
}

echo "════════════════════════════════\n";
echo "💡 TIP: Za više opcija koristi: php quick_notification.php\n\n";

