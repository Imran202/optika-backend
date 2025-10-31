<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Google_Client;
use Google_Service_Sheets;

class ReviewHistoryController extends Controller
{
    private $sheets;
    private $spreadsheetId;
    private $range;
    private $authConfigured = false;

    public function __construct()
    {
        $this->spreadsheetId = env('GOOGLE_SHEETS_REVIEWS_SPREADSHEET_ID');
        $this->range = env('GOOGLE_SHEETS_REVIEWS_RANGE', 'Sheet1!A:K');
        
        $client = new Google_Client();
        
        // Check if we have service account credentials
        if (file_exists(storage_path('app/google-credentials.json'))) {
            try {
                $client->setAuthConfig(storage_path('app/google-credentials.json'));
                $client->setScopes([Google_Service_Sheets::SPREADSHEETS_READONLY]);
                Log::info('ReviewHistoryController: Using service account credentials');
                $this->authConfigured = true;
            } catch (\Exception $e) {
                Log::error('ReviewHistoryController: Failed to load service account credentials: ' . $e->getMessage());
                $apiKey = env('GOOGLE_API_KEY');
                if ($apiKey) {
                    $client->setDeveloperKey($apiKey);
                    Log::info('ReviewHistoryController: Falling back to API key');
                    $this->authConfigured = true;
                } else {
                    Log::warning('ReviewHistoryController: No Google authentication configured');
                }
            }
        } else {
            // Fallback to API key if no service account
            $apiKey = env('GOOGLE_API_KEY');
            if ($apiKey) {
                $client->setDeveloperKey($apiKey);
                Log::info('ReviewHistoryController: Using API key (service account not found)');
                $this->authConfigured = true;
            } else {
                Log::warning('ReviewHistoryController: No Google authentication configured - service account file not found and API key not set');
            }
        }
        
        $this->sheets = new Google_Service_Sheets($client);
    }

    public function getUserReviewHistory(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user->phone) {
                Log::info('Review History Request - User phone is null', [
                    'user_id' => $user->id,
                    'user_email' => $user->email
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Korisnik nema unesen broj telefona'
                ], 400);
            }

            Log::info('Review History Request', [
                'user_id' => $user->id,
                'user_phone' => $user->phone,
                'phone_length' => strlen($user->phone)
            ]);

            $reviews = $this->fetchReviewsFromSheets($user);
            
            Log::info('Review History Response', [
                'user_phone' => $user->phone,
                'reviews_found' => count($reviews)
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'reviews' => $reviews
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching review history', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id ?? 'unknown'
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Greška pri učitavanju historije pregleda'
            ], 500);
        }
    }

    private function fetchReviewsFromSheets($user)
    {
        try {
            Log::info('Starting to fetch reviews from sheets', [
                'spreadsheet_id' => $this->spreadsheetId,
                'range' => $this->range,
                'user_phone' => $user->phone
            ]);
            
            // Validate auth outcome from constructor
            if (!$this->authConfigured) {
                throw new \Exception('Google Sheets API authentication not configured.');
            }

            try {
                $response = $this->sheets->spreadsheets_values->get($this->spreadsheetId, $this->range);
            } catch (\Google_Service_Exception $e) {
                $msg = $e->getMessage();
                Log::error('ReviewHistoryController: Google Sheets API error', ['code' => $e->getCode(), 'message' => $msg]);
                if ($e->getCode() == 403 || strpos($msg, '403') !== false) {
                    throw new \Exception('Permission denied (403). Provjerite da je Google Sheet podijeljen sa service account email-om i da je Google Sheets API omogućen.');
                }
                throw new \Exception('Google Sheets API error: ' . $msg);
            }
            $values = $response->getValues();

            if (empty($values)) {
                Log::info('No data found in Google Sheets');
                return [];
            }

            $headers = array_map('strtolower', $values[0]);
            $dataRows = array_slice($values, 1);

            Log::info('Google Sheets data fetched', [
                'headers' => $headers,
                'rows_count' => count($dataRows),
                'first_row_sample' => array_slice($dataRows, 0, 1)
            ]);

            $reviews = [];
            $totalRows = count($dataRows);
            $matchedRows = 0;
            
            foreach ($dataRows as $rowIndex => $row) {
                $reviewData = array_combine($headers, array_pad($row, count($headers), ''));
                
                Log::info('Checking row ' . ($rowIndex + 1), [
                    'sheet_phone' => $reviewData['telefon'] ?? 'N/A',
                    'user_phone' => $user->phone,
                    'sheet_name' => $reviewData['ime'] ?? 'N/A'
                ]);
                
                if ($this->matchesUser($reviewData, $user)) {
                    $matchedRows++;
                    Log::info('✅ Row matched', [
                        'row' => $rowIndex + 1,
                        'sheet_phone' => $reviewData['telefon'] ?? 'N/A',
                        'user_phone' => $user->phone
                    ]);
                    $formattedReview = $this->formatReviewData($reviewData);
                    $reviews[] = $formattedReview;
                } else {
                    Log::info('❌ Row not matched', [
                        'row' => $rowIndex + 1,
                        'sheet_phone' => $reviewData['telefon'] ?? 'N/A',
                        'user_phone' => $user->phone
                    ]);
                }
            }
            
            Log::info('Review matching completed', [
                'total_rows' => $totalRows,
                'matched_rows' => $matchedRows,
                'user_phone' => $user->phone
            ]);

            return $reviews;

        } catch (\Exception $e) {
            Log::error('Error fetching from Google Sheets', [
                'error' => $e->getMessage(),
                'spreadsheet_id' => $this->spreadsheetId,
                'range' => $this->range
            ]);
            throw $e;
        }
    }

    private function matchesUser($reviewData, $user)
    {
        // Get original phone numbers
        $originalSheetPhone = $reviewData['telefon'] ?? '';
        $originalUserPhone = $user->phone;
        
        // Clean phone numbers (remove spaces, dashes, etc.)
        $sheetPhone = $this->cleanPhoneNumber($originalSheetPhone);
        $userPhone = $this->cleanPhoneNumber($originalUserPhone);
        
        Log::info('Phone matching attempt', [
            'original_sheet_phone' => $originalSheetPhone,
            'cleaned_sheet_phone' => $sheetPhone,
            'original_user_phone' => $originalUserPhone,
            'cleaned_user_phone' => $userPhone,
            'sheet_phone_length' => strlen($sheetPhone),
            'user_phone_length' => strlen($userPhone)
        ]);
        
        // If either phone is empty, no match
        if (empty($sheetPhone) || empty($userPhone)) {
            Log::info('❌ One or both phone numbers are empty');
            return false;
        }
        
        // Check exact match first
        if ($sheetPhone === $userPhone) {
            Log::info('✅ Exact phone match');
            return true;
        }
        
        // Special case: if user phone is 8 digits, try adding leading '0'
        if (strlen($userPhone) === 8) {
            $userPhoneWithZero = '0' . $userPhone;
            if ($sheetPhone === $userPhoneWithZero) {
                Log::info('✅ Phone match with added leading zero', [
                    'user_phone' => $userPhone,
                    'user_phone_with_zero' => $userPhoneWithZero,
                    'sheet_phone' => $sheetPhone
                ]);
                return true;
            }
        }
        
        // Special case: if user phone is 10 digits, try removing leading '0'
        if (strlen($userPhone) === 10 && substr($userPhone, 0, 1) === '0') {
            $userPhoneWithoutZero = substr($userPhone, 1);
            if ($sheetPhone === $userPhoneWithoutZero) {
                Log::info('✅ Phone match with removed leading zero', [
                    'user_phone' => $userPhone,
                    'user_phone_without_zero' => $userPhoneWithoutZero,
                    'sheet_phone' => $sheetPhone
                ]);
                return true;
            }
        }
        
        // Special case: if sheet phone is 8 digits, try adding leading '0'
        if (strlen($sheetPhone) === 8) {
            $sheetPhoneWithZero = '0' . $sheetPhone;
            if ($userPhone === $sheetPhoneWithZero) {
                Log::info('✅ Phone match with added leading zero to sheet', [
                    'user_phone' => $userPhone,
                    'sheet_phone' => $sheetPhone,
                    'sheet_phone_with_zero' => $sheetPhoneWithZero
                ]);
                return true;
            }
        }
        
        // Special case: if sheet phone is 10 digits, try removing leading '0'
        if (strlen($sheetPhone) === 10 && substr($sheetPhone, 0, 1) === '0') {
            $sheetPhoneWithoutZero = substr($sheetPhone, 1);
            if ($userPhone === $sheetPhoneWithoutZero) {
                Log::info('✅ Phone match with removed leading zero from sheet', [
                    'user_phone' => $userPhone,
                    'sheet_phone' => $sheetPhone,
                    'sheet_phone_without_zero' => $sheetPhoneWithoutZero
                ]);
                return true;
            }
        }

        Log::info('❌ No phone match found');
        return false;
    }

    private function cleanPhoneNumber($phone)
    {
        $originalPhone = $phone;
        
        // If phone is empty or just dashes, return empty
        if (empty($phone) || $phone === '---' || $phone === 'N/A') {
            Log::info('Phone number cleaning - empty/dash', [
                'original' => $originalPhone,
                'cleaned' => ''
            ]);
            return '';
        }
        
        // Remove all non-digit characters (spaces, dashes, etc.)
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Remove country code if present (387, 387, etc.)
        if (strlen($phone) > 9 && substr($phone, 0, 3) === '387') {
            $phone = substr($phone, 3);
        }
        
        // If phone is too short (less than 8 digits), it's probably invalid
        if (strlen($phone) < 8) {
            Log::info('Phone number cleaning - too short', [
                'original' => $originalPhone,
                'cleaned' => $phone,
                'length' => strlen($phone)
            ]);
            return '';
        }
        
        Log::info('Phone number cleaning', [
            'original' => $originalPhone,
            'cleaned' => $phone,
            'length' => strlen($phone)
        ]);
        
        return $phone;
    }

    private function formatReviewData($reviewData)
    {
        $protocolDate = $reviewData['datum protokola'] ?? '';
        $reviewDate = $reviewData['datum pregleda'] ?? '';
        
        return [
            'id' => uniqid('review_'),
            'protocolDate' => $this->formatDate($protocolDate),
            'protocolTime' => $reviewData['vrijeme protokola'] ?? '',
            'branch' => $reviewData['poslovnica'] ?? '',
            'name' => $reviewData['ime'] ?? '',
            'email' => $reviewData['email'] ?? '',
            'phone' => $reviewData['telefon'] ?? '',
            'reviewDate' => $this->formatDate($reviewDate),
            'reviewTime' => $reviewData['vrijeme pregleda'] ?? '',
            'employee' => $reviewData['uposlenik'] ?? '',
            'status' => $this->determineReviewStatus($reviewData['stanje'] ?? ''),
            'month' => $reviewData['mjesec'] ?? '',
            'isCompleted' => $this->isReviewCompleted($reviewData['stanje'] ?? ''),
            'isUpcoming' => $this->isReviewUpcoming($protocolDate, $reviewDate)
        ];
    }

    private function formatDate($date)
    {
        if (empty($date)) return '';
        
        // Handle different date formats
        if (strpos($date, '-') !== false) {
            // Already in YYYY-MM-DD format
            return $date;
        } elseif (strpos($date, '/') !== false) {
            // Convert from MM/DD/YYYY format
            $dateObj = \DateTime::createFromFormat('m/d/Y', $date);
            return $dateObj ? $dateObj->format('Y-m-d') : $date;
        }
        
        return $date;
    }

    private function determineReviewStatus($status)
    {
        $status = strtolower(trim($status));
        
        if (strpos($status, 'pregledan') !== false) {
            return 'completed';
        } elseif (strpos($status, 'zakazan') !== false || strpos($status, 'čekanje') !== false) {
            return 'scheduled';
        } elseif (strpos($status, 'otkazan') !== false) {
            return 'cancelled';
        } else {
            return 'unknown';
        }
    }

    private function isReviewCompleted($status)
    {
        $status = strtolower(trim($status));
        return strpos($status, 'pregledan') !== false;
    }

    private function isReviewUpcoming($protocolDate, $reviewDate)
    {
        if (empty($reviewDate)) return false;
        
        $reviewDateTime = \DateTime::createFromFormat('Y-m-d', $reviewDate);
        if (!$reviewDateTime) return false;
        
        $today = new \DateTime();
        return $reviewDateTime > $today;
    }

    public function testConnection()
    {
        try {
            $response = $this->sheets->spreadsheets_values->get($this->spreadsheetId, $this->range);
            $values = $response->getValues();
            
            return response()->json([
                'success' => true,
                'message' => 'Connection successful',
                'data' => [
                    'headers' => $values[0] ?? [],
                    'rows_count' => count($values) - 1,
                    'sample_data' => array_slice($values, 1, 3) ?? []
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
