<?php
define('APP', 1);
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/engine.php';
require_login();

$settings = get_settings();
$campaigns = [];
foreach (docs_all('campaign_') as $k => $c) {
    $d = $c['data'];
    $d['updated_at'] = $c['updated_at'];
    $campaigns[] = $d;
}
$pending = [];
foreach (commands_pending() as $cmd) { $pending[] = ['t' => $cmd['ticker'], 'a' => $cmd['action']]; }
$marks = doc_get('broker_marks');
$engineHealth = doc_get('engine_health');
$names = [];
foreach ((doc_get('candidates')['data'] ?? []) as $c) { $names[$c['ticker']] = $c['name'] ?? $c['ticker']; }
$csrf = csrf_token();
$NAV_ACTIVE = 'auto-paper';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>AI Auto Trade · Paper — Trading AI Horizon</title>
<link rel="icon" type="image/png" href="favicon.png?v=2">
<link rel="stylesheet" href="assets/css/app.css?v=23">
</head>
<body>
<div class="bg"></div>
<?php $NAV_ACTIVE = 'auto-paper'; require __DIR__ . '/inc/nav.php'; ?>
<main class="hero wide">
  <div class="badge" id="engineBadge"><span class="livedot" id="engineDot"></span>
    <span id="engineStatus">Checking PC engine status…</span></div>
  <h1 class="pagetitle" style="font-size:32px">AI Auto Trade · Paper</h1>

  <section class="card" id="emptyState" hidden>
    <h2>No positions being auto-traded yet</h2>
    <p class="muted">Approve a BUY on the <a href="index.php" style="color:var(--brand2)">Dashboard</a> —
    the moment the engine opens the position, it appears here live, no refresh needed.</p>
  </section>

  <div class="mon-grid" id="monGrid"></div>
  <footer class="foot">Moomoo Paper Trading · marks sync about every 10s · P&amp;L uses Moomoo last price</footer>
  <?php require __DIR__ . '/inc/brand_footer.php'; ?>
</main>
<?php require __DIR__ . '/inc/modal.php'; ?>
<script>
const CSRF = '<?= $csrf ?>';
const NAMES = <?= json_encode($names) ?>;
const TARGET_PCT = <?= (float) $settings['profit_alert_pct'] * 100 ?>;
const LIMITS = 'limits: +<?= round($settings['profit_alert_pct'] * 100) ?>% alert · −$<?=
    number_format($settings['loss_alert_usd']) ?> / −$<?= number_format($settings['loss_urgent_usd']) ?>';

let CAMPS = <?= json_encode($campaigns) ?>;
let PENDING = <?= json_encode($pending) ?>;
let MARKS = <?= json_encode($marks) ?>;
let ENGINE_HEALTH = <?= json_encode($engineHealth) ?>;
const MARK_STALE_MS = 35000;
const grid = document.getElementById('monGrid');
const fmt = (n, d = 2) => Number(n || 0).toLocaleString('en-US',
    {minimumFractionDigits: d, maximumFractionDigits: d});
const quoteMinute = s => {
  const m = String(s || '').match(/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2})/);
  return m ? m[1] : (s || 'timestamp unavailable');
};
const pendingHas = (t, a) => PENDING.some(p => p.t === t && p.a === a);
const showable = c => (c.status === 'ACTIVE' || (c.qty || 0) > 0);

function renderEngineHealth() {
  const h = ENGINE_HEALTH && ENGINE_HEALTH.data;
  const status = document.getElementById('engineStatus');
  const dot = document.getElementById('engineDot');
  const seen = h ? Date.parse(h.last_seen_at || '') : 0;
  const staleAfter = Number(h?.stale_after_seconds || 35) * 1000;
  const offline = !h || h.status !== 'running' || !seen || Date.now() - seen > staleAfter;
  dot.classList.toggle('bad-dot', offline);
  if (offline) {
    status.textContent = 'PC ENGINE OFFLINE · Moomoo tracking & auto-trading paused';
    return;
  }
  const mode = h.mode === 'REAL' ? 'REAL' : 'PAPER';
  status.textContent = `MOOMOO OPEN D · ${mode} engine running · marks sync automatically`;
}

function brokerMark(t) {
  const feed = MARKS && MARKS.data;
  const mark = feed && feed.quotes && feed.quotes[t];
  if (!mark) return {ok: false, reason: 'waiting for Moomoo OpenD'};
  const published = Date.parse(feed.published_at || '');
  if (!published || Date.now() - published > MARK_STALE_MS) {
    return {ok: false, reason: 'Moomoo feed stale — P/L hidden'};
  }
  return {ok: true, mark};
}

function cardHTML(c) {
  const t = c.ticker, qty = c.qty || 0;
  let btn;
  if (pendingHas(t, 'APPROVE_SELL_ALL')) {
    btn = `<button class="btn danger locked" disabled><span class="lockdot"></span>
           Sell queued — engine will exit shortly</button>`;
  } else if (qty > 0) {
    btn = `<button class="btn danger sell-click" data-ticker="${t}">
           One-click SELL ALL ${qty} shares</button>`;
  } else if (pendingHas(t, 'CANCEL_CAMPAIGN')) {
    btn = `<button class="btn ghost locked" disabled><span class="lockdot"></span>
           Cancellation queued</button>`;
  } else {
    btn = `<button class="btn ghost cancel-click" data-ticker="${t}">
           Awaiting first buy — cancel campaign</button>`;
  }
  return `
    <div class="mon-head">
      <div><h2 class="mon-tk">${t}</h2>
        <span class="muted">${NAMES[t] || c.name || ''}</span></div>
      <div class="mon-price">
        <b class="live-px" data-t="${t}">$${fmt(c.price)}</b>
        <em class="live-chg" data-t="${t}">—</em></div>
    </div>
    <div class="mon-pl"><span>Current profit</span>
      <b class="live-pl" data-t="${t}">${(c.pnl >= 0 ? '+$' : '−$') + fmt(Math.abs(c.pnl || 0))}</b>
      <em class="live-plpct" data-t="${t}">—</em></div>
    <div class="plbar"><div class="plbar-fill" data-t="${t}"></div></div>
    <div class="tiles mon-tiles">
      <div class="tile"><span>Shares</span><b>${qty}</b></div>
      <div class="tile"><span>Budget used</span><b>$${fmt(c.invested, 0)}
        <em class="muted small">/ $${fmt(c.stock_budget, 0)}</em></b></div>
      <div class="tile"><span>Avg cost</span><b>$${fmt(c.avg_cost)}</b></div>
      <div class="tile"><span>Total value</span><b class="live-val" data-t="${t}">—</b></div>
      <div class="tile"><span>Status</span><b>${c.status || ''}</b></div>
    </div>
    ${btn}
    <p class="muted small" style="margin-top:8px">engine sync: ${c.updated_at || '—'} · ${LIMITS}</p>`;
}

function sig(c) {   // structural signature: when this changes, morph the card
  return [c.qty, c.status, c.invested, c.avg_cost,
          pendingHas(c.ticker, 'APPROVE_SELL_ALL'),
          pendingHas(c.ticker, 'CANCEL_CAMPAIGN')].join('|');
}

const cardEls = {};   // ticker -> {el, sig}
function renderAll(animateNew = false) {
  const visible = CAMPS.filter(showable);
  document.getElementById('emptyState').hidden = visible.length > 0;
  const seen = new Set();
  for (const c of visible) {
    const t = c.ticker;
    seen.add(t);
    const s = sig(c);
    let rec = cardEls[t];
    if (!rec) {                                     // new card slides in
      const el = document.createElement('section');
      el.className = 'card mon-card card-enter';
      el.dataset.ticker = t;
      el.innerHTML = cardHTML(c);
      grid.appendChild(el);
      cardEls[t] = {el, sig: s, qty: c.qty || 0};
      setTimeout(() => el.classList.remove('card-enter'), 700);
    } else if (rec.sig !== s) {                     // changed -> morph in place
      const wasAwaiting = rec.qty <= 0, nowLive = (c.qty || 0) > 0;
      rec.el.classList.add('card-morph');
      setTimeout(() => {
        rec.el.innerHTML = cardHTML(c);
        rec.el.classList.remove('card-morph');
        if (wasAwaiting && nowLive) {               // activation: celebrate it
          rec.el.classList.add('card-activate');
          setTimeout(() => rec.el.classList.remove('card-activate'), 1600);
        }
        renderBrokerMarks();
      }, 260);
      rec.sig = s;
      rec.qty = c.qty || 0;
    }
    if (cardEls[t]) { cardEls[t].data = c; }
  }
  for (const t of Object.keys(cardEls)) {           // gone -> fade out
    if (!seen.has(t)) {
      const el = cardEls[t].el;
      el.classList.add('card-exit');
      setTimeout(() => el.remove(), 450);
      delete cardEls[t];
    }
  }
}

async function syncCampaigns() {
  try {
    const j = await (await fetch('api/campaigns.php')).json();
    if (j.campaigns) {
      CAMPS = j.campaigns;
      PENDING = j.pending || [];
      MARKS = j.broker_marks || null;
      ENGINE_HEALTH = j.engine_health || null;
      renderAll();
      renderBrokerMarks();
      renderEngineHealth();
    }
  } catch (e) { /* offline — keep last */ }
}

function renderBrokerMarks() {
  for (const t of Object.keys(cardEls)) {
    const rec = cardEls[t], el = rec.el, live = brokerMark(t);
    const px = el.querySelector('.live-px');
    const note = el.querySelector('p.muted.small');
    const chg = el.querySelector('.live-chg');
    const plEl = el.querySelector('.live-pl');
    const pp = el.querySelector('.live-plpct');
    const value = el.querySelector('.live-val');
    if (!live.ok) {
      px.textContent = '—';
      chg.textContent = live.reason;
      plEl.textContent = '—'; plEl.className = 'live-pl bad';
      pp.textContent = 'Moomoo feed required'; pp.className = 'live-plpct bad';
      value.textContent = '—';
      note.textContent = live.reason + ' · ' + LIMITS;
      continue;
    }
    const q = live.mark, avg = Number(q.avg_cost || 0);
    const old = parseFloat(px.textContent.replace(/[$,]/g, ''));
    px.textContent = '$' + fmt(q.price);
    if (old && Math.abs(old - q.price) > 0.004) {
      px.classList.remove('flash-up', 'flash-dn'); void px.offsetWidth;
      px.classList.add(q.price > old ? 'flash-up' : 'flash-dn');
    }
    const pl = Number(q.pnl);
    const plp = avg > 0 ? (q.price / avg - 1) * 100 : 0;
    chg.textContent = 'Moomoo last · ' + quoteMinute(q.quote_time);
    chg.className = 'live-chg ok';
    plEl.textContent = (pl >= 0 ? '+$' : '−$') + fmt(Math.abs(pl));
    plEl.className = 'live-pl ' + (pl >= 0 ? 'ok' : 'bad');
    pp.textContent = (plp >= 0 ? '+' : '') + plp.toFixed(2) + '%';
    pp.className = 'live-plpct ' + (plp >= 0 ? 'ok' : 'bad');
    value.textContent = '$' + fmt(q.value);
    const bar = el.querySelector('.plbar-fill');
    bar.style.width = Math.min(100, Math.max(2, (plp / TARGET_PCT) * 100)) + '%';
    bar.className = 'plbar-fill ' + (plp >= 0 ? 'up' : 'dn');
    note.textContent = 'Moomoo OpenD · last price · ' + quoteMinute(q.quote_time) +
      ' · ' + LIMITS;
  }
}

async function liveQuotes() {
  renderBrokerMarks();
  return; // Monitor P/L is broker-authoritative; never fetch Yahoo here.
  /* Retired Yahoo implementation retained only as a migration note. It is not
     executable: Monitor marks must come exclusively from Moomoo OpenD. */
  const tickers = Object.keys(cardEls);
  if (!tickers.length) return;
  try {
    const j = await (await fetch('api/quotes.php?t=' + tickers.join(','))).json();
    for (const t of tickers) {
      const q = (j.quotes || {})[t], rec = cardEls[t];
      if (!q || !rec) continue;
      const c = rec.data || {}, el = rec.el;
      const qty = c.qty || 0, avg = c.avg_cost || 0;
      const px = el.querySelector('.live-px');
      const old = parseFloat(px.textContent.replace(/[$,]/g, ''));
      px.textContent = '$' + fmt(q.price);
      if (old && Math.abs(old - q.price) > 0.004) {
        px.classList.remove('flash-up', 'flash-dn'); void px.offsetWidth;
        px.classList.add(q.price > old ? 'flash-up' : 'flash-dn');
      }
      const chg = el.querySelector('.live-chg');
      chg.textContent = (q.chg_pct >= 0 ? '+' : '') + q.chg_pct.toFixed(2) + '% today';
      chg.className = 'live-chg ' + (q.chg_pct >= 0 ? 'ok' : 'bad');
      if (qty > 0 && avg > 0) {
        const pl = (q.price - avg) * qty, plp = (q.price / avg - 1) * 100;
        const plEl = el.querySelector('.live-pl');
        plEl.textContent = (pl >= 0 ? '+$' : '−$') + fmt(Math.abs(pl));
        plEl.className = 'live-pl ' + (pl >= 0 ? 'ok' : 'bad');
        const pp = el.querySelector('.live-plpct');
        pp.textContent = (plp >= 0 ? '+' : '') + plp.toFixed(2) + '%';
        pp.className = 'live-plpct ' + (plp >= 0 ? 'ok' : 'bad');
        el.querySelector('.live-val').textContent = '$' + fmt(qty * q.price);
        const bar = el.querySelector('.plbar-fill');
        bar.style.width = Math.min(100, Math.max(2, (plp / TARGET_PCT) * 100)) + '%';
        bar.className = 'plbar-fill ' + (plp >= 0 ? 'up' : 'dn');
      }
    }
  } catch (e) {}
}

// One-click handlers via delegation (cards re-render dynamically).
grid.addEventListener('click', e => {
  const sell = e.target.closest('.sell-click');
  const cancel = e.target.closest('.cancel-click');
  if (sell) {
    const t = sell.dataset.ticker, rec = cardEls[t], c = rec?.data || {};
    const pl = rec?.el.querySelector('.live-pl')?.textContent || '—';
    tradeModal({
      title: `Sell ALL ${t}?`, icon: '💰', danger: true,
      rows: [['Company', NAMES[t] || t], ['Ticker', t],
             ['Shares', c.qty || 0], ['Current P&L', pl],
             ['Order', 'sell entire position @ best ask']],
      note: 'Queues your approval — the engine sells at the next opportunity ' +
            '(market hours), then this slot frees up.',
      okLabel: 'Confirm SELL ALL',
      onConfirm: async () => {
        if (await queueCommand('APPROVE_SELL_ALL', t, CSRF)) {
          PENDING.push({t, a: 'APPROVE_SELL_ALL'}); renderAll();
        } else { alert('Could not queue — try again.'); }
      }
    });
  } else if (cancel) {
    const t = cancel.dataset.ticker;
    tradeModal({
      title: `Cancel ${t} campaign?`, icon: '🗑️', danger: true,
      rows: [['Ticker', t], ['Shares held', '0'],
             ['Effect', 'campaign removed before any buy']],
      note: 'Only possible while no shares are held. The engine confirms shortly.',
      okLabel: 'Cancel campaign',
      onConfirm: async () => {
        if (await queueCommand('CANCEL_CAMPAIGN', t, CSRF)) {
          PENDING.push({t, a: 'CANCEL_CAMPAIGN'}); renderAll();
        } else { alert('Could not queue — try again.'); }
      }
    });
  }
});

renderAll(true);
renderBrokerMarks();
renderEngineHealth();
setInterval(liveQuotes, 10000);
setInterval(syncCampaigns, 10000);
setInterval(renderEngineHealth, 10000);
</script>
</body>
</html>
