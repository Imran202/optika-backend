# 📱 Testiranje Push Notifikacija

Ovaj folder sadrži alate za jednostavno testiranje push notifikacija sa tvog laptopa.

## 🚀 Brzo Testiranje

Najbrži način da pošalješ test notifikaciju:

```bash
cd backend
php send_test_notification.php
```

Ova skripta automatski:
- Pronalazi najnovijeg korisnika sa push tokenom (najverovatnije ti)
- Šalje test notifikaciju
- Snima notifikaciju u bazu podataka
- Prikazuje rezultat

## 🎯 Napredno Testiranje

Za više kontrole koristi interaktivnu skriptu:

```bash
cd backend
php quick_notification.php
```

Ova skripta omogućava:
- **Izbor korisnika** - Pošalji jednom korisniku ili svima
- **Tipovi notifikacija**:
  - 🎉 Specijalna ponuda / Popust (zelena ikona)
  - 📅 Podsetnik za termin (plava ikona)
  - 🎁 Bodovi nagrađeni (žuta ikona)
  - 🔔 Opšta notifikacija (crvena ikona)
  - ✏️ Custom poruka
- **Pregled pre slanja** - Potvrda pre nego što se pošalje

## 📊 Tipovi Notifikacija i Boje

Svaki tip notifikacije ima svoju boju u aplikaciji:

| Tip | Boja | Ikona | Primer |
|-----|------|-------|--------|
| `appointment` | 🔵 Plava | 📅 | Pregled očiju |
| `discount` | 🟢 Zelena | 🏷️ | 20% popust |
| `points` | 🟡 Žuta | 🎁 | Osvojili bodove |
| `general` | 🔴 Crvena | 🔔 | Nova kolekcija |

## 💡 Primeri Korišćenja

### Primer 1: Brzi Test
```bash
php send_test_notification.php
# ✅ Instant test notifikacija za prvog korisnika
```

### Primer 2: Popust za sve
```bash
php quick_notification.php
# Izaberi [0] za sve korisnike
# Izaberi [1] za popust
# Potvrdi sa 'da'
```

### Primer 3: Custom Poruka
```bash
php quick_notification.php
# Izaberi korisnika
# Izaberi [5] za custom
# Unesi naslov i poruku
```

## 🔧 Tehnički Detalji

### Kako Radi?

1. **Pronalaženje Korisnika**: Skripte automatski čitaju `users` tabelu iz baze
2. **Push Token**: Koristi se Expo push token koji se registruje kada se prijaviš u aplikaciji
3. **Expo API**: Šalje preko `https://exp.host/--/api/v2/push/send`
4. **Baza Podataka**: Snima notifikaciju u `notifications` tabelu

### Struktura Notifikacije

```php
[
    'to' => 'ExponentPushToken[...]',
    'title' => 'Naslov',
    'body' => 'Poruka',
    'sound' => 'default',
    'priority' => 'high',
    'channelId' => 'default',
    'badge' => 1,
    'data' => [
        'type' => 'appointment|discount|points|general',
        'timestamp' => 1234567890,
        'screen' => 'notifications'
    ]
]
```

## 🐛 Troubleshooting

### "Nema registrovanih push tokena"
**Rešenje**: Prijavi se u aplikaciju. Push token se automatski registruje pri prvoj prijavi.

### "Expo API greška"
**Rešenje**: 
- Proveri da li je aplikacija instalirana na telefonu
- Proveri da li su notifikacije omogućene u postavkama telefona
- Proveri da li je token validan (nije istekao)

### "HTTP greška"
**Rešenje**: Proveri internet konekciju i pokušaj ponovo.

## 📱 Testiranje Modernog UI-a

Nakon što pošalješ notifikaciju, otvori aplikaciju i klikni na:
- 🔔 Ikonu u header-u
- Pull-to-refresh za osvežavanje
- Klikni na notifikaciju da je označiš kao pročitanu
- Checkmark dugme u header-u da označiš sve kao pročitane

### Novi UI Features:
- ✨ Gradient pozadina
- 🎨 Boje prema tipu notifikacije
- 🔄 Animacije pri otvaranju
- 📥 Pull to refresh
- 💫 Modern glassmorphism dizajn
- 🎯 Visual feedback za akcije

## 🔐 Sigurnost

- Push tokeni su jedinstveni za svaki uređaj
- Notifikacije se šalju direktno na registrovane uređaje
- Baza podataka čuva istoriju notifikacija

## 📚 Dodatne Informacije

- Push notifikacije rade samo na **fizičkim uređajima** (ne rade u Expo Go simulatoru)
- Notifikacije se čuvaju u bazi i mogu se videti u aplikaciji
- Svaki korisnik može imati samo jedan aktivni push token (najnoviji uređaj)

---

💡 **Savet**: Koristi `send_test_notification.php` za brzo testiranje tokom razvoja, a `quick_notification.php` za slanje stvarnih notifikacija korisnicima.

