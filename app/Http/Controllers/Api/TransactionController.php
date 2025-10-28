<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{
    /**
     * Get last 3 transactions for the authenticated user
     */
    public function getLastTransactions(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            // Get user's RFID from the user table
            $rfid = $user->rfid ?? null;
            
            if (!$rfid) {
                return response()->json([
                    'success' => false,
                    'message' => 'RFID not found for user'
                ], 404);
            }

            // Get last 3 transactions for this RFID
            $transactions = DB::table('transactions')
                ->where('rfid', $rfid)
                ->orderBy('date', 'desc')
                ->limit(3)
                ->get([
                    'transcation_id',
                    'poslovnica',
                    'vrsta',
                    'date',
                    'points',
                    'action'
                ]);

            // Convert points to points/10
            $transactions = $transactions->map(function ($transaction) {
                $transaction->points = $transaction->points / 10;
                return $transaction;
            });

            return response()->json([
                'success' => true,
                'data' => $transactions
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching transactions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all transactions for the authenticated user
     */
    public function getAllTransactions(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            // Get user's RFID from the user table
            $rfid = $user->rfid ?? null;
            
            if (!$rfid) {
                return response()->json([
                    'success' => false,
                    'message' => 'RFID not found for user'
                ], 404);
            }

            // Get all transactions for this RFID
            $transactions = DB::table('transactions')
                ->where('rfid', $rfid)
                ->orderBy('date', 'desc')
                ->get([
                    'transcation_id',
                    'poslovnica',
                    'vrsta',
                    'date',
                    'points',
                    'action'
                ]);

            // Convert points to points/10
            $transactions = $transactions->map(function ($transaction) {
                $transaction->points = $transaction->points / 10;
                return $transaction;
            });

            return response()->json([
                'success' => true,
                'data' => $transactions
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching transactions: ' . $e->getMessage()
            ], 500);
        }
    }
}
