<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BonusConfig;
use App\Models\User;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class BonusController extends Controller
{
    public function getConfig()
    {
        $config = BonusConfig::getConfig();
        return response()->json($config);
    }

    public function updateConfig(Request $request)
    {
        $request->validate([
            'enabled' => 'boolean',
            'bonus_points' => 'integer|min:0',
            'bonus_title' => 'string|max:255',
            'bonus_message' => 'string|max:500'
        ]);

        $config = BonusConfig::getConfig();
        $config->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Bonus konfiguracija je ažurirana',
            'config' => $config
        ]);
    }

    public function toggle()
    {
        $config = BonusConfig::getConfig();
        $config->enabled = !$config->enabled;
        $config->save();

        return response()->json([
            'success' => true,
            'message' => $config->enabled ? 'Bonus sistem je uključen' : 'Bonus sistem je isključen',
            'enabled' => $config->enabled
        ]);
    }

    public function giveBonusToUser($userId)
    {
        try {
            $config = BonusConfig::getConfig();
            
            if (!$config->enabled) {
                return false;
            }

            $user = User::find($userId);
            if (!$user) {
                return false;
            }

            // Provjeri da li je korisnik već dobio bonus
            $existingBonus = Transaction::where('user_id', $userId)
                ->where('type', 'bonus')
                ->where('description', 'like', '%dobrodošlicu%')
                ->first();

            if ($existingBonus) {
                return false; // Već je dobio bonus
            }

            DB::beginTransaction();

            // Dodaj poene korisniku
            $user->points += $config->bonus_points;
            $user->save();

            // Kreiraj transakciju
            Transaction::create([
                'user_id' => $userId,
                'type' => 'bonus',
                'points' => $config->bonus_points,
                'description' => $config->bonus_message,
                'status' => 'completed'
            ]);

            DB::commit();

            return [
                'success' => true,
                'points' => $config->bonus_points,
                'title' => $config->bonus_title,
                'message' => $config->bonus_message
            ];

        } catch (\Exception $e) {
            DB::rollback();
            return false;
        }
    }
}
