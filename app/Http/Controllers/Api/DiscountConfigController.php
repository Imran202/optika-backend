<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DiscountConfigController extends Controller
{
    /**
     * Get current discount configuration
     */
    public function getConfig()
    {
        try {
            $config = [
                'loyalty_discount_percentage' => config('discount.loyalty_discount_percentage'),
                'cashback_percentage' => config('discount.cashback_percentage'),
                'allow_points_payment' => config('discount.allow_points_payment'),
                'points_to_currency_rate' => config('discount.points_to_currency_rate'),
                'enabled' => config('discount.enabled'),
            ];

            return response()->json([
                'success' => true,
                'config' => $config
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get discount config', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load discount configuration'
            ], 500);
        }
    }

    /**
     * Update discount configuration
     * This should be protected with admin authentication in production
     */
    public function updateConfig(Request $request)
    {
        try {
            $validated = $request->validate([
                'loyalty_discount_percentage' => 'required|numeric|min:0|max:100',
                'cashback_percentage' => 'required|numeric|min:0|max:100',
                'allow_points_payment' => 'required|boolean',
                'points_to_currency_rate' => 'required|numeric|min:0',
                'enabled' => 'required|boolean',
            ]);

            // Update .env file
            $this->updateEnvFile([
                'LOYALTY_DISCOUNT_PERCENTAGE' => $validated['loyalty_discount_percentage'],
                'CASHBACK_PERCENTAGE' => $validated['cashback_percentage'],
                'ALLOW_POINTS_PAYMENT' => $validated['allow_points_payment'] ? 'true' : 'false',
                'POINTS_TO_CURRENCY_RATE' => $validated['points_to_currency_rate'],
                'DISCOUNT_ENABLED' => $validated['enabled'] ? 'true' : 'false',
            ]);

            // Clear config cache
            \Artisan::call('config:clear');

            return response()->json([
                'success' => true,
                'message' => 'Discount configuration updated successfully',
                'config' => $validated
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update discount config', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update discount configuration'
            ], 500);
        }
    }

    /**
     * Update environment file
     */
    private function updateEnvFile(array $data)
    {
        $envFile = base_path('.env');
        $str = file_get_contents($envFile);

        foreach ($data as $key => $value) {
            $str = preg_match("/^{$key}=.*/m", $str)
                ? preg_replace("/^{$key}=.*/m", "{$key}={$value}", $str)
                : $str . "\n{$key}={$value}";
        }

        file_put_contents($envFile, $str);
    }
}

