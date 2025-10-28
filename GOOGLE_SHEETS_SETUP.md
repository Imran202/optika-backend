# Google Sheets Integration Setup

Ovaj dokument objašnjava kako postaviti Google Sheets integraciju za naočale i historiju pregleda.

## 1. Install Google API Client

Run the following command to install the Google API client:

```bash
composer require google/apiclient
```

## 2. Environment Configuration

Add the following variables to your `.env` file:

```env
# Glasses Google Sheets Configuration
GOOGLE_SHEETS_SPREADSHEET_ID=your_glasses_spreadsheet_id_here
GOOGLE_SHEETS_RANGE=Baza!A:AG
GOOGLE_API_KEY=your_google_api_key_here

# Review History Google Sheets Configuration
GOOGLE_SHEETS_REVIEWS_SPREADSHEET_ID=your_reviews_spreadsheet_id_here
GOOGLE_SHEETS_REVIEWS_RANGE=Sheet1!A:K
```

## 3. Google Sheets Setup

### Option A: Using API Key (Public Sheets)

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing one
3. Enable Google Sheets API
4. Create API credentials (API Key)
5. Add the API key to your `.env` file
6. Make sure your Google Sheet is publicly accessible (anyone with link can view)

### Option B: Using Service Account (Recommended for Private Sheets)

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing one
3. Enable Google Sheets API
4. Create a Service Account
5. Download the JSON credentials file
6. Place the credentials file in `storage/app/google-credentials.json`
7. Share your Google Sheet with the service account email

## 4. Google Sheet Structure

### 4.1 Glasses Sheet (Baza)

Your glasses Google Sheet should have the following columns (A to AG):

**Note:** The range is `Baza!A:AG` (not Sheet1)

- **A: PROTOKOL** - Protocol/ID number
- **B: PREZIME** - Last name
- **C: IME** - First name
- **D: BR TEL** - Phone number
- **E: D SPH** - Right eye sphere
- **F: CYL** - Right eye cylinder
- **G: AXA** - Right eye axis
- **H: L PD** - Left pupillary distance
- **I: D PD** - Right pupillary distance
- **J: PD** - Total pupillary distance
- **K: ADD** - Addition (for progressive lenses)
- **L: L SPH** - Left eye sphere
- **M: CYL** - Left eye cylinder
- **N: AXA** - Left eye axis
- **O: PANT** - Pantoscopic tilt
- **P: VERTEX** - Vertex distance
- **Q: DATUM** - Purchase date (DD/MM/YYYY format)
- **R: DOKTOR** - Doctor name
- **S: RADNJA** - Store name
- **T: OKVIR OPIS** - Frame description/model

### 4.2 Review History Sheet

Your review history Google Sheet should have the following columns (A to K):

- **A: Datum protokola** - Protocol date
- **B: Vrijeme protokola** - Protocol time  
- **C: Poslovnica** - Branch/Office name
- **D: Ime** - Patient name
- **E: Email** - Patient email
- **F: Telefon** - Patient phone number
- **G: Datum pregleda** - Review date
- **H: Vrijeme pregleda** - Review time
- **I: Uposlenik** - Employee name
- **J: Stanje** - Review status
- **K: Mjesec** - Month (MM-YYYY format)

## 5. User Matching

### 5.1 Glasses Matching
The glasses system matches users by:
1. Phone number (cleaned of special characters)
2. Full name (first name + last name) as fallback

### 5.2 Review History Matching  
The review history system matches users by:
1. Phone number only (cleaned of special characters)
2. Handles both 8-digit and 10-digit phone numbers
3. No name matching fallback

Make sure the phone numbers in your Google Sheets match the user data in your app.

### 5.3 Phone Number Matching Details

Both systems handle phone number variations:

**8-digit numbers:**
- User: `61201891` → Sheet: `061201891` ✅ (adds leading '0')
- User: `061201891` → Sheet: `61201891` ✅ (removes leading '0')

**10-digit numbers:**
- User: `061201891` → Sheet: `61201891` ✅ (removes leading '0')
- User: `61201891` → Sheet: `061201891` ✅ (adds leading '0')

**Other variations:**
- Partial matches (one contains the other)
- Country code removal (+387, 387, 0)
- Special character cleaning

## 6. Testing

After setup, you can test the integration by:

### 6.1 Test Glasses Integration
1. Make sure a user is logged in
2. Call the API endpoint: `GET /api/user/glasses`
3. The response should contain the user's glasses data from the Google Sheet

### 6.2 Test Review History Integration
1. Make sure a user is logged in
2. Call the API endpoint: `GET /api/user/review-history`
3. The response should contain the user's review history from the Google Sheet

### 6.3 Test Scripts
You can also use the provided test scripts:
- `php test_glasses.php` - Tests glasses connection
- `php test_review_history.php` - Tests review history connection

## 7. API Endpoints

### 7.1 Glasses Endpoints
- `GET /api/user/glasses` - Get user's glasses data
- `GET /api/test-glasses-connection` - Test glasses Google Sheets connection

### 7.2 Review History Endpoints  
- `GET /api/user/review-history` - Get user's review history
- `GET /api/test-review-connection` - Test review history Google Sheets connection

## 8. Troubleshooting

### Common Issues:

1. **"Google Sheets Spreadsheet ID not configured"**
   - Make sure `GOOGLE_SHEETS_SPREADSHEET_ID` is set in your `.env` file
   - For review history, also check `GOOGLE_SHEETS_REVIEWS_SPREADSHEET_ID`

2. **"Access denied" or "Permission denied"**
   - Check if your Google Sheet is publicly accessible (for API key method)
   - Or make sure the service account has access to the sheet (for service account method)

3. **"No data found"**
   - Check if the user's phone number matches the data in the sheet
   - For glasses: also check if the user's name matches
   - Verify the sheet range is correct
   - Make sure the sheet has data in the specified range

4. **"Invalid credentials"**
   - Check if your service account JSON file is in the correct location
   - Verify the service account has the necessary permissions

## 9. Frontend Integration

### 9.1 Glasses Modal
- Automatically integrated into profile screen
- Shows basic info (name, type, warranty status) in header
- Expandable details with prescription, dates, doctor info
- Action buttons for details, service, contact

### 9.2 Review History Modal  
- Automatically integrated into profile screen
- Shows basic info (date, branch, status) in header
- Expandable details with protocol info, employee, contact details
- Action buttons for details, new appointment, contact

Both modals use the same dropdown interface pattern for consistency.
   - Verify your API key is correct
   - Or check if the service account credentials file is properly placed

### Debug Mode:

To see raw data from Google Sheets, check the `rawData` field in the response. This can help identify column mapping issues.

## 10. Testing

To test the integration:

### 10.1 Backend Testing
1. Run the test scripts:
   ```bash
   php test_glasses.php
   php test_review_history.php
   ```

2. Test API endpoints:
   ```bash
   curl -H "Authorization: Bearer YOUR_TOKEN" http://localhost:8000/api/test-glasses-connection
   curl -H "Authorization: Bearer YOUR_TOKEN" http://localhost:8000/api/test-review-connection
   ```

### 10.2 Frontend Testing
1. Open the profile screen
2. Tap "Historija pregleda" to test review history modal
3. Tap "Moje naočale" to test glasses modal
4. Check console logs for API calls and responses

### 10.3 Console Logs
Both modals include extensive logging:
- User information (name, phone, email)
- API call details (URL, token)
- API response (status, data structure)
- Data processing results
