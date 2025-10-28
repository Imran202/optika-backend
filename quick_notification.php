#!/usr/bin/env php
<?php

require __DIR__.'/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n";
echo "╔═══════════════════════════════════════════════════╗\n";
echo "║   OPTIKA LOYALTY - BRZO SLANJE NOTIFIKACIJA      ║\n";
echo "╚═══════════════════════════════════════════════════╝\n\n";

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

// Pronađi sve korisnike sa push tokenima
$users = DB::table('users')
    ->whereNotNull('push_token')
    ->where('push_token', '!=', '')
    ->select('id', 'username', 'push_token', 'userphone')
    ->get();

if ($users->count() === 0) {
    echo "❌ Nema korisnika sa registrovanim push tokenima.\n";
    echo "💡 Prijavite se u aplikaciji da biste registrovali push token.\n\n";
    exit(1);
}

echo "Pronađeno korisnika sa push tokenima: " . $users->count() . "\n\n";

// Prikaži korisnike
foreach ($users as $index => $user) {
    $num = $index + 1;
    $maskedToken = substr($user->push_token, 0, 30) . '...';
    echo "[$num] {$user->username} ({$user->userphone}) - {$maskedToken}\n";
}
echo "[0] Svi korisnici\n\n";

// Izbor korisnika
echo "Izaberite korisnika (broj): ";
$userChoice = trim(fgets(STDIN));

$selectedUsers = [];
if ($userChoice == '0') {
    $selectedUsers = $users->toArray();
    echo "✓ Odabrano: Svi korisnici (" . count($selectedUsers) . ")\n\n";
} else {
    $index = (int)$userChoice - 1;
    if ($index >= 0 && $index < $users->count()) {
        $selectedUsers = [$users[$index]];
        echo "✓ Odabrano: {$users[$index]->username}\n\n";
    } else {
        echo "❌ Nevažeći izbor!\n";
        exit(1);
    }
}

// Meni tipova notifikacija
echo "╔══════════════════════════════════════════════════╗\n";
echo "║         TIPOVI NOTIFIKACIJA                      ║\n";
echo "╚══════════════════════════════════════════════════╝\n";
echo "[1] 🎉 Specijalna ponuda / Popust\n";
echo "[2] 📅 Podsetnik za termin\n";
echo "[3] 🎁 Bodovi nagrađeni\n";
echo "[4] 🔔 Opšta notifikacija\n";
echo "[5] ✏️  Custom notifikacija\n\n";

echo "Izaberite tip notifikacije: ";
$typeChoice = trim(fgets(STDIN));

$title = '';
$body = '';
$type = 'general';

switch ($typeChoice) {
    case '1':
        $title = "🎉 Specijalna ponuda!";
        $body = "Danas ekskluzivni popust samo za vas! Ne propustite priliku.";
        $type = 'discount';
        break;
    
    case '2':
        $title = "📅 Podsetnik za pregled";
        $body = "Vreme je za godišnji pregled očiju. Rezervišite vaš termin danas!";
        $type = 'appointment';
        break;
    
    case '3':
        $title = "🎁 Osvojili ste bodove!";
        $body = "Čestitamo! Dobili ste 50 loyalty bodova. Iskoristite ih na vašoj sledećoj kupovini.";
        $type = 'points';
        break;
    
    case '4':
        $title = "🔔 Obavještenje";
        $body = "Nova kolekcija naočara stigla je u prodavnicu. Posjetite nas!";
        $type = 'general';
        break;
    
    case '5':
        echo "\nUnesite naslov notifikacije: ";
        $title = trim(fgets(STDIN));
        echo "Unesite poruku: ";
        $body = trim(fgets(STDIN));
        
        echo "\nTip notifikacije:\n";
        echo "[1] appointment\n";
        echo "[2] discount\n";
        echo "[3] points\n";
        echo "[4] general\n";
        echo "Izaberi (1-4): ";
        $customType = trim(fgets(STDIN));
        $typeMap = ['1' => 'appointment', '2' => 'discount', '3' => 'points', '4' => 'general'];
        $type = $typeMap[$customType] ?? 'general';
        break;
    
    default:
        echo "❌ Nevažeći izbor!\n";
        exit(1);
}

// Potvrda
echo "\n╔══════════════════════════════════════════════════╗\n";
echo "║         PREGLED NOTIFIKACIJE                     ║\n";
echo "╚══════════════════════════════════════════════════╝\n";
echo "📝 Naslov: {$title}\n";
echo "💬 Poruka: {$body}\n";
echo "🏷️  Tip: {$type}\n";
echo "👥 Broj primaoca: " . count($selectedUsers) . "\n\n";

echo "Da li želite poslati notifikaciju? (da/ne): ";
$confirm = strtolower(trim(fgets(STDIN)));

if ($confirm !== 'da' && $confirm !== 'yes' && $confirm !== 'y') {
    echo "❌ Otkazano.\n\n";
    exit(0);
}

// Slanje notifikacija
echo "\n📤 Šaljem notifikacije...\n\n";

$successCount = 0;
$failCount = 0;

foreach ($selectedUsers as $user) {
    $result = sendPushNotification($user->push_token, $title, $body, $type);
    
    if ($result['success']) {
        $responseData = json_decode($result['response'], true);
        
        // Proveri Expo API status (podržava i array i objekat format)
        $status = $responseData['data']['status'] ?? ($responseData['data'][0]['status'] ?? null);
        
        if ($status === 'ok') {
            echo "✅ {$user->username} - Uspešno poslano\n";
            $successCount++;
            
            // Snimi u bazu podataka
            DB::table('notifications')->insert([
                'user_id' => $user->id,
                'type' => $type,
                'title' => $title,
                'message' => $body,
                'read' => false,
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
echo "╚══════════════════════════════════════════════════╝\n";
echo "✅ Uspešno: {$successCount}\n";
if ($failCount > 0) {
    echo "❌ Neuspešno: {$failCount}\n";
}
echo "📊 Ukupno: " . count($selectedUsers) . "\n\n";

if ($successCount > 0) {
    echo "🎉 Notifikacije su uspešno poslane i sačuvane u bazi!\n\n";
}

