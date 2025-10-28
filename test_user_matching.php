<?php

require_once 'vendor/autoload.php';

use Google_Client;
use Google_Service_Sheets;

// Load environment variables
$envFile = '.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

echo "=== User Matching Test ===\n\n";

$spreadsheetId = $_ENV['GOOGLE_SHEETS_SPREADSHEET_ID'] ?? null;
$range = $_ENV['GOOGLE_SHEETS_RANGE'] ?? 'Baza!A:AG';

if (!$spreadsheetId) {
    echo "❌ ERROR: GOOGLE_SHEETS_SPREADSHEET_ID not set\n";
    exit(1);
}

// Test user data
$testUsers = [
    ['phone' => '671147785', 'name' => 'MUHAMED MULIĆ'],
    ['phone' => '061901057', 'name' => 'FADIL SKORIC'],
    ['phone' => '061201891', 'name' => 'JASMINA BOROVAC'],
    ['phone' => '61201891', 'name' => 'DŽENAN KIŠIĆ'], // Test without leading zero
];

// Phone number cleaning function
function cleanPhoneNumber($phone) {
    return preg_replace('/[^0-9]/', '', $phone);
}

// User matching function
function matchesUser($userPhone, $userName, $sheetPhone, $sheetFullName) {
    // Clean phone numbers for comparison
    $userPhone = cleanPhoneNumber($userPhone);
    $sheetPhone = cleanPhoneNumber($sheetPhone);
    
    // Clean names for comparison
    $userName = strtolower(trim($userName));
    $sheetFullName = strtolower(trim($sheetFullName));
    
    // Primary match: by phone number (exact match)
    if ($userPhone && $sheetPhone && $userPhone === $sheetPhone) {
        return true;
    }
    
    // Secondary match: by name (if phone number doesn't match)
    if ($userName && $sheetFullName && $userName === $sheetFullName) {
        return true;
    }
    
    // Handle different phone number formats
    if ($userPhone && $sheetPhone) {
        // Try adding leading zero to user phone if it's 8 digits
        if (strlen($userPhone) === 8) {
            $userPhoneWithZero = '0' . $userPhone;
            if ($userPhoneWithZero === $sheetPhone) {
                echo "   ✅ Phone match with added leading zero: $userPhone -> $userPhoneWithZero\n";
                return true;
            }
        }
        
        // Try adding leading zero to sheet phone if it's 8 digits
        if (strlen($sheetPhone) === 8) {
            $sheetPhoneWithZero = '0' . $sheetPhone;
            if ($userPhone === $sheetPhoneWithZero) {
                echo "   ✅ Phone match with added leading zero to sheet: $sheetPhone -> $sheetPhoneWithZero\n";
                return true;
            }
        }
        
        // Try removing leading zero from user phone if it's 10 digits
        if (strlen($userPhone) === 10 && substr($userPhone, 0, 1) === '0') {
            $userPhoneWithoutZero = substr($userPhone, 1);
            if ($userPhoneWithoutZero === $sheetPhone) {
                echo "   ✅ Phone match with removed leading zero: $userPhone -> $userPhoneWithoutZero\n";
                return true;
            }
        }
        
        // Try removing leading zero from sheet phone if it's 10 digits
        if (strlen($sheetPhone) === 10 && substr($sheetPhone, 0, 1) === '0') {
            $sheetPhoneWithoutZero = substr($sheetPhone, 1);
            if ($userPhone === $sheetPhoneWithoutZero) {
                echo "   ✅ Phone match with removed leading zero from sheet: $sheetPhone -> $sheetPhoneWithoutZero\n";
                return true;
            }
        }
        
        // Remove country code if present and compare
        $userPhoneClean = preg_replace('/^(\+387|387|0)/', '', $userPhone);
        $sheetPhoneClean = preg_replace('/^(\+387|387|0)/', '', $sheetPhone);
        
        if ($userPhoneClean === $sheetPhoneClean) {
            return true;
        }
        
        // Check if one contains the other
        if (strpos($userPhoneClean, $sheetPhoneClean) !== false || 
            strpos($sheetPhoneClean, $userPhoneClean) !== false) {
            return true;
        }
    }
    
    return false;
}

try {
    // Initialize Google Client
    $client = new Google_Client();
    $client->setApplicationName('Optika Loyalty App Test');
    $client->setScopes([Google_Service_Sheets::SPREADSHEETS_READONLY]);
    
    // Check for Service Account credentials first
    $credentialsPath = 'storage/app/google-credentials.json';
    if (file_exists($credentialsPath)) {
        $client->setAuthConfig($credentialsPath);
        echo "✅ Using Service Account credentials\n";
    } else {
        echo "❌ Service Account credentials not found\n";
        exit(1);
    }
    
    // Create Sheets service
    $service = new Google_Service_Sheets($client);
    
    // Get data from sheets
    echo "📊 Fetching data from Google Sheets...\n";
    $response = $service->spreadsheets_values->get($spreadsheetId, $range);
    $values = $response->getValues();
    
    if (empty($values)) {
        echo "❌ No data found in spreadsheet\n";
        exit(1);
    }
    
    $headers = $values[0];
    echo "✅ Found " . count($values) . " rows of data\n\n";
    
    // Test each user
    foreach ($testUsers as $testUser) {
        echo "🔍 Testing user: {$testUser['name']} ({$testUser['phone']})\n";
        
        $userGlasses = [];
        $matchCount = 0;
        
        for ($i = 1; $i < count($values); $i++) {
            $row = $values[$i];
            $rowData = array_combine($headers, array_pad($row, count($headers), ''));
            
            $sheetPhone = $rowData['BR TEL'] ?? '';
            $sheetFirstName = $rowData['IME'] ?? '';
            $sheetLastName = $rowData['PREZIME'] ?? '';
            $sheetFullName = trim($sheetFirstName . ' ' . $sheetLastName);
            
            if (matchesUser($testUser['phone'], $testUser['name'], $sheetPhone, $sheetFullName)) {
                $matchCount++;
                $userGlasses[] = [
                    'protocol' => $rowData['PROTOKOL'] ?? '',
                    'name' => $sheetFullName,
                    'phone' => $sheetPhone,
                    'frame' => $rowData['OKVIR OPIS'] ?? '',
                    'lens' => $rowData['STAKLA OPIS'] ?? '',
                    'date' => $rowData['DATUM'] ?? '',
                    'doctor' => $rowData['DOKTOR'] ?? '',
                    'store' => $rowData['RADNJA'] ?? ''
                ];
            }
        }
        
        echo "   📱 Matches found: $matchCount\n";
        if ($matchCount > 0) {
            echo "   👓 Glasses found:\n";
            foreach ($userGlasses as $glasses) {
                echo "      - Protocol: {$glasses['protocol']}, Frame: {$glasses['frame']}, Date: {$glasses['date']}\n";
            }
        } else {
            echo "   ❌ No matches found\n";
        }
        echo "\n";
    }
    
    echo "🎉 User matching test completed!\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
