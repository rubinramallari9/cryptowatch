<?php
/**
 * CryptoWatch — News Proxy
 * Burimi: CoinTelegraph RSS (falas, pa çelës API)
 * Cache: 15 minuta
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$CACHE_DIR = sys_get_temp_dir() . '/cryptowatch_cache';
if (!is_dir($CACHE_DIR)) mkdir($CACHE_DIR, 0755, true);

const TTL_NEWS = 900; // 15 minuta

$FEEDS = [
    ''           => 'https://cointelegraph.com/rss',
    'BTC'        => 'https://cointelegraph.com/rss/tag/bitcoin',
    'ETH'        => 'https://cointelegraph.com/rss/tag/ethereum',
    'SOL'        => 'https://cointelegraph.com/rss/tag/solana',
    'Regulation' => 'https://cointelegraph.com/rss/tag/regulation',
    'DeFi'       => 'https://cointelegraph.com/rss/tag/defi',
    'NFT'        => 'https://cointelegraph.com/rss/tag/nft',
];

$category = trim($_GET['category'] ?? '');
if (!array_key_exists($category, $FEEDS)) {
    http_response_code(400);
    echo json_encode(['error' => 'Kategori e pavlefshme.']);
    exit;
}

$feedUrl   = $FEEDS[$category];
$cacheKey  = md5($feedUrl);
$cacheFile = $CACHE_DIR . '/news_' . $cacheKey . '.json';

// Kthe nga cache
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < TTL_NEWS) {
    header('X-Cache: HIT');
    echo file_get_contents($cacheFile);
    exit;
}

// Fetch RSS
$ctx = stream_context_create([
    'http' => [
        'method'        => 'GET',
        'header'        => "User-Agent: Mozilla/5.0 CryptoWatch/1.0\r\n",
        'timeout'       => 10,
        'ignore_errors' => true,
    ],
    'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
]);

$raw = @file_get_contents($feedUrl, false, $ctx);

if (!$raw) {
    if (file_exists($cacheFile)) { header('X-Cache: STALE'); echo file_get_contents($cacheFile); exit; }
    http_response_code(502);
    echo json_encode(['error' => 'RSS feed nuk u mor dot.']);
    exit;
}

// Parse XML
libxml_use_internal_errors(true);
$xml = simplexml_load_string($raw, 'SimpleXMLElement', LIBXML_NOCDATA);

if (!$xml || !isset($xml->channel->item)) {
    if (file_exists($cacheFile)) { header('X-Cache: STALE'); echo file_get_contents($cacheFile); exit; }
    http_response_code(502);
    echo json_encode(['error' => 'RSS parse dështoi.']);
    exit;
}

$articles = [];
$count    = 0;

foreach ($xml->channel->item as $item) {
    if ($count >= 20) break;

    // Imazhi nga media:content ose enclosure
    $imageUrl = '';
    $mediaNs  = $item->children('http://search.yahoo.com/mrss/');
    if ($mediaNs && $mediaNs->content) {
        $attrs    = $mediaNs->content->attributes();
        $imageUrl = (string)($attrs['url'] ?? '');
    }
    if (!$imageUrl && $item->enclosure) {
        $attrs    = $item->enclosure->attributes();
        $imageUrl = (string)($attrs['url'] ?? '');
    }

    // URL e pastër
    $link = trim((string)$item->link);
    $link = strtok($link, '?') ?: $link; // hiq query string

    // Excerpt i pastër
    $desc    = strip_tags((string)$item->description);
    $excerpt = mb_substr(trim($desc), 0, 220);

    $articles[] = [
        'id'           => md5($link),
        'title'        => trim((string)$item->title),
        'url'          => $link,
        'imageurl'     => $imageUrl,
        'source'       => 'CoinTelegraph',
        'source_img'   => 'https://cointelegraph.com/favicon.ico',
        'published_on' => (int)strtotime((string)$item->pubDate),
        'body'         => $excerpt,
        'categories'   => trim((string)($item->category ?? '')),
    ];
    $count++;
}

$output = json_encode(['articles' => $articles]);
file_put_contents($cacheFile, $output);

header('X-Cache: MISS');
echo $output;
