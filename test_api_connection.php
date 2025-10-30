<?php
/**
 * Test script to check API server availability
 * Usage: php test_api_connection.php
 */

$baseUrl = 'https://api.optika.ba/api';

echo "Testing API connection to: $baseUrl\n\n";

// Test 1: Login endpoint (test basic server response)
echo "1. Testing login endpoint: /login\n";
$loginData = json_encode([
    'email' => 'test@example.com',
    'password' => 'test123'
]);

$ch = curl_init($baseUrl . '/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $loginData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo "   ❌ Error: $curlError\n";
} else {
    echo "   ✅ Server responded! HTTP $httpCode\n";
    $responseData = json_decode($response, true);
    if ($responseData) {
        echo "   Response: " . ($responseData['message'] ?? json_encode($responseData)) . "\n";
    } else {
        echo "   Response: " . substr($response, 0, 200) . "\n";
    }
}

echo "\n";

// Test 2: Featured Action endpoint (public - no Google Sheets dependency)
echo "2. Testing public endpoint: /featured-action/config\n";
$ch = curl_init($baseUrl . '/featured-action/config');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo "   ❌ Error: $curlError\n";
} elseif ($httpCode >= 200 && $httpCode < 300) {
    echo "   ✅ Success! HTTP $httpCode\n";
    $responseData = json_decode($response, true);
    if ($responseData) {
        echo "   Response keys: " . implode(', ', array_keys($responseData)) . "\n";
    }
} else {
    echo "   ⚠️  HTTP $httpCode\n";
    echo "   Response: " . substr($response, 0, 200) . "\n";
}

echo "\n";

// Test 3: Discount config endpoint (public - no Google Sheets dependency)
echo "3. Testing public endpoint: /discount/config\n";
$ch = curl_init($baseUrl . '/discount/config');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo "   ❌ Error: $curlError\n";
} elseif ($httpCode >= 200 && $httpCode < 300) {
    echo "   ✅ Success! HTTP $httpCode\n";
} else {
    echo "   ⚠️  HTTP $httpCode\n";
    echo "   Response: " . substr($response, 0, 200) . "\n";
}

echo "\n";

// Test 4: Phone login check endpoint
echo "4. Testing phone login check: /phone-login-or-register\n";
$phoneData = json_encode([
    'phone_number' => '+38761123456'
]);

$ch = curl_init($baseUrl . '/phone-login-or-register');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $phoneData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo "   ❌ Error: $curlError\n";
} else {
    echo "   ✅ Server responded! HTTP $httpCode\n";
    $responseData = json_decode($response, true);
    if ($responseData) {
        echo "   Response: " . json_encode($responseData) . "\n";
    } else {
        echo "   Response: " . substr($response, 0, 200) . "\n";
    }
}

echo "\n";

// Test 5: SSL Certificate check
echo "5. Testing SSL Certificate\n";
$ch = curl_init($baseUrl . '/companies');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$curlInfo = curl_getinfo($ch);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo "   ❌ SSL Error: $curlError\n";
} else {
    echo "   ✅ SSL Certificate valid\n";
    echo "   Protocol: " . $curlInfo['scheme'] . "\n";
}

echo "\n";

// Test 6: DNS Resolution
echo "6. Testing DNS Resolution\n";
$host = 'api.optika.ba';
$ip = gethostbyname($host);
if ($ip === $host) {
    echo "   ❌ DNS resolution failed\n";
} else {
    echo "   ✅ Resolved to: $ip\n";
}

echo "\n";

// Test 7: Server headers
echo "7. Testing Server Headers\n";
$ch = curl_init($baseUrl . '/featured-action/config');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$curlInfo = curl_getinfo($ch);
curl_close($ch);

if ($curlInfo['http_code'] > 0) {
    echo "   ✅ Server responding\n";
    echo "   Content-Type: " . ($curlInfo['content_type'] ?? 'N/A') . "\n";
} else {
    echo "   ❌ Server not responding\n";
}

echo "\n";
echo "=== Test Complete ===\n";

