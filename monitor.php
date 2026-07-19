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
<link rel="stylesheet" href="assets/css/app.css?v=36">
</head>
<body>
<div class="bg"></div>
<?php $NAV_ACTIVE = 'auto-paper'; require __DIR__ . '/inc/nav.php'; ?>
<!-- Outside .hero: that element animates with transform, which would make fixed positioning relative to the page column. -->
<section class="portfolio-summary" id="portfolioSummary" aria-live="polite">
    <div class="portfolio-summary-head">
      <span class="portfolio-kicker">Moomoo Paper Portfolio</span>
      <span class="portfolio-sync" id="portfolioSync">Waiting for fresh Moomoo marks</span>
    </div>
    <div class="portfolio-metrics">
      <div class="portfolio-metric"><span>Total market value</span><b id="portfolioValue">—</b></div>
      <div class="portfolio-metric portfolio-profit" id="portfolioProfitMetric"><span>Combined profit / loss</span><b id="portfolioProfit">—</b></div>
      <div class="portfolio-metric portfolio-return" id="portfolioReturnMetric"><span>Total return</span><b id="portfolioReturn">—</b></div>
    </div>
    <div class="portfolio-action">
      <span id="portfolioTarget">Sell-all unlocks at the portfolio target</span>
      <button type="button" class="portfolio-sell locked" id="portfolioSellAll" disabled>Sell all positions</button>
    </div>
 </section>
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
const PORTFOLIO_PROFIT_TARGET = <?= (float) ($settings['portfolio_profit_alert_usd'] ?? 500) ?>;
const PORTFOLIO_RETURN_TARGET = <?= (float) ($settings['portfolio_return_alert_pct'] ?? 0.09) * 100 ?>;
const LIMITS = 'limits: +<?= round($settings['profit_alert_pct'] * 100) ?>% alert · −$<?=
    number_format($settings['loss_alert_usd']) ?> / −$<?= number_format($settings['loss_urgent_usd']) ?> or −<?=
    round(($settings['loss_urgent_pct'] ?? 0.10) * 100, 1) ?>% urgent';

let CAMPS = <?= json_encode($campaigns) ?>;
let PENDING = <?= json_encode($pending) ?>;
let MARKS = <?= json_encode($marks) ?>;
let ENGINE_HEALTH = <?= json_encode($engineHealth) ?>;
const MARK_STALE_MS = 35000;
let PORTFOLIO = null;
const grid = document.getElementById('monGrid');
const fmt = (n, d = 2) => Number(n || 0).toLocaleString('en-US',
    {minimumFractionDigits: d, maximumFractionDigits: d});
const quoteMinute = s => {
  const m = String(s || '').match(/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2})/);
  return m ? m[1] : (s || 'timestamp unavailable');
};
const pendingHas = (t, a) => PENDING.some(p => p.t === t && p.a === a);
const showable = c => (c.status === 'ACTIVE' || (c.qty || 0) > 0);
const dateLabel = s => {
  if (!s) return 'Syncing…';
  const d = new Date(String(s).length === 10 ? s + 'T12:00:00' : s);
  return Number.isNaN(d.getTime()) ? s : d.toLocaleDateString('en-US',
    {year:'numeric', month:'short', day:'numeric'});
};
const esc = value => String(value ?? '').replace(/[&<>'"]/g, ch => ({
  '&':'&amp;', '<':'&lt;', '>':'&gt;', "'":'&#39;', '"':'&quot;'
})[ch]);

function historyEventView(event) {
  const status = String(event.status || 'RECORDED').toUpperCase();
  const kind = String(event.kind || 'DCA_REVIEW').toUpperCase();
  const qty = Number(event.qty || 0);
  const labels = {
    FILLED: qty > 0 ? `Bought ${fmt(qty, Number.isInteger(qty) ? 0 : 2)} shares` : 'Filled',
    PARTIAL: qty > 0 ? `Partially filled · ${fmt(qty, Number.isInteger(qty) ? 0 : 2)} shares` : 'Partially filled',
    HOLD: 'Hold · no order', BLOCKED: 'Momentum gate blocked',
    AWAITING_DECISION: 'Review due · awaiting you', APPROVED: 'Keep buying approved',
    UNFILLED: 'Unfilled · no order working', REJECTED: 'Order rejected'
  };
  const title = kind === 'INITIAL_BUY' ? 'Initial buy' : (labels[status] || status.replaceAll('_', ' '));
  const tone = kind === 'INITIAL_BUY' ? 'initial' : ({
    FILLED:'filled', PARTIAL:'filled', HOLD:'hold', BLOCKED:'blocked',
    AWAITING_DECISION:'due', APPROVED:'due', UNFILLED:'blocked', REJECTED:'blocked'
  })[status] || 'recorded';
  const meta = [];
  if (Number(event.price || 0) > 0) meta.push(`$${fmt(event.price)}`);
  if (Number(event.proposed_qty || 0) > 0 && !qty) meta.push(`${fmt(event.proposed_qty, 0)} shares proposed`);
  const reasons = Array.isArray(event.gate_reasons) ? event.gate_reasons.filter(Boolean) : [];
  const detail = event.reason || reasons[0] || (kind === 'INITIAL_BUY'
    ? 'Confirmed campaign-opening fill' : 'Recorded by the Paper trading engine');
  return {title, tone, meta, detail};
}

function dcaHistory(c) {
  const events = Array.isArray(c.dca_history) ? [...c.dca_history] : [];
  events.sort((a, b) => String(b.occurred_at || b.scheduled_date || '')
    .localeCompare(String(a.occurred_at || a.scheduled_date || '')));
  const total = Number(c.dca_history_total ?? events.length);
  if (!events.length) return `<div class="dca-history-empty">
    <span class="dca-pulse"></span>Checkpoint ledger will appear after the next PC-engine sync</div>`;
  const rows = events.map((event, index) => {
    const view = historyEventView(event);
    return `<li class="dca-history-item tone-${view.tone} ${index >= 3 ? 'dca-history-more' : ''}">
      <span class="dca-history-node" aria-hidden="true"></span>
      <div class="dca-history-card">
        <div class="dca-history-top"><time>${esc(dateLabel(event.occurred_at || event.scheduled_date))}</time>
          <span class="dca-history-status">${esc(view.title)}</span></div>
        <p>${esc(view.detail)}</p>
        ${view.meta.length ? `<div class="dca-history-meta">${view.meta.map(esc).join('<i>·</i>')}</div>` : ''}
      </div></li>`;
  }).join('');
  return `<div class="dca-history" data-total="${total}">
    <div class="dca-history-head"><div><span>Checkpoint history</span><b>Latest recorded activity</b></div>
      <em>${total} event${total === 1 ? '' : 's'}</em></div>
    <ol class="dca-history-list">${rows}</ol>
    ${events.length > 3 ? `<button type="button" class="dca-history-toggle" aria-expanded="false">
      <span>View full history · ${events.length} events</span><i aria-hidden="true">⌄</i></button>` : ''}
  </div>`;
}

function dcaPanel(c) {
  if (!(c.qty > 0)) return '';
  const t = c.ticker, due = !!c.dca_due, gate = c.dca_gate || {};
  const eligible = gate.eligible === true && c.dca_status !== 'BLOCKED';
  const riskPaused = !!c.dca_paused || c.dca_status === 'PAUSED_ALERT';
  const approvePending = pendingHas(t, 'APPROVE_DCA');
  const holdPending = pendingHas(t, 'HOLD_DCA');
  const proposed = Number(c.dca_proposed_qty || 0);
  const mode = c.dca_sizing_mode === 'adaptive_recovery'
    ? 'Adaptive recovery · base ± step' : 'Progressive strength · recommended';
  const reasons = (gate.reasons || []).join(' · ');
  let controls = '';
  if (due) {
    if (approvePending || holdPending) {
      controls = `<button class="dca-choice dca-pending" disabled><span class="lockdot"></span>
        ${approvePending ? 'KEEP BUYING queued — engine revalidating' : 'HOLD queued — rescheduling'}</button>`;
    } else {
      controls = `<div class="dca-actions">
        <button class="dca-choice dca-keep dca-buy-click" data-ticker="${t}"
          ${eligible && proposed > 0 ? '' : 'disabled'}>
          <span>Keep buying</span><b>${eligible && proposed > 0
            ? (riskPaused ? `Explicit override · ${proposed} shares` : proposed + ' shares')
            : 'Momentum gate blocked'}</b></button>
        <button class="dca-choice dca-hold dca-hold-click" data-ticker="${t}">
          <span>Hold</span><b>No order · review later</b></button></div>`;
    }
  } else {
    controls = `<div class="dca-waiting"><span class="dca-pulse"></span>
      Monitoring · your decision will be requested on the next checkpoint</div>`;
  }
  return `<section class="dca-panel ${due ? (eligible ? 'dca-due' : 'dca-blocked') : ''}">
    <div class="dca-panel-head"><div><span class="dca-kicker">Advanced DCA checkpoint</span>
      <b>${due ? (riskPaused ? 'Alert review · buying paused' :
        (eligible ? 'Your decision is due' : 'Review required · buying blocked')) : 'Scheduled & monitoring'}</b></div>
      <span class="dca-mode">${mode}</span></div>
    <ol class="dca-timeline" aria-label="${t} campaign timeline">
      <li class="dca-step is-complete"><span class="dca-step-number">1</span><div>
        <span>Campaign created</span><b>${dateLabel(c.campaign_created)}</b>
        <em>Strategy lifecycle begins</em></div></li>
      <li class="dca-step ${c.last_dca_decision_date ? 'is-complete' : ''}"><span class="dca-step-number">2</span><div>
        <span>Last buy / decision</span><b>${dateLabel(c.last_dca_decision_date || c.last_buy_date)}</b>
        <em>${c.last_dca_decision ? String(c.last_dca_decision).replaceAll('_',' ') : 'Latest authorized action'}</em></div></li>
      <li class="dca-step ${due ? 'is-due' : 'is-upcoming'}"><span class="dca-step-number">3</span><div>
        <span>Next DCA review</span><b>${dateLabel(c.next_dca_date)}</b>
        <em>Every ${c.dca_gap_bdays || '—'} business days</em></div></li>
    </ol>
    <div class="dca-meta"><span>Review checkpoints <b>${c.dca_checkpoint_count || 0}</b></span>
      <span>Filled tranches <b>${c.tranche_count || 0} / ${c.max_tranches || '—'}</b></span>
      <span>Status <b>${String(c.dca_status || 'SCHEDULED').replaceAll('_',' ')}</b></span>
      ${c.last_dca_decision ? `<span>Last choice <b>${String(c.last_dca_decision).replaceAll('_',' ')}</b></span>` : ''}</div>
    ${reasons ? `<p class="dca-reason">${esc(reasons)}</p>` : ''}
    ${dcaHistory(c)}${controls}</section>`;
}

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

function setPortfolioMetric(id, value, tone = '') {
  const el = document.getElementById(id);
  if (!el) return;
  const previous = el.textContent;
  el.textContent = value;
  el.classList.remove('portfolio-updated', 'portfolio-up', 'portfolio-down');
  if (previous !== '—' && previous !== value) {
    void el.offsetWidth;
    el.classList.add('portfolio-updated');
  }
  if (tone) el.classList.add(tone);
}

function paperEngineRunning() {
  const h = ENGINE_HEALTH && ENGINE_HEALTH.data;
  const seen = h ? Date.parse(h.last_seen_at || '') : 0;
  return !!(h && h.status === 'running' && h.mode !== 'REAL' && seen &&
            Date.now() - seen <= Number(h.stale_after_seconds || 35) * 1000);
}

function setPortfolioAction(state) {
  const target = document.getElementById('portfolioTarget');
  const button = document.getElementById('portfolioSellAll');
  PORTFOLIO = state;
  button.classList.remove('ready', 'locked');
  if (!state) {
    target.textContent = 'Waiting for complete Moomoo portfolio marks';
    button.textContent = 'Sell all positions'; button.disabled = true; button.classList.add('locked');
    return;
  }
  if (!state.paper) {
    target.textContent = 'Paper bulk sell is unavailable while a non-Paper engine is connected';
    button.textContent = 'Paper engine required'; button.disabled = true; button.classList.add('locked');
    return;
  }
  if (!state.qualified) {
    target.textContent = `Unlocks at +$${fmt(PORTFOLIO_PROFIT_TARGET, 0)} or +${PORTFOLIO_RETURN_TARGET.toFixed(2)}% total return`;
    button.textContent = 'Sell all positions'; button.disabled = true; button.classList.add('locked');
    return;
  }
  target.textContent = 'Portfolio target reached · your decision is required';
  button.textContent = `Sell all ${state.tickers.length} positions`;
  button.disabled = false; button.classList.add('ready');
}

function renderPortfolioSummary() {
  const summary = document.getElementById('portfolioSummary');
  const sync = document.getElementById('portfolioSync');
  const marks = CAMPS.filter(showable).map(c => brokerMark(c.ticker));
  const fresh = marks.filter(m => m.ok).map(m => m.mark);
  if (!fresh.length || fresh.length !== marks.length) {
    summary.classList.add('portfolio-stale');
    sync.textContent = fresh.length ? 'Waiting for all Moomoo marks' : 'Waiting for fresh Moomoo marks';
    setPortfolioMetric('portfolioValue', '—');
    setPortfolioMetric('portfolioProfit', '—');
    setPortfolioMetric('portfolioReturn', '—');
    setPortfolioAction(null);
    return;
  }
  const value = fresh.reduce((sum, q) => sum + Number(q.value || 0), 0);
  const invested = fresh.reduce((sum, q) => sum + Number(q.qty || 0) * Number(q.avg_cost || 0), 0);
  const dividends = CAMPS.filter(showable).reduce(
    (sum, c) => sum + Number(c.confirmed_dividends || 0), 0);
  const pnl = fresh.reduce((sum, q) => sum + Number(q.pnl || 0), 0) + dividends;
  const pct = invested > 0 ? (pnl / invested) * 100 : 0;
  const stamp = fresh.map(q => quoteMinute(q.quote_time)).filter(Boolean).sort().pop();
  summary.classList.remove('portfolio-stale');
  sync.textContent = `${fresh.length} active position${fresh.length === 1 ? '' : 's'} · Moomoo last ${stamp || 'just now'}`;
  setPortfolioMetric('portfolioValue', '$' + fmt(value));
  setPortfolioMetric('portfolioProfit', (pnl >= 0 ? '+$' : '−$') + fmt(Math.abs(pnl)), pnl >= 0 ? 'portfolio-up' : 'portfolio-down');
  setPortfolioMetric('portfolioReturn', (pct >= 0 ? '+' : '') + pct.toFixed(2) + '%', pct >= 0 ? 'portfolio-up' : 'portfolio-down');
  const tickers = CAMPS.filter(showable).filter(c => Number(brokerMark(c.ticker).mark.qty || 0) > 0)
    .map(c => c.ticker).filter(t => !pendingHas(t, 'APPROVE_SELL_ALL'));
  setPortfolioAction({tickers, pnl, pct, qualified: pnl > PORTFOLIO_PROFIT_TARGET || pct > PORTFOLIO_RETURN_TARGET,
                      paper: paperEngineRunning()});
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
        <span class="muted">${NAMES[t] || c.name || ''}</span>
        <span class="campaign-created">Campaign · ${dateLabel(c.campaign_created)}</span></div>
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
    ${dcaPanel(c)}
    ${btn}
    <p class="muted small" style="margin-top:8px">engine sync: ${c.updated_at || '—'} · ${LIMITS}</p>`;
}

function sig(c) {   // structural signature: when this changes, morph the card
  return [c.qty, c.status, c.invested, c.avg_cost,
          c.campaign_created, c.last_buy_date, c.last_dca_decision_date,
          c.dca_status, c.dca_due, c.next_dca_date, c.dca_gap_bdays,
          c.dca_proposed_qty, c.dca_sizing_mode, c.tranche_count, c.max_tranches,
          c.risk_state, c.dca_paused, c.confirmed_dividends, c.total_return,
          c.last_dca_decision, c.dca_checkpoint_count, c.dca_history_total,
          JSON.stringify(c.dca_gate || {}), JSON.stringify(c.dca_history || []),
          pendingHas(c.ticker, 'APPROVE_SELL_ALL'),
          pendingHas(c.ticker, 'APPROVE_DCA'), pendingHas(c.ticker, 'HOLD_DCA'),
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
      const historyWasExpanded = rec.el.querySelector('.dca-history')?.classList.contains('is-expanded');
      rec.el.classList.add('card-morph');
      setTimeout(() => {
        rec.el.innerHTML = cardHTML(c);
        if (historyWasExpanded) {
          const history = rec.el.querySelector('.dca-history');
          if (history) {
            history.classList.add('is-expanded');
            const toggle = history.querySelector('.dca-history-toggle');
            if (toggle) {
              toggle.setAttribute('aria-expanded', 'true');
              toggle.querySelector('span').textContent = 'Show latest 3';
            }
          }
        }
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
      renderPortfolioSummary();
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
    const dividends = Number(rec.data?.confirmed_dividends || 0);
    const old = parseFloat(px.textContent.replace(/[$,]/g, ''));
    px.textContent = '$' + fmt(q.price);
    if (old && Math.abs(old - q.price) > 0.004) {
      px.classList.remove('flash-up', 'flash-dn'); void px.offsetWidth;
      px.classList.add(q.price > old ? 'flash-up' : 'flash-dn');
    }
    const pl = Number(q.pnl) + dividends;
    const invested = Number(q.qty || 0) * avg;
    const plp = invested > 0 ? (pl / invested) * 100 : 0;
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
      (dividends ? ` · confirmed dividends +$${fmt(dividends)}` : '') + ' · ' + LIMITS;
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
  const historyToggle = e.target.closest('.dca-history-toggle');
  if (historyToggle) {
    const history = historyToggle.closest('.dca-history');
    const expanded = history.classList.toggle('is-expanded');
    historyToggle.setAttribute('aria-expanded', String(expanded));
    historyToggle.querySelector('span').textContent = expanded
      ? 'Show latest 3' : `View full history · ${history.querySelectorAll('.dca-history-item').length} events`;
    return;
  }
  const sell = e.target.closest('.sell-click');
  const cancel = e.target.closest('.cancel-click');
  const dcaBuy = e.target.closest('.dca-buy-click');
  const dcaHold = e.target.closest('.dca-hold-click');
  if (dcaBuy) {
    const t = dcaBuy.dataset.ticker, c = cardEls[t]?.data || {};
    tradeModal({
      title: `Keep buying ${t}?`, icon: '↗',
      rows: [['Campaign created', dateLabel(c.campaign_created)],
             ['DCA checkpoint', dateLabel(c.next_dca_date)],
             ['Proposed tranche', `${c.dca_proposed_qty || 0} shares`],
             ['Sizing', c.dca_sizing_mode === 'adaptive_recovery' ? 'Adaptive recovery' : 'Progressive strength'],
             ['Authorization', 'This checkpoint only']],
      note: 'The PC engine re-checks momentum, earnings timing, drawdown, budget, spread and Moomoo price before placing one bounded Paper order. If any gate fails, no order is sent.',
      okLabel: `Confirm KEEP BUYING ${t}`,
      onConfirm: async () => {
        if (await queueCommand('APPROVE_DCA', t, CSRF)) {
          PENDING.push({t, a: 'APPROVE_DCA'}); renderAll();
        } else { alert('Could not queue the DCA decision — try again.'); }
      }
    });
  } else if (dcaHold) {
    const t = dcaHold.dataset.ticker, c = cardEls[t]?.data || {};
    tradeModal({
      title: `Hold ${t} at this checkpoint?`, icon: 'Ⅱ',
      rows: [['DCA checkpoint', dateLabel(c.next_dca_date)], ['Order', 'No order'],
             ['Effect', `Schedule another review after ${c.dca_gap_bdays || '—'} business days`]],
      note: 'HOLD keeps the existing Paper position unchanged. Monitoring, alerts and your manual SELL control continue normally.',
      okLabel: `Confirm HOLD ${t}`,
      onConfirm: async () => {
        if (await queueCommand('HOLD_DCA', t, CSRF)) {
          PENDING.push({t, a: 'HOLD_DCA'}); renderAll();
        } else { alert('Could not queue HOLD — try again.'); }
      }
    });
  } else if (sell) {
    const t = sell.dataset.ticker, rec = cardEls[t], c = rec?.data || {};
    const pl = rec?.el.querySelector('.live-pl')?.textContent || '—';
    tradeModal({
      title: `Sell ALL ${t}?`, icon: '💰', danger: true,
      rows: [['Company', NAMES[t] || t], ['Ticker', t],
             ['Shares', c.qty || 0], ['Current P&L', pl],
             ['Order', 'bounded ask → midpoint → bid limit ladder']],
      note: 'Queues your approval — during market hours the engine prioritizes a controlled exit inside the configured spread and slippage collars, cancels every unfilled remainder, then frees the slot after the broker confirms the fill.',
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

document.getElementById('portfolioSellAll').addEventListener('click', () => {
  const p = PORTFOLIO;
  if (!p || !p.qualified || !p.paper || !p.tickers.length) return;
  tradeModal({
    title: 'Sell the whole Paper portfolio?', icon: '💼', danger: true,
    rows: [['Positions', p.tickers.join(', ')], ['Combined P/L', (p.pnl >= 0 ? '+$' : '−$') + fmt(Math.abs(p.pnl))],
           ['Total return', (p.pct >= 0 ? '+' : '') + p.pct.toFixed(2) + '%'],
           ['Orders', `${p.tickers.length} separate Moomoo Paper sell orders`]],
    note: 'This queues one sell-all command for each current Paper position. Orders are submitted separately by the PC engine, so they are not an atomic broker order.',
    okLabel: `Confirm SELL ALL ${p.tickers.length} positions`,
    onConfirm: async () => {
      const results = await Promise.all(p.tickers.map(t => queueCommand('APPROVE_SELL_ALL', t, CSRF)));
      if (results.every(Boolean)) {
        p.tickers.forEach(t => PENDING.push({t, a: 'APPROVE_SELL_ALL'}));
        renderAll(); renderPortfolioSummary();
      } else {
        alert('Some sell commands could not be queued. Check the individual positions before trying again.');
      }
    }
  });
});

renderAll(true);
renderBrokerMarks();
renderPortfolioSummary();
renderEngineHealth();
setInterval(liveQuotes, 10000);
setInterval(syncCampaigns, 10000);
setInterval(renderPortfolioSummary, 10000);
setInterval(renderEngineHealth, 10000);
</script>
</body>
</html>
