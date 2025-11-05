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
                $this->authConfigured = true;
            } catch (\Exception $e) {
                Log::error('ReviewHistoryController: Failed to load service account credentials: ' . $e->getMessage());
                $apiKey = env('GOOGLE_API_KEY');
                if ($apiKey) {
                    $client->setDeveloperKey($apiKey);
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
                return response()->json([
                    'success' => false,
                    'message' => 'Korisnik nema unesen broj telefona'
                ], 400);
            }

            $reviews = $this->fetchReviewsFromSheets($user);

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
                return [];
            }

            $headers = array_map('strtolower', $values[0]);
            $dataRows = array_slice($values, 1);

            $reviews = [];
            $totalRows = count($dataRows);
            $matchedRows = 0;
            
            foreach ($dataRows as $rowIndex => $row) {
                $reviewData = array_combine($headers, array_pad($row, count($headers), ''));
                
                if ($this->matchesUser($reviewData, $user)) {
                    $matchedRows++;
                    $formattedReview = $this->formatReviewData($reviewData);
                    $reviews[] = $formattedReview;
                }
            }
            
            // Log samo ako ima matched reviews ili ako treba debuggirati
            if ($matchedRows > 0) {
                Log::info('Review matching completed', [
                    'matched_rows' => $matchedRows,
                    'user_phone' => $user->phone
                ]);
            }

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
        
        // If either phone is empty, no match
        if (empty($sheetPhone) || empty($userPhone)) {
            return false;
        }
        
        // Check exact match first
        if ($sheetPhone === $userPhone) {
            return true;
        }
        
        // Special case: if user phone is 8 digits, try adding leading '0'
        if (strlen($userPhone) === 8) {
            $userPhoneWithZero = '0' . $userPhone;
            if ($sheetPhone === $userPhoneWithZero) {
                return true;
            }
        }
        
        // Special case: if user phone is 10 digits, try removing leading '0'
        if (strlen($userPhone) === 10 && substr($userPhone, 0, 1) === '0') {
            $userPhoneWithoutZero = substr($userPhone, 1);
            if ($sheetPhone === $userPhoneWithoutZero) {
                return true;
            }
        }
        
        // Special case: if sheet phone is 8 digits, try adding leading '0'
        if (strlen($sheetPhone) === 8) {
            $sheetPhoneWithZero = '0' . $sheetPhone;
            if ($userPhone === $sheetPhoneWithZero) {
                return true;
            }
        }
        
        // Special case: if sheet phone is 10 digits, try removing leading '0'
        if (strlen($sheetPhone) === 10 && substr($sheetPhone, 0, 1) === '0') {
            $sheetPhoneWithoutZero = substr($sheetPhone, 1);
            if ($userPhone === $sheetPhoneWithoutZero) {
                return true;
            }
        }

        return false;
    }

    private function cleanPhoneNumber($phone)
    {
        // If phone is empty or just dashes, return empty
        if (empty($phone) || $phone === '---' || $phone === 'N/A') {
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
            return '';
        }
        
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
