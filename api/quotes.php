<?php
// Near-real-time quotes for the dashboard ticker: GET ?t=CSX,TGT,...
// Auth: logged-in session OR engine Bearer token. Server-side fetch from Yahoo's
// free quote endpoint, shared-cached ~8s so multiple viewers don't hammer it.
define('APP', 1);
require __DIR__ . '/../inc/engine.php';
header('Content-Type: application/json');

boot_session();
if (empty($_SESSION['uid']) && !bearer_ok()) {
    http_response_code(401); echo '{"error":"login"}'; exit;
}

$tickers = array_slice(array_filter(array_map(
    fn($t) => strtoupper(preg_replace('/[^A-Za-z\-]/', '', $t)),
    explode(',', $_GET['t'] ?? ''))), 0, 30);
if (!$tickers) { echo '{"quotes":{}}'; exit; }

sort($tickers);
$cacheKey = 'quotes_' . substr(md5(implode(',', $tickers)), 0, 12);
$cached = doc_get($cacheKey);
if ($cached && (time() - strtotime($cached['updated_at'])) < 8) {
    echo json_encode(['quotes' => $cached['data'], 'cached' => true]);
    exit;
}

$quotes = [];
foreach ($tickers as $t) {
    $url = "https://query1.finance.yahoo.com/v8/finance/chart/{$t}?range=1d&interval=1d";
    $ctx = stream_context_create(['http' => ['timeout' => 6,
        'header' => "User-Agent: Mozilla/5.0 (TradingAIHorizon dashboard)\r\n"]]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) { continue; }
    $j = json_decode($raw, true);
    $meta = $j['chart']['result'][0]['meta'] ?? null;
    if ($meta && isset($meta['regularMarketPrice'])) {
        $px = (float) $meta['regularMarketPrice'];
        $prev = (float) ($meta['chartPreviousClose'] ?? $meta['previousClose'] ?? $px);
        $quotes[$t] = ['price' => round($px, 2),
                       'chg_pct' => $prev > 0 ? round(($px / $prev - 1) * 100, 2) : 0.0];
    }
}
doc_set($cacheKey, $quotes);
echo json_encode(['quotes' => $quotes, 'cached' => false]);
