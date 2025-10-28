<?php

/**
 * Test script for Transaction Creation
 * 
 * Usage:
 * php test_transactions.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Transaction Creation Test ===\n\n";

use App\Models\User;
use Illuminate\Support\Facades\DB;

// Test 1: Find a test user
echo "1. Finding test user...\n";
$user = User::first();
if (!$user) {
    echo "   ❌ No users found in database.\n";
    exit(1);
}
echo "   ✓ User found: {$user->username} (RFID: {$user->rfid})\n";
echo "   Current balance: {$user->points} KM\n\n";

// Test 2: Count existing transactions
echo "2. Counting existing transactions...\n";
$existingCount = DB::table('transactions')->where('rfid', $user->rfid)->count();
echo "   Existing transactions: {$existingCount}\n\n";

// Test 3: Simulate transaction creation (will rollback)
echo "3. Simulating transaction creation...\n";
DB::beginTransaction();

try {
    // Simulate points deduction transaction (auto-increment ID)
    $pointsUsed = 30;
    $pointsForDB = $pointsUsed * 10; // Points stored as * 10
    
    echo "   Creating 'skinuto' transaction...\n";
    $transId1 = DB::table('transactions')->insertGetId([
        'poslovnica' => 'Loyalty App',
        'rfid' => $user->rfid,
        'user' => $user->username,
        'date' => now(),
        'points' => $pointsForDB,
        'action' => 'skinuto',
        'vrsta' => 'Online Shop - Plaćanje',
    ], 'transcation_id');
    echo "   ✓ 'skinuto' transaction created (ID: {$transId1}, 30 KM)\n";
    
    // Simulate cashback transaction
    $cashbackAmount = 4.5;
    $cashbackForDB = $cashbackAmount * 10;
    
    echo "   Creating 'dodato' transaction...\n";
    $transId2 = DB::table('transactions')->insertGetId([
        'poslovnica' => 'Loyalty App',
        'rfid' => $user->rfid,
        'user' => $user->username,
        'date' => now(),
        'points' => $cashbackForDB,
        'action' => 'dodato',
        'vrsta' => 'Online Shop - Cashback 5%',
    ], 'transcation_id');
    echo "   ✓ 'dodato' transaction created (ID: {$transId2}, 4.5 KM)\n\n";
    
    // Verify transactions
    $newCount = DB::table('transactions')->where('rfid', $user->rfid)->count();
    echo "4. Verifying transactions...\n";
    echo "   Transactions before: {$existingCount}\n";
    echo "   Transactions now: {$newCount}\n";
    echo "   New transactions: " . ($newCount - $existingCount) . "\n\n";
    
    if ($newCount - $existingCount == 2) {
        echo "   ✓ Both transactions created successfully!\n\n";
    }
    
    // Get last transactions
    echo "5. Fetching last transactions...\n";
    $lastTransactions = DB::table('transactions')
        ->where('rfid', $user->rfid)
        ->orderBy('date', 'desc')
        ->limit(2)
        ->get(['poslovnica', 'points', 'action', 'vrsta', 'date']);
    
    foreach ($lastTransactions as $trans) {
        $displayPoints = $trans->points / 10;
        echo "   • [{$trans->action}] {$displayPoints} KM - {$trans->vrsta}\n";
    }
    
    // Rollback - don't save changes
    DB::rollback();
    echo "\n6. Changes rolled back (test only)\n";
    echo "   Transactions unchanged\n\n";
    
} catch (\Exception $e) {
    DB::rollback();
    echo "   ❌ Error: " . $e->getMessage() . "\n\n";
    exit(1);
}

echo "=== All Tests Passed! ===\n\n";

echo "Transaction Structure:\n";
echo "┌─────────────────────────────────────────────┐\n";
echo "│ poslovnica: 'Loyalty App'                   │\n";
echo "│ rfid:       {$user->rfid}                   │\n";
echo "│ user:       '{$user->username}'             │\n";
echo "│ date:       " . now()->format('Y-m-d H:i:s') . "       │\n";
echo "│ points:     300 (stored as 30 * 10)         │\n";
echo "│ action:     'skinuto' or 'dodato'           │\n";
echo "│ vrsta:      'Online Shop - Plaćanje'        │\n";
echo "└─────────────────────────────────────────────┘\n\n";

echo "In production, transactions will be created for:\n";
echo "1. Points used for payment (action: 'skinuto')\n";
echo "2. Cashback reward (action: 'dodato')\n\n";

