<?php
/**
 * Check Google Sheets API Authentication Configuration
 * 
 * This script helps diagnose authentication issues with Google Sheets API
 */

require __DIR__ . '/vendor/autoload.php';

use Google_Client;
use Google_Service_Sheets;

echo "🔍 Google Sheets API Authentication Check\n";
echo "==========================================\n\n";

// Check 1: Service Account File
echo "1. Checking Service Account Credentials...\n";
$credentialsPath = __DIR__ . '/storage/app/google-credentials.json';

if (file_exists($credentialsPath)) {
    echo "   ✅ Service account file exists: $credentialsPath\n";
    
    // Try to read and validate JSON
    $credentialsContent = file_get_contents($credentialsPath);
    $credentials = json_decode($credentialsContent, true);
    
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "   ✅ JSON is valid\n";
        
        if (isset($credentials['client_email'])) {
            echo "   ✅ Service account email: " . $credentials['client_email'] . "\n";
            echo "   ⚠️  IMPORTANT: Share your Google Sheet with this email address!\n";
        } else {
            echo "   ❌ Missing 'client_email' in credentials\n";
        }
        
        if (isset($credentials['private_key'])) {
            echo "   ✅ Private key found\n";
        } else {
            echo "   ❌ Missing 'private_key' in credentials\n";
        }
    } else {
        echo "   ❌ Invalid JSON: " . json_last_error_msg() . "\n";
    }
} else {
    echo "   ❌ Service account file NOT found: $credentialsPath\n";
    echo "   💡 You need to: \n";
    echo "      1. Create a Service Account in Google Cloud Console\n";
    echo "      2. Download the JSON credentials\n";
    echo "      3. Place it in: storage/app/google-credentials.json\n";
}

echo "\n";

// Check 2: API Key
echo "2. Checking API Key Configuration...\n";
$apiKey = $_ENV['GOOGLE_API_KEY'] ?? getenv('GOOGLE_API_KEY') ?? null;

if ($apiKey) {
    echo "   ✅ API key is set\n";
    echo "   ℹ️  API key: " . substr($apiKey, 0, 20) . "...\n";
    echo "   ⚠️  Note: API keys only work with PUBLIC Google Sheets\n";
} else {
    echo "   ⚠️  API key not set (this is OK if using service account)\n";
}

echo "\n";

// Check 3: Try to initialize Google Client
echo "3. Testing Google Client Initialization...\n";
try {
    $client = new Google_Client();
    $client->setApplicationName('Optika Loyalty App');
    $client->setScopes([Google_Service_Sheets::SPREADSHEETS_READONLY]);
    
    $authenticated = false;
    
    if (file_exists($credentialsPath)) {
        try {
            $client->setAuthConfig($credentialsPath);
            echo "   ✅ Service account credentials loaded\n";
            $authenticated = true;
        } catch (Exception $e) {
            echo "   ❌ Failed to load service account: " . $e->getMessage() . "\n";
        }
    }
    
    if (!$authenticated && $apiKey) {
        $client->setDeveloperKey($apiKey);
        echo "   ✅ API key loaded\n";
        $authenticated = true;
    }
    
    if (!$authenticated) {
        echo "   ❌ No authentication method configured!\n";
        exit(1);
    }
    
    // Check if we can create a service
    $service = new Google_Service_Sheets($client);
    echo "   ✅ Google Sheets service created successfully\n";
    
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n";

// Check 4: Test connection to the actual spreadsheet
echo "4. Testing Connection to Google Sheet...\n";
$spreadsheetId = '1VWNFTZ1Mzzo9YW1-XvH5doC_N--Lc92ZInkBBHT8Pb0';
$range = 'Firme!B:C';

try {
    $response = $service->spreadsheets_values->get($spreadsheetId, $range);
    $values = $response->getValues();
    
    if ($values && count($values) > 0) {
        echo "   ✅ Successfully connected to Google Sheet!\n";
        echo "   ✅ Retrieved " . count($values) . " rows\n";
    } else {
        echo "   ⚠️  Connected but no data found\n";
    }
    
} catch (\Google_Service_Exception $e) {
    $code = $e->getCode();
    $message = $e->getMessage();
    
    echo "   ❌ Connection failed!\n";
    echo "   Error Code: $code\n";
    echo "   Error Message: $message\n\n";
    
    if ($code == 403) {
        echo "   🔧 Fix Steps:\n";
        echo "      1. Go to your Google Sheet\n";
        echo "      2. Click 'Share'\n";
        echo "      3. Add the service account email as Editor\n";
        echo "      4. Make sure Google Sheets API is enabled in Google Cloud Console\n";
        
        if (isset($credentials['client_email'])) {
            echo "\n   Service account email to share with:\n";
            echo "   👉 " . $credentials['client_email'] . "\n";
        }
    } elseif ($code == 404) {
        echo "   🔧 Fix Steps:\n";
        echo "      1. Check if the Spreadsheet ID is correct\n";
        echo "      2. Check if the sheet exists\n";
    }
    
    exit(1);
} catch (Exception $e) {
    echo "   ❌ Unexpected error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n";
echo "✅ All checks passed! Google Sheets integration is configured correctly.\n";

