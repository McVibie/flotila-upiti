# Flotila Upiti - WordPress Plugin

Custom WordPress plugin za [flotila.hr](https://flotila.hr). Logira WPForms prijave, šalje HTML emailove, omogućuje chat s korisnicima iz WordPress admina i čita odgovore putem IMAP-a.

---

## Što radi

- **Logiranje upita** - svaka WPForms prijava se sprema kao custom post type
- **HTML emailovi** - automatski šalje potvrdu korisniku i notifikaciju adminu u branded HTML templateu
- **Chat u adminu** - pregled svih upita s chat sučeljem, mogućnost slanja odgovora direktno iz WordPressa
- **IMAP čitanje** - svakih 5 minuta povlači odgovore korisnika iz inboxa i dodaje ih u thread
- **Lifetime counter** - ukupan broj primljenih upita koji se ne briše ni kad se upiti obrišu
- **SEO fixes** - meta naslov, opis i OpenGraph tagovi za početnu stranicu putem Rank Math filtera
- **Mobile fixes** - CSS/JS fix za bijeli razmak između headera i hero sekcije na mobitelu
- **WhatsApp gumb** - floating WhatsApp gumb u footeru
- **Android scroll fix** - popravak za blokiran scroll na Androidu kad je modalni popup otvoren

---

## Instalacija

1. Kopiraj `upiti.php` u `/wp-content/plugins/flotila-upiti/`
2. Aktiviraj plugin u WordPress admin → Plugins
3. Konfiguriraj postavke u **Upiti → Postavke**

---

## Postavke

| Polje | Opis |
|---|---|
| Admin email | Prima notifikaciju za svaki novi upit |
| Ime pošiljatelja | Prikazuje se u emailovima (default: Flotila) |
| Email pošiljatelja | `From:` adresa (default: info@flotila.hr) |
| Logo URL | Logo koji se prikazuje u zaglavlju emailova |
| IMAP host/port/user/pass | Za čitanje odgovora iz inboxa |

---

## Email predlošci

Svaka WPForms forma može imati vlastiti predložak emaila. Predlošci podržavaju HTML i sljedeće placeholdere:

```
{ime}          - ime korisnika
{email}        - email korisnika
{podaci}       - sva polja forme
{datum}        - datum i vrijeme prijave
{naziv_forme}  - naziv WPForms forme
```

---

## IMAP - čitanje odgovora

Plugin traži emailove s `[REF:ID]` u naslovu (automatski dodan pri slanju) i vezuje ih za odgovarajući upit. Korisnici mogu odgovoriti direktno na potvrdu emaila.

---

## Zahtjevi

- WordPress 6.0+
- PHP 8.0+
- WPForms (bilo koja verzija)
- Rank Math SEO (opcionalno, za SEO filtere)
- PHP `imap` ekstenzija (opcionalno, za IMAP čitanje)
