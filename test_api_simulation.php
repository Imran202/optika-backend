<?php

require_once 'vendor/autoload.php';

use App\Http\Controllers\Api\GlassesController;
use Illuminate\Http\Request;

echo "=== API Simulation Test ===\n\n";

// Create a mock request with user data
$request = new Request();
$request->setUserResolver(function () {
    return (object) [
        'id' => 1,
        'name' => 'MUHAMED MULIĆ',
        'email' => 'muhamed@test.com',
        'phone' => '671147785'
    ];
});

// Create controller instance
$controller = new GlassesController();

try {
    echo "🔍 Testing with user: MUHAMED MULIĆ (671147785)\n";
    $response = $controller->getUserGlasses($request);
    
    echo "📱 Response status: " . $response->getStatusCode() . "\n";
    $data = json_decode($response->getContent(), true);
    
    if ($data['success']) {
        echo "✅ Success!\n";
        echo "👓 Glasses found: " . count($data['data']['glasses']) . "\n";
        
        foreach ($data['data']['glasses'] as $index => $glasses) {
            echo "   " . ($index + 1) . ". {$glasses['name']} - {$glasses['type']} ({$glasses['purchaseDate']})\n";
        }
    } else {
        echo "❌ Error: " . ($data['message'] ?? 'Unknown error') . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
}

echo "\n=== Test completed ===\n";
