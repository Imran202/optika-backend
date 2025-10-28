<?php

echo "=== OPTIKA LOYALTY - PUSH NOTIFICATION TESTER ===\n\n";

// Funkcija za slanje notifikacije
function sendNotification($token, $title, $body) {
    $url = 'https://exp.host/--/api/v2/push/send';
    
    $data = [
        'to' => $token,
        'title' => $title,
        'body' => $body,
        'sound' => 'default',
        'data' => [
            'screen' => 'profile',
            'timestamp' => time()
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

// Unos token-a
echo "Unesite vaš Expo Push Token (ili pritisnite Enter za test token):\n";
echo "Format: ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]\n";
$token = trim(fgets(STDIN));

if (empty($token)) {
    echo "Koristim test token...\n";
    $token = "ExponentPushToken[test_token]";
}

echo "\nToken: " . substr($token, 0, 30) . "...\n\n";

// Meni
while (true) {
    echo "Izaberite opciju:\n";
    echo "1. Pošalji test notifikaciju\n";
    echo "2. Pošalji custom notifikaciju\n";
    echo "3. Pošalji notifikaciju o popustu\n";
    echo "4. Pošalji notifikaciju o pregledu\n";
    echo "5. Promeni token\n";
    echo "0. Izlaz\n\n";
    
    echo "Vaš izbor: ";
    $choice = trim(fgets(STDIN));
    
    switch ($choice) {
        case '1':
            $result = sendNotification($token, "Optika Loyalty", "Ovo je test notifikacija! 🎉");
            break;
            
        case '2':
            echo "Unesite naslov: ";
            $title = trim(fgets(STDIN));
            echo "Unesite poruku: ";
            $body = trim(fgets(STDIN));
            $result = sendNotification($token, $title, $body);
            break;
            
        case '3':
            $result = sendNotification(
                $token, 
                "🎉 Specijalna ponuda!", 
                "Danas 20% popusta na sve naočare! Ne propustite!"
            );
            break;
            
        case '4':
            $result = sendNotification(
                $token, 
                "👁️ Pregled očiju", 
                "Vreme je za godišnji pregled očiju. Rezervišite termin!"
            );
            break;
            
        case '5':
            echo "Unesite novi token: ";
            $token = trim(fgets(STDIN));
            echo "Token promenjen!\n\n";
            continue 2;
            
        case '0':
            echo "Doviđenja! 👋\n";
            exit;
            
        default:
            echo "Nevažeći izbor. Pokušajte ponovo.\n\n";
            continue 2;
    }
    
    // Prikaz rezultata
    echo "\n=== REZULTAT ===\n";
    if ($result['success']) {
        echo "✅ Notifikacija uspešno poslana!\n";
        $responseData = json_decode($result['response'], true);
        if (isset($responseData['data']['status'])) {
            echo "Status: " . $responseData['data']['status'] . "\n";
        }
    } else {
        echo "❌ Greška prilikom slanja notifikacije.\n";
        if ($result['error']) {
            echo "Error: " . $result['error'] . "\n";
        }
        echo "HTTP Code: " . $result['httpCode'] . "\n";
        echo "Response: " . $result['response'] . "\n";
    }
    echo "================\n\n";
}
?>
