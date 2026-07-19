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
$candidatePrices = [];
foreach (($candDoc['data'] ?? []) as $c) {
    $names[$c['ticker']] = $c['name'] ?? $c['ticker'];
    $candidatePrices[$c['ticker']] = $c['price'] ?? null;
}
$maxConcurrent = (int) ($settings['max_concurrent'] ?? 3);
$activeCount = count($running) + count(array_filter($queued,
    fn($q) => !empty($q['APPROVE_BUY'])));
$slotsFull = $activeCount >= $maxConcurrent;
$analysisStatus = doc_get('analysis_status');
$analysisRunning = in_array($analysisStatus['data']['state'] ?? '', ['starting', 'running'], true);
$analysisQueued = !empty($queued['ALL']['RUN_ANALYSIS']) || $analysisRunning;
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
<link rel="stylesheet" href="assets/css/app.css?v=36">
</head>
<body>
<div class="bg"></div>
<?php require __DIR__ . '/inc/nav.php'; ?>
<main class="hero wide">
  <div class="badge">MOMENTUM · <?= $activeCount ?>/<?= $maxConcurrent ?> SLOTS IN USE</div>
  <h1 class="pagetitle" style="font-size:32px">Dashboard</h1>

  <?php if ($analysisQueued): ?>
    <button class="analyze-btn locked" disabled><span class="lockdot"></span>
      Analysis running — Paper engine continues monitoring…</button>
  <?php else: ?>
    <button class="analyze-btn" id="analyzeBtn">
      <span class="ab-spark">✦</span> Analyze &amp; Pick Up to 3 — AI powered
      <em>multi-horizon momentum · relative strength · Open Attention Trend · <?= htmlspecialchars($settings['ai_model']) ?>
        <?= !empty($settings['ai_challenger_enabled']) ? ' · Qwen challenger' : '' ?></em>
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

  <?php if ($pick): $p = $pick['data'];
        $analysisRaw = (string) ($pick['updated_at'] ?? '');
        $analysisMinute = 'time unavailable';
        if ($analysisRaw !== '') {
            try { $analysisMinute = (new DateTime($analysisRaw))->format('Y-m-d H:i'); }
            catch (Exception $e) { /* Preserve the explicit unavailable label. */ }
        } ?>
  <section class="card">
    <div class="camp-head">
      <h2>AI pick of the day: <span class="grad-t"><?= htmlspecialchars($p['chosen']) ?></span></h2>
      <time class="analysis-time" datetime="<?= htmlspecialchars($pick['updated_at'] ?? '') ?>">
        Latest analysis · <?= htmlspecialchars($analysisMinute) ?></time>
    </div>
    <div class="top3">
      <?php foreach (($p['top3'] ?? []) as $t): $tk = htmlspecialchars($t['ticker']);
            $isChosen = $tk === ($p['chosen'] ?? '');
            $isRunning = !empty($running[$tk]);
            $isQueued = !empty($queued[$tk]['APPROVE_BUY']);
            $analysis = $t['analysis'] ?? $t['reason'] ?? '';
            if ($isChosen && !empty($p['rationale'])) { $analysis = $p['rationale']; }
            $sig = $t['signals'] ?? []; ?>
        <div class="tile pick-tile analysis-source selectable <?= $isChosen ? 'chosen selected' : '' ?>"
             data-ticker="<?= $tk ?>" role="button" tabindex="0"
             data-analysis="<?= htmlspecialchars($analysis) ?>"
             data-basis="<?= htmlspecialchars($t['score_basis'] ?? '') ?>"
             data-score="<?= htmlspecialchars($t['score']) ?>"
             data-signals="<?= htmlspecialchars(json_encode($sig)) ?>">
          <?php if ($isChosen): ?><span class="crown">★ TOP CHOICE</span><?php endif; ?>
          <span><?= $tk ?> <em class="muted"><?= htmlspecialchars($names[$tk] ?? '') ?></em></span>
          <div class="dd-tile-score"><b><?= htmlspecialchars($t['score']) ?></b>
            <span class="dd-status dd-<?= strtolower(htmlspecialchars($t['decision'] ?? 'pass')) ?>">
              <?= htmlspecialchars($t['decision'] ?? 'PASS') ?> · <?= htmlspecialchars($t['confidence'] ?? '—') ?> confidence
            </span></div>
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
            <button class="btn buybtn buy-choice" type="button" data-ticker="<?= $tk ?>"
                    data-name="<?= htmlspecialchars($names[$tk] ?? $tk) ?>"
                    data-price="<?= htmlspecialchars((string) ($sig['price'] ?? $candidatePrices[$tk] ?? '')) ?>">
              Choose Paper or Live</button>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <?php $trace = $p['analysis_trace'] ?? null;
          $traceComplete = !empty($trace['integrity_complete']);
          $traceStages = is_array($trace['stages'] ?? null) ? $trace['stages'] : [];
          $traceOutcome = is_array($trace['outcome'] ?? null) ? $trace['outcome'] : []; ?>
    <style id="analysisBrainDisclosureCritical">
      .brain-disclosure-v2 .brain-head{position:relative}
      .brain-disclosure-v2 .brain-toggle{position:absolute;inset:-8px;z-index:2;width:calc(100% + 16px);
        height:calc(100% + 16px);padding:0;border:0;border-radius:14px;background:transparent;cursor:pointer}
      .brain-disclosure-v2 .brain-toggle:focus-visible{outline:2px solid var(--brand2);outline-offset:2px}
      .brain-disclosure-v2 .brain-head-actions{display:flex;align-items:center;gap:10px;pointer-events:none}
      .brain-disclosure-v2 .brain-chevron{width:20px;height:20px;fill:none;stroke:var(--muted);stroke-width:2;
        stroke-linecap:round;stroke-linejoin:round;transition:transform .36s cubic-bezier(.2,.8,.2,1),stroke .2s}
      .brain-disclosure-v2.open .brain-chevron{transform:rotate(180deg);stroke:var(--brand2)}
      .brain-disclosure-v2 .brain-collapse{display:grid;grid-template-rows:0fr;opacity:0;visibility:hidden;
        transition:grid-template-rows .42s cubic-bezier(.2,.8,.2,1),opacity .24s ease,visibility 0s linear .42s}
      .brain-disclosure-v2 .brain-collapse-inner{min-height:0;overflow:hidden;transform:translateY(-8px);
        transition:transform .38s cubic-bezier(.2,.8,.2,1)}
      .brain-disclosure-v2.open .brain-collapse{grid-template-rows:1fr;opacity:1;visibility:visible;
        transition:grid-template-rows .42s cubic-bezier(.2,.8,.2,1),opacity .24s ease,visibility 0s}
      .brain-disclosure-v2.open .brain-collapse-inner{transform:none}
      @media(prefers-reduced-motion:reduce){.brain-disclosure-v2 .brain-chevron,
        .brain-disclosure-v2 .brain-collapse,.brain-disclosure-v2 .brain-collapse-inner{transition:none!important}}
    </style>
    <section class="analysis-brain brain-disclosure-v2 <?= $trace ? ($traceComplete ? 'trace-complete' : 'trace-incomplete') : 'trace-legacy' ?>"
             aria-labelledby="analysisBrainTitle">
      <div class="brain-head">
        <button class="brain-toggle" id="analysisBrainToggle" type="button" aria-expanded="false"
                aria-controls="analysisBrainPanel" aria-label="Expand Analysis Brain"></button>
        <div class="brain-heading">
          <div class="brain-mark" aria-hidden="true">
            <svg viewBox="0 0 64 64" role="img">
              <path d="M31 12c-7-7-18-2-17 7-7 2-8 12-2 16-4 8 3 16 11 14 2 6 9 6 12 1V16c0-4-1-6-4-4Z"/>
              <path d="M33 12c7-7 18-2 17 7 7 2 8 12 2 16 4 8-3 16-11 14-2 6-9 6-12 1V16c0-4 1-6 4-4Z"/>
              <path class="brain-circuit" d="M20 23h8l4 5m12-5h-8l-4 5M17 37h9l6-5m15 5h-9l-6-5M24 47v-6l8-5m8 11v-6l-8-5"/>
              <circle cx="20" cy="23" r="2"/><circle cx="44" cy="23" r="2"/>
              <circle cx="17" cy="37" r="2"/><circle cx="47" cy="37" r="2"/>
            </svg>
          </div>
          <div><span class="brain-kicker" id="analysisBrainKicker">DECISION PATH · CLICK TO EXPAND</span>
            <h3 id="analysisBrainTitle">Analysis Brain</h3>
            <p>How the market became this result—using recorded counts, sources, and rejection reasons.</p>
          </div>
        </div>
        <div class="brain-head-actions">
          <span class="brain-integrity">
            <i></i><?= $trace ? ($traceComplete ? 'Complete evidence trail' : 'Incomplete — review required') : 'Historical result — not verifiable' ?>
          </span>
          <svg class="brain-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg>
        </div>
      </div>

      <div class="brain-collapse" id="analysisBrainPanel" aria-hidden="true" inert><div class="brain-collapse-inner">
      <?php if ($trace && $traceStages): ?>
        <div class="brain-outcome">
          <div class="brain-result-count">
            <span><?= (int) ($traceOutcome['selected_count'] ?? count($p['top3'] ?? [])) ?></span>
            <em>selected<br>of 3 maximum</em>
          </div>
          <div><b><?= $traceComplete ? 'The run finished completely.' : 'The run did not preserve a complete evidence trail.' ?></b>
            <p><?= htmlspecialchars($traceOutcome['explanation'] ?? 'Open the stages below to inspect the outcome.') ?></p>
          </div>
          <div class="brain-decision-ring" aria-label="Final review decisions">
            <span class="pass"><?= (int) ($traceOutcome['pass_count'] ?? 0) ?><small>PASS</small></span>
            <span class="watch"><?= (int) ($traceOutcome['watch_count'] ?? 0) ?><small>WATCH</small></span>
            <span class="veto"><?= (int) ($traceOutcome['veto_count'] ?? 0) ?><small>VETO</small></span>
          </div>
        </div>

        <div class="brain-flow" aria-label="Analysis stages">
          <?php foreach ($traceStages as $i => $stage):
            $input = (int) ($stage['input_count'] ?? 0);
            $output = (int) ($stage['output_count'] ?? 0);
            $removed = (int) ($stage['removed_count'] ?? max(0, $input - $output));
            $retention = $input > 0 ? max(3, min(100, round(100 * $output / $input))) : 0;
            $stageJson = htmlspecialchars(json_encode($stage, JSON_UNESCAPED_SLASHES)); ?>
            <button class="brain-stage" type="button"
                    data-stage="<?= $stageJson ?>" data-removed="<?= $removed ?>"
                    aria-pressed="false">
              <span class="brain-step"><?= $i + 1 ?></span>
              <em><?= htmlspecialchars($stage['label'] ?? 'Analysis stage') ?></em>
              <strong><span><?= number_format($input) ?></span><i>→</i><?= number_format($output) ?></strong>
              <span class="brain-retention"><i style="width:<?= $retention ?>%"></i></span>
              <small><?= $removed ? number_format($removed) . ' did not advance' : 'complete at this stage' ?></small>
            </button>
          <?php endforeach; ?>
        </div>
        <div class="brain-detail" id="brainDetail" aria-live="polite">
          <div class="brain-detail-empty">Select a stage to see its source and exact reasons.</div>
        </div>
      <?php else: ?>
        <div class="brain-legacy-note">
          <span>!</span><div><b>This saved result cannot prove why only <?= count($p['reviewed'] ?? []) ?> stocks were reviewed.</b>
            <p>It was created before stage counts and rejection reasons were recorded. The two-stock audit may be reasonable,
              but the old record is not enough to verify it. Run a new analysis to produce the complete evidence map—no conclusion is being invented.</p>
          </div>
        </div>
      <?php endif; ?>
      </div></div>
    </section>

    <?php $reviewed = $p['reviewed'] ?? [];
          $selectedTickers = array_fill_keys(array_column($p['top3'] ?? [], 'ticker'), true);
          if ($reviewed):
            $passCount = count(array_filter($reviewed, fn($r) => ($r['decision'] ?? '') === 'PASS'));
            $watchCount = count(array_filter($reviewed, fn($r) => ($r['decision'] ?? '') === 'WATCH'));
            $vetoCount = count(array_filter($reviewed, fn($r) => ($r['decision'] ?? '') === 'VETO'));
            $qwenTokens = (int) ($p['challenger']['usage']['total_tokens'] ?? 0); ?>
      <section class="dd-audit open" id="ddAudit">
        <button class="dd-audit-toggle" id="ddAuditToggle" type="button"
                aria-expanded="true" aria-controls="ddAuditPanel">
          <span><b>Due-diligence audit</b> · <?= count($reviewed) ?> reviewed</span>
          <span class="dd-audit-counts"><i class="dd-pass"><?= $passCount ?> PASS</i>
            <i class="dd-watch"><?= $watchCount ?> WATCH</i>
            <i class="dd-veto"><?= $vetoCount ?> VETO</i>
            <?php if ($qwenTokens > 0): ?><i class="dd-qwen-usage">QWEN <?= number_format($qwenTokens) ?> TOKENS</i><?php endif; ?></span>
          <svg class="dd-audit-chev" viewBox="0 0 24 24" width="18" height="18"
               aria-hidden="true"><path d="M7 10l5 5 5-5" fill="none"
               stroke="currentColor" stroke-width="2" stroke-linecap="round"
               stroke-linejoin="round"/></svg>
        </button>
        <div class="dd-audit-wrap" id="ddAuditPanel"><div class="dd-audit-body">
          <div class="dd-review-grid">
          <?php foreach ($reviewed as $r):
              $reviewTicker = strtoupper((string) ($r['ticker'] ?? '?'));
              $reviewTickerHtml = htmlspecialchars($reviewTicker);
              $decision = strtoupper($r['decision'] ?? 'WATCH');
              $qwenVerdict = strtoupper($r['challenger']['effective_verdict'] ?? 'UNAVAILABLE');
              $qwenReason = $r['challenger']['reason'] ?? '';
              $qwenDisplayReason = $qwenReason;
              if ($qwenVerdict === 'FAILED' && preg_match('/HTTP 400$/', $qwenReason)) {
                  $qwenDisplayReason .= ' · Detailed Groq message was not retained for this historical result.';
              }
              $reviewSignals = [
                'decision' => $decision,
                'quant_score' => $r['quant_score'] ?? null,
                'ai_due_diligence_score' => $r['due_diligence_score'] ?? null,
                'final_score' => $r['final_score'] ?? null,
                'evidence_coverage_pct' => $r['evidence_coverage_pct'] ?? null,
                'confidence' => $r['confidence'] ?? null,
                'challenger_model' => $p['challenger']['model'] ?? 'qwen/qwen3.6-27b',
                'challenger_status' => $p['challenger']['status'] ?? strtolower($qwenVerdict),
                'challenger_verdict' => $qwenVerdict,
                'challenger_reason' => $qwenReason,
              ]; ?>
            <div class="dd-review-row analysis-source selectable" role="button" tabindex="0"
                    data-ticker="<?= $reviewTickerHtml ?>"
                    data-analysis="<?= htmlspecialchars($r['analysis'] ?? $r['reason'] ?? '') ?>"
                    data-basis="<?= htmlspecialchars($r['score_basis'] ?? '') ?>"
                    data-score="<?= htmlspecialchars($r['final_score'] ?? 0) ?>"
                    data-signals="<?= htmlspecialchars(json_encode($reviewSignals)) ?>"
                    aria-label="View full AI analysis for <?= $reviewTickerHtml ?>">
              <div><b><?= $reviewTickerHtml ?></b>
                <span class="dd-status dd-<?= strtolower(htmlspecialchars($decision)) ?>"><?= htmlspecialchars($decision) ?></span></div>
              <div class="dd-review-scores">
                <span>Quant <b><?= number_format((float) ($r['quant_score'] ?? 0), 1) ?></b></span>
                <span>AI DD <b><?= number_format((float) ($r['due_diligence_score'] ?? 0), 1) ?></b></span>
                <span>Final <b><?= number_format((float) ($r['final_score'] ?? 0), 1) ?></b></span>
                <span>Evidence <b><?= number_format((float) ($r['evidence_coverage_pct'] ?? 0), 0) ?>%</b></span>
                <span>Confidence <b><?= htmlspecialchars($r['confidence'] ?? '—') ?></b></span>
                <span>Qwen <b><?= htmlspecialchars($qwenVerdict) ?></b></span>
              </div>
              <p><?= htmlspecialchars($r['reason'] ?? '') ?></p>
              <?php if (in_array($qwenVerdict, ['FAILED', 'UNAVAILABLE'], true) && $qwenReason): ?>
                <small class="qwen-note">Qwen <?= strtolower($qwenVerdict) ?>:
                  <?= htmlspecialchars($qwenDisplayReason) ?></small>
              <?php endif; ?>
              <div class="dd-review-footer">
                <span class="dd-review-action">View full analysis <b>↓</b></span>
                <?php if (!empty($running[$reviewTicker])): ?>
                  <button class="dd-candidate-buy locked" type="button" disabled>Auto-trading active</button>
                <?php elseif (!empty($queued[$reviewTicker]['APPROVE_BUY'])): ?>
                  <button class="dd-candidate-buy locked" type="button" disabled>Paper order queued</button>
                <?php elseif ($decision !== 'PASS' || empty($selectedTickers[$reviewTicker])): ?>
                  <button class="dd-candidate-buy locked" type="button" disabled
                          title="Only final evidence-supported selections can start a campaign">
                    <?= htmlspecialchars($decision) ?> · No buy</button>
                <?php elseif ($slotsFull): ?>
                  <button class="dd-candidate-buy locked" type="button" disabled>Campaign slots full</button>
                <?php else: ?>
                  <button class="dd-candidate-buy buy-choice" type="button"
                          data-ticker="<?= $reviewTickerHtml ?>"
                          data-name="<?= htmlspecialchars($names[$reviewTicker] ?? $reviewTicker) ?>"
                          data-price="<?= htmlspecialchars((string) ($candidatePrices[$reviewTicker] ?? '')) ?>">
                    Choose buy mode</button>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
          </div>
        </div></div>
      </section>
    <?php endif; ?>

    <div class="ai-panel open" id="aiPanel">
      <button class="ai-panel-head" id="aiPanelHead" type="button"
              aria-expanded="true" aria-controls="aiPanelContent">
        <span>Full AI analysis — <b class="grad-t" id="aiPanelTicker"><?= htmlspecialchars($p['chosen'] ?? '') ?></b>
          <em class="muted small">evidence / bull case / bear case / invalidation / score</em></span>
        <svg class="chev" viewBox="0 0 24 24" width="18" height="18">
          <path d="M7 10l5 5 5-5" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round"/></svg>
      </button>
      <div class="ai-panel-wrap" id="aiPanelContent"><div class="ai-panel-body" id="aiPanelBody"></div></div>
    </div>

    <p class="muted small">Click a card to read its analysis and score breakdown.
      The result may contain fewer than three stocks when sector, correlation, earnings,
      liquidity, or risk requirements leave fewer legitimate candidates.
      A Paper Buy creates the campaign behind the scenes — once the simulated order is placed on
      Moomoo, auto-trading activates and the position appears on
      <a href="monitor.php" style="color:var(--brand2)">Monitor</a>. Budgets:
      $<?= number_format($settings['budget_usd'] / max(1, $maxConcurrent), 0) ?>/stock,
      $<?= number_format($settings['budget_usd'], 0) ?> global. Nothing trades without your click.
      Live production remains locked until its separate real-money safeguards are completed.</p>
  </section>
  <?php else: ?>
    <section class="card"><h2>No AI pick yet</h2>
      <p class="muted">Press <b>Analyze &amp; Pick Up to 3</b> above (engine service must
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
    <h3>AI momentum analysis in progress</h3>
    <p class="an-stage" id="anStage">Contacting engine…</p>
    <div class="an-track"><div class="an-fill" id="anFill"></div></div>
    <p class="an-pct" id="anPct">0%</p>
    <p class="muted small" id="anHint">Screening the full universe, verifying company identity and
      14-day Wikimedia attention, liquidity, earnings and risk, then running the structured AI audit — usually 3–8 minutes.</p>
  </div>
</div>

<style id="purchaseModeCritical">
  .purchase-mode-v2{position:fixed;inset:0;z-index:80;display:grid;place-items:center;padding:20px;
    background:rgba(4,7,16,.78);backdrop-filter:blur(10px);opacity:0;visibility:hidden;
    pointer-events:none;transition:opacity .28s ease,visibility 0s linear .28s}
  .purchase-mode-v2.open{opacity:1;visibility:visible;pointer-events:auto;transition:opacity .28s ease,visibility 0s}
  .purchase-mode-v2 .purchase-dialog{width:min(94vw,620px);max-height:min(90vh,760px);overflow:auto;
    transform:translateY(18px) scale(.97);transition:transform .34s cubic-bezier(.2,.85,.25,1)}
  .purchase-mode-v2.open .purchase-dialog{transform:none}
  @media(prefers-reduced-motion:reduce){.purchase-mode-v2,.purchase-mode-v2 .purchase-dialog{transition:none!important}}
</style>
<div class="purchase-mode-v2" id="purchaseModeBack" aria-hidden="true">
  <section class="purchase-dialog" role="dialog" aria-modal="true"
           aria-labelledby="purchaseModeTitle" aria-describedby="purchaseModeIntro">
    <button class="purchase-close" id="purchaseModeClose" type="button" aria-label="Close purchase decision">×</button>
    <div class="purchase-heading">
      <span class="purchase-mark" aria-hidden="true">↗</span>
      <div><span class="purchase-kicker">HUMAN APPROVAL REQUIRED</span>
        <h3 id="purchaseModeTitle">Choose trading account</h3>
        <p id="purchaseModeIntro">No order is sent until you choose an account and confirm.</p></div>
    </div>
    <div class="purchase-summary" id="purchaseModeSummary"></div>
    <div class="purchase-accounts" role="group" aria-label="Trading account">
      <button class="purchase-account selected" id="purchasePaperChoice" type="button"
              aria-pressed="true" data-mode="paper">
        <span class="purchase-account-icon" aria-hidden="true">▣</span>
        <span><b>Paper Trading</b><small>Moomoo simulated account · current engine</small></span>
        <em>READY</em>
      </button>
      <button class="purchase-account live" id="purchaseLiveChoice" type="button"
              aria-pressed="false" data-mode="live">
        <span class="purchase-account-icon" aria-hidden="true">◆</span>
        <span><b>Live Production</b><small>Real money · isolated production controls required</small></span>
        <em>LOCKED</em>
      </button>
    </div>
    <div class="purchase-notice paper" id="purchaseModeNotice" aria-live="polite"></div>
    <div class="purchase-actions">
      <button class="btn ghost" id="purchaseModeCancel" type="button">Do not buy</button>
      <button class="btn" id="purchaseModeConfirm" type="button">Queue Paper Buy</button>
    </div>
  </section>
</div>

<?php require __DIR__ . '/inc/modal.php'; ?>
<script>
const CSRF = '<?= $csrf ?>';
const TRANCHE = <?= (int) $settings['tranche_base'] ?>;

// ---- pick cards: select -> analysis + score breakdown ----
const panel = document.getElementById('aiPanel');
const esc = value => String(value ?? '').replace(/[&<>'"]/g, char => ({
  '&':'&amp;', '<':'&lt;', '>':'&gt;', "'":'&#39;', '"':'&quot;'
})[char]);
const scoreFieldNames = {
  'est_current_year.eps_growth_est':'current-year earnings-per-share growth estimate',
  'est_next_year.eps_growth_est':'next-year earnings-per-share growth estimate',
  earnings_growth_yoy:'earnings growth year over year', earnings_surprise_avg:'average earnings surprise',
  revenue_growth_yoy:'revenue growth year over year', gross_margin:'gross margin',
  operating_margin:'operating margin', profit_margin:'profit margin', return_on_equity:'return on equity',
  free_cash_flow:'free cash flow', total_cash:'total cash', total_debt:'total debt',
  current_ratio:'current ratio', forward_pe:'forward price-to-earnings ratio',
  price_to_book:'price-to-book ratio', analyst_target_mean:'average analyst target',
  next_earnings_date:'next earnings date', open_attention_strength:'open-attention strength'
};
function humanizeScoreBasis(value) {
  let text = String(value || '').trim();
  text = text.replace(/Final\s+([\d.]+)\s*=\s*70% quantitative\s+([\d.]+)\s*\+\s*30% due diligence\s+([\d.]+)\.?/i,
    'Final score $1 combines 70% quantitative evidence ($2) and 30% due-diligence evidence ($3).');
  Object.keys(scoreFieldNames).sort((a,b) => b.length-a.length).forEach(field => {
    text = text.replaceAll(field, scoreFieldNames[field]);
  });
  text = text.replace(/\b[a-z][a-z0-9_]*(?:\.[a-z0-9_]+)+\b/gi,
    field => field.replaceAll('.', ' ').replaceAll('_', ' '));
  text = text.replace(/\b[a-z][a-z0-9]*_[a-z0-9_]+\b/gi, field => field.replaceAll('_', ' '));
  return text.replace(/\s+/g, ' ').replace(/\.\s*,/g, ',').trim();
}
function renderPanel(card) {
  const body = document.getElementById('aiPanelBody');
  document.getElementById('aiPanelTicker').textContent = card.dataset.ticker;
  let sig = {};
  try { sig = JSON.parse(card.dataset.signals || '{}'); } catch (e) {}
  const label = {price: 'Price', decision: 'Decision', final_score: 'Final score',
                 quant_score: 'Quant score', ai_due_diligence_score: 'AI due diligence',
                 confidence: 'Evidence confidence', evidence_coverage_pct: 'Evidence coverage',
                 challenger_model: 'Independent challenger',
                 challenger_status: 'Challenger status',
                 challenger_verdict: 'Qwen verdict',
                 challenger_reason: 'Qwen review',
                 dd_earnings: 'DD · Earnings (30)', dd_business_quality: 'DD · Business quality (25)',
                 dd_financial_strength: 'DD · Financial strength (20)',
                 dd_valuation: 'DD · Valuation (10)', dd_catalysts: 'DD · Catalysts (10)',
                 dd_attention: 'DD · Attention (5)', sector: 'Sector',
                 momentum_12_1: '12–1 month momentum', momentum_6_1: '6–1 month momentum',
                 momentum_3_1: '3–1 month momentum', relative_spy: 'Strength vs SPY',
                 relative_sector: 'Strength vs sector', high_52w_proximity: '52-week high proximity',
                 trend_consistency: 'Trend consistency', volatility_63d: '63-day volatility',
                 max_drawdown_63d: '63-day max drawdown', avg_dollar_volume: 'Average dollar volume',
                 spread_pct: 'Bid / ask spread', earnings_momentum: 'Earnings momentum',
                 earnings_in_bdays: 'Earnings in business days',
                 avg_ma_slope_yr: 'Avg MA slope /yr',
                 news_articles_14d: 'News articles (14d)', reddit_mentions: 'Reddit mentions',
                 buzz_score: 'Buzz score',
                 open_attention_ratio: 'Open attention · 14d / baseline',
                 open_attention_week_ratio: 'Open attention · latest 7d / prior 7d',
                 open_attention_state: 'Open attention state',
                 open_attention_elevated_days: 'Elevated attention days',
                 open_attention_spike_share: 'Largest-day share',
                 open_attention_latest_date: 'Attention data through',
                 open_attention_source: 'Attention source',
                 open_attention_page: 'Verified company page'};
  const pctSignals = new Set(['momentum_12_1', 'momentum_6_1', 'momentum_3_1',
    'relative_spy', 'relative_sector', 'high_52w_proximity', 'trend_consistency',
    'volatility_63d', 'max_drawdown_63d', 'spread_pct', 'earnings_momentum']);
  const fmtV = (k, v) => v == null ? '—'
      : k === 'price' ? '$' + Number(v).toFixed(2)
      : k === 'avg_dollar_volume' ? '$' + (Number(v) / 1e6).toFixed(1) + 'M'
      : k === 'evidence_coverage_pct' ? Number(v).toFixed(0) + '%'
      : ['final_score', 'quant_score', 'ai_due_diligence_score'].includes(k)
        ? Number(v).toFixed(1) + ' / 100'
      : pctSignals.has(k) ? (Number(v) * 100).toFixed(1) + '%'
      : k === 'avg_ma_slope_yr' ? (v >= 0 ? '+' : '') + (v * 100).toFixed(0) + '%'
      : ['open_attention_ratio', 'open_attention_week_ratio'].includes(k)
        ? Number(v).toFixed(2) + 'x'
      : k === 'open_attention_spike_share' ? (Number(v) * 100).toFixed(1) + '%'
      : k === 'open_attention_elevated_days' ? Number(v).toFixed(0) + ' / 14'
      : v;
  const sigHtml = Object.keys(label).map(k =>
      `<div class="sigcell"><span>${esc(label[k])}</span><b>${esc(fmtV(k, sig[k]))}</b></div>`).join('');
  const basis = card.dataset.basis
      ? `<div class="basis"><b>How the score of ${esc(card.dataset.score)} was built:</b> ` +
        `<span>${esc(humanizeScoreBasis(card.dataset.basis))}</span></div>` : '';
  body.innerHTML =
      `<div class="siggrid">${sigHtml}</div>${basis}` + mdReport(card.dataset.analysis);
}

// Render the analyst report: "HEADING:" lines become section titles, prose flows.
function mdReport(text) {
  return String(text || '').split(/\n+/).map(line => {
    const m = line.match(/^\s*([A-Z][A-Z &()\/0-9–-]{3,40}):\s*(.*)$/);
    if (m) {
      return `<h4 class="rpt-h">${esc(m[1])}</h4>` + (m[2] ? `<p>${esc(m[2])}</p>` : '');
    }
    return line.trim() ? `<p>${esc(line)}</p>` : '';
  }).join('');
}
const audit = document.getElementById('ddAudit');
const auditToggle = document.getElementById('ddAuditToggle');
if (audit && auditToggle) {
  auditToggle.addEventListener('click', () => {
    const opening = !audit.classList.contains('open');
    audit.classList.toggle('open', opening);
    auditToggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
  });
}

const analysisBrain = document.querySelector('.analysis-brain.brain-disclosure-v2');
const analysisBrainToggle = document.getElementById('analysisBrainToggle');
const analysisBrainPanel = document.getElementById('analysisBrainPanel');
const analysisBrainKicker = document.getElementById('analysisBrainKicker');
if (analysisBrain && analysisBrainToggle && analysisBrainPanel) {
  analysisBrainToggle.addEventListener('click', () => {
    const opening = !analysisBrain.classList.contains('open');
    analysisBrain.classList.toggle('open', opening);
    analysisBrainToggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
    analysisBrainToggle.setAttribute('aria-label', `${opening ? 'Collapse' : 'Expand'} Analysis Brain`);
    analysisBrainPanel.setAttribute('aria-hidden', opening ? 'false' : 'true');
    analysisBrainPanel.inert = !opening;
    if (analysisBrainKicker) {
      analysisBrainKicker.textContent = `DECISION PATH · ${opening ? 'CLICK ANY STAGE' : 'CLICK TO EXPAND'}`;
    }
  });
}

// ---- Analysis Brain: recorded stage counts and rejection evidence ----
const brainStages = [...document.querySelectorAll('.brain-stage[data-stage]')];
const brainDetail = document.getElementById('brainDetail');
function addBrainText(parent, tag, className, value) {
  const node = document.createElement(tag);
  if (className) node.className = className;
  node.textContent = value == null ? '' : String(value);
  parent.appendChild(node);
  return node;
}
function renderBrainStage(button) {
  if (!brainDetail || !button) return;
  let stage = {};
  try { stage = JSON.parse(button.dataset.stage || '{}'); } catch (e) { return; }
  brainStages.forEach(item => {
    const selected = item === button;
    item.classList.toggle('selected', selected);
    item.setAttribute('aria-pressed', selected ? 'true' : 'false');
  });
  brainDetail.classList.add('switching');
  setTimeout(() => {
    brainDetail.replaceChildren();
    const head = document.createElement('div');
    head.className = 'brain-detail-head';
    const title = document.createElement('div');
    addBrainText(title, 'span', '', 'SELECTED STAGE');
    addBrainText(title, 'h4', '', stage.label || 'Analysis stage');
    head.appendChild(title);
    addBrainText(head, 'strong', '',
      `${Number(stage.input_count || 0).toLocaleString()} → ${Number(stage.output_count || 0).toLocaleString()}`);
    brainDetail.appendChild(head);
    addBrainText(brainDetail, 'p', 'brain-detail-summary', stage.summary || 'No stage summary was recorded.');
    const source = document.createElement('div');
    source.className = 'brain-source';
    addBrainText(source, 'span', '', 'Evidence source');
    addBrainText(source, 'b', '', stage.source || 'Not recorded');
    brainDetail.appendChild(source);
    const reasons = Array.isArray(stage.reasons) ? stage.reasons : [];
    const reasonHead = document.createElement('div');
    reasonHead.className = 'brain-reason-head';
    addBrainText(reasonHead, 'b', '', reasons.length ? 'Why stocks did not advance' : 'Stage result');
    if (reasons.length) addBrainText(reasonHead, 'small', '', 'Reason signals can overlap');
    brainDetail.appendChild(reasonHead);
    if (!reasons.length) {
      addBrainText(brainDetail, 'p', 'brain-clear', 'No stocks were removed at this stage.');
    } else {
      const list = document.createElement('div');
      list.className = 'brain-reasons';
      reasons.forEach(reason => {
        const row = document.createElement('div');
        const label = document.createElement('div');
        addBrainText(label, 'b', '', reason.reason || 'Did not qualify');
        const examples = Array.isArray(reason.examples) && reason.examples.length
          ? `Examples: ${reason.examples.join(', ')}` : '';
        if (examples) addBrainText(label, 'small', '', examples);
        row.appendChild(label);
        addBrainText(row, 'strong', '', Number(reason.count || 0).toLocaleString());
        list.appendChild(row);
      });
      brainDetail.appendChild(list);
    }
    brainDetail.classList.remove('switching');
  }, window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 150);
}
if (brainStages.length) {
  brainStages.forEach(stage => stage.addEventListener('click', () => renderBrainStage(stage)));
  const mostExplanatory = brainStages.reduce((best, stage) =>
    Number(stage.dataset.removed || 0) > Number(best.dataset.removed || 0) ? stage : best,
    brainStages[0]);
  renderBrainStage(mostExplanatory);
}

if (panel) {
  const panelHead = document.getElementById('aiPanelHead');
  panelHead.addEventListener('click', () => {
    const opening = !panel.classList.contains('open');
    panel.classList.toggle('open', opening);
    panelHead.setAttribute('aria-expanded', opening ? 'true' : 'false');
  });
  const cards = document.querySelectorAll('.analysis-source.selectable');
  cards.forEach(card => {
    const select = () => {
      const ticker = card.dataset.ticker;
      document.querySelectorAll('.analysis-source.selected')
        .forEach(c => c.classList.remove('selected'));
      document.querySelectorAll('.analysis-source').forEach(c => {
        if (c.dataset.ticker === ticker) c.classList.add('selected');
      });
      const body = document.getElementById('aiPanelBody');
      body.classList.add('swapping');
      setTimeout(() => {
        renderPanel(card);
        body.classList.remove('swapping');
        panel.classList.add('open');
        panelHead.setAttribute('aria-expanded', 'true');
        if (card.classList.contains('dd-review-row')) {
          panel.scrollIntoView({behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches
            ? 'auto' : 'smooth', block: 'start'});
        }
      }, 180);
    };
    card.addEventListener('click', e => {
      if (card.classList.contains('dd-review-row') || !e.target.closest('button')) select();
    });
    if (!card.matches('button')) {
      card.addEventListener('keydown', e => {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); select(); }
      });
    }
  });
  const chosen = document.querySelector('.pick-tile.chosen') || cards[0];
  if (chosen) renderPanel(chosen);
}

// ---- candidate purchase decision: Paper is operational; Live stays fail-closed ----
const purchaseBack = document.getElementById('purchaseModeBack');
const purchaseDialog = purchaseBack?.querySelector('.purchase-dialog');
const purchaseSummary = document.getElementById('purchaseModeSummary');
const purchaseNotice = document.getElementById('purchaseModeNotice');
const purchaseConfirm = document.getElementById('purchaseModeConfirm');
const purchasePaper = document.getElementById('purchasePaperChoice');
const purchaseLive = document.getElementById('purchaseLiveChoice');
let purchaseState = {ticker:'', name:'', price:'', mode:'paper', trigger:null};

function selectPurchaseMode(mode) {
  purchaseState.mode = mode;
  [purchasePaper, purchaseLive].forEach(choice => {
    const selected = choice.dataset.mode === mode;
    choice.classList.toggle('selected', selected);
    choice.setAttribute('aria-pressed', selected ? 'true' : 'false');
  });
  const live = mode === 'live';
  purchaseNotice.className = `purchase-notice ${live ? 'live' : 'paper'}`;
  purchaseNotice.textContent = live
    ? 'Live production is intentionally locked. This platform has no isolated live campaign ledger or authenticated live-order queue yet, so no real-money order can be sent from this modal.'
    : `Paper confirmation queues ${purchaseState.ticker} for the running Moomoo simulated-account engine. The engine rechecks market hours, campaign slots, budget, correlation, spread, and current broker prices before ordering.`;
  purchaseConfirm.textContent = live ? 'Open Live Preparation' : 'Queue Paper Buy';
  purchaseConfirm.classList.toggle('danger', live);
}

function closePurchaseMode() {
  if (!purchaseBack) return;
  purchaseBack.classList.remove('open');
  purchaseBack.setAttribute('aria-hidden', 'true');
  setTimeout(() => purchaseState.trigger?.focus(), 0);
}

function openPurchaseMode(btn) {
  if (!purchaseBack) return;
  purchaseState = {ticker:btn.dataset.ticker || '', name:btn.dataset.name || '',
                   price:btn.dataset.price || '', mode:'paper', trigger:btn};
  document.getElementById('purchaseModeTitle').textContent = `Buy ${purchaseState.ticker}?`;
  const shownPrice = purchaseState.price && Number.isFinite(Number(purchaseState.price))
    ? `$${Number(purchaseState.price).toFixed(2)}` : 'Verified again at order time';
  purchaseSummary.innerHTML = [
    ['Company', purchaseState.name || purchaseState.ticker],
    ['First tranche', `${TRANCHE} shares`],
    ['Analysis price', shownPrice]
  ].map(([label,value]) => `<span><small>${esc(label)}</small><b>${esc(value)}</b></span>`).join('');
  selectPurchaseMode('paper');
  purchaseBack.classList.add('open');
  purchaseBack.setAttribute('aria-hidden', 'false');
  setTimeout(() => purchasePaper.focus(), 40);
}

function lockCandidatePurchase(ticker) {
  document.querySelectorAll('.buy-choice').forEach(btn => {
    if (btn.dataset.ticker !== ticker) return;
    btn.disabled = true;
    btn.classList.add('locked');
    btn.textContent = 'Paper order queued';
  });
}

document.querySelectorAll('.buy-choice').forEach(btn => {
  btn.addEventListener('click', event => {
    event.stopPropagation();
    openPurchaseMode(btn);
  });
});
purchasePaper?.addEventListener('click', () => selectPurchaseMode('paper'));
purchaseLive?.addEventListener('click', () => selectPurchaseMode('live'));
document.getElementById('purchaseModeClose')?.addEventListener('click', closePurchaseMode);
document.getElementById('purchaseModeCancel')?.addEventListener('click', closePurchaseMode);
purchaseBack?.addEventListener('click', event => {
  if (event.target === purchaseBack) closePurchaseMode();
});
document.addEventListener('keydown', event => {
  if (event.key === 'Escape' && purchaseBack?.classList.contains('open')) closePurchaseMode();
});
purchaseConfirm?.addEventListener('click', async () => {
  if (purchaseState.mode === 'live') {
    location.href = 'auto_trade_live.php';
    return;
  }
  purchaseConfirm.disabled = true;
  purchaseConfirm.textContent = 'Queuing safely…';
  try {
    if (await queueCommand('APPROVE_BUY', purchaseState.ticker, CSRF)) {
      lockCandidatePurchase(purchaseState.ticker);
      closePurchaseMode();
    } else {
      purchaseNotice.className = 'purchase-notice live';
      purchaseNotice.textContent = 'The authenticated Paper approval could not be queued. No order was sent. Please try again.';
    }
  } finally {
    purchaseConfirm.disabled = false;
    if (purchaseBack?.classList.contains('open')) selectPurchaseMode('paper');
  }
});

// ---- Analyze overlay ----
let analysisAudioContext = null;

// Original Web Audio completion cue: a short coin-payout style rise, created
// locally rather than loading or imitating a copyrighted casino sound asset.
function primeAnalysisCompletionSound() {
  try {
    const AudioContext = window.AudioContext || window.webkitAudioContext;
    if (!AudioContext) return;
    analysisAudioContext = analysisAudioContext || new AudioContext();
    if (analysisAudioContext.state === 'suspended') analysisAudioContext.resume();
  } catch (e) {}
}

function playAnalysisCompletionSound() {
  const ctx = analysisAudioContext;
  if (!ctx) return;
  const play = () => {
    const start = ctx.currentTime + 0.03;
    const notes = [1318.5, 1568, 1975.5, 2637];
    notes.forEach((frequency, index) => {
      const oscillator = ctx.createOscillator();
      const gain = ctx.createGain();
      const at = start + index * 0.105;
      oscillator.type = index === notes.length - 1 ? 'sine' : 'triangle';
      oscillator.frequency.setValueAtTime(frequency, at);
      gain.gain.setValueAtTime(0.0001, at);
      gain.gain.exponentialRampToValueAtTime(index === notes.length - 1 ? 0.16 : 0.10, at + 0.012);
      gain.gain.exponentialRampToValueAtTime(0.0001, at + (index === notes.length - 1 ? 0.38 : 0.13));
      oscillator.connect(gain).connect(ctx.destination);
      oscillator.start(at); oscillator.stop(at + 0.4);
    });
  };
  if (ctx.state === 'suspended') ctx.resume().then(play).catch(() => {});
  else play();
}

const anBtn = document.getElementById('analyzeBtn');
if (anBtn) anBtn.addEventListener('click', async () => {
  primeAnalysisCompletionSound();
  const ov = document.getElementById('anOverlay');
  const fill = document.getElementById('anFill');
  const pct = document.getElementById('anPct');
  const stage = document.getElementById('anStage');
  const stages = [[4, 'Contacting engine…'], [15, 'Screening 500+ stocks — momentum, liquidity & risk'],
                  [35, 'Comparing SPY, sectors, earnings & Open Attention Trend'],
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
        stage.textContent = 'Done — new qualified picks ready!';
        playAnalysisCompletionSound();
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
