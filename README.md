# ₿ CryptoWatch

Platforma e Monitorimit të Kriptomonedhave  
Shkolla "Hermann Gmeiner" — Klasa 11-A — 2026

## Si ta nisësh projektin

### Kërkesat
- XAMPP (Apache + MySQL + PHP 8.x)
- Browser modern

### Hapat

1. **Kopjo projektin** tek `htdocs` e XAMPP:
   ```
   C:\xampp\htdocs\cryptowatch\   (Windows)
   /Applications/XAMPP/htdocs/cryptowatch/   (Mac)
   ```

2. **Krijo databazën** — hap `http://localhost/phpmyadmin`, kliko **Import** dhe ngarko:
   ```
   db/schema.sql
   ```

3. **Konfiguro lidhjen** (nëse password-i i MySQL është i ndryshëm):  
   Hap `db/connection.php` dhe ndrysho `DB_PASS`.

4. **Hap shfletuesin**:
   ```
   http://localhost/cryptowatch/
   ```

## Struktura e folderëve

```
cryptowatch/
├── pages/
│   ├── login.php       ← Hyrja
│   ├── register.php    ← Regjistrimi
│   └── dashboard.php   ← Paneli personal
├── includes/
│   ├── auth.php        ← Session bootstrap
│   └── functions.php   ← Funksionet e DB
├── db/
│   ├── connection.php  ← Lidhja PDO
│   └── schema.sql      ← Struktura e DB
├── api/
│   └── api_request.js  ← Wrapper CoinGecko
├── assets/
│   ├── css/style.css   ← Stili (dark theme)
│   └── js/crypto.js    ← Logjika live + grafiku
├── index.php           ← Faqja kryesore
└── logout.php          ← Dalja nga sesioni
```

## Teknologjitë

| Teknologjia | Përdorimi |
|-------------|-----------|
| PHP 8       | Backend, autentikimi, session |
| MySQL + PDO | Databaza, prepared statements |
| HTML5 + CSS3 | Ndërfaqja, dark theme responsive |
| JavaScript (ES2022) | Fetch API, auto-refresh, grafiku |
| CoinGecko API | Çmimet live (falas, pa API key) |
| Chart.js | Grafiku 7-ditor |
| XAMPP | Server lokal |

## Siguria

- Fjalëkalimet enkriptohen me **bcrypt** (`password_hash`)
- Queries mbrohen nga SQL Injection me **PDO prepared statements**
- Të dhënat e outputit shfaqen me `htmlspecialchars()` (XSS protection)
- Session rigjenerojnë ID pas login-it (`session_regenerate_id`)

## Ekipi

- **Rubin Ramallari** — Backend Developer, Git, DB
- **Rexhino Durro** — Frontend Developer, API Integration
- **Mësuese**: Realda Ristani
