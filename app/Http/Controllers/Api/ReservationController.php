<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class ReservationController extends Controller
{
    /**
     * Get available time slots for a specific date and poslovnica
     */
    public function getAvailableTimeSlots(Request $request)
    {
        try {
            $request->validate([
                'datum' => 'required|date',
                'poslovnica' => 'required|string'
            ]);

            $datum = $request->datum;
            $poslovnica = $request->poslovnica;

            // Generate all time slots from 10:00 to 21:00 every 30 minutes
            $timeSlots = [];
            $startTime = Carbon::createFromTime(10, 0, 0);
            $endTime = Carbon::createFromTime(21, 0, 0);

            while ($startTime <= $endTime) {
                $timeSlots[] = $startTime->format('H:i:s');
                $startTime->addMinutes(30);
            }

            // Get booked time slots for this date and poslovnica
            $bookedSlots = DB::table('rezervacije')
                ->where('datum', $datum)
                ->where('poslovnica', $poslovnica)
                ->pluck('vrijeme')
                ->map(function($time) {
                    // Remove microseconds from time format
                    return substr($time, 0, 8);
                })
                ->toArray();

            // Get admin opening times for this date and poslovnica
            $adminOpenings = DB::table('admin_otvaranje')
                ->where('datum', $datum)
                ->where('poslovnica', $poslovnica)
                ->pluck('vrijeme')
                ->toArray();

            // Create final time slots array
            $finalTimeSlots = [];

            // Add all regular time slots (all are available by default)
            foreach ($timeSlots as $slot) {
                $slotTime = Carbon::createFromFormat('H:i:s', $slot);
                $isBooked = in_array($slot, $bookedSlots);

                $finalTimeSlots[] = [
                    'time' => $slot,
                    'time_display' => $slotTime->format('H:i'),
                    'available' => !$isBooked,
                    'booked' => $isBooked,
                    'is_admin_slot' => false
                ];
            }

            // Add admin opening times (if not already in regular slots)
            foreach ($adminOpenings as $adminSlot) {
                $adminTime = Carbon::createFromFormat('H:i:s', $adminSlot);
                $adminTimeFormatted = $adminTime->format('H:i:s');
                
                // Check if this admin slot is already in regular slots
                $exists = false;
                foreach ($finalTimeSlots as $slot) {
                    if ($slot['time'] === $adminTimeFormatted) {
                        $exists = true;
                        break;
                    }
                }

                // If admin slot doesn't exist in regular slots, add it
                if (!$exists) {
                    $isBooked = in_array($adminTimeFormatted, $bookedSlots);
                    
                    $finalTimeSlots[] = [
                        'time' => $adminTimeFormatted,
                        'time_display' => $adminTime->format('H:i'),
                        'available' => !$isBooked,
                        'booked' => $isBooked,
                        'is_admin_slot' => true
                    ];
                }
            }

            // Sort by time
            usort($finalTimeSlots, function($a, $b) {
                return strtotime($a['time']) - strtotime($b['time']);
            });

            return response()->json([
                'success' => true,
                'data' => $finalTimeSlots
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching time slots: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new reservation
     */
    public function createReservation(Request $request)
    {
        try {
            $request->validate([
                'poslovnica' => 'required|string',
                'datum' => 'required|date',
                'vrijeme' => 'required|date_format:H:i:s',
                'ime' => 'required|string',
                'prezime' => 'required|string',
                'telefon' => 'required|string',
                'email' => 'required|email'
            ]);

            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            // Check if the time slot is still available
            $existingReservation = DB::table('rezervacije')
                ->where('datum', $request->datum)
                ->where('vrijeme', $request->vrijeme)
                ->where('poslovnica', $request->poslovnica)
                ->first();

            if ($existingReservation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ovaj termin je već zauzet'
                ], 400);
            }

            // Get next available ID
            $nextId = DB::table('rezervacije')->max('id_rezervacije') + 1;

            // Create reservation
            $reservationId = DB::table('rezervacije')->insertGetId([
                'id_rezervacije' => $nextId,
                'poslovnica' => $request->poslovnica,
                'datum' => $request->datum,
                'vrijeme' => $request->vrijeme,
                'ime' => $request->ime,
                'prezime' => $request->prezime,
                'telefon' => $request->telefon,
                'email' => $request->email
            ]);

            // Format time from H:i:s to H:i for Pipedream
            $vrijemeFormatted = Carbon::createFromFormat('H:i:s', $request->vrijeme)->format('H:i');
            
            // Format date to Y-m-d format
            $datumFormatted = Carbon::parse($request->datum)->format('Y-m-d');

            // Send reservation data to Pipedream webhook
            try {
                $pipedreamData = [
                    'id_rezervacije' => $nextId,
                    'poslovnica' => $request->poslovnica,
                    'datum' => $datumFormatted,
                    'vrijeme' => $vrijemeFormatted,
                    'ime' => $request->ime,
                    'prezime' => $request->prezime,
                    'telefon' => $request->telefon,
                    'email' => $request->email
                ];

                $response = Http::timeout(10)->post('https://eo5deg3erplz8mo.m.pipedream.net/', $pipedreamData);
                
                // Log the response for debugging (optional)
                \Log::info('Pipedream webhook response', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
            } catch (\Exception $e) {
                // Log error but don't fail the reservation creation
                \Log::error('Failed to send reservation to Pipedream', [
                    'error' => $e->getMessage(),
                    'reservation_id' => $nextId
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Rezervacija je uspješno kreirana',
                'data' => [
                    'id_rezervacije' => $nextId,
                    'poslovnica' => $request->poslovnica,
                    'datum' => $request->datum,
                    'vrijeme' => $request->vrijeme
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating reservation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's reservations
     */
    public function getUserReservations(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $reservations = DB::table('rezervacije')
                ->where('email', $user->useremail)
                ->orderBy('datum', 'desc')
                ->orderBy('vrijeme', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $reservations
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching reservations: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a reservation
     */
    public function updateReservation(Request $request)
    {
        try {
            $request->validate([
                'id_rezervacije' => 'required|integer',
                'poslovnica' => 'required|string',
                'datum' => 'required|date',
                'vrijeme' => 'required|date_format:H:i:s',
            ]);

            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            // Check if reservation belongs to user
            $reservation = DB::table('rezervacije')
                ->where('id_rezervacije', $request->id_rezervacije)
                ->where('email', $user->useremail)
                ->first();

            if (!$reservation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Rezervacija nije pronađena'
                ], 404);
            }

            // Check if the new time slot is available
            $existingReservation = DB::table('rezervacije')
                ->where('datum', $request->datum)
                ->where('vrijeme', $request->vrijeme)
                ->where('poslovnica', $request->poslovnica)
                ->where('id_rezervacije', '!=', $request->id_rezervacije)
                ->first();

            if ($existingReservation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ovaj termin je već zauzet'
                ], 400);
            }

            // Update reservation
            DB::table('rezervacije')
                ->where('id_rezervacije', $request->id_rezervacije)
                ->update([
                    'poslovnica' => $request->poslovnica,
                    'datum' => $request->datum,
                    'vrijeme' => $request->vrijeme
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Rezervacija je uspješno ažurirana'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating reservation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel a reservation
     */
    public function cancelReservation(Request $request)
    {
        try {
            $request->validate([
                'id_rezervacije' => 'required|integer'
            ]);

            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            // Check if reservation belongs to user
            $reservation = DB::table('rezervacije')
                ->where('id_rezervacije', $request->id_rezervacije)
                ->where('email', $user->useremail)
                ->first();

            if (!$reservation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Rezervacija nije pronađena'
                ], 404);
            }

            // Delete reservation
            DB::table('rezervacije')
                ->where('id_rezervacije', $request->id_rezervacije)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Rezervacija je uspješno otkazana'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error canceling reservation: ' . $e->getMessage()
            ], 500);
        }
    }
}
