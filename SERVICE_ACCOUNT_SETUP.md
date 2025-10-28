# Service Account Setup za Google Sheets

## 1. Kreiraj Service Account

1. Idite na [Google Cloud Console](https://console.cloud.google.com/)
2. Kreirajte novi projekat ili izaberite postojeći
3. Idite na "APIs & Services" → "Library"
4. Pronađite i omogućite "Google Sheets API"
5. Idite na "APIs & Services" → "Credentials"
6. Kliknite "Create Credentials" → "Service Account"
7. Unesite ime za Service Account (npr. "optika-sheets")
8. Kliknite "Create and Continue"
9. U "Grant this service account access to project" izaberite "Editor"
10. Kliknite "Done"

## 2. Preuzmi JSON credentials

1. Kliknite na kreirani Service Account
2. Idite na "Keys" tab
3. Kliknite "Add Key" → "Create new key"
4. Izaberite "JSON"
5. Kliknite "Create"
6. JSON fajl će se automatski preuzeti

## 3. Postavi credentials u Laravel

1. Preimenujte preuzeti JSON fajl u `google-credentials.json`
2. Stavite ga u `storage/app/google-credentials.json`
3. Dodajte u `.env` fajl:
```env
GOOGLE_SHEETS_SPREADSHEET_ID=your_spreadsheet_id
GOOGLE_SHEETS_RANGE=Baza!A:AG
# Ne treba vam GOOGLE_API_KEY kada koristite Service Account
```

## 4. Podijeli Google Sheet sa Service Account

1. Idite na vaš Google Sheet
2. Kliknite "Share"
3. U email polje unesite Service Account email (npr. `optika-sheets@project-id.iam.gserviceaccount.com`)
4. Dajte "Editor" dozvole
5. Kliknite "Send"

## 5. Testiraj integraciju

Pokrenite test:
```bash
php test_glasses.php
```

## Troubleshooting

### "Permission denied" error
- Provjerite da li je Service Account email dodan kao Editor u Google Sheet
- Provjerite da li je JSON fajl na pravom mjestu (`storage/app/google-credentials.json`)

### "File not found" error
- Provjerite da li je `storage/app/google-credentials.json` fajl postoji
- Provjerite dozvole za fajl

### "Invalid credentials" error
- Provjerite da li je JSON fajl validan
- Provjerite da li je Service Account kreiran ispravno
