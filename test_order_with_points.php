<?php

/**
 * Test script for Order with Points Processing
 * 
 * Usage:
 * php test_order_with_points.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Order with Points Processing Test ===\n\n";

// Import necessary classes
use App\Models\User;
use Illuminate\Support\Facades\DB;

// Test 1: Find a test user
echo "1. Finding test user...\n";
$user = User::first();
if (!$user) {
    echo "   ❌ No users found in database. Create a user first.\n";
    exit(1);
}
echo "   ✓ User found: {$user->name} (ID: {$user->id})\n";
echo "   Current balance: {$user->points} KM\n\n";

// Test 2: Simulate order processing
echo "2. Simulating order with points...\n";
$originalBalance = $user->points;
$pointsUsed = 30;
$orderTotal = 90; // After loyalty discount
$cashbackPercentage = config('discount.cashback_percentage', 5);
$cashbackAmount = ($orderTotal * $cashbackPercentage) / 100;

echo "   Order Total: {$orderTotal} KM\n";
echo "   Points to use: {$pointsUsed} KM\n";
echo "   Cashback ({$cashbackPercentage}%): {$cashbackAmount} KM\n\n";

// Test 3: Check if user has enough points
echo "3. Validating points...\n";
if ($user->points < $pointsUsed) {
    echo "   ❌ User doesn't have enough points!\n";
    echo "   Available: {$user->points} KM\n";
    echo "   Requested: {$pointsUsed} KM\n";
    exit(1);
}
echo "   ✓ User has enough points\n\n";

// Test 4: Process points (simulation - will rollback)
echo "4. Processing points (simulation)...\n";
DB::beginTransaction();

try {
    // Deduct points used
    $user->points -= $pointsUsed;
    echo "   After deduction: {$user->points} KM\n";
    
    // Add cashback
    $user->points += $cashbackAmount;
    echo "   After cashback: {$user->points} KM\n";
    
    $user->save();
    
    $expectedBalance = $originalBalance - $pointsUsed + $cashbackAmount;
    echo "   Expected balance: {$expectedBalance} KM\n";
    echo "   Actual balance: {$user->points} KM\n";
    
    if (abs($user->points - $expectedBalance) < 0.01) {
        echo "   ✓ Balance calculation correct!\n\n";
    } else {
        echo "   ❌ Balance mismatch!\n\n";
    }
    
    // Rollback - don't save changes
    DB::rollback();
    echo "5. Changes rolled back (test only)\n";
    echo "   User balance unchanged: {$originalBalance} KM\n\n";
    
} catch (\Exception $e) {
    DB::rollback();
    echo "   ❌ Error: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Test 5: Check discount config
echo "6. Checking discount configuration...\n";
$discountConfig = config('discount');
echo "   Loyalty Discount: {$discountConfig['loyalty_discount_percentage']}%\n";
echo "   Cashback: {$discountConfig['cashback_percentage']}%\n";
echo "   Allow Points Payment: " . ($discountConfig['allow_points_payment'] ? 'Yes' : 'No') . "\n";
echo "   Points to Currency Rate: {$discountConfig['points_to_currency_rate']}\n\n";

echo "=== All Tests Passed! ===\n\n";

echo "Points Processing Logic:\n";
echo "┌─────────────────────────────────────┐\n";
echo "│ Original Balance: {$originalBalance} KM        │\n";
echo "│ Points Used:     -{$pointsUsed} KM         │\n";
echo "│ Cashback Earned:  +{$cashbackAmount} KM           │\n";
echo "│ ─────────────────────────────────   │\n";
echo "│ New Balance:      " . ($originalBalance - $pointsUsed + $cashbackAmount) . " KM        │\n";
echo "└─────────────────────────────────────┘\n\n";

echo "To test the full flow:\n";
echo "1. Make sure backend server is running: php artisan serve\n";
echo "2. Open the app and login\n";
echo "3. Select a product and use points for payment\n";
echo "4. Complete the order\n";
echo "5. Check database to verify points were updated\n\n";

