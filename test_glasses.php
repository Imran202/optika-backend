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

echo "=== Google Sheets Integration Test ===\n\n";

// Check environment variables
$spreadsheetId = $_ENV['GOOGLE_SHEETS_SPREADSHEET_ID'] ?? null;
$apiKey = $_ENV['GOOGLE_API_KEY'] ?? null;
$range = $_ENV['GOOGLE_SHEETS_RANGE'] ?? 'Sheet1!A:T';

echo "Spreadsheet ID: " . ($spreadsheetId ?: 'NOT SET') . "\n";
echo "API Key: " . ($apiKey ? substr($apiKey, 0, 10) . '...' : 'NOT SET') . "\n";
echo "Range: $range\n\n";

if (!$spreadsheetId) {
    echo "❌ ERROR: GOOGLE_SHEETS_SPREADSHEET_ID not set in .env file\n";
    exit(1);
}

// Check if we have either API Key or Service Account credentials
$credentialsPath = 'storage/app/google-credentials.json';
if (!$apiKey && !file_exists($credentialsPath)) {
    echo "❌ ERROR: Neither GOOGLE_API_KEY nor Service Account credentials found\n";
    echo "Please set up either:\n";
    echo "1. GOOGLE_API_KEY in .env file, OR\n";
    echo "2. Service Account credentials in storage/app/google-credentials.json\n";
    exit(1);
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
        $client->setDeveloperKey($apiKey);
        echo "✅ Using API Key\n";
    }
    
    echo "✅ Google Client initialized successfully\n";
    
    // Create Sheets service
    $service = new Google_Service_Sheets($client);
    echo "✅ Google Sheets service created\n";
    
    // Test connection by getting first few rows
    echo "\n📊 Testing connection...\n";
    $response = $service->spreadsheets_values->get($spreadsheetId, 'Baza!A1:AG5');
    $values = $response->getValues();
    
    if (empty($values)) {
        echo "❌ No data found in spreadsheet\n";
        exit(1);
    }
    
    echo "✅ Connection successful!\n";
    echo "📈 Total rows found: " . count($values) . "\n\n";
    
    // Show headers
    if (isset($values[0])) {
        echo "📋 Headers:\n";
        foreach ($values[0] as $index => $header) {
            echo "  " . chr(65 + $index) . ": $header\n";
        }
        echo "\n";
    }
    
    // Show sample data
    if (count($values) > 1) {
        echo "📝 Sample data (first 2 rows):\n";
        for ($i = 1; $i < min(3, count($values)); $i++) {
            echo "Row $i: " . implode(' | ', $values[$i]) . "\n";
        }
        echo "\n";
    }
    
    // Test specific range
    echo "🔍 Testing full range: $range\n";
    $fullResponse = $service->spreadsheets_values->get($spreadsheetId, $range);
    $fullValues = $fullResponse->getValues();
    
    echo "✅ Full range test successful!\n";
    echo "📊 Total rows in full range: " . count($fullValues) . "\n";
    
    if (count($fullValues) > 1) {
        echo "👤 Sample user data:\n";
        $headers = $fullValues[0];
        for ($i = 1; $i < min(3, count($fullValues)); $i++) {
            $row = $fullValues[$i];
            echo "User $i:\n";
            foreach ($headers as $index => $header) {
                $value = $row[$index] ?? '';
                echo "  $header: $value\n";
            }
            echo "\n";
        }
    }
    
    echo "🎉 All tests passed! Google Sheets integration is working correctly.\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
