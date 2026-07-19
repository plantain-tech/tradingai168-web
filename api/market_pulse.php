<?php
// Shared broker-first market panel. Moomoo rows arrive from the PC engine;
// exact instruments unavailable from OpenD are fetched and labelled as Yahoo backup.
define('APP', 1);
require __DIR__ . '/../inc/engine.php';
header('Content-Type: application/json');

boot_session();
if (empty($_SESSION['uid'])) { http_response_code(401); echo '{"error":"login"}'; exit; }

$groups = [
    'us' => [
        ['id'=>'sp500', 'label'=>'S&P 500', 'symbol'=>'^GSPC'],
        ['id'=>'dow', 'label'=>'Dow 30', 'symbol'=>'^DJI'],
        ['id'=>'nasdaq', 'label'=>'Nasdaq', 'symbol'=>'^IXIC'],
        ['id'=>'russell', 'label'=>'Russell 2000', 'symbol'=>'^RUT'],
        ['id'=>'vix', 'label'=>'VIX', 'symbol'=>'^VIX'],
        ['id'=>'gold', 'label'=>'Gold', 'symbol'=>'GC=F'],
        ['id'=>'btc', 'label'=>'Bitcoin USD', 'symbol'=>'BTC-USD'],
        ['id'=>'crude', 'label'=>'Crude Oil', 'symbol'=>'CL=F'],
    ],
    'crypto' => [
        ['id'=>'btc', 'label'=>'Bitcoin USD', 'symbol'=>'BTC-USD'],
        ['id'=>'xrp', 'label'=>'XRP USD', 'symbol'=>'XRP-USD'],
        ['id'=>'eth', 'label'=>'Ethereum USD', 'symbol'=>'ETH-USD'],
        ['id'=>'usdt', 'label'=>'Tether USD', 'symbol'=>'USDT-USD'],
        ['id'=>'bnb', 'label'=>'BNB USD', 'symbol'=>'BNB-USD'],
        ['id'=>'sol', 'label'=>'Solana USD', 'symbol'=>'SOL-USD'],
        ['id'=>'doge', 'label'=>'Dogecoin USD', 'symbol'=>'DOGE-USD'],
    ],
];

function yahoo_pulse_quote(string $symbol): ?array {
    $url = 'https://query1.finance.yahoo.com/v8/finance/chart/' . rawurlencode($symbol)
         . '?range=1d&interval=5m&includePrePost=true';
    $ctx = stream_context_create(['http' => ['timeout' => 4,
        'header' => "User-Agent: Mozilla/5.0 (TradingAIHorizon market panel)\r\n"]]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) { return null; }
    $result = json_decode($raw, true)['chart']['result'][0] ?? null;
    if (!$result) { return null; }
    $meta = $result['meta'] ?? [];
    $closes = array_values(array_filter($result['indicators']['quote'][0]['close'] ?? [],
        fn($value) => is_numeric($value) && (float) $value > 0));
    $price = (float) ($meta['regularMarketPrice'] ?? (end($closes) ?: 0));
    $previous = (float) ($meta['chartPreviousClose'] ?? $meta['previousClose'] ?? 0);
    if ($price <= 0) { return null; }
    return [
        'price' => round($price, 6),
        'change' => $previous > 0 ? round($price - $previous, 6) : null,
        'change_pct' => $previous > 0 ? round(($price / $previous - 1) * 100, 4) : null,
        'spark' => array_map(fn($value) => round((float) $value, 6), array_slice($closes, -24)),
        'quote_time' => !empty($meta['regularMarketTime'])
            ? gmdate('Y-m-d H:i:s', (int) $meta['regularMarketTime']) . ' UTC' : '',
        'market_state' => $meta['marketState'] ?? null,
        'source' => 'Yahoo Finance backup',
        'symbol' => $symbol,
        'stale' => false,
    ];
}

$brokerDoc = doc_get('market_pulse');
$brokerData = $brokerDoc['data'] ?? [];
$brokerTime = strtotime($brokerData['published_at'] ?? '') ?: 0;
$brokerFresh = $brokerTime && time() - $brokerTime <= 150;
$items = $brokerFresh && is_array($brokerData['items'] ?? null) ? $brokerData['items'] : [];
foreach ($items as $id => &$item) {
    $item['source'] = 'Moomoo OpenD';
    $item['stale'] = false;
}
unset($item);

$definitions = [];
foreach ($groups as $rows) foreach ($rows as $row) $definitions[$row['id']] = $row;
$missing = array_diff(array_keys($definitions), array_keys($items));
$cache = doc_get('market_pulse_yahoo_v1');
$cacheAge = $cache ? time() - (strtotime($cache['updated_at']) ?: 0) : PHP_INT_MAX;
$cachedItems = is_array($cache['data']['items'] ?? null) ? $cache['data']['items'] : [];
if ($missing && $cacheAge <= 45) {
    foreach ($missing as $id) if (isset($cachedItems[$id])) $items[$id] = $cachedItems[$id];
}
$missing = array_diff(array_keys($definitions), array_keys($items));
if ($missing) {
    $freshBackup = [];
    foreach ($missing as $id) {
        $quote = yahoo_pulse_quote($definitions[$id]['symbol']);
        if ($quote) $freshBackup[$id] = $quote;
    }
    if ($freshBackup) {
        doc_set('market_pulse_yahoo_v1', [
            'items'=>$freshBackup,
            'fetched_at'=>gmdate('c'),
        ]);
        $items += $freshBackup;
    }
    $stillMissing = array_diff($missing, array_keys($freshBackup));
    if ($stillMissing && $cacheAge <= 600) {
        foreach ($stillMissing as $id) if (isset($cachedItems[$id])) {
            $items[$id] = $cachedItems[$id];
            $items[$id]['source'] = 'Yahoo Finance backup · delayed';
            $items[$id]['stale'] = true;
        }
    }
}

foreach ($groups as &$rows) foreach ($rows as &$row) {
    $row['quote'] = $items[$row['id']] ?? null;
}
unset($rows, $row);
echo json_encode(['groups'=>$groups, 'broker_fresh'=>(bool) $brokerFresh,
                  'broker_published_at'=>$brokerData['published_at'] ?? null,
                  'generated_at'=>gmdate('c')]);
