<?php

/**
 * Test script for Companies API - Google Sheets Integration
 * 
 * This script tests the connection to Google Sheets and retrieves company data.
 */

require __DIR__ . '/vendor/autoload.php';

use Google\Client;
use Google\Service\Sheets;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

echo "🧪 Testing Companies API - Google Sheets Integration\n";
echo "=================================================\n\n";

// Configuration
$spreadsheetId = '1VWNFTZ1Mzzo9YW1-XvH5doC_N--Lc92ZInkBBHT8Pb0';
$range = 'Firme!B:C'; // IME FIRME ZA WEB (B) i Adresa (C)

echo "📋 Configuration:\n";
echo "   Spreadsheet ID: $spreadsheetId\n";
echo "   Range: $range\n\n";

try {
    // Initialize Google Client
    $client = new Client();
    $client->setApplicationName('Optika Loyalty App');
    $client->setScopes([Sheets::SPREADSHEETS_READONLY]);
    
    // Check for credentials
    $credentialsPath = __DIR__ . '/storage/app/google-credentials.json';
    
    if (file_exists($credentialsPath)) {
        echo "✅ Found service account credentials\n";
        $client->setAuthConfig($credentialsPath);
    } else {
        echo "❌ Service account credentials not found at: $credentialsPath\n";
        echo "   Please follow SERVICE_ACCOUNT_SETUP.md to set up credentials\n";
        exit(1);
    }
    
    // Create Sheets service
    $service = new Sheets($client);
    
    echo "📊 Fetching data from Google Sheets...\n\n";
    
    // Get data from sheet
    $response = $service->spreadsheets_values->get($spreadsheetId, $range);
    $values = $response->getValues();
    
    if (empty($values)) {
        echo "❌ No data found in the spreadsheet\n";
        exit(1);
    }
    
    echo "✅ Successfully retrieved data!\n";
    echo "   Total rows: " . count($values) . "\n\n";
    
    // Display first row (header)
    if (count($values) > 0) {
        echo "📌 Header row:\n";
        echo "   Column B: " . ($values[0][0] ?? 'N/A') . "\n";
        echo "   Column C: " . ($values[0][1] ?? 'N/A') . "\n\n";
    }
    
    // Display first 5 companies
    echo "🏢 First 5 companies:\n";
    echo "-------------------\n";
    
    $companyCount = 0;
    for ($i = 1; $i < min(6, count($values)); $i++) {
        $row = $values[$i];
        
        if (count($row) >= 2) {
            $name = trim($row[0] ?? '');
            $address = trim($row[1] ?? '');
            
            if (!empty($name)) {
                $companyCount++;
                echo "\n$companyCount. $name\n";
                echo "   Adresa: $address\n";
            }
        }
    }
    
    // Count total valid companies (skip header)
    $validCompanies = 0;
    for ($i = 1; $i < count($values); $i++) {
        $row = $values[$i];
        if (count($row) >= 2 && !empty(trim($row[0] ?? ''))) {
            $validCompanies++;
        }
    }
    
    echo "\n\n📊 Summary:\n";
    echo "   Total rows: " . count($values) . "\n";
    echo "   Valid companies: $validCompanies\n";
    
    echo "\n✅ Test completed successfully!\n";
    echo "\n💡 Next steps:\n";
    echo "   1. Make sure your backend server is running (php artisan serve)\n";
    echo "   2. Test the API endpoint: http://localhost:8000/api/companies\n";
    echo "   3. Open the mobile app and navigate to Sindikati screen\n\n";
    
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "\n📝 Troubleshooting:\n";
    echo "   1. Make sure google-credentials.json exists in storage/app/\n";
    echo "   2. Verify the spreadsheet ID is correct\n";
    echo "   3. Ensure the sheet name 'Firme' exists\n";
    echo "   4. Check that the service account has access to the spreadsheet\n\n";
    exit(1);
}

