<?php
define('APP', 1);
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/engine.php';
require_login();

$settings = get_settings();
$campaigns = docs_all('campaign_');
$candDoc = doc_get('candidates');
$csrf = csrf_token();

$names = [];
foreach (($candDoc['data'] ?? []) as $c) { $names[$c['ticker']] = $c['name'] ?? $c['ticker']; }
$queued = [];
foreach (commands_pending() as $cmd) { $queued[$cmd['ticker']][$cmd['action']] = true; }

$positions = [];
foreach ($campaigns as $k => $c) {
    $d = $c['data'];
    if (($d['qty'] ?? 0) > 0 || ($d['status'] ?? '') === 'ACTIVE') {
        $positions[] = ['d' => $d, 'updated' => $c['updated_at']];
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Monitor — Trading AI Horizon</title>
<link rel="stylesheet" href="assets/css/app.css?v=8">
</head>
<body>
<div class="bg"></div>
<main class="hero wide">
  <nav class="nav">
    <a href="index.php">Dashboard</a><a href="monitor.php" class="on">Monitor</a>
    <a href="settings.php">Settings</a><a href="logout.php">Log out</a>
  </nav>
  <div class="badge"><span class="livedot"></span> LIVE MONITOR · refreshes every 10s</div>
  <h1 class="pagetitle" style="font-size:32px">Position Monitor</h1>

  <?php if (!$positions): ?>
    <section class="card"><h2>No positions being auto-traded yet</h2>
      <p class="muted">Approve a BUY on the <a href="index.php" style="color:var(--brand2)">Dashboard</a> —
      once the engine opens the position, it appears here with live tracking.</p></section>
  <?php endif; ?>

  <div class="mon-grid">
  <?php foreach ($positions as $p): $d = $p['d']; $t = htmlspecialchars($d['ticker']);
        $qty = (float) ($d['qty'] ?? 0); $avg = (float) ($d['avg_cost'] ?? 0);
        $sellQueued = !empty($queued[$t]['APPROVE_SELL_ALL']); ?>
    <section class="card mon-card" data-ticker="<?= $t ?>" data-qty="<?= $qty ?>" data-avg="<?= $avg ?>">
      <div class="mon-head">
        <div>
          <h2 class="mon-tk"><?= $t ?></h2>
          <span class="muted"><?= htmlspecialchars($names[$t] ?? $d['name'] ?? '') ?></span>
        </div>
        <div class="mon-price">
          <b class="live-px" data-t="<?= $t ?>">$<?= number_format((float) ($d['price'] ?? 0), 2) ?></b>
          <em class="live-chg" data-t="<?= $t ?>">—</em>
        </div>
      </div>

      <div class="mon-pl">
        <span>Current profit</span>
        <b class="live-pl" data-t="<?= $t ?>">$0</b>
        <em class="live-plpct" data-t="<?= $t ?>">0.00%</em>
      </div>
      <div class="plbar"><div class="plbar-fill" data-t="<?= $t ?>"></div></div>

      <div class="tiles mon-tiles">
        <div class="tile"><span>Shares</span><b><?= $qty ?> / <?= $settings['target_shares'] ?></b></div>
        <div class="tile"><span>Avg cost</span><b>$<?= number_format($avg, 2) ?></b></div>
        <div class="tile"><span>Total value</span><b class="live-val" data-t="<?= $t ?>">—</b></div>
        <div class="tile"><span>Status</span><b><?= htmlspecialchars($d['status'] ?? '') ?></b></div>
      </div>

      <?php if ($sellQueued): ?>
        <button class="btn danger locked" disabled><span class="lockdot"></span>
          Sell queued — engine will exit on next tick</button>
      <?php elseif ($qty > 0): ?>
        <button class="btn danger sell-click" data-ticker="<?= $t ?>"
                data-name="<?= htmlspecialchars($names[$t] ?? $t) ?>">
          One-click SELL ALL <?= $qty ?> shares</button>
      <?php endif; ?>
      <p class="muted small" style="margin-top:8px">updated by engine: <?= htmlspecialchars($p['updated']) ?> ·
        limits: +<?= round($settings['profit_alert_pct'] * 100) ?>% alert ·
        −$<?= number_format($settings['loss_alert_usd']) ?> / −$<?= number_format($settings['loss_urgent_usd']) ?></p>
    </section>
  <?php endforeach; ?>
  </div>

  <footer class="foot">prices refresh every ~10s · P&amp;L computed live in your browser</footer>
</main>
<?php require __DIR__ . '/inc/modal.php'; ?>
<script>
const CSRF = '<?= $csrf ?>';
const cards = [...document.querySelectorAll('.mon-card')];
const tickers = cards.map(c => c.dataset.ticker);

function fmt(n, d = 2) { return n.toLocaleString('en-US', {minimumFractionDigits: d, maximumFractionDigits: d}); }

async function refresh() {
  if (!tickers.length) return;
  try {
    const r = await fetch('api/quotes.php?t=' + tickers.join(','));
    const j = await r.json();
    for (const card of cards) {
      const t = card.dataset.ticker, q = (j.quotes || {})[t];
      if (!q) continue;
      const qty = parseFloat(card.dataset.qty), avg = parseFloat(card.dataset.avg);
      const val = qty * q.price, pl = (q.price - avg) * qty;
      const plp = avg > 0 ? (q.price / avg - 1) * 100 : 0;
      const set = (sel, txt) => { const e = card.querySelector(sel); if (e) e.textContent = txt; };

      const pxEl = card.querySelector('.live-px');
      const old = parseFloat(pxEl.textContent.replace(/[$,]/g, ''));
      pxEl.textContent = '$' + fmt(q.price);
      if (old && old !== q.price) {
        pxEl.classList.remove('flash-up', 'flash-dn'); void pxEl.offsetWidth;
        pxEl.classList.add(q.price > old ? 'flash-up' : 'flash-dn');
      }
      const chgEl = card.querySelector('.live-chg');
      chgEl.textContent = (q.chg_pct >= 0 ? '+' : '') + q.chg_pct.toFixed(2) + '% today';
      chgEl.className = 'live-chg ' + (q.chg_pct >= 0 ? 'ok' : 'bad');

      set('.live-val', '$' + fmt(val));
      const plEl = card.querySelector('.live-pl');
      plEl.textContent = (pl >= 0 ? '+$' : '−$') + fmt(Math.abs(pl));
      plEl.className = 'live-pl ' + (pl >= 0 ? 'ok' : 'bad');
      const ppEl = card.querySelector('.live-plpct');
      ppEl.textContent = (plp >= 0 ? '+' : '') + plp.toFixed(2) + '%';
      ppEl.className = 'live-plpct ' + (plp >= 0 ? 'ok' : 'bad');

      const bar = card.querySelector('.plbar-fill');
      const target = <?= (float) $settings['profit_alert_pct'] * 100 ?>;
      bar.style.width = Math.min(100, Math.max(2, (plp / target) * 100)) + '%';
      bar.className = 'plbar-fill ' + (plp >= 0 ? 'up' : 'dn');
    }
  } catch (e) { /* offline — keep last */ }
}
refresh();
setInterval(refresh, 10000);

document.querySelectorAll('.sell-click').forEach(btn => {
  btn.addEventListener('click', () => {
    const t = btn.dataset.ticker;
    const card = btn.closest('.mon-card');
    const pl = card.querySelector('.live-pl').textContent;
    tradeModal({
      title: `Sell ALL ${t}?`, icon: '💰', danger: true,
      rows: [['Company', btn.dataset.name], ['Ticker', t],
             ['Shares', card.dataset.qty], ['Current P&L', pl],
             ['Order', 'sell entire position @ best ask']],
      note: 'Queues your approval — the engine sells on its next tick, then this ' +
            'stock unlocks for future buys.',
      okLabel: 'Confirm SELL ALL',
      onConfirm: async () => {
        if (await queueCommand('APPROVE_SELL_ALL', t, CSRF)) {
          lockButton(btn, 'Sell queued — engine will exit on next tick');
        } else { alert('Could not queue — try again.'); }
      }
    });
  });
});
</script>
</body>
</html>
