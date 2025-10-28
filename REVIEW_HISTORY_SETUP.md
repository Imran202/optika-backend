# Review History Google Sheets Setup

Ovaj dokument objašnjava kako postaviti Google Sheets integraciju za historiju pregleda.

## 1. Google Sheets Dokument

Kreirajte novi Google Sheets dokument sa sljedećim kolonama:

| Kolona | Header | Opis |
|--------|--------|------|
| A | `Datum protokola` | Datum kada je kreiran protokol |
| B | `Vrijeme protokola` | Vrijeme kada je kreiran protokol |
| C | `Poslovnica` | Naziv poslovnice (npr. Mercator) |
| D | `Ime` | Ime pacijenta |
| E | `Email` | Email adresa pacijenta |
| F | `Telefon` | Broj telefona pacijenta |
| G | `Datum pregleda` | Datum zakazanog pregleda |
| H | `Vrijeme pregleda` | Vrijeme zakazanog pregleda |
| I | `Uposlenik` | Ime uposlenika koji radi pregled |
| J | `Stanje` | Status pregleda (Pregledan, Zakazan, itd.) |
| K | `Mjesec` | Mjesec pregleda (format: MM-YYYY) |

## 2. Environment Variables

Dodajte sljedeće varijable u vaš `.env` fajl:

```env
# Review History Google Sheets
GOOGLE_SHEETS_REVIEWS_SPREADSHEET_ID=your_spreadsheet_id_here
GOOGLE_SHEETS_REVIEWS_RANGE=Sheet1!A:K
```

**Napomena:** `GOOGLE_SHEETS_REVIEWS_SPREADSHEET_ID` mora biti različit od `GOOGLE_SHEETS_SPREADSHEET_ID` koji se koristi za naočale.

## 3. Google Sheets ID

Da biste pronašli ID vašeg Google Sheets dokumenta:

1. Otvorite Google Sheets dokument
2. Pogledajte URL u browseru
3. ID je dio između `/d/` i `/edit`

Primjer:
```
https://docs.google.com/spreadsheets/d/1ABC123DEF456GHI789JKL/edit#gid=0
                                    ↑
                                Ovo je ID
```

## 4. Testiranje Konekcije

Pokrenite test script da provjerite konekciju:

```bash
php test_review_history.php
```

## 5. API Endpoints

Nakon setup-a, dostupni su sljedeći endpointi:

- `GET /api/user/review-history` - Dohvata historiju pregleda za trenutnog korisnika
- `GET /api/test-review-connection` - Testira konekciju sa Google Sheets

## 6. User Matching Logic

Sistem pronalazi preglede koristeći **samo broj telefona**:

1. **Phone Matching**:
   - Tačan match broja telefona
   - Partial match (jedan broj sadrži drugi)
   - Automatsko dodavanje '0' na početak 8-cifrenih brojeva
   - Automatsko uklanjanje '0' sa početka 10-cifrenih brojeva

**Napomena:** Za razliku od naočala, historija pregleda koristi samo broj telefona za match-ovanje, bez fallback-a na ime.

## 7. Troubleshooting

### Greška: "GOOGLE_SHEETS_REVIEWS_SPREADSHEET_ID not set"
- Provjerite da li je varijabla dodana u `.env` fajl
- Provjerite da li je `.env` fajl u root direktoriju backend-a

### Greška: "Service Account credentials not found"
- Provjerite da li je `google-credentials.json` u `storage/app/` direktoriju
- Provjerite da li je Service Account dodan kao editor na Google Sheets

### Greška: "Permission denied"
- Provjerite da li je Service Account dodan kao editor
- Provjerite da li je Google Sheets dokument javno dostupan (ako koristite API Key)

### Nema podataka
- Provjerite da li je `GOOGLE_SHEETS_REVIEWS_RANGE` tačan
- Provjerite da li su podaci u Google Sheets-u
- Provjerite da li se korisnik poklapa sa podacima u sheet-u

## 8. Primjer Podataka

Vaš Google Sheets treba izgledati ovako:

| Datum protokola | Vrijeme protokola | Poslovnica | Ime | Email | Telefon | Datum pregleda | Vrijeme pregleda | Uposlenik | Stanje | Mjesec |
|------------------|-------------------|------------|-----|-------|---------|-----------------|------------------|-----------|---------|---------|
| 2024-07-19 | 18:13 | Mercator | SAMRA | opsa.mercator@gmail.com | 33653300 | 2024-07-19 | 18:15 | Opt. Alma Čehić | Pregledan | 07-2024 |

## 9. Frontend Integration

Modal za historiju pregleda je automatski integrisan u profile screen i prikazuje:

- Osnovne informacije (datum, poslovnica, status)
- Detaljne informacije kada se otvori dropdown
- Akcije (Detalji, Novi pregled, Kontakt)
- Loading, error i empty states
