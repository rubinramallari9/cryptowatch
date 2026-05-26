<?php
/**
 * CryptoWatch — API Proxy
 * Çdo kërkesë për CoinGecko kalon nëpër këtë skedar PHP.
 * Kjo zgjidh CORS dhe zvogëlon rate-limiting me cache lokal.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// --- Cache TTL sipas llojit të kërkesës ---
const TTL_MARKETS = 60;   // sekonda
const TTL_DETAIL  = 120;  // 2 minuta
const TTL_CHART   = 600;  // 10 minuta

$COINGECKO_BASE = 'https://api.coingecko.com/api/v3';
$CACHE_DIR      = sys_get_temp_dir() . '/cryptowatch_cache';

if (!is_dir($CACHE_DIR)) {
    mkdir($CACHE_DIR, 0755, true);
}

// --- Lexo dhe valido parametrin e endpoint-it ---
$endpoint = trim($_GET['endpoint'] ?? '');
if (!$endpoint) {
    http_response_code(400);
    echo json_encode(['error' => 'endpoint mungon']);
    exit;
}

// Lejo vetëm endpoint-e të njohur (siguri)
$allowed = [
    'coins/markets',
    'coins/{id}',
    'coins/{id}/market_chart',
];

// Ndërto URL-në e plotë
$params = $_GET;
unset($params['endpoint']);
$queryString = http_build_query($params);
$fullUrl = $COINGECKO_BASE . '/' . ltrim($endpoint, '/');
if ($queryString) $fullUrl .= '?' . $queryString;

// --- Cache key ---
$cacheKey  = md5($fullUrl);
$cacheFile = $CACHE_DIR . '/' . $cacheKey . '.json';

// Cakto TTL bazuar në llojin e endpoint-it
if (str_contains($endpoint, 'market_chart')) {
    $ttl = TTL_CHART;
} elseif ($endpoint === 'coins/markets') {
    $ttl = TTL_MARKETS;
} else {
    $ttl = TTL_DETAIL;
}

// --- Kthe cache nëse është i vlefshëm ---
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
    header('X-Cache: HIT');
    echo file_get_contents($cacheFile);
    exit;
}

// --- Bëj kërkesën tek CoinGecko ---
$ctx = stream_context_create([
    'http' => [
        'method'  => 'GET',
        'header'  => "User-Agent: CryptoWatch/1.0\r\n",
        'timeout' => 10,
        'ignore_errors' => true,
    ],
    'ssl' => [
        'verify_peer'      => true,
        'verify_peer_name' => true,
    ],
]);

$body = @file_get_contents($fullUrl, false, $ctx);

// Kontrollojmë status code nga headers
$statusLine = $http_response_header[0] ?? 'HTTP/1.1 500';
preg_match('/\d{3}/', $statusLine, $m);
$statusCode = (int)($m[0] ?? 500);

if ($statusCode === 429) {
    // Rate limited — serve stale cache (up to 1 hour) rather than failing
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 3600) {
        header('X-Cache: STALE-429');
        echo file_get_contents($cacheFile);
        exit;
    }
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit i tejkaluar. Provo pas pak sekondash.']);
    exit;
}

if ($statusCode !== 200 || $body === false) {
    http_response_code($statusCode ?: 502);
    echo json_encode(['error' => 'API request dështoi', 'status' => $statusCode]);
    exit;
}

// Ruaj në cache dhe kthe
file_put_contents($cacheFile, $body);
header('X-Cache: MISS');
echo $body;
