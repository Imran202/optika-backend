#!/usr/bin/env php
<?php

require __DIR__.'/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n";
echo "╔═══════════════════════════════════════════════════╗\n";
echo "║   OPTIKA LOYALTY - NOTIFIKACIJE SA POSTOM        ║\n";
echo "╚═══════════════════════════════════════════════════╝\n\n";

// Funkcija za slanje notifikacije
function sendPushNotification($token, $title, $body, $data = []) {
    $url = 'https://exp.host/--/api/v2/push/send';
    
    $payload = [
        'to' => $token,
        'title' => $title,
        'body' => $body,
        'sound' => 'default',
        'priority' => 'high',
        'channelId' => 'default',
        'badge' => 1,
        'data' => array_merge([
            'type' => 'post',
            'timestamp' => time(),
        ], $data)
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
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

// Pronađi sve korisnike sa push tokenima
$users = DB::table('users')
    ->whereNotNull('push_token')
    ->where('push_token', '!=', '')
    ->select('id', 'username', 'push_token', 'userphone')
    ->get();

if ($users->isEmpty()) {
    echo "❌ Nema registrovanih push tokena.\n\n";
    exit(1);
}

echo "👥 Pronađeno {$users->count()} korisnika sa push tokenima\n\n";

// Unos podataka za notifikaciju
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "           OSNOVNI PODACI NOTIFIKACIJE\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "📝 Naslov notifikacije: ";
$title = trim(fgets(STDIN));
if (empty($title)) {
    echo "❌ Naslov ne može biti prazan.\n\n";
    exit(1);
}

echo "💬 Poruka notifikacije: ";
$message = trim(fgets(STDIN));
if (empty($message)) {
    echo "❌ Poruka ne može biti prazna.\n\n";
    exit(1);
}

// Pitaj da li notifikacija ima post
echo "\n📄 Da li želite dodati post (detaljni sadržaj)? (da/ne): ";
$hasPostInput = strtolower(trim(fgets(STDIN)));
$hasPost = in_array($hasPostInput, ['da', 'yes', 'y']);

$postTitle = null;
$postDescription = null;
$postImage = null;

if ($hasPost) {
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "              SADRŽAJ POSTA\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    echo "📋 Naslov posta: ";
    $postTitle = trim(fgets(STDIN));
    if (empty($postTitle)) {
        $postTitle = $title; // Ako nije unesen, koristi naslov notifikacije
        echo "   └─ Koristi se naslov notifikacije: {$postTitle}\n";
    }
    
    echo "\n📝 Opis posta (može biti dug): ";
    $postDescription = trim(fgets(STDIN));
    if (empty($postDescription)) {
        echo "❌ Opis posta ne može biti prazan.\n\n";
        exit(1);
    }
    
    echo "\n🖼️  URL slike (opciono, enter za preskakanje): ";
    $postImage = trim(fgets(STDIN));
    if (empty($postImage)) {
        $postImage = null;
        echo "   └─ Post bez slike\n";
    }
}

// Tip notifikacije
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "              TIP NOTIFIKACIJE\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
echo "Tipovi: general, promo, loyalty, appointment, health\n";
echo "🏷️  Tip (enter za 'general'): ";
$type = trim(fgets(STDIN));
if (empty($type)) {
    $type = 'general';
}

// Odabir primaoca
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "              PRIMAOCI\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
echo "1. Svi korisnici ({$users->count()})\n";
echo "2. Samo jedan korisnik\n";
echo "\nOdaberite opciju (1/2): ";
$choice = trim(fgets(STDIN));

$selectedUsers = [];

if ($choice === '2') {
    echo "\n👤 Korisnici:\n";
    foreach ($users as $index => $user) {
        echo ($index + 1) . ". {$user->username} ({$user->userphone})\n";
    }
    echo "\nUnesite broj korisnika: ";
    $userIndex = (int)trim(fgets(STDIN)) - 1;
    
    if (!isset($users[$userIndex])) {
        echo "❌ Neispravan izbor.\n\n";
        exit(1);
    }
    
    $selectedUsers = [$users[$userIndex]];
} else {
    $selectedUsers = $users->all();
}

// Pregled
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "              PREGLED\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
echo "📝 Naslov: {$title}\n";
echo "💬 Poruka: {$message}\n";
echo "🏷️  Tip: {$type}\n";

if ($hasPost) {
    echo "\n📄 POST SADRŽAJ:\n";
    echo "   Naslov: {$postTitle}\n";
    echo "   Opis: " . substr($postDescription, 0, 60) . (strlen($postDescription) > 60 ? '...' : '') . "\n";
    echo "   Slika: " . ($postImage ? $postImage : 'Nema') . "\n";
} else {
    echo "📄 Post: Nema\n";
}

echo "\n👥 Broj primaoca: " . count($selectedUsers) . "\n\n";

echo "Da li želite poslati notifikaciju? (da/ne): ";
$confirm = strtolower(trim(fgets(STDIN)));

if (!in_array($confirm, ['da', 'yes', 'y'])) {
    echo "❌ Otkazano.\n\n";
    exit(0);
}

// Slanje notifikacija
echo "\n📤 Šaljem notifikacije...\n\n";

$successCount = 0;
$failCount = 0;

foreach ($selectedUsers as $user) {
    // Pripremi data objekat za push notifikaciju
    $pushData = [
        'type' => $hasPost ? 'post' : $type,
        'timestamp' => time(),
    ];
    
    if ($hasPost) {
        $pushData['has_post'] = true;
        $pushData['post_title'] = $postTitle;
        $pushData['post_description'] = $postDescription;
        if ($postImage) {
            $pushData['post_image'] = $postImage;
        }
    }
    
    $result = sendPushNotification($user->push_token, $title, $message, $pushData);
    
    if ($result['success']) {
        $responseData = json_decode($result['response'], true);
        
        // Proveri Expo API status
        $status = $responseData['data']['status'] ?? ($responseData['data'][0]['status'] ?? null);
        
        if ($status === 'ok') {
            echo "✅ {$user->username} - Uspešno poslano\n";
            $successCount++;
            
            // Snimi u bazu podataka
            DB::table('notifications')->insert([
                'user_id' => $user->id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'read' => false,
                'has_post' => $hasPost,
                'post_title' => $postTitle,
                'post_description' => $postDescription,
                'post_image' => $postImage,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $errorMsg = $responseData['data']['message'] ?? ($responseData['data'][0]['message'] ?? 'Unknown error');
            echo "❌ {$user->username} - Greška: {$errorMsg}\n";
            $failCount++;
        }
    } else {
        echo "❌ {$user->username} - HTTP greška: {$result['httpCode']}\n";
        if ($result['error']) {
            echo "   └─ {$result['error']}\n";
        }
        $failCount++;
    }
}

// Sumiranje
echo "\n╔══════════════════════════════════════════════════╗\n";
echo "║              REZULTAT                            ║\n";
echo "╚══════════════════════════════════════════════════╝\n\n";
echo "✅ Uspešno: {$successCount}\n";
echo "❌ Neuspešno: {$failCount}\n";
echo "📊 Ukupno: " . ($successCount + $failCount) . "\n\n";

if ($successCount > 0) {
    echo "🎉 Notifikacije uspešno poslane!\n\n";
}

