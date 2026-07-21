<?php if (!defined('APP')) { http_response_code(403); exit('Forbidden'); }
$isLive = $ACCOUNT_KIND === 'live';
$accountDoc = doc_get($isLive ? 'account_live' : 'account_paper');
$accountTitle = $isLive ? 'Account Info · Live' : 'Account Info · Paper';
$accountLabel = $isLive ? 'LIVE ACCOUNT · READ ONLY' : 'PAPER ACCOUNT · MOO MOO SIM';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $accountTitle ?> — Trading AI Horizon</title>
<link rel="icon" type="image/png" href="favicon.png?v=2">
<link rel="stylesheet" href="assets/css/app.css?v=41">
</head>
<body>
<div class="bg"></div>
<?php require __DIR__ . '/nav.php'; ?>
<main class="hero account-page">
  <div class="badge"><span class="livedot"></span> <?= $accountLabel ?></div>
  <h1 class="pagetitle"><?= $accountTitle ?></h1>
  <p class="account-subtitle">Moomoo account balances, published read-only from your PC engine.</p>

  <section class="card account-hero" id="accountHero">
    <div class="account-hero-top">
      <div><span class="account-eyebrow">Net Assets</span><strong class="account-net" data-field="net_assets">—</strong>
        <span class="account-currency" data-field="currency">USD</span></div>
      <div class="account-state" id="accountState">Waiting for Moomoo OpenD</div>
    </div>
    <div class="account-main-grid">
      <div class="account-stat"><span>Max Buying Power</span><b data-field="buying_power">—</b></div>
      <div class="account-stat"><span>Cash</span><b data-field="cash">—</b></div>
      <div class="account-stat"><span>Market Value</span><b data-field="market_value">—</b></div>
      <div class="account-stat"><span>Cash Withdrawable</span><b data-field="withdrawable_cash">—</b></div>
    </div>
  </section>

  <section class="account-detail-grid" id="accountDetails">
    <div class="card account-detail"><span>Long Positions MV</span><b data-field="long_market_value">—</b></div>
    <div class="card account-detail"><span>Short Positions MV</span><b data-field="short_market_value">—</b></div>
    <div class="card account-detail"><span>Frozen Cash</span><b data-field="frozen_cash">—</b></div>
    <div class="card account-detail"><span>Pending Assets</span><b data-field="pending_assets">—</b></div>
    <div class="card account-detail"><span>Unrealized P/L</span><b data-field="unrealized_pnl">—</b></div>
    <div class="card account-detail"><span>Realized P/L</span><b data-field="realized_pnl">—</b></div>
  </section>

  <p class="account-note" id="accountNote">No account snapshot has arrived from the PC engine yet.</p>
  <footer class="foot">Read-only account information · no trade unlock · no orders from this page</footer>
  <?php require __DIR__ . '/brand_footer.php'; ?>
</main>
<script>
const KIND = '<?= $ACCOUNT_KIND ?>';
let SNAPSHOT = <?= json_encode($accountDoc) ?>;
const STALE_MS = 45000;
const moneyFields = new Set(['net_assets', 'buying_power', 'cash', 'market_value',
  'withdrawable_cash', 'long_market_value', 'short_market_value', 'frozen_cash',
  'pending_assets', 'unrealized_pnl', 'realized_pnl']);
const fmtMoney = n => n == null ? '—' : new Intl.NumberFormat('en-US',
  {style: 'currency', currency: 'USD', minimumFractionDigits: 2}).format(Number(n));
const minute = s => {
  const d = new Date(s || '');
  return Number.isNaN(d.valueOf()) ? 'timestamp unavailable' :
    d.toISOString().slice(0, 16).replace('T', ' ') + ' UTC';
};

function renderAccount(doc, animate = false) {
  const data = doc && doc.data;
  const state = document.getElementById('accountState');
  const note = document.getElementById('accountNote');
  const hero = document.getElementById('accountHero');
  if (!data) {
    state.textContent = 'Waiting for Moomoo OpenD'; state.className = 'account-state stale';
    note.textContent = 'No account snapshot has arrived from the PC engine yet.';
    return;
  }
  const age = Date.now() - Date.parse(data.published_at || '');
  const stale = !Number.isFinite(age) || age > STALE_MS;
  document.querySelectorAll('[data-field]').forEach(el => {
    const field = el.dataset.field, value = data[field];
    el.textContent = moneyFields.has(field) ? fmtMoney(value) : (value || 'USD');
    if (field.endsWith('_pnl')) el.className = value == null ? '' : (value >= 0 ? 'positive' : 'negative');
  });
  state.textContent = stale ? 'Moomoo feed stale' : 'Moomoo OpenD · synced';
  state.className = 'account-state ' + (stale ? 'stale' : 'fresh');
  note.textContent = `${data.source || 'Moomoo OpenD'} · account snapshot ${minute(data.published_at)} · ${stale ? 'values may be stale' : 'read-only'}`;
  if (animate) { hero.classList.remove('account-updated'); void hero.offsetWidth; hero.classList.add('account-updated'); }
}

async function refreshAccount() {
  try {
    const r = await fetch('api/accounts.php');
    if (!r.ok) throw new Error('account feed unavailable');
    const j = await r.json();
    const next = j[KIND] || null;
    const changed = next && next.data && (!SNAPSHOT || next.data.published_at !== SNAPSHOT.data?.published_at);
    SNAPSHOT = next; renderAccount(SNAPSHOT, changed);
  } catch (e) {
    document.getElementById('accountState').textContent = 'Account feed unavailable';
    document.getElementById('accountState').className = 'account-state stale';
  }
}
renderAccount(SNAPSHOT);
refreshAccount();
setInterval(refreshAccount, 15000);
</script>
</body>
</html>
