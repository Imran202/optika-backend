<?php

require_once 'vendor/autoload.php';

use Google_Client;
use Google_Service_Sheets;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Configuration
$spreadsheetId = $_ENV['GOOGLE_SHEETS_REVIEWS_SPREADSHEET_ID'] ?? null;
$range = $_ENV['GOOGLE_SHEETS_REVIEWS_RANGE'] ?? 'Sheet1!A:K';

echo "=== Review History Google Sheets Test ===\n";
echo "Spreadsheet ID: " . ($spreadsheetId ?: 'NOT SET') . "\n";
echo "Range: $range\n\n";

if (!$spreadsheetId) {
    echo "❌ GOOGLE_SHEETS_REVIEWS_SPREADSHEET_ID not set in .env\n";
    echo "Please add it to your .env file:\n";
    echo "GOOGLE_SHEETS_REVIEWS_SPREADSHEET_ID=your_spreadsheet_id_here\n";
    exit(1);
}

try {
    $client = new Google_Client();
    
    // Check if we have service account credentials
    if (file_exists('storage/app/google-credentials.json')) {
        echo "✅ Using Service Account credentials\n";
        $client->setAuthConfig('storage/app/google-credentials.json');
        $client->setScopes([Google_Service_Sheets::SPREADSHEETS_READONLY]);
    } else {
        echo "❌ Service Account credentials not found\n";
        echo "Please place google-credentials.json in storage/app/\n";
        exit(1);
    }
    
    $sheets = new Google_Service_Sheets($client);
    
    echo "🔍 Fetching data from Google Sheets...\n";
    $response = $sheets->spreadsheets_values->get($spreadsheetId, $range);
    $values = $response->getValues();
    
    if (empty($values)) {
        echo "❌ No data found in Google Sheets\n";
        exit(1);
    }
    
    echo "✅ Successfully connected to Google Sheets!\n";
    echo "Headers: " . implode(', ', $values[0]) . "\n";
    echo "Total rows: " . (count($values) - 1) . "\n\n";
    
    // Show first few rows
    echo "=== Sample Data ===\n";
    for ($i = 1; $i < min(4, count($values)); $i++) {
        echo "Row $i: " . implode(' | ', $values[$i]) . "\n";
    }
    
    // Test user matching logic
    echo "\n=== Testing User Matching ===\n";
    
    // Simulate user data
    $testUsers = [
        [
            'name' => 'SAMRA',
            'phone' => '33653300',
            'email' => 'test@example.com'
        ],
        [
            'name' => 'Test User',
            'phone' => '061234567',
            'email' => 'user@example.com'
        ],
        [
            'name' => 'Test User 2',
            'phone' => '061201891',
            'email' => 'user2@example.com'
        ]
    ];
    
            foreach ($testUsers as $index => $user) {
            echo "\n--- Test User " . ($index + 1) . " ---\n";
            echo "Name: " . $user['name'] . "\n";
            echo "Phone: " . $user['phone'] . "\n";
            echo "Email: " . $user['email'] . "\n";
            
            $matches = [];
            foreach (array_slice($values, 1) as $rowIndex => $row) {
                $rowData = array_combine($values[0], array_pad($row, count($values[0]), ''));
                
                // Check phone match only
                $sheetPhone = preg_replace('/[^0-9]/', '', $rowData['telefon'] ?? '');
                $userPhone = preg_replace('/[^0-9]/', '', $user['phone']);
                
                $phoneMatch = false;
                if ($sheetPhone === $userPhone) {
                    $phoneMatch = true;
                } elseif ($sheetPhone && $userPhone && (str_contains($sheetPhone, $userPhone) || str_contains($userPhone, $sheetPhone))) {
                    $phoneMatch = true;
                } elseif (strlen($userPhone) === 8) {
                    $userPhoneWithZero = '0' . $userPhone;
                    if ($sheetPhone === $userPhoneWithZero) {
                        $phoneMatch = true;
                    }
                } elseif (strlen($userPhone) === 10 && substr($userPhone, 0, 1) === '0') {
                    $userPhoneWithoutZero = substr($userPhone, 1);
                    if ($sheetPhone === $userPhoneWithoutZero) {
                        $phoneMatch = true;
                    }
                }
                
                if ($phoneMatch) {
                    $matches[] = [
                        'row' => $rowIndex + 2,
                        'phone_match' => $phoneMatch,
                        'data' => $rowData
                    ];
                }
            }
            
            if (empty($matches)) {
                echo "❌ No matches found\n";
            } else {
                echo "✅ Found " . count($matches) . " matches:\n";
                foreach ($matches as $match) {
                    echo "  Row " . $match['row'] . ": ";
                    echo "Phone: " . ($match['phone_match'] ? '✅' : '❌') . "\n";
                    echo "  Data: " . ($match['data']['ime'] ?? 'N/A') . " | " . ($match['data']['telefon'] ?? 'N/A') . "\n";
                }
            }
        }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n=== Test completed ===\n";
