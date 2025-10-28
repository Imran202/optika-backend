<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

echo "=== User Phone Number Test ===\n\n";

try {
    // Check users table structure
    echo "📋 Checking users table structure...\n";
    $columns = DB::select("PRAGMA table_info(users)");
    echo "Users table columns:\n";
    foreach ($columns as $column) {
        echo "  - {$column->name} ({$column->type})\n";
    }
    echo "\n";
    
    // Check all users and their phone numbers
    echo "👥 Checking all users...\n";
    $users = DB::table('users')->select('id', 'name', 'email', 'phone')->get();
    
    foreach ($users as $user) {
        echo "User ID: {$user->id}\n";
        echo "  Name: {$user->name}\n";
        echo "  Email: {$user->email}\n";
        echo "  Phone: " . ($user->phone ?: 'NULL') . "\n";
        echo "  Phone length: " . ($user->phone ? strlen($user->phone) : 0) . "\n";
        echo "\n";
    }
    
    // Check specific user by email
    echo "🔍 Checking specific user by email...\n";
    $specificUser = DB::table('users')->where('email', 'dzeno.tm@gmail.com')->first();
    
    if ($specificUser) {
        echo "Found user:\n";
        echo "  ID: {$specificUser->id}\n";
        echo "  Name: {$specificUser->name}\n";
        echo "  Email: {$specificUser->email}\n";
        echo "  Phone: " . ($specificUser->phone ?: 'NULL') . "\n";
        echo "  Phone length: " . ($specificUser->phone ? strlen($specificUser->phone) : 0) . "\n";
    } else {
        echo "❌ User not found\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== Test completed ===\n";
