<?php

return [
    'enabled' => env('FEATURED_ACTION_ENABLED', false),
    
    'content' => [
        'title' => env('FEATURED_ACTION_TITLE', 'Istaknuta akcija'),
        'subtitle' => env('FEATURED_ACTION_SUBTITLE', 'Za naše cijenjene loyalty članove pripremili smo posebnu ponudu koju ne želite propustiti!'),
        'badge_text' => env('FEATURED_ACTION_BADGE', 'NOVO'),
    ],
    
    'action' => [
        'brand_name' => env('FEATURED_ACTION_BRAND', 'Polaroid brend'),
        'description' => env('FEATURED_ACTION_DESCRIPTION', '15% popusta + 5% na karticu'),
        'meta_text' => env('FEATURED_ACTION_META', 'Ograničeno vrijeme'),
        'logo_path' => env('FEATURED_ACTION_LOGO', 'polaroid-logo.png'),
    ],
    
    'design' => [
        'gradient_start' => env('FEATURED_ACTION_GRADIENT_START', '#667eea'),
        'gradient_end' => env('FEATURED_ACTION_GRADIENT_END', '#764ba2'),
        'badge_gradient_start' => env('FEATURED_ACTION_BADGE_GRADIENT_START', '#4facfe'),
        'badge_gradient_end' => env('FEATURED_ACTION_BADGE_GRADIENT_END', '#00f2fe'),
    ],
    
    'timing' => [
        'start_date' => env('FEATURED_ACTION_START_DATE', null),
        'end_date' => env('FEATURED_ACTION_END_DATE', null),
    ]
];
