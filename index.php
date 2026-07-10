<?php
define('APP', 1);
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/engine.php';
require_login();

$settings = get_settings();
$pick = doc_get('daily_pick');
$candDoc = doc_get('candidates');
$campaigns = docs_all('campaign_');
$csrf = csrf_token();

function spark(array $hist): string {                 // tiny SVG equity sparkline
    $vals = array_map(fn($h) => (float) $h[1], $hist);
    if (count($vals) < 2) { return ''; }
    $w = 280; $h = 56; $mn = min($vals); $mx = max($vals);
    $rng = ($mx - $mn) ?: 1;
    $pts = [];
    foreach ($vals as $i => $v) {
        $x = round($i / (count($vals) - 1) * $w, 1);
        $y = round($h - (($v - $mn) / $rng) * ($h - 6) - 3, 1);
        $pts[] = "$x,$y";
    }
    $line = implode(' ', $pts);
    $up = end($vals) >= $vals[0] ? '#34d399' : '#f87171';
    return "<svg class='spark' viewBox='0 0 $w $h' preserveAspectRatio='none'>
            <polyline points='$line' fill='none' stroke='$up' stroke-width='2'/></svg>";
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Trading AI Horizon — Dashboard</title>
<link rel="stylesheet" href="assets/css/app.css?v=7">
</head>
<body>
<div class="bg"></div>
<main class="hero wide">
  <nav class="nav">
    <a href="index.php" class="on">Dashboard</a><a href="settings.php">Settings</a>
    <a href="logout.php">Log out</a>
  </nav>

  <?php $top10 = array_slice($candDoc['data'] ?? [], 0, 10);
        if ($top10): ?>
  <div class="ticker"><div class="ticker-track">
    <?php foreach ([0, 1] as $copy): /* duplicated for seamless loop */ ?>
      <?php foreach ($top10 as $c): $t = htmlspecialchars($c['ticker']); ?>
      <span class="tk"><b><?= $t ?></b>
        <span class="tk-px" data-t="<?= $t ?>">$<?= number_format((float) $c['price'], 2) ?></span>
        <em class="tk-chg" data-t="<?= $t ?>"></em></span>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </div></div>
  <?php endif; ?>

  <div class="badge">MOMENTUM · <?= count($campaigns) ? 'CAMPAIGN LIVE' : 'NO OPEN CAMPAIGN' ?></div>
  <h1 class="pagetitle" style="font-size:32px">Dashboard</h1>

  <?php if (!$campaigns && !$pick): ?>
    <section class="card"><h2>Waiting for engine data</h2>
      <p class="muted">Run a pick or a tick on your PC and it appears here:</p>
      <p class="muted"><code>python -X utf8 scripts/pick_stock.py --no-trends --push</code><br>
      <code>python runner/momentum_loop.py --ticker TGT</code></p></section>
  <?php endif; ?>

  <?php foreach ($campaigns as $k => $c): $d = $c['data'];
        $pnlClass = ($d['pnl'] ?? 0) >= 0 ? 'ok' : 'bad'; ?>
  <section class="card camp">
    <div class="camp-head">
      <h2><?= htmlspecialchars($d['ticker']) ?> campaign
          <span class="muted" style="font-weight:400">· <?= htmlspecialchars($d['status']) ?></span></h2>
      <span class="muted" style="font-size:11px">updated <?= htmlspecialchars($c['updated_at']) ?></span>
    </div>
    <div class="tiles">
      <div class="tile"><span>P&amp;L</span><b class="<?= $pnlClass ?>">$<?= number_format($d['pnl'] ?? 0, 0) ?></b></div>
      <div class="tile"><span>Shares</span><b><?= $d['qty'] ?? 0 ?> / <?= $settings['target_shares'] ?></b></div>
      <div class="tile"><span>Avg cost</span><b>$<?= number_format($d['avg_cost'] ?? 0, 2) ?></b></div>
      <div class="tile"><span>Price</span><b>$<?= number_format($d['price'] ?? 0, 2) ?></b></div>
    </div>
    <?= spark($d['history'] ?? []) ?>
    <div class="limits muted">
      profit alert +<?= round($settings['profit_alert_pct'] * 100) ?>% ·
      loss −$<?= number_format($settings['loss_alert_usd']) ?> ·
      urgent −$<?= number_format($settings['loss_urgent_usd']) ?> ·
      budget $<?= number_format($settings['budget_usd']) ?>
    </div>
    <?php if (!empty($d['alerts'])): ?>
      <div class="alerts">
      <?php foreach (array_slice(array_reverse($d['alerts']), 0, 3) as $a): ?>
        <p class="alert-<?= $a['level'] === 'WIN' ? 'ok' : 'bad' ?>">
          [<?= htmlspecialchars($a['level']) ?>] <?= htmlspecialchars($a['msg']) ?>
          <span class="muted">(<?= htmlspecialchars($a['date']) ?>)</span></p>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if (($d['qty'] ?? 0) > 0 && ($d['status'] ?? '') === 'ACTIVE'): ?>
      <button class="btn danger oneclick" data-action="APPROVE_SELL_ALL"
              data-ticker="<?= htmlspecialchars($d['ticker']) ?>">
        One-click SELL ALL <?= $d['qty'] ?> sh (queued for engine, at best ask)</button>
    <?php endif; ?>
  </section>
  <?php endforeach; ?>

  <?php if ($pick): $p = $pick['data']; ?>
  <section class="card">
    <div class="camp-head">
      <h2>AI pick of the day: <span class="grad-t"><?= htmlspecialchars($p['chosen']) ?></span></h2>
      <span class="muted" style="font-size:11px"><?= htmlspecialchars($p['source'] ?? '') ?>
        · <?= htmlspecialchars($pick['updated_at']) ?></span>
    </div>
    <div class="top3">
      <?php foreach (($p['top3'] ?? []) as $t): ?>
        <div class="tile"><span><?= htmlspecialchars($t['ticker']) ?></span>
          <b><?= htmlspecialchars($t['score']) ?></b>
          <p class="muted small"><?= htmlspecialchars($t['reason']) ?></p></div>
      <?php endforeach; ?>
    </div>
    <details class="rationale" open>
      <summary>Full AI analysis — why / how / trend / risk</summary>
      <p><?= nl2br(htmlspecialchars($p['rationale'] ?? '')) ?></p>
    </details>
    <button class="btn oneclick" data-action="APPROVE_BUY"
            data-ticker="<?= htmlspecialchars($p['chosen']) ?>">
      One-click APPROVE BUY — first tranche <?= $settings['tranche_base'] ?> shares @ best bid</button>
    <p class="muted small">Queues your approval; the engine places the order on its
      next tick (with --execute). Nothing trades without this click.</p>
  </section>
  <?php endif; ?>

  <footer class="foot">Trading AI Horizon · momentum engine · you approve, it executes</footer>
</main>

<script>
// Live ticker: poll fresh quotes every 10s and update prices in place.
const tickers = [...new Set([...document.querySelectorAll('.tk-px')].map(e => e.dataset.t))];
async function refreshQuotes() {
  if (!tickers.length) return;
  try {
    const r = await fetch('api/quotes.php?t=' + tickers.join(','));
    const j = await r.json();
    for (const [t, q] of Object.entries(j.quotes || {})) {
      document.querySelectorAll(`.tk-px[data-t="${t}"]`).forEach(e => {
        const old = parseFloat(e.textContent.slice(1));
        e.textContent = '$' + q.price.toFixed(2);
        if (old && old !== q.price) {
          e.classList.remove('flash-up', 'flash-dn');
          void e.offsetWidth;                       // restart animation
          e.classList.add(q.price > old ? 'flash-up' : 'flash-dn');
        }
      });
      document.querySelectorAll(`.tk-chg[data-t="${t}"]`).forEach(e => {
        e.textContent = (q.chg_pct >= 0 ? '+' : '') + q.chg_pct.toFixed(2) + '%';
        e.className = 'tk-chg ' + (q.chg_pct >= 0 ? 'ok' : 'bad');
      });
    }
  } catch (e) { /* offline: keep last prices */ }
}
refreshQuotes();
setInterval(refreshQuotes, 10000);

document.querySelectorAll('.oneclick').forEach(btn => {
  btn.addEventListener('click', async () => {
    if (!confirm(`Confirm: ${btn.dataset.action.replace('_', ' ')} ${btn.dataset.ticker}?`)) return;
    btn.disabled = true; const old = btn.textContent;
    btn.textContent = 'Queueing...';
    try {
      const r = await fetch('api/command.php', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: btn.dataset.action, ticker: btn.dataset.ticker,
                              csrf: '<?= $csrf ?>'})});
      const j = await r.json();
      btn.textContent = j.ok ? 'QUEUED ✓ — engine will act on next tick' : ('Failed: ' + (j.error || '?'));
    } catch (e) { btn.textContent = 'Network error'; btn.disabled = false; }
    setTimeout(() => { btn.textContent = old; btn.disabled = false; }, 6000);
  });
});
</script>
</body>
</html>
