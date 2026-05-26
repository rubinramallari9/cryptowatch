<?php
/**
 * CryptoWatch — AI Chat Endpoint (Groq API, FALAS)
 * Streamon përgjigjet nëpër Server-Sent Events (SSE).
 * Dokumentacion: https://console.groq.com/docs/openai
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

function sse(string $event, mixed $data): void {
    echo "event: {$event}\ndata: " . json_encode($data) . "\n\n";
    flush();
}

function die_error(string $msg): never {
    sse('error', ['message' => $msg]);
    exit;
}

// Valido request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') die_error('Metoda e gabuar.');

$body = json_decode(file_get_contents('php://input'), true);
if (empty($body['messages']))                            die_error('Payload i pavlefshëm.');
if (!defined('GROQ_API_KEY') || GROQ_API_KEY === 'YOUR_GROQ_API_KEY_HERE') {
    die_error('API key mungon. Hap config.php dhe vendos GROQ_API_KEY.');
}

// Ndërto historikun (max 20 turns, max 3000 chars/turn)
$history = array_map(fn($m) => [
    'role'    => $m['role'] === 'assistant' ? 'assistant' : 'user',
    'content' => mb_substr(strip_tags($m['content']), 0, 3000),
], array_slice($body['messages'], -20));

// System prompt
$system = [
    'role'    => 'system',
    'content' => <<<PROMPT
Ti je CryptoAnalyst, asistent ekspert për hulumtim të tregut të kriptomonedhave.
Platforma: CryptoWatch — monitorim live me CoinGecko API.

Specializohesh në:
- Analizë teknike dhe fundamentale të kriptomonedhave (BTC, ETH, SOL, BNB, XRP etj.)
- Shpjegim trendesh tregu, kapitalizimi, volumi, likuiditeti
- Koncepte blockchain: DeFi, NFT, Layer 2, halving, staking, tokenomics
- Krahasim projektesh dhe ekosistemesh
- Vlerësim rreziqesh investimi

Rregulla të detyrueshme:
- Mos jep këshilla "bli" ose "shit" — kjo është edukuese, jo financiare
- Thekso gjithmonë volatilitetin e tregut
- Përgjigju SHQIP nëse pyetja është shqip, anglisht nëse është anglisht
- Struktura e qartë me tituj, lista dhe emoji ku ndihmon
- Përgjigje të sakta, të analizuara mirë
PROMPT,
];

// Thirr Groq API me streaming
$payload = json_encode([
    'model'       => GROQ_MODEL,
    'messages'    => array_merge([$system], $history),
    'max_tokens'  => 1024,
    'temperature' => 0.7,
    'stream'      => true,
]);

$ctx = stream_context_create([
    'http' => [
        'method'        => 'POST',
        'header'        => implode("\r\n", [
            'Content-Type: application/json',
            'Authorization: Bearer ' . GROQ_API_KEY,
        ]),
        'content'       => $payload,
        'timeout'       => 30,
        'ignore_errors' => true,
    ],
    'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
]);

$stream = @fopen('https://api.groq.com/openai/v1/chat/completions', 'r', false, $ctx);

if (!$stream) die_error('Lidhja me Groq API dështoi.');

// Lexo dhe transmeto SSE
$buffer = '';
while (!feof($stream)) {
    $chunk = fread($stream, 4096);
    if ($chunk === false) break;

    $buffer .= $chunk;
    $lines   = explode("\n", $buffer);
    $buffer  = array_pop($lines); // ruaj rreshtin e paplotë

    foreach ($lines as $line) {
        $line = trim($line);
        if (!str_starts_with($line, 'data: ')) continue;

        $json = trim(substr($line, 6));
        if ($json === '[DONE]') { sse('done', []); break 2; }

        $obj = json_decode($json, true);
        $text = $obj['choices'][0]['delta']['content'] ?? null;
        if ($text !== null) {
            sse('delta', ['text' => $text]);
        }
    }
}

fclose($stream);
