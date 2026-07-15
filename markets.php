<?php
define('APP', 1);
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/engine.php';
require_login();

$mkt = doc_get('market_table');
$rows = $mkt['data']['rows'] ?? [];
$builtAt = $mkt['data']['built_at'] ?? null;
$NAV_ACTIVE = 'mkt';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Markets — Trading AI Horizon</title>
<link rel="icon" type="image/png" href="favicon.png?v=2">
<link rel="stylesheet" href="assets/css/app.css?v=21">
</head>
<body>
<div class="bg"></div>
<?php require __DIR__ . '/inc/nav.php'; ?>
<main class="hero" style="max-width:1100px">
  <div class="badge"><span class="livedot"></span> ETF UNIVERSE · DOW 30 + NASDAQ-100 + S&amp;P 500 · RESEARCH</div>
  <h1 class="pagetitle" style="font-size:32px">Markets</h1>

  <?php if (!$rows): ?>
    <section class="card"><h2>No market data yet</h2>
      <p class="muted">The engine service refreshes this table daily, or run
      <code>python -m data.market_table</code> once on your PC.</p></section>
  <?php else: ?>
  <section class="card mkt-card">
    <div class="mkt-tools">
      <input class="in mkt-search" id="mktSearch" type="search"
             placeholder="Search ticker or company…  (e.g. NVDA, Coca-Cola)">
      <span class="muted small"><b id="mktCount"><?= count($rows) ?></b> holdings ·
        data as of <?= htmlspecialchars($builtAt ?? '—') ?></span>
    </div>
    <div class="mkt-wrap">
      <table class="mkt" id="mktTable">
        <thead><tr>
          <th data-k="t" class="sortable">Ticker</th>
          <th data-k="n" class="sortable left">Company</th>
          <th data-k="p" class="sortable num">Price</th>
          <th data-k="c" class="sortable num">Chg %</th>
          <th data-k="ma50" class="sortable num">MA50</th>
          <th data-k="ma200" class="sortable num">MA200</th>
          <th data-k="pe" class="sortable num">P/E</th>
          <th data-k="pb" class="sortable num">P/B</th>
          <th data-k="mc" class="sortable num">Mkt Cap</th>
          <th data-k="hi" class="sortable num">52wk High</th>
          <th data-k="lo" class="sortable num">52wk Low</th>
        </tr></thead>
        <tbody id="mktBody"></tbody>
      </table>
    </div>
    <p class="muted small" id="marketFeed" style="margin-top:10px">Visible prices refresh
      best-effort every ~12s from Yahoo Finance public data; not broker-real-time.
      P/E and market cap are re-derived from that price. MA/52wk/P&#8203;/B refresh daily.
      "—" = fundamental unavailable from the free feed.</p>
  </section>
  <?php endif; ?>
  <footer class="foot">the same universe the AI screener hunts in</footer>
  <?php require __DIR__ . '/inc/brand_footer.php'; ?>
</main>
<script>
const ROWS = <?= json_encode($rows) ?>;
const body = document.getElementById('mktBody');
const fmt = (n, d = 2) => n == null ? '—'
    : Number(n).toLocaleString('en-US', {minimumFractionDigits: d, maximumFractionDigits: d});
const mcap = v => v == null ? '—'
    : v >= 1e12 ? (v / 1e12).toFixed(2) + 'T'
    : v >= 1e9 ? (v / 1e9).toFixed(1) + 'B' : (v / 1e6).toFixed(0) + 'M';

let sortKey = 'mc', sortDir = -1, query = '';

function render() {
  const q = query.toLowerCase();
  let rows = ROWS.filter(r => !q || r.t.toLowerCase().includes(q)
                              || (r.n || '').toLowerCase().includes(q));
  rows.sort((a, b) => {
    const av = a[sortKey], bv = b[sortKey];
    if (av == null) return 1; if (bv == null) return -1;
    return (typeof av === 'string' ? av.localeCompare(bv) : av - bv) * sortDir;
  });
  document.getElementById('mktCount').textContent = rows.length;
  liveSet = rows.slice(0, 30).map(r => r.t);           // live-update the rows in view
  body.innerHTML = rows.map((r, i) => {
    const chgCls = r.c >= 0 ? 'ok' : 'bad';
    const above = r.p >= r.ma50 ? 'ok' : 'bad';
    const nearHi = r.hi && r.p >= r.hi * 0.95;
    return `<tr class="mrowa" data-t="${r.t}" style="animation-delay:${Math.min(i, 25) * 18}ms">
      <td class="tkc"><b>${r.t}</b>${nearHi ? ' <span class="hi52" title="within 5% of 52wk high">▲</span>' : ''}</td>
      <td class="left name">${r.n || ''}</td>
      <td class="num m-p"><b>$${fmt(r.p)}</b></td>
      <td class="num m-c ${chgCls}">${r.c >= 0 ? '+' : ''}${fmt(r.c)}%</td>
      <td class="num ${above}">$${fmt(r.ma50)}</td>
      <td class="num">$${fmt(r.ma200)}</td>
      <td class="num m-pe">${fmt(r.pe, 1)}</td>
      <td class="num">${fmt(r.pb)}</td>
      <td class="num m-mc">${mcap(r.mc)}</td>
      <td class="num">$${fmt(r.hi)}</td>
      <td class="num">$${fmt(r.lo)}</td></tr>`;
  }).join('');
  body.querySelectorAll('tr[data-t] .tkc b').forEach(b => {
    const t = b.closest('tr').dataset.t;
    const a = document.createElement('a');
    a.className = 'stock-link';
    a.href = 'https://finance.yahoo.com/quote/' + encodeURIComponent(t);
    a.target = '_blank';
    a.rel = 'noopener noreferrer';
    a.title = 'Open ' + t + ' on Yahoo Finance';
    b.replaceWith(a); a.appendChild(b);
  });
  liveTick();                                          // refresh new view immediately
}

// ---- best-effort Yahoo reference-price sync for the rows in view (~12s) ----
let liveSet = [];
const BASE = Object.fromEntries(ROWS.map(r => [r.t, r]));
const marketFeed = document.getElementById('marketFeed');
async function liveTick() {
  if (!liveSet.length) return;
  try {
    const r = await fetch('api/market_quotes.php?t=' + liveSet.join(','));
    if (!r.ok) throw new Error('quote endpoint unavailable');
    const j = await r.json();
    for (const [t, q] of Object.entries(j.quotes || {})) {
      const tr = body.querySelector(`tr[data-t="${t}"]`);
      const base = BASE[t];
      if (!tr || !base) continue;
      const pEl = tr.querySelector('.m-p b');
      const old = parseFloat(pEl.textContent.replace(/[$,]/g, ''));
      pEl.textContent = '$' + fmt(q.price);
      if (old && Math.abs(old - q.price) > 0.004) {
        pEl.classList.remove('flash-up', 'flash-dn'); void pEl.offsetWidth;
        pEl.classList.add(q.price > old ? 'flash-up' : 'flash-dn');
      }
      const cEl = tr.querySelector('.m-c');
      cEl.textContent = (q.chg_pct >= 0 ? '+' : '') + fmt(q.chg_pct) + '%';
      cEl.className = 'num m-c ' + (q.chg_pct >= 0 ? 'ok' : 'bad');
      if (base.pe && base.p) {                          // re-derive from live price
        tr.querySelector('.m-pe').textContent = fmt(base.pe * q.price / base.p, 1);
      }
      if (base.mc && base.p) {
        tr.querySelector('.m-mc').textContent = mcap(base.mc * q.price / base.p);
      }
    }
    if (marketFeed) marketFeed.textContent = j.partial
      ? 'Yahoo reference quote update was partial — some rows retain the daily snapshot.'
      : 'Yahoo Finance reference prices updated · not broker-real-time.';
  } catch (e) {
    if (marketFeed) marketFeed.textContent =
      'Yahoo reference quote refresh unavailable — showing the daily research snapshot.';
  }
}
setInterval(liveTick, 12000);
document.querySelectorAll('th.sortable').forEach(th => {
  th.addEventListener('click', () => {
    const k = th.dataset.k;
    sortDir = (sortKey === k) ? -sortDir : (k === 't' || k === 'n' ? 1 : -1);
    sortKey = k;
    document.querySelectorAll('th.sortable').forEach(h => h.classList.remove('on-asc', 'on-desc'));
    th.classList.add(sortDir === 1 ? 'on-asc' : 'on-desc');
    render();
  });
});
const search = document.getElementById('mktSearch');
if (search) search.addEventListener('input', e => { query = e.target.value; render(); });
if (body) render();
</script>
</body>
</html>
