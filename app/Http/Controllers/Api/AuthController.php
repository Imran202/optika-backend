<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Models\BonusConfig;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    /**
     * Normalizuje telefonski broj u standardni format
     * Podržava različite formate: 38762267066, +38762267066, 062267066, 62267066
     * Vraća standardni format: 062267066 (sa vodećom nulom za BiH brojeve)
     */
    private function normalizePhoneNumber($phone)
    {
        // Ukloni sve non-digit znakove osim +
        $phone = preg_replace('/[^\d+]/', '', $phone);
        
        // Ukloni +387 ili 387 prefix ako postoji
        if (strpos($phone, '+387') === 0) {
            $phone = substr($phone, 4);
        } elseif (strpos($phone, '387') === 0 && strlen($phone) > 3) {
            $phone = substr($phone, 3);
        }
        
        // Ukloni vodeće nule (osim ako je broj 9 cifara i počinje sa 0)
        $phone = ltrim($phone, '0');
        
        // Za BiH brojeve (8 ili 9 cifara), dodaj vodeću nulu
        $length = strlen($phone);
        if ($length >= 8 && $length <= 9) {
            // Ako je 8 cifara, dodaj vodeću nulu (npr. 62267066 -> 062267066)
            if ($length === 8) {
                $phone = '0' . $phone;
            }
            // Ako je 9 cifara, već je u dobrom formatu (ali provjerimo da počinje sa 0)
            if ($length === 9 && $phone[0] !== '0') {
                $phone = '0' . $phone;
            }
        }
        
        return $phone;
    }
    
    /**
     * Generiše sve moguće varijante telefonskog broja za pretraživanje
     */
    private function getPhoneVariants($phone)
    {
        $normalized = $this->normalizePhoneNumber($phone);
        $variants = [$normalized];
        
        if (strlen($normalized) === 9 && $normalized[0] === '0') {
            // Normalizovani format: 062267066
            $withoutZero = substr($normalized, 1); // 62267066
            
            // Dodaj varijante:
            $variants[] = $withoutZero; // 62267066
            $variants[] = '387' . $withoutZero; // 38762267066
            $variants[] = '+387' . $withoutZero; // +38762267066
        } elseif (strlen($normalized) === 8) {
            // Normalizovani format: 62267066 (8 cifara)
            $variants[] = '387' . $normalized; // 38762267066
            $variants[] = '+387' . $normalized; // +38762267066
        }
        
        return array_unique($variants);
    }
    
    /**
     * Pronalazi korisnika po telefonskom broju, pokušavajući sve moguće varijante
     */
    private function findUserByPhone($phone)
    {
        $variants = $this->getPhoneVariants($phone);
        
        \Log::info('Searching for user by phone variants', [
            'original_phone' => $phone,
            'variants' => $variants
        ]);
        
        // Pokušaj pronaći korisnika sa bilo kojom varijantom
        foreach ($variants as $variant) {
            $user = User::where('userphone', $variant)->first();
            if ($user) {
                \Log::info('User found with phone variant', [
                    'variant' => $variant,
                    'user_id' => $user->id
                ]);
                return $user;
            }
        }
        
        return null;
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,useremail',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Sva polja su obavezna.', 'errors' => $validator->errors()], 400);
        }

        $user = User::create([
            'username' => $request->name,
            'useremail' => $request->email,
            'userphone' => '', // Will be set during phone registration
            'dt' => now(),
            'rfid' => User::generateUniqueRfid(),
            'points' => 0,
            'count' => 0,
            'dioptrija' => 0,
            'dsph' => '',
            'dcyl' => '',
            'daxa' => '',
            'lsph' => '',
            'lcyl' => '',
            'laxa' => '',
            'ldadd' => '',
            'bonus_status' => 0,
        ]);

        return response()->json(['message' => 'Registracija uspješna!']);
    }

    public function login(Request $request)
    {
        // This method is kept for backward compatibility but phone-based auth is preferred
        return response()->json(['message' => 'Molimo koristite phone-based prijavu.'], 400);
    }

    public function phoneLoginOrRegister(Request $request)
    {
        $request->validate([
            'phone_number' => 'required|string',
        ]);
        $phone = $request->input('phone_number');
        
        \Log::info('Phone lookup', [
            'full_phone' => $phone
        ]);
        
        // Koristi helper funkciju koja traži sa svim mogućim varijantama
        $user = $this->findUserByPhone($phone);
        
        if ($user) {
            // Phone exists, proceed to OTP
            return response()->json(['exists' => true]);
        } else {
            // Phone doesn't exist, show registration
            return response()->json(['exists' => false]);
        }
    }

    public function sendOtp(Request $request)
    {
        \Log::info('Send OTP request received', [
            'phone_number' => $request->phone_number,
            'ip' => $request->ip()
        ]);

        $request->validate([
            'phone_number' => 'required|string',
        ]);

        $phoneNumber = $request->phone_number;

        // Server-side rate limiting
        $phoneKey = 'otp_attempts_' . $phoneNumber;
        $ipKey = 'otp_ip_attempts_' . $request->ip();
        $recentKey = 'otp_recent_' . $phoneNumber;

        // Check recent OTP (30 seconds cooldown)
        if (Cache::has($recentKey)) {
            \Log::warning('OTP rate limit hit - recent OTP exists', ['phone' => $phoneNumber]);
            return response()->json([
                'success' => false,
                'message' => 'Molimo sačekajte 30 sekundi prije ponovnog slanja OTP-a.'
            ], 429);
        }

        // Check phone attempts (max 5 per hour)
        $phoneAttempts = Cache::get($phoneKey, 0);
        if ($phoneAttempts >= 20) {
            \Log::warning('OTP rate limit hit - phone attempts exceeded', ['phone' => $phoneNumber, 'attempts' => $phoneAttempts]);
            return response()->json([
                'success' => false,
                'message' => 'Previše pokušaja. Pokušajte ponovo za 1 sat.'
            ], 429);
        }

        // Check IP attempts (max 10 per hour)
        $ipAttempts = Cache::get($ipKey, 0);
        if ($ipAttempts >= 15) {
            \Log::warning('OTP rate limit hit - IP attempts exceeded', ['ip' => $request->ip(), 'attempts' => $ipAttempts]);
            return response()->json([
                'success' => false,
                'message' => 'Previše pokušaja s ove IP adrese. Pokušajte ponovo za 1 sat.'
            ], 429);
        }

        // Generate OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        \Log::info('OTP generated', ['phone' => $phoneNumber, 'otp' => $otp]);

        // Store OTP in cache (5 minutes)
        Cache::put('otp_' . $phoneNumber, $otp, 300);

        // Update rate limiting counters
        Cache::put($phoneKey, $phoneAttempts + 1, 3600);
        Cache::put($ipKey, $ipAttempts + 1, 3600);
        Cache::put($recentKey, true, 30);

        // Send SMS
        $message = "Vaš OTP kod je: {$otp}. Kod je važan 5 minuta.";
        $smsResult = $this->sendSms($phoneNumber, $message);

        \Log::info('SMS send result', [
            'phone' => $phoneNumber,
            'sms_result' => $smsResult
        ]);

        if ($smsResult['success']) {
            return response()->json([
                'success' => true,
                'message' => 'OTP kod je poslan na vaš broj telefona.'
            ]);
        } else {
            \Log::error('SMS send failed', [
                'phone' => $phoneNumber,
                'error' => $smsResult
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Greška pri slanju OTP-a. Pokušajte ponovo.'
            ], 500);
        }
    }

    public function verifyOtp(Request $request)
    {
        \Log::info('Verify OTP request received', [
            'phone_number' => $request->phone_number,
            'otp' => $request->otp
        ]);

        $request->validate([
            'phone_number' => 'required|string',
            'otp' => 'required|string|size:6',
        ]);

        $phone = $request->input('phone_number');
        $otp = $request->input('otp');
        
        // Check OTP from cache (use full phone number for cache)
        $cacheKey = 'otp_' . $phone;
        $storedOtp = Cache::get($cacheKey);
        
        \Log::info('OTP verification details', [
            'phone' => $phone,
            'cache_key' => $cacheKey,
            'stored_otp' => $storedOtp,
            'submitted_otp' => $otp,
            'otp_match' => $storedOtp === $otp,
            'cache_exists' => Cache::has($cacheKey)
        ]);
        
        if (!$storedOtp || $storedOtp !== $otp) {
            \Log::warning('OTP verification failed', [
                'phone' => $phone,
                'stored_otp' => $storedOtp,
                'submitted_otp' => $otp
            ]);
            return response()->json([
                'message' => 'Pogrešan OTP kod.',
                'success' => false
            ], 400);
        }
        
        // Remove OTP from cache after successful verification
        Cache::forget($cacheKey);
        
        \Log::info('OTP verification successful', ['phone' => $phone]);
        
        // Koristi helper funkciju koja traži sa svim mogućim varijantama
        $user = $this->findUserByPhone($phone);
        
        if ($user) {
            // Existing user - login
            $token = $user->createToken('api-token')->plainTextToken;
            
            \Log::info('🔑 Token created for existing user login', [
                'user_id' => $user->id,
                'username' => $user->username,
                'email' => $user->useremail,
                'phone' => $user->userphone,
                'token_preview' => substr($token, 0, 20) . '...',
                'token_length' => strlen($token),
                'current_push_token' => $user->push_token ? substr($user->push_token, 0, 30) . '...' : 'NULL'
            ]);
            
            \Log::info('📱 IMPORTANT: Waiting for push token update from mobile app...', [
                'user_id' => $user->id,
                'next_expected_call' => '/user/push-token',
                'note' => 'Mobile app should call /user/push-token within next 5-10 seconds'
            ]);
            
            return response()->json([
                'message' => 'Uspješna prijava!',
                'success' => true,
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->username ?? $user->name ?? 'Korisnik',
                    'email' => $user->useremail ?? $user->email ?? 'user@example.com',
                    'phone' => $user->userphone ?? '',
                    'barcodeId' => (string)$user->rfid, // Use RFID as barcode ID
                    'points' => (int)($user->points / 10), // Convert points to loyalty points
                    'is_app' => (int)($user->is_app ?? 0),
                    'dioptrija' => (int)($user->dioptrija ?? 0),
                    'diopterData' => [
                        'dsph' => $user->dsph ?? '',
                        'dcyl' => $user->dcyl ?? '',
                        'daxa' => $user->daxa ?? '',
                        'lsph' => $user->lsph ?? '',
                        'lcyl' => $user->lcyl ?? '',
                        'laxa' => $user->laxa ?? '',
                        'ldadd' => $user->ldadd ?? '',
                    ]
                ],
                'isNewUser' => false
            ]);
        } else {
            // New user - return success for registration completion
            return response()->json([
                'message' => 'OTP kod je ispravan.',
                'success' => true,
                'isNewUser' => true
            ]);
        }
    }

    public function checkEmailAvailability(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
        ]);

        $email = $request->input('email');
        $exists = User::where('useremail', $email)->exists();

        return response()->json([
            'available' => !$exists,
            'message' => $exists ? 'Email je već u upotrebi.' : 'Email je dostupan.'
        ]);
    }

    public function completeRegistration(Request $request)
    {
        \Log::info('🆕 Complete registration request started', [
            'timestamp' => now()->toDateTimeString(),
            'request_data' => $request->all()
        ]);
        
        try {
            $validator = Validator::make($request->all(), [
                'phone_number' => 'required|string',
                'name' => 'required|string|max:255',
                'surname' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users,useremail',
            ]);

            if ($validator->fails()) {
                \Log::warning('Complete registration validation failed', [
                    'errors' => $validator->errors()->toArray(),
                    'request_data' => $request->all()
                ]);
                
                // Specijalna poruka za već postojeći email
                $errors = $validator->errors();
                if ($errors->has('email') && str_contains($errors->first('email'), 'has already been taken')) {
                    return response()->json([
                        'message' => 'Email adresa je već registrovana. Molimo koristite drugi email.',
                        'success' => false,
                        'error_type' => 'duplicate_email',
                        'errors' => $errors
                    ], 422);
                }
                
                return response()->json([
                    'message' => 'Podaci nisu validni.',
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

        $phone = $request->input('phone_number');
        $name = $request->input('name');
        $surname = $request->input('surname');
        $email = $request->input('email');

        // Normalizuj telefonski broj prije spremanja u bazu
        $normalizedPhone = $this->normalizePhoneNumber($phone);

        // Create user with existing table structure
        $user = User::create([
            'username' => $name . ' ' . $surname,
            'useremail' => $email,
            'userphone' => $normalizedPhone,
            'dt' => now(),
            'rfid' => User::generateUniqueRfid(), // Unique RFID
            'points' => 0,
            'count' => 0,
            'dioptrija' => 0,
            'dsph' => '',
            'dcyl' => '',
            'daxa' => '',
            'lsph' => '',
            'lcyl' => '',
            'laxa' => '',
            'ldadd' => '',
            'bonus_status' => 0,
        ]);

        $token = $user->createToken('api-token')->plainTextToken;
        
        \Log::info('🔑 Token created for new user', [
            'user_id' => $user->id,
            'token_preview' => substr($token, 0, 20) . '...',
            'token_length' => strlen($token)
        ]);
        
        // Give bonus points to new user
        $bonusResult = $this->giveBonusToUser($user->id);
        
        // Refresh user data to get updated points
        $user->refresh();
        
        $response = [
            'message' => 'Registracija uspješna!',
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->username,
                'email' => $user->useremail,
                'phone' => $user->userphone,
                'barcodeId' => (string)$user->rfid, // Use RFID as barcode ID
                'points' => (int)($user->points / 10), // Show actual points
                'is_app' => (int)($user->is_app ?? 0),
                'dioptrija' => (int)($user->dioptrija ?? 0),
                'diopterData' => [
                    'dsph' => $user->dsph ?? '',
                    'dcyl' => $user->dcyl ?? '',
                    'daxa' => $user->daxa ?? '',
                    'lsph' => $user->lsph ?? '',
                    'lcyl' => $user->lcyl ?? '',
                    'laxa' => $user->laxa ?? '',
                    'ldadd' => $user->ldadd ?? '',
                ]
            ]
        ];
        
        // Add bonus notification if user received bonus
        if ($bonusResult && $bonusResult['success']) {
            $response['bonus'] = [
                'title' => $bonusResult['title'],
                'message' => $bonusResult['message'],
                'points' => $bonusResult['points']
            ];
        }
        
        \Log::info('✅ Complete registration successful', [
            'user_id' => $user->id,
            'email' => $user->useremail,
            'username' => $user->username,
            'phone' => $user->userphone,
            'token_issued' => substr($token, 0, 20) . '...'
        ]);
        
        \Log::info('📱 IMPORTANT: Waiting for push token update from mobile app...', [
            'user_id' => $user->id,
            'email' => $user->useremail,
            'next_expected_call' => '/user/push-token',
            'note' => 'Mobile app should call /user/push-token endpoint with Bearer token within next 5-10 seconds'
        ]);
        
        return response()->json($response);
        } catch (\Exception $e) {
            \Log::error('Complete registration error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            
            return response()->json([
                'message' => 'Došlo je do greške prilikom registracije.',
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function sendSms($phoneNumber, $message)
    {
        // Log the SMS attempt
        \Log::info('SMS sending attempt', [
            'phone_number' => $phoneNumber,
            'message' => $message,
            'sms_base_url' => env('SMS_BASE_URL'),
            'sms_authorization' => env('SMS_AUTHORIZATION') ? 'Set' : 'Not set'
        ]);

        // phoneNumber already comes with country code from frontend
        $curl = curl_init();

        $postData = [
            'messages' => [
                [
                    'sender' => 'OPTIKA.BA',
                    'destinations' => [
                        ['to' => $phoneNumber]
                    ],
                    'content' => [
                        'text' => $message
                    ]
                ]
            ]
        ];

        \Log::info('SMS request data', ['post_data' => $postData]);

        curl_setopt_array($curl, array(
            CURLOPT_URL => env('SMS_BASE_URL') . '/sms/3/messages',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($postData),
            CURLOPT_HTTPHEADER => array(
                'Authorization: ' . env('SMS_AUTHORIZATION'),
                'Content-Type: application/json',
                'Accept: application/json'
            ),
        ));

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        \Log::info('SMS response', [
            'http_code' => $httpCode,
            'response' => $response,
            'curl_error' => $error
        ]);

        if ($httpCode === 200) {
            return ['success' => true, 'response' => $response];
        } else {
            return ['success' => false, 'response' => $response, 'httpCode' => $httpCode, 'error' => $error];
        }
    }

    public function getUserProfile(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'message' => 'Korisnik nije pronađen.',
                'success' => false
            ], 404);
        }

        // Osvežavamo korisnika iz baze da dobijemo najnovije podatke
        $freshUser = $user->fresh();

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $freshUser->id,
                'name' => $freshUser->username ?? $freshUser->name ?? 'Korisnik',
                'email' => $freshUser->useremail ?? $freshUser->email ?? 'user@example.com',
                'phone' => $freshUser->userphone ?? '',
                'barcodeId' => (string)$freshUser->rfid, // Use RFID as barcode ID
                'points' => (int)($freshUser->points / 10), // Convert points to loyalty points
                'is_app' => (int)($freshUser->is_app ?? 0),
                'dioptrija' => (int)($freshUser->dioptrija ?? 0),
                'diopterData' => [
                    'dsph' => $freshUser->dsph ?? '',
                    'dcyl' => $freshUser->dcyl ?? '',
                    'daxa' => $freshUser->daxa ?? '',
                    'lsph' => $freshUser->lsph ?? '',
                    'lcyl' => $freshUser->lcyl ?? '',
                    'laxa' => $freshUser->laxa ?? '',
                    'ldadd' => $freshUser->ldadd ?? '',
                ]
            ]
        ]);
    }

    public function awardAppBonus(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Korisnik nije pronađen.'], 404);
        }

        if ((int)($user->is_app ?? 0) === 1) {
            return response()->json([
                'success' => true,
                'already_awarded' => true,
                'user' => [
                    'id' => $user->id,
                    'points' => (int)($user->points / 10),
                    'is_app' => 1,
                ]
            ]);
        }

        $result = $this->giveBonusToUser($user->id);
        $user->refresh();

        if ($result && $result['success']) {
            return response()->json([
                'success' => true,
                'bonus' => $result,
                'user' => [
                    'id' => $user->id,
                    'points' => (int)($user->points / 10),
                    'is_app' => (int)($user->is_app ?? 0),
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Bonus nije dodijeljen.'
        ], 400);
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'Korisnik nije pronađen.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,useremail,' . $user->id,
            'phone' => 'sometimes|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Greška u validaciji podataka.', 'errors' => $validator->errors()], 400);
        }

        try {
            $updateData = [];
            
            if ($request->has('name')) {
                $updateData['username'] = $request->name;
            }
            
            if ($request->has('email')) {
                $updateData['useremail'] = $request->email;
            }
            
            if ($request->has('phone')) {
                // Normalizuj telefonski broj prije spremanja
                $updateData['userphone'] = $this->normalizePhoneNumber($request->phone);
            }

            $user->update($updateData);

            // Osvežavamo korisnika da dobijemo najnovije podatke
            $freshUser = $user->fresh();

            return response()->json([
                'message' => 'Profil uspješno ažuriran.',
                'user' => [
                    'id' => $freshUser->id,
                    'name' => $freshUser->username,
                    'email' => $freshUser->useremail,
                    'phone' => $freshUser->userphone,
                    'barcodeId' => (string)$freshUser->rfid,
                    'points' => (int)($freshUser->points / 10), // Convert points to loyalty points
                    'dioptrija' => (int)($freshUser->dioptrija ?? 0),
                    'diopterData' => [
                        'dsph' => $freshUser->dsph ?? '',
                        'dcyl' => $freshUser->dcyl ?? '',
                        'daxa' => $freshUser->daxa ?? '',
                        'lsph' => $freshUser->lsph ?? '',
                        'lcyl' => $freshUser->lcyl ?? '',
                        'laxa' => $freshUser->laxa ?? '',
                        'ldadd' => $freshUser->ldadd ?? '',
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Profile update error: ' . $e->getMessage());
            return response()->json(['message' => 'Došlo je do greške prilikom ažuriranja profila.'], 500);
        }
    }

    public function updateDiopter(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'Korisnik nije pronađen.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'dsph' => 'required|string|max:10',
            'dcyl' => 'required|string|max:10',
            'daxa' => 'required|string|max:10',
            'lsph' => 'required|string|max:10',
            'lcyl' => 'required|string|max:10',
            'laxa' => 'required|string|max:10',
            'ldadd' => 'nullable|string|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Greška u validaciji podataka.',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $user->update([
                'dioptrija' => 1, // Mark that user has diopter data
                'dsph' => $request->dsph,
                'dcyl' => $request->dcyl,
                'daxa' => $request->daxa,
                'lsph' => $request->lsph,
                'lcyl' => $request->lcyl,
                'laxa' => $request->laxa,
                'ldadd' => $request->ldadd ?? '',
            ]);

            // Refresh user data
            $freshUser = $user->fresh();

            return response()->json([
                'success' => true,
                'message' => 'Dioptrija uspješno ažurirana.',
                'user' => [
                    'id' => $freshUser->id,
                    'name' => $freshUser->username,
                    'email' => $freshUser->useremail,
                    'phone' => $freshUser->userphone,
                    'barcodeId' => (string)$freshUser->rfid,
                    'points' => (int)($freshUser->points / 10),
                    'dioptrija' => (int)($freshUser->dioptrija ?? 0),
                    'diopterData' => [
                        'dsph' => $freshUser->dsph ?? '',
                        'dcyl' => $freshUser->dcyl ?? '',
                        'daxa' => $freshUser->daxa ?? '',
                        'lsph' => $freshUser->lsph ?? '',
                        'lcyl' => $freshUser->lcyl ?? '',
                        'laxa' => $freshUser->laxa ?? '',
                        'ldadd' => $freshUser->ldadd ?? '',
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Diopter update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Došlo je do greške prilikom ažuriranja dioptrije.'
            ], 500);
        }
    }

    public function updateNotifications(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'Korisnik nije pronađen.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'pushNotifications' => 'sometimes|boolean',
            'emailNotifications' => 'sometimes|boolean',
            'smsNotifications' => 'sometimes|boolean',
            'appointmentReminders' => 'sometimes|boolean',
            'loyaltyUpdates' => 'sometimes|boolean',
            'promotionalOffers' => 'sometimes|boolean',
            'orderUpdates' => 'sometimes|boolean',
            'healthReminders' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Greška u validaciji podataka.', 'errors' => $validator->errors()], 400);
        }

        try {
            $notificationSettings = [
                'pushNotifications' => $request->input('pushNotifications', true),
                'emailNotifications' => $request->input('emailNotifications', true),
                'smsNotifications' => $request->input('smsNotifications', false),
                'appointmentReminders' => $request->input('appointmentReminders', true),
                'loyaltyUpdates' => $request->input('loyaltyUpdates', true),
                'promotionalOffers' => $request->input('promotionalOffers', false),
                'orderUpdates' => $request->input('orderUpdates', true),
                'healthReminders' => $request->input('healthReminders', true),
            ];

            // Save notification settings to database
            $user->update(['notification_settings' => $notificationSettings]);

            return response()->json([
                'message' => 'Postavke notifikacija uspješno ažurirane.',
                'notifications' => $notificationSettings
            ]);
        } catch (\Exception $e) {
            \Log::error('Notification settings update error: ' . $e->getMessage());
            return response()->json(['message' => 'Došlo je do greške prilikom ažuriranja postavki notifikacija.'], 500);
        }
    }

    public function getNotificationSettings(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'Korisnik nije pronađen.'], 404);
        }

        try {
            // Get notification settings from database or use defaults
            $notificationSettings = $user->notification_settings ?? [
                'pushNotifications' => true,
                'emailNotifications' => true,
                'smsNotifications' => false,
                'appointmentReminders' => true,
                'loyaltyUpdates' => true,
                'promotionalOffers' => false,
                'orderUpdates' => true,
                'healthReminders' => true,
            ];

            return response()->json([
                'success' => true,
                'notifications' => $notificationSettings
            ]);
        } catch (\Exception $e) {
            \Log::error('Get notification settings error: ' . $e->getMessage());
            return response()->json(['message' => 'Došlo je do greške prilikom dohvatanja postavki notifikacija.'], 500);
        }
    }

    // Funkcija za dohvatanje notifikacija korisnika
    public function getNotifications(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'Korisnik nije pronađen.'], 404);
        }

        try {
            // Dohvati notifikacije iz baze podataka
            $notifications = $user->notifications()
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($notification) {
                    return [
                        'id' => $notification->id,
                        'type' => $notification->type,
                        'title' => $notification->title,
                        'message' => $notification->message,
                        'timestamp' => $notification->created_at->toISOString(),
                        'read' => $notification->read,
                        'icon' => $notification->icon,
                        'color' => $notification->color,
                        'has_post' => $notification->has_post ?? false,
                        'post_title' => $notification->post_title,
                        'post_description' => $notification->post_description,
                        'post_image' => $notification->post_image,
                    ];
                });

            $unreadCount = $notifications->where('read', false)->count();

            return response()->json([
                'success' => true,
                'notifications' => $notifications,
                'unread_count' => $unreadCount
            ]);
        } catch (\Exception $e) {
            \Log::error('Get notifications error: ' . $e->getMessage());
            return response()->json(['message' => 'Došlo je do greške prilikom dohvatanja notifikacija.'], 500);
        }
    }

    // Funkcija za označavanje notifikacije kao pročitane
    public function markNotificationAsRead(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'Korisnik nije pronađen.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'notification_id' => 'required|integer|exists:notifications,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'ID notifikacije je obavezan i mora biti valjan.'], 400);
        }

        try {
            $notification = $user->notifications()->findOrFail($request->notification_id);
            $notification->markAsRead();

            return response()->json([
                'success' => true,
                'message' => 'Notifikacija označena kao pročitana.'
            ]);
        } catch (\Exception $e) {
            \Log::error('Mark notification as read error: ' . $e->getMessage());
            return response()->json(['message' => 'Došlo je do greške prilikom označavanja notifikacije.'], 500);
        }
    }

    // Funkcija za označavanje svih notifikacija kao pročitane
    public function markAllNotificationsAsRead(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'Korisnik nije pronađen.'], 404);
        }

        try {
            $user->notifications()
                ->where('read', false)
                ->update([
                    'read' => true,
                    'read_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Sve notifikacije označene kao pročitane.'
            ]);
        } catch (\Exception $e) {
            \Log::error('Mark all notifications as read error: ' . $e->getMessage());
            return response()->json(['message' => 'Došlo je do greške prilikom označavanja notifikacija.'], 500);
        }
    }

    public function updatePushToken(Request $request)
    {
        \Log::info('🔔 UpdatePushToken request received', [
            'timestamp' => now()->toDateTimeString(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'has_auth' => Auth::check(),
            'user_id' => Auth::id(),
            'auth_header_present' => $request->hasHeader('Authorization'),
            'auth_header' => $request->hasHeader('Authorization') ? 'Bearer ***' : 'MISSING',
            'request_data' => $request->all(),
            'all_headers' => $request->headers->all()
        ]);
        
        $user = Auth::user();
        
        if (!$user) {
            \Log::warning('❌ UpdatePushToken: User not authenticated', [
                'auth_check' => Auth::check(),
                'auth_id' => Auth::id(),
                'request_ip' => $request->ip(),
                'has_bearer_token' => $request->bearerToken() !== null,
                'bearer_token_length' => $request->bearerToken() ? strlen($request->bearerToken()) : 0
            ]);
            return response()->json(['message' => 'Korisnik nije pronađen.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'push_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            \Log::warning('UpdatePushToken: Validation failed', [
                'errors' => $validator->errors()->toArray()
            ]);
            return response()->json([
                'message' => 'Push token je obavezan.',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            \Log::info('📝 Updating push token for user', [
                'user_id' => $user->id,
                'username' => $user->username,
                'user_email' => $user->useremail,
                'user_phone' => $user->userphone,
                'current_push_token' => $user->push_token ? substr($user->push_token, 0, 50) . '...' : 'NULL',
                'new_push_token' => substr($request->push_token, 0, 50) . '...',
                'new_push_token_length' => strlen($request->push_token),
                'is_expo_token' => str_starts_with($request->push_token, 'ExponentPushToken[')
            ]);
            
            $result = $user->update(['push_token' => $request->push_token]);
            
            // Refresh user to verify update
            $user->refresh();
            
            \Log::info('✅ Push token update result', [
                'user_id' => $user->id,
                'username' => $user->username,
                'update_result' => $result ? 'SUCCESS' : 'FAILED',
                'stored_push_token' => $user->push_token ? substr($user->push_token, 0, 50) . '...' : 'NULL',
                'stored_push_token_full_length' => $user->push_token ? strlen($user->push_token) : 0,
                'push_token_set' => !empty($user->push_token),
                'tokens_match' => $user->push_token === $request->push_token
            ]);

            return response()->json([
                'message' => 'Push token uspješno ažuriran.',
                'success' => true,
                'push_token_set' => !empty($user->push_token)
            ]);
        } catch (\Exception $e) {
            \Log::error('Push token update error', [
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Došlo je do greške prilikom ažuriranja push token-a.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Funkcija za slanje push notifikacija
    public function sendPushNotification(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'Korisnik nije pronađen.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'data' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Greška u validaciji podataka.', 'errors' => $validator->errors()], 400);
        }

        try {
            $pushToken = $user->push_token;
            
            if (!$pushToken) {
                return response()->json(['message' => 'Push token nije pronađen.'], 400);
            }

            // Slanje push notifikacije preko Expo
            $response = $this->sendExpoPushNotification(
                $pushToken,
                $request->title,
                $request->body,
                $request->data ?? []
            );

            return response()->json([
                'message' => 'Push notifikacija uspješno poslana.',
                'success' => true,
                'response' => $response
            ]);
        } catch (\Exception $e) {
            \Log::error('Push notification error: ' . $e->getMessage());
            return response()->json(['message' => 'Došlo je do greške prilikom slanja push notifikacije.'], 500);
        }
    }

    private function sendExpoPushNotification($pushToken, $title, $body, $data = [])
    {
        $message = [
            'to' => $pushToken,
            'sound' => 'default',
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://exp.host/--/api/v2/push/send',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($message),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Accept-encoding: gzip, deflate',
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            throw new \Exception('cURL Error: ' . $err);
        }

        return json_decode($response, true);
    }

    private function giveBonusToUser($userId)
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

            // Provjeri da li je korisnik već dobio app bonus
            if ($user->is_app == 1) {
                return false; // Već je dobio bonus
            }

            DB::beginTransaction();

            $user->points += $config->bonus_points;
            $user->is_app = 1;
            $user->save();

            // Kreiraj transakciju
            Transaction::create([
                'rfid' => $user->rfid,
                'user' => $user->username,
                'poslovnica' => 'Online',
                'points' => $config->bonus_points,
                'action' => 'dodato',
                'vrsta' => 'Bonus - Dobrodošlica'
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
            \Log::error('Bonus system error: ' . $e->getMessage());
            return false;
        }
    }
}
