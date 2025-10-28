<?php

return [
    // Loyalty popust za sve korisnike aplikacije
    'loyalty_discount_percentage' => env('LOYALTY_DISCOUNT_PERCENTAGE', 10),
    
    // Cashback procenat koji se vraća nakon kupovine
    'cashback_percentage' => env('CASHBACK_PERCENTAGE', 5),
    
    // Da li korisnik može da koristi points kao novac
    'allow_points_payment' => env('ALLOW_POINTS_PAYMENT', true),
    
    // Konverzija: 1 point = koliko KM
    'points_to_currency_rate' => env('POINTS_TO_CURRENCY_RATE', 1), // 1 point = 1 KM
    
    // Da li je sistem popusta aktivan
    'enabled' => env('DISCOUNT_ENABLED', true),
];

