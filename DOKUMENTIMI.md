# Dokumentimi Teknik — CryptoWatch

**Shkolla për Teknologji Informacioni dhe Komunikimi "Hermann Gmeiner"**  
**Lënda:** Zhvillim Website & Cloud Computing  
**Klasa:** 11-A | **Viti:** 2026  
**Kryetar:** Rubin Ramallari | **Anëtar:** Rexhino Durro  
**Mësuese:** Realda Ristani

---

## 1. Hyrje

CryptoWatch është një aplikacion web i plotë për monitorimin e kriptomonedhave në kohë reale. Ky dokument shpjegon në detaje çdo skedar, çdo funksion dhe çdo vendim teknik të marrë gjatë zhvillimit.

---

## 2. Struktura e Projektit

```
cryptowatch/
│
├── index.php                  ← Faqja kryesore (home)
├── logout.php                 ← Dalja nga sesioni
├── config.php                 ← Çelësi API i Groq
│
├── pages/
│   ├── login.php              ← Hyrja e përdoruesit
│   ├── register.php           ← Regjistrimi i përdoruesit
│   └── dashboard.php          ← Paneli personal
│
├── includes/
│   ├── auth.php               ← Bootstrap i sesionit PHP
│   ├── functions.php          ← Funksionet e databazës
│   └── chat_widget.php        ← HTML i chat-it AI (partial)
│
├── db/
│   ├── connection.php         ← Lidhja PDO me MySQL
│   └── schema.sql             ← Struktura e databazës
│
├── api/
│   ├── proxy.php              ← Proxy për CoinGecko API
│   ├── news.php               ← Proxy për RSS lajme
│   ├── chat.php               ← Backend AI chat (Groq)
│   └── api_request.js         ← Wrapper JS për CoinGecko
│
├── assets/
│   ├── css/
│   │   └── style.css          ← Stili i plotë (dark theme)
│   └── js/
│       ├── crypto.js          ← Logjika live e çmimeve
│       ├── news.js            ← Logjika e feed lajmeve
│       └── chat.js            ← Logjika e chat-it AI
│
└── vendor/                    ← Varësitë Composer (Groq SDK)
```

---

## 3. Databaza MySQL

### 3.1 Konfigurimi (`db/connection.php`)

```php
define('DB_HOST',    'localhost');
define('DB_NAME',    'cryptowatch');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');
```

Lidhja krijohet me **PDO (PHP Data Objects)** — një shtresë abstraksioni që mbron nga SQL Injection.

**Singleton pattern** — funksioni `getDB()` krijon vetëm një instancë të lidhjes për gjithë jetëgjatësinë e kërkesës:

```php
function getDB(): PDO {
    static $pdo = null;         // ruhet ndërmjet thirrjeve
    if ($pdo === null) {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
    return $pdo;
}
```

### 3.2 Skema (`db/schema.sql`)

```sql
CREATE TABLE users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100)        NOT NULL,
    email      VARCHAR(150) UNIQUE NOT NULL,
    password   VARCHAR(255)        NOT NULL,   -- bcrypt hash
    favorites  TEXT                DEFAULT NULL, -- JSON array
    created_at TIMESTAMP           DEFAULT CURRENT_TIMESTAMP
);
```

**Shpjegim kolonash:**

| Kolona | Tipi | Arsyeja |
|--------|------|---------|
| `id` | INT AUTO_INCREMENT | Identifikues unik i automatizuar |
| `name` | VARCHAR(100) | Emri i plotë i përdoruesit |
| `email` | VARCHAR(150) UNIQUE | Email unik — nuk lejohen duplikate |
| `password` | VARCHAR(255) | Hash bcrypt (60+ karaktere) |
| `favorites` | TEXT | JSON si `["bitcoin","ethereum"]` |
| `created_at` | TIMESTAMP | Plotësohet automatikisht nga MySQL |

---

## 4. Backend PHP

### 4.1 Autentikimi (`includes/functions.php`)

#### `registerUser(string $name, string $email, string $password): bool|string`

```php
function registerUser(string $name, string $email, string $password): bool|string {
    $db = getDB();
    // 1. Kontrollo nëse emaili ekziston
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) return 'Ky email është tashmë i regjistruar.';

    // 2. Enkipto fjalëkalimin me bcrypt
    $hash = password_hash($password, PASSWORD_BCRYPT);

    // 3. Ruaj në databazë
    $stmt = $db->prepare('INSERT INTO users (name, email, password) VALUES (?, ?, ?)');
    $stmt->execute([$name, $email, $hash]);
    return true;
}
```

**Pse bcrypt?** Algoritmi bcrypt është i ngadaltë me qëllim — e bën të pamundur sulmin "brute force". Çdo hash është i ndryshëm edhe për të njëjtin fjalëkalim (salt i rastësishëm i integruar).

#### `loginUser(string $email, string $password): bool|string`

```php
function loginUser(string $email, string $password): bool|string {
    $db = getDB();
    $stmt = $db->prepare('SELECT id, name, password FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // password_verify() krahason me hash-in e ruajtur
    if (!$user || !password_verify($password, $user['password'])) {
        return 'Email ose fjalëkalim i gabuar.';
    }

    // Rigjeneroj ID sesionit — mbron nga Session Fixation Attack
    session_regenerate_id(true);
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    return true;
}
```

#### `getFavorites(int $userId): array` dhe `saveFavorites(int $userId, array $favorites): void`

Monedhat e preferuara ruhen si **JSON** në kolonën `favorites`:

```php
// Lexo: "["bitcoin","ethereum"]" → ['bitcoin', 'ethereum']
function getFavorites(int $userId): array {
    $stmt = $db->prepare('SELECT favorites FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return json_decode($row['favorites'], true) ?? [];
}

// Shkruaj: ['bitcoin', 'ethereum'] → "["bitcoin","ethereum"]"
function saveFavorites(int $userId, array $favorites): void {
    $stmt = $db->prepare('UPDATE users SET favorites = ? WHERE id = ?');
    $stmt->execute([json_encode(array_values($favorites)), $userId]);
}
```

#### `requireLogin(): void`

```php
function requireLogin(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: /pages/login.php');
        exit;  // KRITIKE: exit pas header() ndalon ekzekutimin
    }
}
```

Kjo funksion vendoset në krye të çdo faqeje të mbrojtur si `dashboard.php`.

### 4.2 Faqet

#### `pages/register.php`
- Validon: fushat bosh, format email, fjalëkalim minimum 6 karaktere, konfirmim përputhje
- Kthen mesazhe gabimi specifike pa zbuluar informacion të ndjeshëm
- Pas suksesit shfaq link drejtpërdrejt tek login

#### `pages/login.php`
- Pas hyrjes ridrejton automatikisht me `header('Location: /pages/dashboard.php')`
- Nëse është tashmë i loguar ridrejton menjëherë (nuk rindërtohet faqja)

#### `pages/dashboard.php`
- Thirrja e parë: `requireLogin()` — bllokon nëse nuk ka sesion
- Menaxhon POST për shto/largo preferuara dhe ridrejton (PRG pattern — mbron nga riparaqitja e formës)

#### `logout.php`
```php
$_SESSION = [];      // fshin të gjitha të dhënat e sesionit
session_destroy();   // fshin cookie-n nga serveri
header('Location: /pages/login.php');
exit;
```

---

## 5. API Proxy (`api/proxy.php`)

### Pse nevojitet proxy?

Pa proxy, browseri do të bënte kërkesa direkte tek CoinGecko:

```
Browser → CoinGecko API  ✗ (CORS error + Rate limit individual)
```

Me proxy:
```
Browser → proxy.php (server) → CoinGecko API  ✓
```

**Dy probleme të zgjidhura:**
1. **CORS** — CoinGecko bllokon kërkesat nga `localhost`. Serveri nuk ka CORS.
2. **Rate Limiting (429)** — API falas lejon ~30 kërkesa/minutë. Proxy i bashkon të gjithë vizitorët.

### Sistemi i Cache

```php
const TTL_MARKETS = 60;   // çmimet: 1 minutë
const TTL_DETAIL  = 120;  // detaje monedhash: 2 minuta
const TTL_CHART   = 600;  // grafiqet: 10 minuta
```

**Fluksi i cache:**
```
Kërkesë → skedar cache ekziston? → [JO] → thirr CoinGecko → ruaj → kthe
                                 → [PO] → TTL i kaluar? → [JO] → kthe cache (HIT)
                                                         → [PO] → thirr CoinGecko
                                                                 → nëse 429 → kthe stale (deri 1 orë)
```

---

## 6. News Feed (`api/news.php`)

### Burimi i të dhënave

Lajmet vijnë nga **CoinTelegraph RSS** — komplet falas, pa çelës API:

```
https://cointelegraph.com/rss                    → Të gjitha lajmet
https://cointelegraph.com/rss/tag/bitcoin        → Vetëm Bitcoin
https://cointelegraph.com/rss/tag/ethereum       → Vetëm Ethereum
https://cointelegraph.com/rss/tag/defi           → Vetëm DeFi
... etj.
```

### Parsing RSS me SimpleXML

```php
$xml = simplexml_load_string($raw, 'SimpleXMLElement', LIBXML_NOCDATA);

foreach ($xml->channel->item as $item) {
    // Imazhi gjendet brenda namespace-it media:
    $mediaNs  = $item->children('http://search.yahoo.com/mrss/');
    $imageUrl = (string)($mediaNs->content->attributes()['url'] ?? '');

    // Pastro URL nga parametrat UTM
    $link = strtok((string)$item->link, '?');

    // Pastro excerpt nga tagjet HTML
    $excerpt = mb_substr(strip_tags((string)$item->description), 0, 220);
}
```

**Cache: 15 minuta** — lajmet nuk ndryshojnë sekondë pas sekonde.

---

## 7. AI Chat (`api/chat.php`)

### Arkitektura

```
Browser (chat.js) → POST /api/chat.php → Groq API → SSE stream → Browser
```

### Server-Sent Events (SSE)

SSE lejon serverin të "shtyjë" të dhëna tek browseri pa polling:

```php
header('Content-Type: text/event-stream');  // protocol SSE
header('Cache-Control: no-cache');

// Çdo fragment teksti dërgohet menjëherë:
echo "event: delta\n";
echo "data: {\"text\": \"Mir\"}\n\n";
flush();  // kritike — dërgon menjëherë pa buffering
```

### Groq API

Groq ofron modele LLM falas me shpejtësi shumë të lartë:

```php
$payload = json_encode([
    'model'       => 'llama-3.3-70b-versatile',  // falas
    'messages'    => $history,                    // historiku i bisedës
    'max_tokens'  => 1024,
    'stream'      => true,                        // SSE streaming
]);

$stream = fopen('https://api.groq.com/openai/v1/chat/completions', 'r', false, $ctx);

while (!feof($stream)) {
    $chunk = fread($stream, 4096);
    // Parse çdo rresht "data: {...}"
    // Nxirr delta->content
    // Dërgo si SSE tek browseri
}
```

### System Prompt

AI është konfiguruar si **specialist i tregut të kriptomonedhave**:

- I përgjigjet shqip nëse pyetja është shqip
- Shpjegon koncepte (DeFi, halving, staking, etj.)
- **Nuk jep këshilla financiare direkte** ("bli/shit")
- Thekson volatilitetin e tregut

### Historiku i Bisedës

```php
// Max 20 turns të fundit dërgohen tek API (kontekst i plotë)
$history = array_slice($body['messages'], -20);
```

---

## 8. Frontend JavaScript

### 8.1 `crypto.js` — Çmimet Live

#### Funksioni `apiUrl()`

```javascript
function apiUrl(endpoint, params = {}) {
    const qs = Object.entries(params)
        .map(([k, v]) => `${k}=${encodeURIComponent(v)}`).join('&');
    return '/api/proxy.php?endpoint=' + encodeURIComponent(endpoint)
           + (qs ? '&' + qs : '');
}
```

Çdo kërkesë kalon nëpër proxy PHP — kurrë direkte tek CoinGecko.

#### Loop i Refresh-it

```javascript
async function refresh() {
    const coins = await fetchMarkets();
    if (isDashboard) {
        renderDashboardTable(coins);
        renderFavoritesGrid(coins);
    } else {
        renderMarketTable(coins);
        renderTicker(coins);
    }
}

// Boot
await refresh();
setInterval(refresh, 60_000);  // çdo 60 sekonda
```

#### Modal i Detajeve

Kur klikoni emrin e monedhës:
1. Shfaqet modal me të dhënat e `allCoins` (cache në memorie — zero API call shtesë)
2. Bëhet vetëm **1 API call** për grafikun e çmimit
3. Grafiku ripiktohet kur ndërrohet periudha (1D / 7D / 30D / 1V)

```javascript
async function openModal(coinId) {
    const coin = allCoins.find(c => c.id === coinId); // nga cache
    renderModalDetail(coin);                           // menjëherë
    await loadModalChart(coinId, 1);                  // vetëm grafiku
}
```

### 8.2 `news.js` — Feed Lajmeve

```javascript
async function fetchNews(category = '') {
    const url = '/api/news.php' + (category ? '?category=' + category : '');
    const res  = await fetch(url);
    return (await res.json()).articles ?? [];
}
```

**Skeleton Loader** — ndërkohë që ngarkohen lajmet, shfaqen kartela "fantasmë" me animacion shimmer:

```javascript
function showSkeleton() {
    grid.innerHTML = Array(6).fill(`
        <article class="news-card news-skeleton">
            <div class="skeleton-box"></div>
            <div class="skeleton-line w100"></div>
            ...
        </article>`).join('');
}
```

### 8.3 `chat.js` — AI Chat Widget

**Streaming i fragmenteve:**

```javascript
const reader = resp.body.getReader();
const decoder = new TextDecoder();

while (true) {
    const { done, value } = await reader.read();
    if (done) break;

    // Parse SSE: "event: delta\ndata: {"text":"Mir"}\n\n"
    const lines = (buffer + decoder.decode(value)).split('\n');
    for (const line of lines) {
        if (line.startsWith('data: ')) {
            const { text } = JSON.parse(line.slice(6));
            assistantText += text;
            bubbleEl.innerHTML = mdToHtml(assistantText); // update live
        }
    }
}
```

**Markdown bazë i integruar:**

```javascript
function mdToHtml(text) {
    return text
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.+?)\*/g,     '<em>$1</em>')
        .replace(/`(.+?)`/g,       '<code>$1</code>')
        .replace(/\n/g,            '<br>');
}
```

---

## 9. CSS (`assets/css/style.css`)

### Design System

```css
:root {
    --bg:       #0f172a;  /* sfondi kryesor */
    --surface:  #1e293b;  /* kartelat, modalin */
    --surface2: #263346;  /* hover, header */
    --border:   #334155;  /* kufijtë */
    --text:     #e2e8f0;  /* teksti kryesor */
    --muted:    #94a3b8;  /* teksti dytësor */
    --accent:   #f7931a;  /* ngjyra Bitcoin/orange */
    --green:    #22c55e;  /* ndryshim pozitiv */
    --red:      #ef4444;  /* ndryshim negativ */
}
```

### Responsivitet

```css
/* Tablet */
@media (max-width: 768px) {
    .news-grid { grid-template-columns: 1fr; }
    .news-card-featured { grid-column: span 1; }
}

/* Mobile */
@media (max-width: 480px) {
    .chat-panel { width: calc(100vw - 2rem); }
    .modal { max-height: 95vh; }
}
```

### Animacionet

```css
/* Modal hyrje */
@keyframes modalIn {
    from { opacity: 0; transform: translateY(24px) scale(0.97); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
}

/* Shimmer loader */
@keyframes shimmer {
    from { background-position: 200% 0; }
    to   { background-position: -200% 0; }
}

/* Ticker lajmesh */
@keyframes scrollTicker {
    from { transform: translateX(0); }
    to   { transform: translateX(-50%); }
}
```

---

## 10. Siguria

| Kërcënimi | Teknika e Mbrojtjes | Implementimi |
|-----------|--------------------|--------------| 
| **SQL Injection** | PDO Prepared Statements | `$stmt = $db->prepare('SELECT ... WHERE email = ?')` |
| **XSS** | htmlspecialchars() | `<?= htmlspecialchars($var) ?>` |
| **Password Exposure** | bcrypt hashing | `password_hash($pass, PASSWORD_BCRYPT)` |
| **Session Fixation** | Rigjenerim ID | `session_regenerate_id(true)` |
| **CORS Abuse** | Server-side proxy | Browseri nuk komunikon me API të jashtme |
| **Akses i paautorizuar** | Session guard | `requireLogin()` në çdo faqe të mbrojtur |
| **Rate Limit Bypass** | Cache server-side | proxy.php bllokon kërkesat e tepërta |

---

## 11. Stack Teknologjik

| Teknologjia | Versioni | Roli |
|------------|---------|------|
| PHP | 8.5 | Backend, autentikimi, proxy, chat |
| MySQL | 8.x | Databaza e përdoruesve |
| PDO | built-in PHP | Lidhja e sigurt me DB |
| HTML5 | — | Struktura e faqeve |
| CSS3 | — | Dizajni, animacionet, responsiviteti |
| JavaScript ES2022 | — | Logjika live, fetch, SSE, modal |
| Chart.js | 4.4.0 | Grafiqet e çmimeve |
| CoinGecko API | v3 | Çmimet live (falas) |
| CoinTelegraph RSS | — | Lajmet (falas, pa çelës) |
| Groq API | v1 | AI chat (falas, Llama 3.3 70B) |
| Composer | 2.x | Menaxhues varësish PHP |

---

## 12. Si ta Nisësh Projektin

### Kërkesat

- PHP 8.0+
- MySQL 8.0+
- Composer
- Lidhje interneti (për API-të)

### Hapat

```bash
# 1. Instalo varësitë
composer install

# 2. Krijo databazën
mysql -u root < db/schema.sql

# 3. Konfiguro API key (Groq — falas nga console.groq.com)
# Hap config.php dhe vendos çelësin

# 4. Nis serverin
php -S localhost:8080

# 5. Hap browserin
open http://localhost:8080
```

---

## 13. Fluksi i Plotë i Aplikacionit

```
Vizitor hap index.php
    │
    ├─→ PHP ekzekuton includes/auth.php (session_start)
    │
    ├─→ Browser ngarkon assets/js/crypto.js
    │       │
    │       └─→ fetchMarkets() → GET /api/proxy.php?endpoint=coins/markets
    │                               │
    │                               └─→ Cache HIT? → kthe JSON
    │                               └─→ Cache MISS → thirr CoinGecko → cache → kthe
    │
    ├─→ Browser ngarkon assets/js/news.js
    │       │
    │       └─→ fetchNews() → GET /api/news.php
    │                           │
    │                           └─→ Cache HIT? → kthe JSON
    │                           └─→ Cache MISS → thirr CoinTelegraph RSS → parse → cache → kthe
    │
    └─→ Klikim monedhë → openModal(coinId)
            │
            ├─→ renderModalDetail(allCoins[coinId])  ← 0 API calls
            └─→ loadModalChart(coinId, 1) → GET /api/proxy.php?endpoint=coins/.../market_chart

Vizitor klikon 🤖 (chat)
    │
    └─→ Shkruan mesazh → POST /api/chat.php
            │
            └─→ Groq API (Llama 3.3 70B) → SSE stream → browser shfaq live
```

---

*Dokumentimi i fundit: Maj 2026*  
*CryptoWatch v1.0 — Shkolla "Hermann Gmeiner" — Klasa 11-A*
