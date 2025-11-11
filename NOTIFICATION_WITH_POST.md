# Notifikacije sa Postom - Uputstvo

Ovaj sistem omogućava slanje push notifikacija koje imaju dodatni sadržaj (post) koji korisnik može pogledati kada klikne na notifikaciju.

## Šta je novo?

- **Notifikacije sa postom**: Notifikacije sada mogu imati detaljni sadržaj sa naslovom, opisom i slikom
- **Klik na notifikaciju**: Kada korisnik klikne na notifikaciju sa postom, otvara se modal sa punim sadržajem
- **Vizuelni indikator**: Notifikacije sa postom imaju badge "Post" tako da korisnik zna da postoji dodatni sadržaj

## Backend - Migracija Baze Podataka

Prvo pokrenite migraciju da dodate nove kolone u `notifications` tabelu:

```bash
cd backend
php artisan migrate
```

Ova migracija dodaje sledeće kolone u `notifications` tabelu:
- `has_post` (boolean) - da li notifikacija ima post
- `post_title` (string) - naslov posta
- `post_description` (text) - opis posta
- `post_image` (string) - URL slike posta (opciono)

## Slanje Notifikacija sa Postom

Koristite novu skriptu `send_notification_with_post.php`:

```bash
cd backend
php send_notification_with_post.php
```

### Interaktivni Proces

Skripta će vas voditi kroz sledeće korake:

1. **Osnovni podaci notifikacije**:
   - Naslov notifikacije (prikazuje se u notifikaciji)
   - Poruka notifikacije (kratak opis)

2. **Da li želite dodati post?** (da/ne)
   - Ako odaberete "da", bićete upitani za:
     - Naslov posta (ili koristite naslov notifikacije)
     - Opis posta (može biti dug tekst)
     - URL slike (opciono)

3. **Tip notifikacije**:
   - general (opšte)
   - promo (promocija)
   - loyalty (loyalty program)
   - appointment (termin)
   - health (zdravlje)

4. **Primaoci**:
   - Svi korisnici
   - Samo jedan korisnik

5. **Potvrda i slanje**

### Primeri

#### Primer 1: Obična notifikacija (bez posta)

```
📝 Naslov notifikacije: Dobrodošli u Optiku!
💬 Poruka notifikacije: Hvala što ste se pridružili našem loyalty programu.
📄 Da li želite dodati post? ne
🏷️  Tip: general
```

#### Primer 2: Notifikacija sa postom

```
📝 Naslov notifikacije: Nova kolekcija naočara!
💬 Poruka notifikacije: Pogledajte našu najnoviju kolekciju.
📄 Da li želite dodati post? da
📋 Naslov posta: Nova kolekcija naočara - Proljeće 2025
📝 Opis posta: Otkrijte našu ekskluzivnu kolekciju proljetnih naočara. 
   Moderne dizajne, vrhunske brendove i posebne popuste za loyalty članove.
   Posjetite nas u bilo kojoj od naših poslovnica i isprobajte najnovije modele!
🖼️  URL slike: https://optika.ba/images/spring-collection-2025.jpg
🏷️  Tip: promo
```

#### Primer 3: Zdravstveni podsetnik sa postom

```
📝 Naslov notifikacije: Vreme je za kontrolu vida
💬 Poruka notifikacije: Prošlo je 6 meseci od vaše poslednje kontrole.
📄 Da li želite dodati post? da
📋 Naslov posta: Zašto je redovna kontrola vida važna?
📝 Opis posta: Redovna kontrola vida je ključna za očuvanje zdravlja očiju.
   Preporučujemo kontrolu svakih 6-12 meseci. Naši stručnjaci će proveriti
   vaš vid, dijagnostikovati eventualne probleme i preporučiti najbolje rešenje.
   
   Zakažite besplatan termin u bilo kojoj od naših poslovnica:
   - Stari Grad
   - Mercator
   - Grand
   - Importanne
   - Stup
🖼️  URL slike: https://optika.ba/images/eye-checkup.jpg
🏷️  Tip: health
```

## Frontend - Prikaz Notifikacija

### Automatsko Rukovanje

Frontend automatski prepoznaje notifikacije sa postom i:
1. Prikazuje badge "Post" na kartici notifikacije
2. Omogućava klik na notifikaciju
3. Otvara modal sa punim sadržajem posta

### NotificationPostModal

Nova komponenta koja prikazuje post sa:
- Slikom (ako postoji)
- Naslovom
- Detaljnim opisom
- Dugmetom za zatvaranje

## API Endpoint

Ako želite programski slati notifikacije, možete koristiti postojeći API:

```php
POST /api/user/send-notification
Authorization: Bearer {token}

{
  "title": "Naslov notifikacije",
  "body": "Poruka notifikacije",
  "data": {
    "type": "post",
    "has_post": true,
    "post_title": "Naslov posta",
    "post_description": "Opis posta",
    "post_image": "https://url-slike.jpg" // opciono
  }
}
```

## Struktura Baze Podataka

### Notifications Tabela

```sql
CREATE TABLE notifications (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT NOT NULL,
  type VARCHAR(255) NOT NULL,
  title VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  read BOOLEAN DEFAULT FALSE,
  icon VARCHAR(255) NULL,
  color VARCHAR(255) NULL,
  read_at TIMESTAMP NULL,
  has_post BOOLEAN DEFAULT FALSE,
  post_title VARCHAR(255) NULL,
  post_description TEXT NULL,
  post_image VARCHAR(255) NULL,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX (user_id, read),
  INDEX (user_id, created_at)
);
```

## Testiranje

1. Pokrenite skriptu za slanje notifikacije:
   ```bash
   php send_notification_with_post.php
   ```

2. Odaberite sebe kao primaoca (opcija 2)

3. Proverite da li je notifikacija stigla na telefon

4. Kliknite na notifikaciju da vidite post sadržaj

## Napomene

- **Slike**: URL slike mora biti javno dostupan. Možete koristiti slike sa vašeg sajta ili servisa kao što su Cloudinary, AWS S3, itd.
- **Opis posta**: Nema ograničenja na dužinu, možete pisati detaljan sadržaj
- **Tip notifikacije**: Određuje boju i ikonicu notifikacije u aplikaciji
- **Push token**: Korisnici moraju imati registrovan push token da bi primili notifikaciju

## Troubleshooting

### Notifikacija ne stiže?

- Proverite da li korisnik ima push token u bazi
- Proverite da li je aplikacija u foreground/background modu
- Proverite Expo push logs

### Post se ne otvara?

- Proverite da li notifikacija ima `has_post: true`
- Proverite da li postoje `post_title` i `post_description`
- Proverite konzolu za greške

### Slika se ne prikazuje?

- Proverite da li je URL slike validan i javno dostupan
- Proverite mrežnu konekciju
- Proverite da li slika ima pravilan format (jpg, png, webp)

## Primer Kompletnog Workflow-a

```bash
# 1. Migracija baze
cd backend
php artisan migrate

# 2. Pošalji test notifikaciju sa postom
php send_notification_with_post.php

# 3. Unesi podatke:
📝 Naslov: Specijalna ponuda!
💬 Poruka: Popust od 30% na sve naočare.
📄 Post: da
📋 Naslov posta: Specijalna ponuda - 30% popust
📝 Opis: Tokom cijelog mjeseca dobijate 30% popusta...
🖼️  Slika: https://optika.ba/images/promo.jpg
🏷️  Tip: promo
👥 Primaoci: Svi korisnici

# 4. Potvrdi slanje

# 5. Proveri na aplikaciji
```

## Zaključak

Sistem notifikacija sa postom omogućava bogatu komunikaciju sa korisnicima. Koristite ga za:
- Promocije i specijalnu ponudu
- Edukativni sadržaj
- Zdravstvene podsetnike
- Novosti i objave
- Bilo koji sadržaj koji zahteva detaljno objašnjenje

