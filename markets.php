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
<link rel="stylesheet" href="assets/css/app.css?v=12">
</head>
<body>
<div class="bg"></div>
<?php require __DIR__ . '/inc/nav.php'; ?>
<main class="hero" style="max-width:1100px">
  <div class="badge">ETF UNIVERSE · DOW 30 + NASDAQ-100 + S&amp;P 500</div>
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
    <p class="muted small" style="margin-top:10px">Price/MA/52wk computed from daily
      history; P/E, P/B and market cap from Yahoo Finance (— when unavailable).
      Refreshed daily by the engine service.</p>
  </section>
  <?php endif; ?>
  <footer class="foot">the same universe the AI screener hunts in</footer>
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
  body.innerHTML = rows.map((r, i) => {
    const chgCls = r.c >= 0 ? 'ok' : 'bad';
    const above = r.p >= r.ma50 ? 'ok' : 'bad';
    const nearHi = r.hi && r.p >= r.hi * 0.95;
    return `<tr class="mrowa" style="animation-delay:${Math.min(i, 25) * 18}ms">
      <td class="tkc"><b>${r.t}</b>${nearHi ? ' <span class="hi52" title="within 5% of 52wk high">▲</span>' : ''}</td>
      <td class="left name">${r.n || ''}</td>
      <td class="num"><b>$${fmt(r.p)}</b></td>
      <td class="num ${chgCls}">${r.c >= 0 ? '+' : ''}${fmt(r.c)}%</td>
      <td class="num ${above}">$${fmt(r.ma50)}</td>
      <td class="num">$${fmt(r.ma200)}</td>
      <td class="num">${fmt(r.pe, 1)}</td>
      <td class="num">${fmt(r.pb)}</td>
      <td class="num">${mcap(r.mc)}</td>
      <td class="num">$${fmt(r.hi)}</td>
      <td class="num">$${fmt(r.lo)}</td></tr>`;
  }).join('');
}
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
