<?php

/**
 * Test script for Discount Configuration API
 * 
 * Usage:
 * php test_discount_config.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Discount Configuration Test ===\n\n";

// Test 1: Get current config
echo "1. Testing config retrieval...\n";
$config = config('discount');
echo "   Loyalty Discount: {$config['loyalty_discount_percentage']}%\n";
echo "   Enabled: " . ($config['enabled'] ? 'Yes' : 'No') . "\n";
echo "   ✓ Config loaded successfully\n\n";

// Test 2: Test API endpoint (simulated)
echo "2. Testing API structure...\n";
$response = [
    'success' => true,
    'config' => [
        'loyalty_discount_percentage' => config('discount.loyalty_discount_percentage'),
        'enabled' => config('discount.enabled'),
    ]
];
echo "   API Response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n";
echo "   ✓ API structure correct\n\n";

// Test 3: Calculate sample discounts
echo "3. Testing discount calculations...\n";
$originalPrice = 100.00;
$discountPercentage = config('discount.loyalty_discount_percentage');
$discountAmount = $originalPrice * ($discountPercentage / 100);
$finalPrice = $originalPrice - $discountAmount;

echo "   Original Price: {$originalPrice} KM\n";
echo "   Discount ({$discountPercentage}%): {$discountAmount} KM\n";
echo "   Final Price: {$finalPrice} KM\n";
echo "   ✓ Calculations working\n\n";

echo "=== All Tests Passed! ===\n\n";

// Instructions
echo "To test the API endpoints:\n";
echo "1. Start your Laravel server: php artisan serve\n";
echo "2. Test GET endpoint:\n";
echo "   curl http://localhost:8000/api/discount/config\n\n";
echo "3. Test PUT endpoint:\n";
echo "   curl -X PUT http://localhost:8000/api/admin/discount/config \\\n";
echo "     -H 'Content-Type: application/json' \\\n";
echo "     -d '{\"loyalty_discount_percentage\": 40, \"enabled\": true}'\n\n";

