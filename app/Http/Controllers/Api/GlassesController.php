<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Google_Client;
use Google_Service_Sheets;
use Exception;

class GlassesController extends Controller
{
    private $spreadsheetId;
    private $range;
    private $googleClient;

    public function __construct()
    {
        $this->spreadsheetId = env('GOOGLE_SHEETS_SPREADSHEET_ID');
        $this->range = env('GOOGLE_SHEETS_RANGE', 'Baza!A:AG'); // Baza sheet with A:AG columns
        
        $this->googleClient = new Google_Client();
        $this->googleClient->setApplicationName('Optika Loyalty App');
        $this->googleClient->setScopes([Google_Service_Sheets::SPREADSHEETS_READONLY]);
        
        // Set credentials from service account key file
        $credentialsPath = storage_path('app/google-credentials.json');
        if (file_exists($credentialsPath)) {
            try {
                $this->googleClient->setAuthConfig($credentialsPath);
                \Log::info('GlassesController: Using service account credentials');
            } catch (Exception $e) {
                \Log::error('GlassesController: Failed to load service account credentials: ' . $e->getMessage());
                $apiKey = env('GOOGLE_API_KEY');
                if ($apiKey) {
                    $this->googleClient->setDeveloperKey($apiKey);
                    \Log::info('GlassesController: Falling back to API key');
                } else {
                    \Log::warning('GlassesController: No Google authentication configured');
                }
            }
        } else {
            // Fallback to API key if service account is not available
            $apiKey = env('GOOGLE_API_KEY');
            if ($apiKey) {
                $this->googleClient->setDeveloperKey($apiKey);
                \Log::info('GlassesController: Using API key (service account not found)');
            } else {
                \Log::warning('GlassesController: No Google authentication configured - service account file not found and API key not set');
            }
        }
    }

    /**
     * Test endpoint to check Google Sheets connection and user matching
     */
    public function testConnection(Request $request): JsonResponse
    {
        try {
            if (!$this->spreadsheetId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Google Sheets Spreadsheet ID not configured'
                ], 400);
            }

            $service = new Google_Service_Sheets($this->googleClient);
            
            // Try to get just the first few rows to test connection
            $response = $service->spreadsheets_values->get($this->spreadsheetId, 'Sheet1!A1:T5');
            $values = $response->getValues();

            if (empty($values)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No data found in spreadsheet'
                ], 404);
            }

            // Test user matching with sample data
            $testUser = (object) [
                'phone' => '671147785',
                'name' => 'MUHAMED MULIĆ'
            ];
            
            $userGlasses = [];
            for ($i = 1; $i < min(10, count($values)); $i++) {
                $row = $values[$i];
                $rowData = array_combine($values[0], array_pad($row, count($values[0]), ''));
                
                $userPhone = $testUser->phone;
                $userName = $testUser->name;
                
                $sheetPhone = $rowData['BR TEL'] ?? '';
                $sheetFirstName = $rowData['IME'] ?? '';
                $sheetLastName = $rowData['PREZIME'] ?? '';
                $sheetFullName = trim($sheetFirstName . ' ' . $sheetLastName);
                
                if ($this->matchesUser($userPhone, $userName, $sheetPhone, $sheetFullName)) {
                    $glasses = $this->formatGlassesData($rowData, $i);
                    if ($glasses) {
                        $userGlasses[] = $glasses;
                    }
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Google Sheets connection successful',
                'data' => [
                    'totalRows' => count($values),
                    'headers' => $values[0] ?? [],
                    'sampleData' => array_slice($values, 1, 2), // First 2 data rows
                    'testUser' => [
                        'phone' => $testUser->phone,
                        'name' => $testUser->name
                    ],
                    'matchingGlasses' => $userGlasses
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error connecting to Google Sheets: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's glasses data from Google Sheets
     */
    public function getUserGlasses(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            \Log::info('🔍 User requesting glasses', [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_phone' => $user->phone,
                'user_phone_length' => $user->phone ? strlen($user->phone) : 0,
                'user_email' => $user->email,
                'user_object' => json_encode($user)
            ]);

            // Check if user has phone number
            if (!$user->phone) {
                \Log::warning('⚠️ User has no phone number', [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'user_email' => $user->email
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Korisnik nema broj telefona. Molimo vas da ažurirate svoj profil.'
                ], 400);
            }
            
            $glassesData = $this->fetchGlassesFromSheets($user);
            
            \Log::info('✅ Glasses data found', [
                'user_phone' => $user->phone,
                'glasses_count' => count($glassesData)
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'glasses' => $glassesData
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching glasses data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fetch glasses data from Google Sheets
     */
    private function fetchGlassesFromSheets($user): array
    {
        if (!$this->spreadsheetId) {
            throw new Exception('Google Sheets Spreadsheet ID not configured');
        }

        \Log::info('📊 Fetching data from Google Sheets', [
            'spreadsheet_id' => $this->spreadsheetId,
            'range' => $this->range,
            'user_phone' => $user->phone
        ]);

        // Check auth configured
        if (!$this->googleClient->getAccessToken() && !$this->googleClient->getDeveloperKey()) {
            throw new Exception('Google Sheets API authentication not configured.');
        }

        $service = new Google_Service_Sheets($this->googleClient);
        
        try {
            $response = $service->spreadsheets_values->get($this->spreadsheetId, $this->range);
        } catch (\Google_Service_Exception $e) {
            $msg = $e->getMessage();
            \Log::error('GlassesController: Google Sheets API error', ['code' => $e->getCode(), 'message' => $msg]);
            if ($e->getCode() == 403 || strpos($msg, '403') !== false) {
                throw new Exception('Permission denied (403). Provjerite da je Google Sheet podijeljen sa service account email-om i da je Google Sheets API omogućen.');
            }
            throw new Exception('Google Sheets API error: ' . $msg);
        }
        $values = $response->getValues();

        if (empty($values)) {
            return [];
        }

        // Get headers (first row)
        $headers = $values[0];
        
        // Find user's data by phone number or name
        $userGlasses = [];
        
        for ($i = 1; $i < count($values); $i++) {
            $row = $values[$i];
            
            // Map row data to headers
            $rowData = array_combine($headers, array_pad($row, count($headers), ''));
            
            // Check if this row belongs to the current user
            // We'll match by phone number or name
            $userPhone = $user->phone ?? '';
            $userName = $user->name ?? '';
            
            $sheetPhone = $rowData['BR TEL'] ?? '';
            $sheetFirstName = $rowData['IME'] ?? '';
            $sheetLastName = $rowData['PREZIME'] ?? '';
            $sheetFullName = trim($sheetFirstName . ' ' . $sheetLastName);
            
            // Match by phone number or name
            if ($this->matchesUser($userPhone, $userName, $sheetPhone, $sheetFullName)) {
                \Log::info('✅ Found matching user in sheet', [
                    'protocol' => $rowData['PROTOKOL'] ?? '',
                    'user_phone' => $userPhone,
                    'sheet_phone' => $sheetPhone,
                    'sheet_name' => $sheetFullName,
                    'frame' => $rowData['OKVIR OPIS'] ?? '',
                    'date' => $rowData['DATUM'] ?? ''
                ]);
                
                $glasses = $this->formatGlassesData($rowData, $i);
                if ($glasses) {
                    $userGlasses[] = $glasses;
                }
            }
        }
        
        \Log::info('📊 Search completed', [
            'user_phone' => $user->phone,
            'total_rows_searched' => count($values) - 1,
            'glasses_found' => count($userGlasses)
        ]);

        return $userGlasses;
    }

    /**
     * Check if sheet row matches the current user by phone number
     */
    private function matchesUser($userPhone, $userName, $sheetPhone, $sheetFullName): bool
    {
        // Clean phone numbers for comparison
        $userPhone = $this->cleanPhoneNumber($userPhone);
        $sheetPhone = $this->cleanPhoneNumber($sheetPhone);
        
        // Validate phone number lengths - must be at least 8 digits
        if (strlen($userPhone) < 8 || strlen($sheetPhone) < 8) {
            \Log::info('⚠️ Phone number too short for comparison', [
                'user_phone' => $userPhone,
                'user_phone_length' => strlen($userPhone),
                'sheet_phone' => $sheetPhone,
                'sheet_phone_length' => strlen($sheetPhone)
            ]);
            return false;
        }
        
        // Additional validation: phone numbers must be reasonable length (not too long either)
        if (strlen($userPhone) > 15 || strlen($sheetPhone) > 15) {
            \Log::info('⚠️ Phone number too long for comparison', [
                'user_phone' => $userPhone,
                'user_phone_length' => strlen($userPhone),
                'sheet_phone' => $sheetPhone,
                'sheet_phone_length' => strlen($sheetPhone)
            ]);
            return false;
        }
        
        // Specific validation for Bosnian phone numbers: must be 8-10 digits
        // 8 digits: 61123456 (without leading 0)
        // 9 digits: 061123456 (with leading 0)
        // 10 digits: 0611234567 (with leading 0 and area code)
        if ((strlen($userPhone) < 8 || strlen($userPhone) > 10) || 
            (strlen($sheetPhone) < 8 || strlen($sheetPhone) > 10)) {
            \Log::info('⚠️ Invalid Bosnian phone number length', [
                'user_phone' => $userPhone,
                'user_phone_length' => strlen($userPhone),
                'sheet_phone' => $sheetPhone,
                'sheet_phone_length' => strlen($sheetPhone),
                'note' => 'Bosnian numbers should be 8-10 digits'
            ]);
            return false;
        }
        
        // Clean names for comparison
        $userName = strtolower(trim($userName));
        $sheetFullName = strtolower(trim($sheetFullName));
        
        // Primary match: by phone number (exact match)
        if ($userPhone && $sheetPhone && $userPhone === $sheetPhone) {
            \Log::info('✅ Exact phone number match', [
                'user_phone' => $userPhone,
                'sheet_phone' => $sheetPhone
            ]);
            return true;
        }
        
        // Secondary match: by name (if phone number doesn't match)
        if ($userName && $sheetFullName && $userName === $sheetFullName) {
            return true;
        }
        
        // Handle different phone number formats (only for valid Bosnian numbers)
        if ($userPhone && $sheetPhone) {
            // Try adding leading zero to user phone if it's 8 digits
            if (strlen($userPhone) === 8) {
                $userPhoneWithZero = '0' . $userPhone;
                if ($userPhoneWithZero === $sheetPhone) {
                    \Log::info('✅ Phone match with added leading zero', [
                        'original' => $userPhone,
                        'with_zero' => $userPhoneWithZero,
                        'sheet_phone' => $sheetPhone
                    ]);
                    return true;
                }
            }
            
            // Try adding leading zero to sheet phone if it's 8 digits
            if (strlen($sheetPhone) === 8) {
                $sheetPhoneWithZero = '0' . $sheetPhone;
                if ($userPhone === $sheetPhoneWithZero) {
                    \Log::info('✅ Phone match with added leading zero to sheet', [
                        'user_phone' => $userPhone,
                        'sheet_original' => $sheetPhone,
                        'sheet_with_zero' => $sheetPhoneWithZero
                    ]);
                    return true;
                }
            }
            
            // Try removing leading zero from user phone if it's 9 digits
            if (strlen($userPhone) === 9 && substr($userPhone, 0, 1) === '0') {
                $userPhoneWithoutZero = substr($userPhone, 1);
                if ($userPhoneWithoutZero === $sheetPhone) {
                    \Log::info('✅ Phone match with removed leading zero', [
                        'original' => $userPhone,
                        'without_zero' => $userPhoneWithoutZero,
                        'sheet_phone' => $sheetPhone
                    ]);
                    return true;
                }
            }
            
            // Try removing leading zero from sheet phone if it's 9 digits
            if (strlen($sheetPhone) === 9 && substr($sheetPhone, 0, 1) === '0') {
                $sheetPhoneWithoutZero = substr($sheetPhone, 1);
                if ($userPhone === $sheetPhoneWithoutZero) {
                    \Log::info('✅ Phone match with removed leading zero from sheet', [
                        'user_phone' => $userPhone,
                        'sheet_original' => $sheetPhone,
                        'sheet_without_zero' => $sheetPhoneWithoutZero
                    ]);
                    return true;
                }
            }
            
            // Remove country code if present and compare (only for 10+ digit numbers)
            if (strlen($userPhone) >= 10 || strlen($sheetPhone) >= 10) {
                $userPhoneClean = preg_replace('/^(\+387|387|0)/', '', $userPhone);
                $sheetPhoneClean = preg_replace('/^(\+387|387|0)/', '', $sheetPhone);
                
                // Only exact match after removing country code
                if ($userPhoneClean === $sheetPhoneClean) {
                    \Log::info('✅ Phone match after removing country code', [
                        'user_phone_clean' => $userPhoneClean,
                        'sheet_phone_clean' => $sheetPhoneClean
                    ]);
                    return true;
                }
            }
            
            // Check if cleaned sheet phone contains user phone (for cases like "061/050-059" -> "061050059")
            if (strlen($sheetPhone) > strlen($userPhone)) {
                if (strpos($sheetPhone, $userPhone) !== false) {
                    \Log::info('✅ Phone match - user phone found within cleaned sheet phone', [
                        'user_phone' => $userPhone,
                        'sheet_phone' => $sheetPhone,
                        'match_type' => 'contains'
                    ]);
                    return true;
                }
            }
            
            // Check if user phone contains cleaned sheet phone
            if (strlen($userPhone) > strlen($sheetPhone)) {
                if (strpos($userPhone, $sheetPhone) !== false) {
                    \Log::info('✅ Phone match - cleaned sheet phone found within user phone', [
                        'user_phone' => $userPhone,
                        'sheet_phone' => $sheetPhone,
                        'match_type' => 'contained_by'
                    ]);
                    return true;
                }
            }
            
            // For complex formats like "061/050-059", clean all characters and check as one number
            $cleanedSheetPhone = preg_replace('/[^0-9]/', '', $sheetPhone);
            if (strlen($cleanedSheetPhone) >= 8 && strlen($cleanedSheetPhone) <= 15) {
                if ($this->comparePhoneNumbers($userPhone, $cleanedSheetPhone)) {
                    \Log::info('✅ Phone match - complex format cleaned and matched', [
                        'user_phone' => $userPhone,
                        'sheet_phone' => $sheetPhone,
                        'cleaned_sheet_phone' => $cleanedSheetPhone,
                        'match_type' => 'complex_cleaned'
                    ]);
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Clean phone number for comparison
     */
    private function cleanPhoneNumber($phone): string
    {
        // Remove all non-numeric characters including slashes, dashes, spaces
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        
        // Log the cleaning process for debugging
        if ($cleaned !== $phone) {
            \Log::info('🧹 Phone number cleaned', [
                'original' => $phone,
                'cleaned' => $cleaned
            ]);
        }
        
        return $cleaned;
    }



    /**
     * Compare two phone numbers with all possible format variations
     */
    private function comparePhoneNumbers($phone1, $phone2): bool
    {
        // Direct match
        if ($phone1 === $phone2) {
            return true;
        }
        
        // Try adding/removing leading zero
        if (strlen($phone1) === 8 && strlen($phone2) === 9 && substr($phone2, 0, 1) === '0') {
            if ('0' . $phone1 === $phone2) {
                return true;
            }
        }
        
        if (strlen($phone1) === 9 && strlen($phone2) === 8 && substr($phone1, 0, 1) === '0') {
            if (substr($phone1, 1) === $phone2) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Format raw sheet data into glasses object
     */
    private function formatGlassesData($rowData, $rowIndex): ?array
    {
        try {
            // Extract prescription data based on your sheet structure
            $rightSphere = $rowData['D SPH'] ?? '';
            $rightCylinder = $rowData['CYL'] ?? '';
            $rightAxis = $rowData['AXA'] ?? '';
            $leftSphere = $rowData['L SPH'] ?? '';
            $leftCylinder = $rowData['CYL'] ?? ''; // Same CYL column for both eyes
            $leftAxis = $rowData['AXA'] ?? ''; // Same AXA column for both eyes
            $addition = $rowData['ADD'] ?? '';
            $pd = $rowData['PD'] ?? '';
            
            // Get frame description and other details
            $frameDescription = $rowData['OKVIR OPIS'] ?? '';
            $lensDescription = $rowData['STAKLA OPIS'] ?? '';
            $purchaseDate = $rowData['DATUM'] ?? '';
            $doctor = $rowData['DOKTOR'] ?? '';
            $store = $rowData['RADNJA'] ?? '';
            
            // Build prescription string
            $prescription = $this->buildPrescriptionString(
                $rightSphere, $rightCylinder, $rightAxis,
                $leftSphere, $leftCylinder, $leftAxis,
                $addition, $pd
            );
            
            // Determine glasses type based on prescription
            $type = $this->determineGlassesType($rightSphere, $leftSphere, $addition);
            
            // Get frame brand from description
            $frameBrand = $this->extractFrameBrand($frameDescription);
            
            // Format purchase date
            $formattedDate = $this->formatDate($purchaseDate);
            
            // Calculate warranty end date (2 years from purchase)
            $warrantyEnd = $this->calculateWarrantyEnd($formattedDate);
            
            // Determine status
            $status = $this->determineStatus($warrantyEnd);
            
            // Calculate row number for Google Sheets link (rowIndex + 1 because we start from 0)
            $rowNumber = $rowIndex + 1;
            
            // Create warranty link with row number
            $warrantyLink = "https://docs.google.com/spreadsheets/d/1rROSvIe4cghj4NPXZMTarBEFh8omkzuBADDH7SZPVIA/edit?gid=306429836&range=A{$rowNumber}";
            
            return [
                'id' => $rowIndex,
                'name' => $frameDescription ?: 'Naočale',
                'type' => $type,
                'purchaseDate' => $formattedDate,
                'warrantyEnd' => $warrantyEnd,
                'frameBrand' => $frameBrand,
                'lensType' => $this->determineLensType($addition),
                'prescription' => $prescription,
                'status' => $status,
                'doctor' => $doctor,
                'store' => $store,
                'lensDescription' => $lensDescription,
                'rowNumber' => $rowNumber,
                'warrantyLink' => $warrantyLink,
                'rawData' => $rowData // Include raw data for debugging
            ];
            
        } catch (Exception $e) {
            \Log::error('Error formatting glasses data: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Build prescription string from individual values
     */
    private function buildPrescriptionString($rightSphere, $rightCylinder, $rightAxis, 
                                           $leftSphere, $leftCylinder, $leftAxis, 
                                           $addition, $pd): string
    {
        $parts = [];
        
        if ($rightSphere || $rightCylinder) {
            $right = "OD: " . ($rightSphere ?: '0.00');
            if ($rightCylinder) {
                $right .= ", " . $rightCylinder;
                if ($rightAxis) {
                    $right .= " x " . $rightAxis;
                }
            }
            $parts[] = $right;
        }
        
        if ($leftSphere || $leftCylinder) {
            $left = "OS: " . ($leftSphere ?: '0.00');
            if ($leftCylinder) {
                $left .= ", " . $leftCylinder;
                if ($leftAxis) {
                    $left .= " x " . $leftAxis;
                }
            }
            $parts[] = $left;
        }
        
        if ($addition) {
            $parts[] = "ADD: " . $addition;
        }
        
        if ($pd) {
            $parts[] = "PD: " . $pd;
        }
        
        return $parts ? implode(' | ', $parts) : 'Bez dioptrije';
    }

    /**
     * Determine glasses type based on prescription
     */
    private function determineGlassesType($rightSphere, $leftSphere, $addition): string
    {
        if ($addition && floatval($addition) > 0) {
            return 'Progresivne naočale';
        }
        
        if (($rightSphere && floatval($rightSphere) != 0) || 
            ($leftSphere && floatval($leftSphere) != 0)) {
            return 'Dioptrijske naočale';
        }
        
        return 'Suncane naočale';
    }

    /**
     * Determine lens type based on addition
     */
    private function determineLensType($addition): string
    {
        if ($addition && floatval($addition) > 0) {
            return 'Progresivne';
        }
        
        return 'Standardne';
    }

    /**
     * Extract frame brand from description
     */
    private function extractFrameBrand($description): string
    {
        if (!$description) {
            return 'Nepoznato';
        }
        
        // Common frame brands
        $brands = ['RAY-BAN', 'OAKLEY', 'TOM FORD', 'ZANZARA', 'RIO', 'LES HOMES'];
        
        foreach ($brands as $brand) {
            if (stripos($description, $brand) !== false) {
                return $brand;
            }
        }
        
        return 'Nepoznato';
    }

    /**
     * Format date from MM/DD/YYYY to DD.MM.YYYY
     */
    private function formatDate($date): string
    {
        if (!$date) {
            return date('d.m.Y');
        }
        
        // If date is already in DD.MM.YYYY format
        if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $date)) {
            return $date;
        }
        
        // If date is in MM/DD/YYYY format (your format)
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $date, $matches)) {
            $month = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $day = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            $year = $matches[3];
            return "$day.$month.$year";
        }
        
        // If date is in DD/MM/YYYY format
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $date)) {
            return str_replace('/', '.', $date);
        }
        
        return $date;
    }

    /**
     * Calculate warranty end date (2 years from purchase)
     */
    private function calculateWarrantyEnd($purchaseDate): string
    {
        if (!$purchaseDate) {
            return date('d.m.Y', strtotime('+2 years'));
        }
        
        $date = \DateTime::createFromFormat('d.m.Y', $purchaseDate);
        if (!$date) {
            return date('d.m.Y', strtotime('+2 years'));
        }
        
        $date->add(new \DateInterval('P2Y'));
        return $date->format('d.m.Y');
    }

    /**
     * Determine warranty status
     */
    private function determineStatus($warrantyEnd): string
    {
        if (!$warrantyEnd) {
            return 'active';
        }
        
        $warrantyDate = \DateTime::createFromFormat('d.m.Y', $warrantyEnd);
        if (!$warrantyDate) {
            return 'active';
        }
        
        $now = new \DateTime();
        
        if ($warrantyDate < $now) {
            return 'expired';
        }
        
        // If warranty expires within 30 days, show as warranty
        $thirtyDaysFromNow = (new \DateTime())->add(new \DateInterval('P30D'));
        if ($warrantyDate <= $thirtyDaysFromNow) {
            return 'warranty';
        }
        
        return 'active';
    }
}
