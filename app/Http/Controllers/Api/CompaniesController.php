<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Google_Client;
use Google_Service_Sheets;
use Exception;
use Illuminate\Support\Facades\Log;

class CompaniesController extends Controller
{
    private $spreadsheetId;
    private $range;
    private $googleClient;

    public function __construct()
    {
        // Spreadsheet ID iz linka: https://docs.google.com/spreadsheets/d/1VWNFTZ1Mzzo9YW1-XvH5doC_N--Lc92ZInkBBHT8Pb0
        $this->spreadsheetId = '1VWNFTZ1Mzzo9YW1-XvH5doC_N--Lc92ZInkBBHT8Pb0';
        $this->range = 'Firme!B:C'; // Kolona B (IME FIRME ZA WEB) i C (Adresa)
        
        $this->googleClient = new Google_Client();
        $this->googleClient->setApplicationName('Optika Loyalty App');
        $this->googleClient->setScopes([Google_Service_Sheets::SPREADSHEETS_READONLY]);
        
        // Set credentials from service account key file
        $credentialsPath = storage_path('app/google-credentials.json');
        if (file_exists($credentialsPath)) {
            try {
                $this->googleClient->setAuthConfig($credentialsPath);
                Log::info('Using service account credentials from: ' . $credentialsPath);
            } catch (Exception $e) {
                Log::error('Failed to load service account credentials: ' . $e->getMessage());
                // Fallback to API key
                $apiKey = env('GOOGLE_API_KEY');
                if ($apiKey) {
                    $this->googleClient->setDeveloperKey($apiKey);
                    Log::info('Falling back to API key');
                } else {
                    Log::error('No authentication method available - neither service account nor API key found');
                }
            }
        } else {
            // Fallback to API key if service account is not available
            $apiKey = env('GOOGLE_API_KEY');
            if ($apiKey) {
                $this->googleClient->setDeveloperKey($apiKey);
                Log::info('Using API key (service account not found)');
            } else {
                Log::warning('No Google authentication configured - service account file not found and API key not set');
            }
        }
    }

    /**
     * Get all companies from Google Sheets
     */
    public function getCompanies(Request $request): JsonResponse
    {
        try {
            if (!$this->spreadsheetId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Google Sheets Spreadsheet ID not configured'
                ], 400);
            }

            Log::info('📊 Fetching companies from Google Sheets', [
                'spreadsheet_id' => $this->spreadsheetId,
                'range' => $this->range
            ]);

            $companies = $this->fetchCompaniesFromSheets();

            Log::info('✅ Successfully fetched companies', [
                'count' => count($companies)
            ]);

            return response()->json([
                'success' => true,
                'data' => $companies,
                'count' => count($companies)
            ]);

        } catch (Exception $e) {
            Log::error('❌ Error fetching companies from Google Sheets', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Check for specific error types and provide better error messages
            $errorMessage = $e->getMessage();
            $isAuthError = false;
            
            if (strpos($errorMessage, '403') !== false || 
                strpos($errorMessage, 'Permission denied') !== false ||
                strpos($errorMessage, 'unregistered callers') !== false) {
                $isAuthError = true;
                $errorMessage = 'Google Sheets authentication failed. Please check service account credentials or API key configuration.';
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch companies: ' . $errorMessage,
                'error_type' => $isAuthError ? 'authentication' : 'general'
            ], $isAuthError ? 401 : 500);
        }
    }

    /**
     * Fetch companies data from Google Sheets
     */
    private function fetchCompaniesFromSheets(): array
    {
        if (!$this->spreadsheetId) {
            throw new Exception('Google Sheets Spreadsheet ID not configured');
        }

        // Check if client is properly authenticated
        if (!$this->googleClient->getAccessToken() && !$this->googleClient->getDeveloperKey()) {
            throw new Exception('Google Sheets API authentication not configured. Please set up service account credentials or API key.');
        }

        $service = new Google_Service_Sheets($this->googleClient);
        
        try {
            $response = $service->spreadsheets_values->get($this->spreadsheetId, $this->range);
        } catch (\Google_Service_Exception $e) {
            $errorMessage = $e->getMessage();
            Log::error('Google Sheets API error', [
                'error' => $errorMessage,
                'code' => $e->getCode()
            ]);
            
            // Re-throw with more context
            if ($e->getCode() == 403 || strpos($errorMessage, '403') !== false) {
                throw new Exception('Permission denied (403). Please ensure: 1) Service account email is shared with the Google Sheet, 2) Google Sheets API is enabled, 3) Credentials are valid.');
            }
            
            throw new Exception('Google Sheets API error: ' . $errorMessage);
        }
        $values = $response->getValues();

        if (empty($values)) {
            return [];
        }

        $companies = [];
        
        // Skip header row (index 0) and start from row 1
        for ($i = 1; $i < count($values); $i++) {
            $row = $values[$i];
            
            // Ensure we have both columns (name and address)
            if (count($row) >= 2) {
                $name = trim($row[0] ?? '');
                $address = trim($row[1] ?? '');
                
                // Only add if name is not empty
                if (!empty($name)) {
                    // Try to extract location from address (usually the last part)
                    $location = $this->extractLocation($address);
                    
                    // Try to determine industry from existing mappings
                    $industry = $this->determineIndustry($name);
                    
                    $companies[] = [
                        'id' => $i,
                        'name' => $name,
                        'address' => $address,
                        'location' => $location,
                        'industry' => $industry,
                        'discount' => '10%' // Svi imaju 10% popusta
                    ];
                }
            }
        }

        Log::info('📋 Processed companies', [
            'total_rows' => count($values),
            'valid_companies' => count($companies)
        ]);

        return $companies;
    }

    /**
     * Extract location from address
     */
    private function extractLocation(string $address): string
    {
        if (empty($address)) {
            return 'Sarajevo';
        }

        // Common location patterns
        $locations = [
            'Sarajevo', 'Istočno Sarajevo', 'Ilidža', 'Ilijaš', 'Vogošća', 
            'Hrasnica', 'Breza', 'Kiseljak', 'Sokolje', 'Tarčin', 'Pale', 'Šip'
        ];

        foreach ($locations as $location) {
            if (stripos($address, $location) !== false) {
                return $location;
            }
        }

        // Default to Sarajevo if no location found
        return 'Sarajevo';
    }

    /**
     * Determine industry based on company name keywords
     */
    private function determineIndustry(string $name): string
    {
        $name_lower = mb_strtolower($name);

        // Državna služba
        if (preg_match('/(agencija|ministarstvo|općina|grad|parlament|sud|tužilaštvo|policij|granič|institucija|komisija|zavod|uprava)/u', $name_lower)) {
            return 'Državna služba';
        }

        // Obrazovanje
        if (preg_match('/(škola|gimnazija|fakultet|univerzitet|akademija|obrazovan)/u', $name_lower)) {
            return 'Obrazovanje';
        }

        // Zdravstvo
        if (preg_match('/(bolnic|zdravstv|medicin|psihijatrij|apoteke|dom zdravlja)/u', $name_lower)) {
            return 'Zdravstvo';
        }

        // IT
        if (preg_match('/(software|tech|digital|info|ping|atlantbh|softhouse|bloomteq|alen\.ba|vortt)/u', $name_lower)) {
            return 'IT';
        }

        // Transport
        if (preg_match('/(aerodrom|autoceste|centrotrans|prijevoz|želje|gas promet)/u', $name_lower)) {
            return 'Transport';
        }

        // Mediji
        if (preg_match('/(avaz|tv|medij|radio|oslobođenje|nova bh|universal media)/u', $name_lower)) {
            return 'Mediji';
        }

        // Osiguranje
        if (preg_match('/(osiguranje|osiguranj|grawe|uniqa|adriatic)/u', $name_lower)) {
            return 'Osiguranje';
        }

        // Bankarstvo
        if (preg_match('/(bank|privredna banka)/u', $name_lower)) {
            return 'Bankarstvo';
        }

        // Energetika
        if (preg_match('/(gas|energo|elektro|toplane)/u', $name_lower)) {
            return 'Energetika';
        }

        // Trgovina
        if (preg_match('/(trade|trgovin|market|konzum|globus|pelikan|epicentar|nelt)/u', $name_lower)) {
            return 'Trgovina';
        }

        // Kultura
        if (preg_match('/(pozorište|teatar|bibloteka|kultura)/u', $name_lower)) {
            return 'Kultura';
        }

        // Sport
        if (preg_match('/(sport|fk |rekreacij)/u', $name_lower)) {
            return 'Sport';
        }

        // Komunalne usluge
        if (preg_match('/(vodovod|kanalizacija|pokop|park d\.o\.o)/u', $name_lower)) {
            return 'Komunalne usluge';
        }

        // Proizvodnja
        if (preg_match('/(klas|hayat|proizvodn)/u', $name_lower)) {
            return 'Proizvodnja';
        }

        // Farmacija
        if (preg_match('/(bosnalijek|farmaceutski)/u', $name_lower)) {
            return 'Farmacija';
        }

        // Pošta
        if (preg_match('/(pošte|pošta)/u', $name_lower)) {
            return 'Pošta';
        }

        // Finansije
        if (preg_match('/(bamcard|finansij)/u', $name_lower)) {
            return 'Finansije';
        }

        // Konsalting
        if (preg_match('/(consulting|konsalting|gms)/u', $name_lower)) {
            return 'Konsalting';
        }

        // Marketing
        if (preg_match('/(mccann|marketing)/u', $name_lower)) {
            return 'Marketing';
        }

        // Telekomunikacije
        if (preg_match('/(telecom|telekom|telekomunikacij)/u', $name_lower)) {
            return 'Telekomunikacije';
        }

        // Logistika
        if (preg_match('/(logistic|logistik)/u', $name_lower)) {
            return 'Logistika';
        }

        // Sindikati
        if (preg_match('/(sindikat)/u', $name_lower)) {
            return 'Sindikati';
        }

        // Pravo
        if (preg_match('/(legalis|pravni)/u', $name_lower)) {
            return 'Pravo';
        }

        // Ugostiteljstvo
        if (preg_match('/(ugostiteljsk)/u', $name_lower)) {
            return 'Ugostiteljstvo';
        }

        // Inženjering
        if (preg_match('/(engineering|inženjer)/u', $name_lower)) {
            return 'Inženjering';
        }

        // Usluge (default for service companies)
        if (preg_match('/(servis|usluge)/u', $name_lower)) {
            return 'Usluge';
        }

        // Default
        return 'Ostalo';
    }
}

