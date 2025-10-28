<?php

echo "=== Simple Google Sheets Test ===\n\n";

// Check if .env file exists
if (!file_exists('.env')) {
    echo "❌ .env file not found\n";
    echo "Please create .env file with:\n";
    echo "GOOGLE_SHEETS_SPREADSHEET_ID=your_spreadsheet_id\n";
    echo "GOOGLE_SHEETS_RANGE=Baza!A:AG\n";
    echo "GOOGLE_API_KEY=your_api_key\n";
    exit(1);
}

// Load environment variables
$lines = file('.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

$spreadsheetId = $_ENV['GOOGLE_SHEETS_SPREADSHEET_ID'] ?? null;
$range = $_ENV['GOOGLE_SHEETS_RANGE'] ?? null;
$apiKey = $_ENV['GOOGLE_API_KEY'] ?? null;

echo "Spreadsheet ID: " . ($spreadsheetId ?: 'NOT SET') . "\n";
echo "Range: " . ($range ?: 'NOT SET') . "\n";
echo "API Key: " . ($apiKey ? 'SET' : 'NOT SET') . "\n";

// Check if Service Account credentials exist
$credentialsPath = 'storage/app/google-credentials.json';
if (file_exists($credentialsPath)) {
    echo "Service Account: FOUND\n";
} else {
    echo "Service Account: NOT FOUND\n";
}

echo "\nNext steps:\n";
echo "1. Make sure your Google Sheet is shared publicly OR\n";
echo "2. Set up Service Account (see SERVICE_ACCOUNT_SETUP.md)\n";
echo "3. Run: php test_glasses.php\n";
