<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;

class FeaturedActionController extends Controller
{
    /**
     * Get featured action configuration
     */
    public function getConfig(): JsonResponse
    {
        // Try to get from cache first, fallback to config
        $config = Cache::get('featured_action_config', Config::get('featured_action'));
        
        // Check if action is enabled and within date range
        $isActive = $this->isActionActive($config);
        
        return response()->json([
            'enabled' => $config['enabled'] && $isActive,
            'content' => $config['content'],
            'action' => $config['action'],
            'design' => $config['design'],
            'is_active' => $isActive
        ]);
    }
    
    /**
     * Update featured action configuration
     */
    public function updateConfig(Request $request): JsonResponse
    {
        $request->validate([
            'enabled' => 'boolean',
            'content.title' => 'string|max:100',
            'content.subtitle' => 'string|max:500',
            'content.badge_text' => 'string|max:20',
            'action.brand_name' => 'string|max:100',
            'action.description' => 'string|max:200',
            'action.meta_text' => 'string|max:100',
            'action.logo_path' => 'string|max:100',
            'design.gradient_start' => 'string|max:20',
            'design.gradient_end' => 'string|max:20',
            'design.badge_gradient_start' => 'string|max:20',
            'design.badge_gradient_end' => 'string|max:20',
            'timing.start_date' => 'nullable|date',
            'timing.end_date' => 'nullable|date|after:timing.start_date'
        ]);
        
        // Get current config
        $config = Cache::get('featured_action_config', Config::get('featured_action'));
        
        // Update config with new values
        foreach ($request->all() as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $subKey => $subValue) {
                    if (isset($config[$key][$subKey])) {
                        $config[$key][$subKey] = $subValue;
                    }
                }
            } else {
                if (isset($config[$key])) {
                    $config[$key] = $value;
                }
            }
        }
        
        // Store updated config in cache
        Cache::put('featured_action_config', $config, 3600); // Cache for 1 hour
        
        // Also try to update .env file
        $this->updateEnvVariables($request->all());
        
        return response()->json([
            'success' => true,
            'message' => 'Featured action configuration updated successfully'
        ]);
    }
    
    /**
     * Toggle featured action on/off
     */
    public function toggle(Request $request): JsonResponse
    {
        $enabled = $request->boolean('enabled');
        
        // Get current config
        $config = Cache::get('featured_action_config', Config::get('featured_action'));
        
        // Update enabled status
        $config['enabled'] = $enabled;
        
        // Store in cache
        Cache::put('featured_action_config', $config, 3600); // Cache for 1 hour
        
        // Also try to update .env file
        $this->updateEnvVariable('FEATURED_ACTION_ENABLED', $enabled ? 'true' : 'false');
        
        return response()->json([
            'success' => true,
            'enabled' => $enabled,
            'message' => $enabled ? 'Featured action enabled' : 'Featured action disabled'
        ]);
    }
    
    /**
     * Check if action is currently active based on dates
     */
    private function isActionActive(array $config): bool
    {
        $now = now();
        $startDate = $config['timing']['start_date'] ? \Carbon\Carbon::parse($config['timing']['start_date']) : null;
        $endDate = $config['timing']['end_date'] ? \Carbon\Carbon::parse($config['timing']['end_date']) : null;
        
        if ($startDate && $now->lt($startDate)) {
            return false;
        }
        
        if ($endDate && $now->gt($endDate)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Update environment variables
     */
    private function updateEnvVariables(array $data): void
    {
        $envFile = base_path('.env');
        $envContent = file_get_contents($envFile);
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $subKey => $subValue) {
                    $envKey = 'FEATURED_ACTION_' . strtoupper($key . '_' . $subKey);
                    $envContent = $this->updateEnvVariable($envKey, $subValue, $envContent);
                }
            } else {
                $envKey = 'FEATURED_ACTION_' . strtoupper($key);
                $envContent = $this->updateEnvVariable($envKey, $value, $envContent);
            }
        }
        
        file_put_contents($envFile, $envContent);
    }
    
    /**
     * Update single environment variable
     */
    private function updateEnvVariable(string $key, $value, ?string $envContent = null): string
    {
        if ($envContent === null) {
            $envFile = base_path('.env');
            $envContent = file_get_contents($envFile);
        }
        
        $escapedValue = is_string($value) ? '"' . addslashes($value) . '"' : ($value ? 'true' : 'false');
        
        if (strpos($envContent, $key . '=') !== false) {
            $envContent = preg_replace(
                '/^' . preg_quote($key, '/') . '=.*$/m',
                $key . '=' . $escapedValue,
                $envContent
            );
        } else {
            $envContent .= "\n" . $key . '=' . $escapedValue;
        }
        
        return $envContent;
    }
}
