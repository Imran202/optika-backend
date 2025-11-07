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
    private $authConfigured = false;

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
                $this->authConfigured = true;
            } catch (Exception $e) {
                \Log::error('GlassesController: Failed to load service account credentials: ' . $e->getMessage());
                $apiKey = env('GOOGLE_API_KEY');
                if ($apiKey) {
                    $this->googleClient->setDeveloperKey($apiKey);
                    $this->authConfigured = true;
                } else {
                    \Log::warning('GlassesController: No Google authentication configured');
                }
            }
        } else {
            // Fallback to API key if service account is not available
            $apiKey = env('GOOGLE_API_KEY');
            if ($apiKey) {
                $this->googleClient->setDeveloperKey($apiKey);
                $this->authConfigured = true;
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

            // Check if user has phone number
            if (!$user->phone) {
                return response()->json([
                    'success' => false,
                    'message' => 'Korisnik nema broj telefona. Molimo vas da ažurirate svoj profil.'
                ], 400);
            }
            
            $glassesData = $this->fetchGlassesFromSheets($user);

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

        // Check auth configured (based on constructor outcome)
        if (!$this->authConfigured) {
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
                $glasses = $this->formatGlassesData($rowData, $i);
                if ($glasses) {
                    $userGlasses[] = $glasses;
                }
            }
        }

        return $userGlasses;
    }

    /**
     * Check if sheet row matches the current user by phone number
     */
    private function matchesUser($userPhone, $userName, $sheetPhone, $sheetFullName): bool
    {
        // Generiši sve moguće varijante za oba broja
        $userPhoneVariants = $this->getPhoneVariants($userPhone);
        $sheetPhoneVariants = $this->getPhoneVariants($sheetPhone);
        
        \Log::info('📞 Glasses matching attempt', [
            'original_user_phone' => $userPhone,
            'user_phone_variants' => $userPhoneVariants,
            'original_sheet_phone' => $sheetPhone,
            'sheet_phone_variants' => $sheetPhoneVariants
        ]);
        
        // Ako bilo koja varijanta user-a odgovara bilo kojoj varijanti sheet-a, onda se podudaraju
        if (!empty($userPhoneVariants) && !empty($sheetPhoneVariants)) {
            foreach ($userPhoneVariants as $userVariant) {
                foreach ($sheetPhoneVariants as $sheetVariant) {
                    if ($userVariant === $sheetVariant) {
                        \Log::info('✅ Glasses phone match found!', [
                            'original_user_phone' => $userPhone,
                            'original_sheet_phone' => $sheetPhone,
                            'matched_variant' => $userVariant
                        ]);
                        return true;
                    }
                }
            }
        }
        
        // Sekundarno podudaranje po imenu (ako telefonski brojevi ne odgovaraju)
        $userName = strtolower(trim($userName));
        $sheetFullName = strtolower(trim($sheetFullName));
        
        if ($userName && $sheetFullName && $userName === $sheetFullName) {
            \Log::info('✅ Glasses name match found!', [
                'user_name' => $userName,
                'sheet_name' => $sheetFullName
            ]);
            return true;
        }
        
        \Log::info('❌ No glasses match found', [
            'original_user_phone' => $userPhone,
            'original_sheet_phone' => $sheetPhone,
            'user_name' => $userName,
            'sheet_name' => $sheetFullName
        ]);
        
        return false;
    }

    /**
     * Clean phone number - ukloni sve specijalne znakove (razmake, crtice, kose crte, itd.)
     */
    private function cleanPhoneNumber($phone): string
    {
        if (empty($phone)) {
            return '';
        }
        
        // Ukloni sve osim cifara
        return preg_replace('/[^0-9]/', '', $phone);
    }
    
    /**
     * Normalizuje telefonski broj u standardni format
     * Podržava različite formate: 38762267066, +38762267066, 062267066, 62267066, "062 267 066", "062/267-066"
     * Vraća standardni format: 062267066 (sa vodećom nulom za BiH brojeve)
     */
    private function normalizePhoneNumber($phone)
    {
        // Prvo očisti broj od svih specijalnih znakova
        $phone = $this->cleanPhoneNumber($phone);
        
        if (empty($phone)) {
            return '';
        }
        
        // Ukloni +387 ili 387 prefix ako postoji
        if (strpos($phone, '387') === 0 && strlen($phone) > 3) {
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
     * Generiše sve moguće varijante telefonskog broja za podudaranje
     */
    private function getPhoneVariants($phone)
    {
        $normalized = $this->normalizePhoneNumber($phone);
        
        if (empty($normalized)) {
            return [];
        }
        
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
