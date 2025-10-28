<?php

require_once 'vendor/autoload.php';

use App\Http\Controllers\Api\ReviewHistoryController;

// Test phone matching logic
echo "=== Phone Matching Logic Test ===\n\n";

// Simulate user data
$testUsers = [
    [
        'phone' => '61201891', // 8 digits
        'description' => '8-digit user phone'
    ],
    [
        'phone' => '061201891', // 10 digits
        'description' => '10-digit user phone'
    ],
    [
        'phone' => '061234567', // 10 digits
        'description' => '10-digit user phone 2'
    ]
];

// Simulate sheet data
$sheetPhones = [
    '61201891',    // 8 digits
    '061201891',   // 10 digits
    '061234567',   // 10 digits
    '33653300',    // 8 digits
    '061901057',   // 10 digits
    '12345678',    // 8 digits
    '0987654321'   // 10 digits
];

function cleanPhoneNumber($phone) {
    $originalPhone = $phone;
    
    // If phone is empty or just dashes, return empty
    if (empty($phone) || $phone === '---' || $phone === 'N/A') {
        echo "  Phone cleaning: '$originalPhone' -> '' (empty/dash)\n";
        return '';
    }
    
    // Remove all non-digit characters (spaces, dashes, etc.)
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Remove country code if present (387, 387, etc.)
    if (strlen($phone) > 9 && substr($phone, 0, 3) === '387') {
        $phone = substr($phone, 3);
    }
    
    // If phone is too short (less than 8 digits), it's probably invalid
    if (strlen($phone) < 8) {
        echo "  Phone cleaning: '$originalPhone' -> '' (too short: $phone)\n";
        return '';
    }
    
    echo "  Phone cleaning: '$originalPhone' -> '$phone' (length: " . strlen($phone) . ")\n";
    
    return $phone;
}

function matchesUser($userPhone, $sheetPhone) {
    $userPhone = cleanPhoneNumber($userPhone);
    $sheetPhone = cleanPhoneNumber($sheetPhone);
    
    echo "  Comparing: user='$userPhone' vs sheet='$sheetPhone'\n";
    
    // Check exact match first
    if ($userPhone === $sheetPhone) {
        echo "  ✅ Exact match\n";
        return true;
    }
    
            // No partial matching - only exact matches and leading zero variations
    
    // Special case: if user phone is 8 digits, try adding leading '0'
    if (strlen($userPhone) === 8) {
        $userPhoneWithZero = '0' . $userPhone;
        if ($sheetPhone === $userPhoneWithZero) {
            echo "  ✅ Match with added leading zero: '$userPhone' -> '$userPhoneWithZero'\n";
            return true;
        }
    }
    
            // Special case: if user phone is 10 digits, try removing leading '0'
        if (strlen($userPhone) === 10 && substr($userPhone, 0, 1) === '0') {
            $userPhoneWithoutZero = substr($userPhone, 1);
            if ($sheetPhone === $userPhoneWithoutZero) {
                echo "  ✅ Match with removed leading zero: '$userPhone' -> '$userPhoneWithoutZero'\n";
                return true;
            }
        }
        
        // Special case: if sheet phone is 8 digits, try adding leading '0'
        if (strlen($sheetPhone) === 8) {
            $sheetPhoneWithZero = '0' . $sheetPhone;
            if ($userPhone === $sheetPhoneWithZero) {
                echo "  ✅ Match with added leading zero to sheet: '$sheetPhone' -> '$sheetPhoneWithZero'\n";
                return true;
            }
        }
        
        // Special case: if sheet phone is 10 digits, try removing leading '0'
        if (strlen($sheetPhone) === 10 && substr($sheetPhone, 0, 1) === '0') {
            $sheetPhoneWithoutZero = substr($sheetPhone, 1);
            if ($userPhone === $sheetPhoneWithoutZero) {
                echo "  ✅ Match with removed leading zero from sheet: '$sheetPhone' -> '$sheetPhoneWithoutZero'\n";
                return true;
            }
        }

    echo "  ❌ No match found\n";
    return false;
}

// Test each user against each sheet phone
foreach ($testUsers as $userIndex => $user) {
    echo "\n--- Test User " . ($userIndex + 1) . " ---\n";
    echo "User Phone: {$user['phone']} ({$user['description']})\n";
    echo "Length: " . strlen($user['phone']) . " digits\n";
    
    $matches = [];
    
    foreach ($sheetPhones as $sheetIndex => $sheetPhone) {
        echo "\n  Testing against sheet phone " . ($sheetIndex + 1) . ": $sheetPhone\n";
        
        if (matchesUser($user['phone'], $sheetPhone)) {
            $matches[] = [
                'sheet_index' => $sheetIndex + 1,
                'sheet_phone' => $sheetPhone
            ];
        }
    }
    
    if (empty($matches)) {
        echo "\n  ❌ No matches found for user {$user['phone']}\n";
    } else {
        echo "\n  ✅ Found " . count($matches) . " matches for user {$user['phone']}:\n";
        foreach ($matches as $match) {
            echo "    - Sheet phone " . $match['sheet_index'] . ": " . $match['sheet_phone'] . "\n";
        }
    }
}

echo "\n=== Test completed ===\n";
