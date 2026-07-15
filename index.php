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

// Slots: buying locks when max_concurrent campaigns are running or queued.
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
$activeCount = count($running) + count(array_filter($queued,
    fn($q) => !empty($q['APPROVE_BUY'])));
$slotsFull = $activeCount >= $maxConcurrent;
$analysisQueued = !empty($queued['ALL']['RUN_ANALYSIS']);
// Show an AI-failure card only if the error is NEWER than the last good pick.
$anErr = doc_get('analysis_error');
$showErr = $anErr && (!$pick || ($anErr['updated_at'] > $pick['updated_at']));
$NAV_ACTIVE = 'dash';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Trading AI Horizon — Dashboard</title>
<link rel="icon" type="image/png" href="favicon.png?v=2">
<link rel="stylesheet" href="assets/css/app.css?v=22">
</head>
<body>
<div class="bg"></div>
<?php require __DIR__ . '/inc/nav.php'; ?>
<main class="hero wide">
  <?php $top10 = array_slice($candDoc['data'] ?? [], 0, 10);
        if ($top10): ?>
  <div class="ticker" title="Yahoo Finance reference prices; refreshes without reloading this page"><div class="ticker-track">
    <?php foreach ([0, 1] as $copy): ?>
      <?php foreach ($top10 as $c): $t = htmlspecialchars($c['ticker']);
            $yahoo = 'https://finance.yahoo.com/quote/' . rawurlencode($c['ticker']); ?>
      <span class="tk"><a class="stock-link" href="<?= $yahoo ?>" target="_blank" rel="noopener noreferrer"
          title="Open <?= $t ?> on Yahoo Finance"><b><?= $t ?></b></a>
        <span class="tk-px" data-t="<?= $t ?>">$<?= number_format((float) $c['price'], 2) ?></span>
        <em class="tk-chg" data-t="<?= $t ?>"></em></span>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </div></div>
  <?php endif; ?>

  <div class="badge">MOMENTUM · <?= $activeCount ?>/<?= $maxConcurrent ?> SLOTS IN USE</div>
  <h1 class="pagetitle" style="font-size:32px">Dashboard</h1>

  <?php if ($analysisQueued): ?>
    <button class="analyze-btn locked" disabled><span class="lockdot"></span>
      Analysis queued — engine is working…</button>
  <?php else: ?>
    <button class="analyze-btn" id="analyzeBtn">
      <span class="ab-spark">✦</span> Analyze &amp; Pick Top 3 — AI powered
      <em>screens 500+ stocks · buzz &amp; trends · <?= htmlspecialchars($settings['ai_model']) ?></em>
    </button>
  <?php endif; ?>

  <?php if ($showErr): $ae = $anErr['data']; ?>
  <section class="card" style="border-color:rgba(248,113,113,.4)">
    <h2 class="bad">AI analysis failed — no pick was produced (no silent fallback)</h2>
    <p class="muted" style="margin:8px 0"><b>Stage:</b> <?= htmlspecialchars($ae['stage'] ?? '?') ?>
      &nbsp;·&nbsp; <b>Reason:</b> <?= htmlspecialchars($ae['reason'] ?? '?') ?></p>
    <p class="muted small">Let's fix it together — check these:</p>
    <ul class="muted small" style="text-align:left; margin:6px 0 0 18px; line-height:1.8">
      <?php foreach (($ae['hints'] ?? []) as $h): ?>
        <li><?= htmlspecialchars($h) ?></li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>

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
            if ($isChosen && !empty($p['rationale'])) { $analysis = $p['rationale']; }
            $sig = $t['signals'] ?? []; ?>
        <div class="tile pick-tile selectable <?= $isChosen ? 'chosen selected' : '' ?>"
             data-ticker="<?= $tk ?>" role="button" tabindex="0"
             data-analysis="<?= htmlspecialchars($analysis) ?>"
             data-basis="<?= htmlspecialchars($t['score_basis'] ?? '') ?>"
             data-score="<?= htmlspecialchars($t['score']) ?>"
             data-signals="<?= htmlspecialchars(json_encode($sig)) ?>">
          <?php if ($isChosen): ?><span class="crown">★ TOP CHOICE</span><?php endif; ?>
          <span><?= $tk ?> <em class="muted"><?= htmlspecialchars($names[$tk] ?? '') ?></em></span>
          <b><?= htmlspecialchars($t['score']) ?></b>
          <p class="muted small"><?= htmlspecialchars($t['reason']) ?></p>
          <?php if ($isRunning): ?>
            <button class="btn buybtn locked" disabled><span class="lockdot"></span>
              Auto-trading active — manage it on Monitor</button>
          <?php elseif ($isQueued): ?>
            <button class="btn buybtn locked" disabled><span class="lockdot"></span>
              Order queued — activating on Monitor shortly</button>
          <?php elseif ($slotsFull): ?>
            <button class="btn buybtn locked" disabled>
              All <?= $maxConcurrent ?> slots in use — sell a position to free one</button>
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
          <em class="muted small">why / how / trend / risk / score</em></span>
        <svg class="chev" viewBox="0 0 24 24" width="18" height="18">
          <path d="M7 10l5 5 5-5" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round"/></svg>
      </button>
      <div class="ai-panel-wrap"><div class="ai-panel-body" id="aiPanelBody"></div></div>
    </div>

    <p class="muted small">Click a card to read its analysis and score breakdown.
      A BUY creates the campaign behind the scenes — once the order is placed on
      Moomoo, auto-trading activates and the position appears on
      <a href="monitor.php" style="color:var(--brand2)">Monitor</a>. Budgets:
      $<?= number_format($settings['budget_usd'] / max(1, $maxConcurrent), 0) ?>/stock,
      $<?= number_format($settings['budget_usd'], 0) ?> global. Nothing trades without your click.</p>
  </section>
  <?php else: ?>
    <section class="card"><h2>No AI pick yet</h2>
      <p class="muted">Press <b>Analyze &amp; Pick Top 3</b> above (engine service must
      be running: <code>python runner/service.py</code>).</p></section>
  <?php endif; ?>

  <footer class="foot">Trading AI Horizon · momentum engine · you approve, it executes</footer>
  <?php require __DIR__ . '/inc/brand_footer.php'; ?>
</main>

<!-- Analyze overlay -->
<div class="an-overlay" id="anOverlay" aria-hidden="true">
  <div class="an-box">
    <svg class="an-logo" width="54" height="54" viewBox="0 0 48 48">
      <defs><linearGradient id="anhz" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0" stop-color="#6366f1"/><stop offset="1" stop-color="#22d3ee"/></linearGradient></defs>
      <rect x="3" y="3" width="42" height="42" rx="12" fill="none" stroke="url(#anhz)" stroke-width="2"/>
      <circle cx="24" cy="27" r="7" fill="url(#anhz)"/>
      <line x1="12" y1="33" x2="36" y2="33" stroke="#0b0f1a" stroke-width="3"/>
    </svg>
    <h3>AI analysis in progress</h3>
    <p class="an-stage" id="anStage">Contacting engine…</p>
    <div class="an-track"><div class="an-fill" id="anFill"></div></div>
    <p class="an-pct" id="anPct">0%</p>
    <p class="muted small" id="anHint">Screening the full universe, scanning buzz,
      and asking the AI to rank — usually 1–3 minutes.</p>
  </div>
</div>

<?php require __DIR__ . '/inc/modal.php'; ?>
<script>
const CSRF = '<?= $csrf ?>';
const TRANCHE = <?= (int) $settings['tranche_base'] ?>;

// ---- Yahoo reference ticker: updates in place; Monitor broker marks stay separate. ----
const tickers = [...new Set([...document.querySelectorAll('.tk-px')].map(e => e.dataset.t))];
async function refreshQuotes() {
  if (!tickers.length) return;
  try {
    const r = await fetch('api/market_quotes.php?t=' + tickers.join(','));
    if (!r.ok) throw new Error('quote endpoint unavailable');
    const j = await r.json();
    for (const [t, q] of Object.entries(j.quotes || {})) {
      document.querySelectorAll(`.tk-px[data-t="${t}"]`).forEach(e => {
        const old = parseFloat(e.textContent.slice(1));
        e.textContent = '$' + q.price.toFixed(2);
        if (old && old !== q.price) {
          e.classList.remove('flash-up', 'flash-dn'); void e.offsetWidth;
          e.classList.add(q.price > old ? 'flash-up' : 'flash-dn');
        }
      });
      document.querySelectorAll(`.tk-chg[data-t="${t}"]`).forEach(e => {
        e.textContent = (q.chg_pct >= 0 ? '+' : '') + q.chg_pct.toFixed(2) + '%';
        e.className = 'tk-chg ' + (q.chg_pct >= 0 ? 'ok' : 'bad');
      });
    }
  } catch (e) {}
}
refreshQuotes();
setInterval(refreshQuotes, 10000);

// ---- pick cards: select -> analysis + score breakdown ----
const panel = document.getElementById('aiPanel');
function renderPanel(card) {
  const body = document.getElementById('aiPanelBody');
  document.getElementById('aiPanelTicker').textContent = card.dataset.ticker;
  let sig = {};
  try { sig = JSON.parse(card.dataset.signals || '{}'); } catch (e) {}
  const label = {price: 'Price', avg_ma_slope_yr: 'Avg MA slope /yr',
                 news_articles_14d: 'News articles (14d)', reddit_mentions: 'Reddit mentions',
                 buzz_score: 'Buzz score', google_trends: 'Trends momentum'};
  const fmtV = (k, v) => v == null ? '—'
      : k === 'price' ? '$' + Number(v).toFixed(2)
      : k === 'avg_ma_slope_yr' ? (v >= 0 ? '+' : '') + (v * 100).toFixed(0) + '%'
      : k === 'google_trends' ? Number(v).toFixed(2) + 'x' : v;
  const sigHtml = Object.keys(label).map(k =>
      `<div class="sigcell"><span>${label[k]}</span><b>${fmtV(k, sig[k])}</b></div>`).join('');
  const basis = card.dataset.basis
      ? `<div class="basis"><b>How the score of ${card.dataset.score} was built:</b> ` +
        `${card.dataset.basis}</div>` : '';
  body.innerHTML =
      `<div class="siggrid">${sigHtml}</div>${basis}` + mdReport(card.dataset.analysis);
}

// Render the analyst report: "HEADING:" lines become section titles, prose flows.
function mdReport(text) {
  const esc = s => s.replace(/&/g, '&amp;').replace(/</g, '&lt;');
  return text.split(/\n+/).map(line => {
    const m = line.match(/^\s*([A-Z][A-Z &()\/0-9–-]{3,40}):\s*(.*)$/);
    if (m) {
      return `<h4 class="rpt-h">${esc(m[1])}</h4>` + (m[2] ? `<p>${esc(m[2])}</p>` : '');
    }
    return line.trim() ? `<p>${esc(line)}</p>` : '';
  }).join('');
}
if (panel) {
  document.getElementById('aiPanelHead').addEventListener('click',
    () => panel.classList.toggle('open'));
  const cards = document.querySelectorAll('.pick-tile.selectable');
  cards.forEach(card => {
    const select = () => {
      document.querySelectorAll('.pick-tile.selected')
        .forEach(c => c.classList.remove('selected'));
      card.classList.add('selected');
      const body = document.getElementById('aiPanelBody');
      body.classList.add('swapping');
      setTimeout(() => {
        renderPanel(card);
        body.classList.remove('swapping');
        panel.classList.add('open');
      }, 180);
    };
    card.addEventListener('click', e => { if (!e.target.closest('button')) select(); });
    card.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); select(); }
    });
  });
  const chosen = document.querySelector('.pick-tile.chosen') || cards[0];
  if (chosen) renderPanel(chosen);
}

// ---- one-click BUY ----
document.querySelectorAll('.buy-click').forEach(btn => {
  btn.addEventListener('click', () => {
    const t = btn.dataset.ticker;
    const px = document.querySelector(`.tk-px[data-t="${t}"]`)?.textContent || '—';
    tradeModal({
      title: `Buy ${t}?`, icon: '🚀',
      rows: [['Company', btn.dataset.name], ['Ticker', t],
             ['First tranche', TRANCHE + ' shares @ best bid'], ['Last price', px],
             ['Then', 'DCA autopilot until the stock budget is spent']],
      note: 'On confirm: a campaign is created and the order goes to Moomoo. Once ' +
            'placed, auto-trading is officially ACTIVE — monitor it and sell any ' +
            'time on the Monitor page. Loss alerts and budget caps stay enforced.',
      okLabel: 'Confirm BUY',
      onConfirm: async () => {
        if (await queueCommand('APPROVE_BUY', t, CSRF)) {
          lockButton(btn, 'Order queued — activating on Monitor shortly');
        } else { alert('Could not queue — try again.'); }
      }
    });
  });
});

// ---- Analyze overlay ----
const anBtn = document.getElementById('analyzeBtn');
if (anBtn) anBtn.addEventListener('click', async () => {
  const ov = document.getElementById('anOverlay');
  const fill = document.getElementById('anFill');
  const pct = document.getElementById('anPct');
  const stage = document.getElementById('anStage');
  const stages = [[4, 'Contacting engine…'], [15, 'Screening 500+ stocks — price & trend rules'],
                  [35, 'Scanning news & social buzz (free sources)'],
                  [50, 'AI pass 1 — shortlisting the strongest candidates…'],
                  [64, 'Deep due diligence: financials, insiders, earnings calendar…'],
                  [82, 'AI pass 2 — writing the analyst report…'],
                  [93, 'Publishing the new Top 3…']];
  let before = null;
  try { before = (await (await fetch('api/docmeta.php?k=daily_pick')).json()).updated_at; }
  catch (e) {}
  if (!(await queueCommand('RUN_ANALYSIS', 'ALL', CSRF))) { alert('Could not start.'); return; }
  ov.classList.add('open');
  const t0 = Date.now();
  let done = false;
  const anim = setInterval(() => {
    const sec = (Date.now() - t0) / 1000;
    const target = Math.min(94, 4 + 90 * (1 - Math.exp(-sec / 55)));
    fill.style.width = target + '%';
    pct.textContent = Math.round(target) + '%';
    for (const [p, txt] of stages) if (target >= p) stage.textContent = txt;
    if (sec > 420 && !done) {                    // 7 min: engine likely offline
      clearInterval(anim); clearInterval(poll);
      stage.textContent = 'No response — is the engine service running?';
      document.getElementById('anHint').textContent =
        'Start it on your PC:  python runner/service.py   — then try again.';
      fill.style.width = '0%'; pct.textContent = '';
      setTimeout(() => ov.classList.remove('open'), 6000);
    }
  }, 400);
  let errBefore = null;
  try { errBefore = (await (await fetch('api/docmeta.php?k=analysis_error')).json()).updated_at; }
  catch (e) {}
  const poll = setInterval(async () => {
    try {
      const now = (await (await fetch('api/docmeta.php?k=daily_pick')).json()).updated_at;
      if (now && now !== before) {
        done = true; clearInterval(anim); clearInterval(poll);
        fill.style.width = '100%'; pct.textContent = '100%';
        stage.textContent = 'Done — new Top 3 ready!';
        setTimeout(() => location.reload(), 900);
        return;
      }
      const ej = await (await fetch('api/docmeta.php?k=analysis_error')).json();
      if (ej.updated_at && ej.updated_at !== errBefore) {   // honest failure surfaced
        done = true; clearInterval(anim); clearInterval(poll);
        stage.textContent = 'Analysis failed — ' + (ej.data?.reason || 'see dashboard');
        document.getElementById('anHint').textContent =
          'No fallback was used. Reloading to show the full diagnostic…';
        fill.style.width = '0%'; pct.textContent = '';
        setTimeout(() => location.reload(), 2500);
      }
    } catch (e) {}
  }, 5000);
});
</script>
</body>
</html>
