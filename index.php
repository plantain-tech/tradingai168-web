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

// Button states: a ticker is LOCKED while its campaign runs; QUEUED if a command waits.
$running = [];
foreach ($campaigns as $k => $c) {
    $d = $c['data'];
    if (($d['status'] ?? '') === 'ACTIVE') { $running[$d['ticker']] = true; }
}
$queued = [];
foreach (commands_pending() as $cmd) { $queued[$cmd['ticker']][$cmd['action']] = true; }
$names = [];
foreach (($candDoc['data'] ?? []) as $c) { $names[$c['ticker']] = $c['name'] ?? $c['ticker']; }
$maxConcurrent = (int) ($settings['max_concurrent'] ?? 3);
$slotsFull = count($running) + count(array_filter($queued,
    fn($q) => !empty($q['APPROVE_BUY']))) >= $maxConcurrent;
$NAV_ACTIVE = 'dash';

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
<link rel="stylesheet" href="assets/css/app.css?v=11">
</head>
<body>
<div class="bg"></div>
<?php require __DIR__ . '/inc/nav.php'; ?>
<main class="hero wide">
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
      <p class="muted">Start the engine service once on your PC and everything else is click-driven:</p>
      <p class="muted"><code>python runner/service.py</code> (leave running) ·
      <code>python -X utf8 scripts/pick_stock.py --no-trends --push</code> for a fresh pick</p></section>
  <?php endif; ?>

  <?php foreach ($campaigns as $k => $c): $d = $c['data'];
        $st = $d['status'] ?? '';
        if ($st === 'CANCELLED') { continue; }                    // gone from UI
        $qty0 = (float) ($d['qty'] ?? 0);
        if ($qty0 <= 0 && $st === 'ACTIVE'): ?>
  <section class="card slim-card">
    <div><b><?= htmlspecialchars($d['ticker']) ?></b>
      <span class="muted">campaign created — awaiting first buy</span></div>
    <button class="btn ghost cancel-click" data-ticker="<?= htmlspecialchars($d['ticker']) ?>">
      Cancel campaign</button>
  </section>
  <?php continue; endif;
        if ($qty0 <= 0) { continue; }                              // closed/empty: hide
        $pnlClass = ($d['pnl'] ?? 0) >= 0 ? 'ok' : 'bad'; ?>
  <section class="card camp">
    <div class="camp-head">
      <h2><?= htmlspecialchars($d['ticker']) ?> campaign
          <span class="muted" style="font-weight:400">· <?= htmlspecialchars($d['status']) ?></span></h2>
      <span class="muted" style="font-size:11px">updated <?= htmlspecialchars($c['updated_at']) ?></span>
    </div>
    <div class="tiles">
      <div class="tile"><span>P&amp;L</span><b class="<?= $pnlClass ?>">$<?= number_format($d['pnl'] ?? 0, 0) ?></b></div>
      <div class="tile"><span>Shares</span><b><?= $d['qty'] ?? 0 ?></b></div>
      <div class="tile"><span>Budget used</span><b>$<?= number_format($d['invested'] ?? 0, 0) ?>
        <em class="muted small">/ $<?= number_format($d['stock_budget'] ?? 0, 0) ?></em></b></div>
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
      <?php foreach (($p['top3'] ?? []) as $t): $tk = htmlspecialchars($t['ticker']);
            $isChosen = $tk === ($p['chosen'] ?? '');
            $isRunning = !empty($running[$tk]);
            $isQueued = !empty($queued[$tk]['APPROVE_BUY']);
            $analysis = $t['analysis'] ?? $t['reason'] ?? '';
            if ($isChosen && !empty($p['rationale'])) { $analysis = $p['rationale']; } ?>
        <div class="tile pick-tile selectable <?= $isChosen ? 'chosen selected' : '' ?>"
             data-ticker="<?= $tk ?>" role="button" tabindex="0"
             data-analysis="<?= htmlspecialchars($analysis) ?>">
          <?php if ($isChosen): ?><span class="crown">★ TOP CHOICE</span><?php endif; ?>
          <span><?= $tk ?> <em class="muted"><?= htmlspecialchars($names[$tk] ?? '') ?></em></span>
          <b><?= htmlspecialchars($t['score']) ?></b>
          <p class="muted small"><?= htmlspecialchars($t['reason']) ?></p>
          <?php if ($isRunning): ?>
            <button class="btn buybtn locked" disabled><span class="lockdot"></span>
              Auto-trading running — until position fully sold</button>
          <?php elseif ($isQueued): ?>
            <button class="btn buybtn locked" disabled><span class="lockdot"></span>
              Queued — engine will buy on next tick</button>
          <?php elseif ($slotsFull): ?>
            <button class="btn buybtn locked" disabled>
              Max <?= $maxConcurrent ?> stocks trading — sell one to free a slot</button>
          <?php else: ?>
            <button class="btn buybtn buy-click" data-ticker="<?= $tk ?>"
                    data-name="<?= htmlspecialchars($names[$tk] ?? $tk) ?>">
              One-click BUY <?= $tk ?></button>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="ai-panel open" id="aiPanel">
      <button class="ai-panel-head" id="aiPanelHead" type="button">
        <span>Full AI analysis — <b class="grad-t" id="aiPanelTicker"><?= htmlspecialchars($p['chosen'] ?? '') ?></b>
          <em class="muted small">why / how / trend / risk</em></span>
        <svg class="chev" viewBox="0 0 24 24" width="18" height="18">
          <path d="M7 10l5 5 5-5" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round"/></svg>
      </button>
      <div class="ai-panel-wrap"><div class="ai-panel-body" id="aiPanelBody"><?=
        nl2br(htmlspecialchars($p['rationale'] ?? '')) ?></div></div>
    </div>

    <p class="muted small">Click a card to read its analysis. A BUY click queues your
      approval; the engine service buys the first tranche @ best bid and runs the DCA
      autopilot within its per-stock budget
      ($<?= number_format($settings['budget_usd'] / max(1, $maxConcurrent), 0) ?> each,
      $<?= number_format($settings['budget_usd'], 0) ?> global). Nothing trades without your click.</p>
  </section>
  <?php endif; ?>

  <footer class="foot">Trading AI Horizon · momentum engine · you approve, it executes</footer>
</main>
<?php require __DIR__ . '/inc/modal.php'; ?>
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

const CSRF = '<?= $csrf ?>';
const TRANCHE = <?= (int) $settings['tranche_base'] ?>;

// ---- pick cards: click to select -> animated analysis swap ----
const panel = document.getElementById('aiPanel');
if (panel) {
  const body = document.getElementById('aiPanelBody');
  const tkEl = document.getElementById('aiPanelTicker');
  document.getElementById('aiPanelHead').addEventListener('click',
    () => panel.classList.toggle('open'));
  document.querySelectorAll('.pick-tile.selectable').forEach(card => {
    const select = () => {
      document.querySelectorAll('.pick-tile.selected')
        .forEach(c => c.classList.remove('selected'));
      card.classList.add('selected');
      tkEl.textContent = card.dataset.ticker;
      body.classList.add('swapping');
      setTimeout(() => {
        body.innerHTML = card.dataset.analysis.replace(/\n/g, '<br>');
        body.classList.remove('swapping');
        panel.classList.add('open');
      }, 180);
    };
    card.addEventListener('click', e => {
      if (e.target.closest('button')) return;      // buttons keep their own action
      select();
    });
    card.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); select(); }
    });
  });
}

// ---- cancel a not-yet-filled campaign ----
document.querySelectorAll('.cancel-click').forEach(btn => {
  btn.addEventListener('click', () => {
    const t = btn.dataset.ticker;
    tradeModal({
      title: `Cancel ${t} campaign?`, icon: '🗑️', danger: true,
      rows: [['Ticker', t], ['Shares held', '0'],
             ['Effect', 'campaign removed before any buy']],
      note: 'Only possible while no shares are held. The engine confirms on its next cycle.',
      okLabel: 'Cancel campaign',
      onConfirm: async () => {
        if (await queueCommand('CANCEL_CAMPAIGN', t, CSRF)) {
          lockButton(btn, 'Cancellation queued');
        } else { alert('Could not queue — try again.'); }
      }
    });
  });
});

document.querySelectorAll('.buy-click').forEach(btn => {
  btn.addEventListener('click', () => {
    const t = btn.dataset.ticker;
    const px = document.querySelector(`.tk-px[data-t="${t}"]`)?.textContent || '—';
    tradeModal({
      title: `Buy ${t}?`, icon: '🚀',
      rows: [['Company', btn.dataset.name], ['Ticker', t],
             ['First tranche', TRANCHE + ' shares'], ['Order', 'limit @ best bid'],
             ['Last price', px],
             ['Then', 'DCA autopilot ±5 sh / 5 days → 100 sh']],
      note: 'Queues your approval — the engine places the order on its next tick. ' +
            'Loss alerts and budget caps stay enforced.',
      okLabel: 'Confirm BUY',
      onConfirm: async () => {
        if (await queueCommand('APPROVE_BUY', t, CSRF)) {
          lockButton(btn, 'Auto-trading running — until position fully sold');
        } else { alert('Could not queue — try again.'); }
      }
    });
  });
});

document.querySelectorAll('.oneclick').forEach(btn => {   // sell-all on campaign card
  btn.addEventListener('click', () => {
    const t = btn.dataset.ticker;
    tradeModal({
      title: `Sell ALL ${t}?`, icon: '💰', danger: true,
      rows: [['Ticker', t], ['Order', 'sell entire position @ best ask']],
      note: 'Queues your approval — the engine sells on its next tick.',
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
