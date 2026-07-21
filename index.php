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
$engineHealth = doc_get('engine_health');
$engineHealthData = $engineHealth['data'] ?? [];
$engineLastSeen = strtotime((string) ($engineHealthData['last_seen_at'] ?? '')) ?: 0;
$engineStaleAfter = max(30, (int) ($engineHealthData['stale_after_seconds'] ?? 95));
$engineOnline = (($engineHealthData['status'] ?? '') === 'running' && $engineLastSeen
                 && time() - $engineLastSeen <= $engineStaleAfter);
$analysisState = strtolower((string) ($analysisStatus['data']['state'] ?? ''));
$analysisStatusAge = max(0, time() - (int) ($analysisStatus['updated_epoch'] ?? 0));
$analysisRunning = in_array($analysisState, ['starting', 'running'], true)
    && $analysisStatusAge <= 35 * 60 && $engineOnline;
$analysisQueued = !empty($queued['ALL']['RUN_ANALYSIS']) || $analysisRunning;
$anErr = doc_get('analysis_error');
$statusRunId = (string) ($analysisStatus['data']['run_id'] ?? '');
$pickRunId = (string) ($pick['data']['run_id'] ?? '');
$errorRunId = (string) ($anErr['data']['run_id'] ?? '');
$statusAfterPick = $analysisStatus && (!$pick
    || ($statusRunId && $pickRunId && $statusRunId !== $pickRunId)
    || (($analysisStatus['updated_epoch'] ?? 0) > ($pick['updated_epoch'] ?? 0)));
$latestTerminalOutcome = in_array($analysisState, ['failed', 'completed_no_pick'], true)
    && $statusAfterPick;
$errorMatchesStatus = $anErr && (!$statusRunId || !$errorRunId || $statusRunId === $errorRunId);
$latestNoPick = $latestTerminalOutcome && ($analysisState === 'completed_no_pick'
    || ($errorMatchesStatus && analysis_is_no_pick($anErr['data'] ?? [])));
$showErr = $latestTerminalOutcome;
$pickHistorical = (bool) ($pick && $latestTerminalOutcome);
$currentChallenger = ($errorMatchesStatus
    && is_array($anErr['data']['details']['challenger'] ?? null))
    ? $anErr['data']['details']['challenger'] : [];
$currentChallengerStatus = strtolower((string) ($currentChallenger['status'] ?? ''));
$currentChallengerComplete = $latestNoPick
    && $currentChallengerStatus === 'completed';
$currentChallengerRequests = (int) ($currentChallenger['request_budget']['completed_requests']
    ?? $currentChallenger['request_budget']['completed_batches'] ?? 0);
$candidateRows = array_values(array_filter(
    is_array($candDoc['data'] ?? null) ? $candDoc['data'] : [],
    fn($row) => is_array($row) && !empty($row['ticker'])
));
usort($candidateRows, fn($a, $b) =>
    ((float) ($b['quant_score'] ?? 0) <=> (float) ($a['quant_score'] ?? 0))
    ?: strcmp((string) ($a['ticker'] ?? ''), (string) ($b['ticker'] ?? '')));
$candidateRunId = (string) ($candidateRows[0]['run_id'] ?? '');
$candidateCurrent = !$statusRunId || ($candidateRunId && $candidateRunId === $statusRunId);
if (!$candidateCurrent) { $candidateRows = []; }
$selectedTickersForWatchlist = array_fill_keys(
    array_column($pick['data']['top3'] ?? [], 'ticker'), true);
$reviewedForWatchlist = [];
$reviewSource = ($errorMatchesStatus && is_array($anErr['data']['details']['reviewed'] ?? null))
    ? $anErr['data']['details']['reviewed']
    : ($pick['data']['reviewed'] ?? []);
foreach ($reviewSource as $reviewRow) {
    $reviewTicker = strtoupper((string) ($reviewRow['ticker'] ?? ''));
    if ($reviewTicker !== '') { $reviewedForWatchlist[$reviewTicker] = $reviewRow; }
}
$NAV_ACTIVE = 'dash';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Trading AI Horizon — Dashboard</title>
<link rel="icon" type="image/png" href="favicon.png?v=2">
<link rel="stylesheet" href="assets/css/app.css?v=41">
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

  <?php if ($showErr): $ae = $errorMatchesStatus ? $anErr['data'] : [
      'stage' => 'analysis worker',
      'reason' => ($analysisStatus['data']['message'] ?? 'The run ended without a detailed diagnostic.'),
      'hints' => ['Paper monitoring remained independent. Review the PC worker log before retrying.'],
  ]; ?>
  <section class="card analysis-outcome-card <?= $latestNoPick ? 'no-pick' : 'failed' ?>">
    <h2 class="<?= $latestNoPick ? 'wait' : 'bad' ?>">
      <?= $latestNoPick
          ? 'Analysis completed — no stock qualified in this run'
          : 'AI analysis failed — no pick was produced (no silent fallback)' ?></h2>
    <p class="muted" style="margin:8px 0"><b>Stage:</b> <?= htmlspecialchars($ae['stage'] ?? '?') ?>
      &nbsp;·&nbsp; <b>Reason:</b> <?= htmlspecialchars($ae['reason'] ?? '?') ?></p>
    <p class="muted small"><?= $latestNoPick
        ? 'The engine completed normally and kept its standards. No candidate was invented.'
        : "Let's fix it together — check these:" ?></p>
    <ul class="muted small" style="text-align:left; margin:6px 0 0 18px; line-height:1.8">
      <?php foreach (($ae['hints'] ?? []) as $h): ?>
        <li><?= htmlspecialchars($h) ?></li>
      <?php endforeach; ?>
    </ul>
    <?php if ($latestNoPick && $currentChallengerStatus): ?>
      <div class="current-challenger-state <?= $currentChallengerComplete ? 'complete' : 'limited' ?>">
        <i></i><span><b>Current Qwen challenger: <?= htmlspecialchars($currentChallengerStatus) ?></b>
          <?= $currentChallengerComplete
              ? ($currentChallengerRequests > 0
                  ? htmlspecialchars("{$currentChallengerRequests} independent stock reviews completed in this run.")
                  : 'The current per-stock challenger completed normally.')
              : htmlspecialchars((string) ($currentChallenger['reason']
                    ?? 'The current challenger did not complete every independent stock review.')) ?></span>
      </div>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <?php if ($pick): $p = $pick['data'];
        $analysisRaw = (string) ($pick['updated_at'] ?? '');
        $analysisMinute = 'time unavailable';
        if ($analysisRaw !== '') {
            try { $analysisMinute = (new DateTime($analysisRaw))->format('Y-m-d H:i'); }
            catch (Exception $e) { /* Preserve the explicit unavailable label. */ }
        } ?>
  <section class="card <?= $pickHistorical ? 'historical-pick' : '' ?>">
    <?php if ($pickHistorical): ?>
      <div class="historical-pick-banner"><b>Previous qualified result — historical</b>
        <span><?= $latestNoPick
            ? 'The newest run completed without a qualified pick.'
            : 'The newest run failed before publishing a qualified result.' ?>
          This older result is preserved for review. An explicit historical Paper buy is available
          when a slot is open; every current broker and portfolio guard is checked again.</span></div>
    <?php endif; ?>
    <div class="camp-head">
      <h2><?= $pickHistorical ? 'Previous qualified pick:' : 'AI pick of the day:' ?>
        <span class="grad-t"><?= htmlspecialchars($p['chosen']) ?></span></h2>
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
            $sig = $t['signals'] ?? [];
            $sig['historical_result'] = $pickHistorical;
            $sig['latest_challenger_complete'] = $pickHistorical && $currentChallengerComplete; ?>
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
                    data-decision="<?= htmlspecialchars((string) ($t['decision'] ?? 'PASS')) ?>"
                    data-historical="<?= $pickHistorical ? '1' : '0' ?>"
                    data-analysis-time="<?= htmlspecialchars($analysisMinute) ?>"
                    data-run-id="<?= htmlspecialchars($pickRunId) ?>"
                    data-price="<?= htmlspecialchars((string) ($sig['price'] ?? $candidatePrices[$tk] ?? '')) ?>">
              <?= $pickHistorical ? 'Review historical Paper buy' : 'Choose Paper or Live' ?></button>
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
              $qwenHistoricalFailure = $pickHistorical
                  && in_array($qwenVerdict, ['FAILED', 'UNAVAILABLE'], true);
              $qwenDisplayReason = $qwenReason;
              $qwenBrief = 'Provider diagnostic';
              if (preg_match('/HTTP\s+(\d+)/i', $qwenReason, $qwenHttp)) {
                  $qwenBrief = 'HTTP ' . $qwenHttp[1];
              }
              if (stripos($qwenReason, 'rate_limit_exceeded') !== false
                  || stripos($qwenReason, 'tokens per minute') !== false) {
                  $qwenBrief .= ' - Token limit exceeded';
              } elseif (stripos($qwenReason, 'local token preflight') !== false) {
                  $qwenBrief = 'Local token preflight - Request not sent';
              }
              if ($qwenVerdict === 'FAILED' && preg_match('/HTTP 400$/', $qwenReason)) {
                  $qwenDisplayReason .= ' · Detailed Groq message was not retained for this historical result.';
              }
              $reviewSignals = [
                'historical_result' => $pickHistorical,
                'latest_challenger_complete' => $pickHistorical && $currentChallengerComplete,
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
                <span>Qwen <b><?= htmlspecialchars($qwenHistoricalFailure
                    ? 'OLDER RUN ' . $qwenVerdict : $qwenVerdict) ?></b></span>
              </div>
              <p><?= htmlspecialchars($r['reason'] ?? '') ?></p>
              <?php if (in_array($qwenVerdict, ['FAILED', 'UNAVAILABLE'], true) && $qwenReason): ?>
                <details class="qwen-alert">
                  <summary>
                    <span class="qwen-alert-mark" aria-hidden="true">!</span>
                    <span><b><?= $qwenHistoricalFailure ? 'Earlier saved Qwen ' : 'Qwen ' ?><?= strtolower(htmlspecialchars($qwenVerdict)) ?></b>
                      <small><?= htmlspecialchars(($qwenHistoricalFailure && $currentChallengerComplete)
                          ? "Earlier saved failure - latest run's challenger completed"
                          : $qwenBrief) ?></small></span>
                    <em>Full details</em>
                  </summary>
                  <div class="qwen-alert-body">
                    <span><?= $qwenHistoricalFailure
                        ? 'This diagnostic belongs to the older saved analysis. It is retained for audit honesty and is not the current challenger status.'
                        : 'Groq returned this bounded diagnostic. No Qwen verdict was invented.' ?></span>
                    <p><?= htmlspecialchars($qwenDisplayReason) ?></p>
                  </div>
                </details>
              <?php endif; ?>
              <div class="dd-review-footer">
                <span class="dd-review-action">View full analysis <b>↓</b></span>
                <?php if (!empty($running[$reviewTicker])): ?>
                  <button class="dd-candidate-buy locked" type="button" disabled>Auto-trading active</button>
                <?php elseif (!empty($queued[$reviewTicker]['APPROVE_BUY'])): ?>
                  <button class="dd-candidate-buy locked" type="button" disabled>Paper order queued</button>
                <?php elseif ($decision === 'VETO'): ?>
                  <button class="dd-candidate-buy locked" type="button" disabled
                          title="A vetoed candidate cannot start a campaign">VETO · No buy</button>
                <?php elseif ($slotsFull): ?>
                  <button class="dd-candidate-buy locked" type="button" disabled>Campaign slots full</button>
                <?php elseif (!in_array($decision, ['PASS', 'WATCH'], true)): ?>
                  <button class="dd-candidate-buy locked" type="button" disabled>Not eligible</button>
                <?php else: ?>
                  <button class="dd-candidate-buy buy-choice" type="button"
                          data-ticker="<?= $reviewTickerHtml ?>"
                          data-name="<?= htmlspecialchars($names[$reviewTicker] ?? $reviewTicker) ?>"
                          data-decision="<?= htmlspecialchars($decision) ?>"
                          data-historical="<?= $pickHistorical ? '1' : '0' ?>"
                          data-analysis-time="<?= htmlspecialchars($analysisMinute) ?>"
                          data-run-id="<?= htmlspecialchars($pickRunId) ?>"
                          data-price="<?= htmlspecialchars((string) ($candidatePrices[$reviewTicker] ?? '')) ?>">
                    <?= $pickHistorical
                        ? ($decision === 'WATCH' ? 'Review historical WATCH buy' : 'Review historical Paper buy')
                        : ($decision === 'WATCH' ? 'Review WATCH buy' : 'Choose buy mode') ?></button>
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
      be running: <code>python -X utf8 runner/service.py</code>).</p></section>
  <?php endif; ?>

  <?php if ($candidateRows):
      $leadingPotentials = array_slice($candidateRows, 0, 3);
      $additionalPotentials = array_slice($candidateRows, 3); ?>
    <section class="card momentum-watchlist" aria-labelledby="momentumWatchlistTitle">
      <div class="momentum-watchlist-head">
        <div><span class="momentum-watchlist-kicker">DETERMINISTIC MARKET RANKING</span>
          <h2 id="momentumWatchlistTitle">Quantitative momentum leaders</h2>
          <p>Ordered by quantitative score. These names passed the market-data screen;
            only cards explicitly marked <b>AI-qualified</b> are approved selections.</p></div>
        <span class="momentum-watchlist-count"><?= count($candidateRows) ?> researched</span>
      </div>
      <div class="momentum-leaders">
        <?php foreach ($leadingPotentials as $candidate):
            $ticker = strtoupper((string) ($candidate['ticker'] ?? ''));
            $signals = is_array($candidate['signals'] ?? null) ? $candidate['signals'] : [];
            $isSelected = !empty($selectedTickersForWatchlist[$ticker]);
            $reviewDecision = strtoupper((string) ($reviewedForWatchlist[$ticker]['final_decision']
                ?? $reviewedForWatchlist[$ticker]['decision'] ?? ''));
            $tierText = $isSelected ? 'AI-qualified'
                : (in_array($reviewDecision, ['WATCH', 'VETO'], true)
                    ? 'AI ' . $reviewDecision . ' · research only'
                    : 'Potential · research only'); ?>
          <article class="momentum-potential <?= $isSelected ? 'qualified' : '' ?>">
            <div class="momentum-potential-top"><span class="momentum-rank">#<?= (int) ($candidate['quant_rank'] ?? 0) ?></span>
              <span class="momentum-tier <?= $isSelected ? 'qualified' : '' ?>">
                <?= htmlspecialchars($tierText) ?></span></div>
            <div class="momentum-potential-company"><div><b><?= htmlspecialchars($ticker) ?></b>
              <small><?= htmlspecialchars((string) ($candidate['name'] ?? $ticker)) ?></small></div>
              <strong><?= number_format((float) ($candidate['quant_score'] ?? 0), 1) ?><em>Quant score</em></strong></div>
            <div class="momentum-potential-metrics">
              <span><small>Price</small><b>$<?= number_format((float) ($candidate['price'] ?? 0), 2) ?></b></span>
              <span><small>3-month momentum</small><b><?= number_format(100 * (float) ($signals['momentum_3_1'] ?? 0), 1) ?>%</b></span>
              <span><small>Relative to S&amp;P 500</small><b><?= number_format(100 * (float) ($signals['relative_spy'] ?? 0), 1) ?>%</b></span>
              <span><small>Recent volume</small><b><?= number_format(100 * (float) ($signals['recent_volume_ratio'] ?? 0), 0) ?>%</b></span>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
      <?php if ($additionalPotentials): ?>
        <details class="momentum-more">
          <summary>Show <?= count($additionalPotentials) ?> additional potential momentum
            <?= count($additionalPotentials) === 1 ? 'stock' : 'stocks' ?></summary>
          <div class="momentum-more-list">
            <?php foreach ($additionalPotentials as $candidate): $signals = $candidate['signals'] ?? []; ?>
              <div><span><b>#<?= (int) ($candidate['quant_rank'] ?? 0) ?> ·
                    <?= htmlspecialchars((string) ($candidate['ticker'] ?? '')) ?></b>
                  <small><?= htmlspecialchars((string) ($candidate['name'] ?? '')) ?></small></span>
                <strong><?= number_format((float) ($candidate['quant_score'] ?? 0), 1) ?></strong>
                <em><?= number_format(100 * (float) ($signals['momentum_3_1'] ?? 0), 1) ?>% three-month ·
                  <?= number_format(100 * (float) ($signals['relative_spy'] ?? 0), 1) ?>% vs S&amp;P 500</em></div>
            <?php endforeach; ?>
          </div>
        </details>
      <?php endif; ?>
      <p class="momentum-watchlist-note"><i></i><span><b>Research list, not a forced recommendation.</b>
        A potential remains non-buyable until evidence review awards PASS and the final sector,
        correlation, account-budget, Moomoo price, spread, and market-hours checks succeed.</span></p>
    </section>
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
    <div class="an-run-state"><span id="anState">QUEUED</span><time id="anElapsed">00:00</time></div>
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
  const qwenReason = String(sig.challenger_reason || '').trim();
  const historicalQwen = Boolean(sig.historical_result);
  const latestChallengerComplete = Boolean(sig.latest_challenger_complete);
  let qwenHtml = '';
  if (qwenReason) {
    const http = qwenReason.match(/HTTP\s+(\d+)/i);
    const limited = /rate_limit_exceeded|tokens per minute/i.test(qwenReason);
    const preflight = /local token preflight/i.test(qwenReason);
    const brief = preflight ? 'Local token preflight - Request not sent'
      : `${http ? `HTTP ${http[1]}` : 'Provider response'}${limited ? ' - Token limit exceeded' : ''}`;
    qwenHtml = `<details class="qwen-alert qwen-panel-alert"><summary>` +
      `<span class="qwen-alert-mark" aria-hidden="true">!</span>` +
      `<span><b>${historicalQwen ? 'Earlier saved Qwen review' : 'Qwen review'} - ${esc(String(sig.challenger_verdict || sig.challenger_status || 'Unavailable'))}</b>` +
      `<small>${esc(historicalQwen && latestChallengerComplete ? "Earlier saved failure - latest run's challenger completed" : brief)}</small></span><em>Full details</em></summary>` +
      `<div class="qwen-alert-body"><span>${historicalQwen ? 'Saved historical diagnostic - retained for audit honesty' : 'Complete bounded challenger diagnostic'}</span>` +
      `<p>${esc(qwenReason)}</p></div></details>`;
  }
  const basis = card.dataset.basis
      ? `<div class="basis"><b>How the score of ${esc(card.dataset.score)} was built:</b> ` +
        `<span>${esc(humanizeScoreBasis(card.dataset.basis))}</span></div>` : '';
  body.innerHTML =
      `<div class="siggrid">${sigHtml}</div>${qwenHtml}${basis}` + mdReport(card.dataset.analysis);
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
      if (e.target.closest('.qwen-alert')) return;
      if (card.classList.contains('dd-review-row') || !e.target.closest('button')) select();
    });
    if (!card.matches('button')) {
      card.addEventListener('keydown', e => {
        if (e.target.closest('.qwen-alert')) return;
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
let purchaseState = {ticker:'', name:'', price:'', decision:'PASS', historical:false,
  analysisTime:'', runId:'', mode:'paper', trigger:null};

function selectPurchaseMode(mode) {
  purchaseState.mode = mode;
  [purchasePaper, purchaseLive].forEach(choice => {
    const selected = choice.dataset.mode === mode;
    choice.classList.toggle('selected', selected);
    choice.setAttribute('aria-pressed', selected ? 'true' : 'false');
  });
  const live = mode === 'live';
  const watchOverride = purchaseState.decision === 'WATCH';
  const historicalOverride = purchaseState.historical;
  purchaseNotice.className = `purchase-notice ${live ? 'live' : 'paper'}`;
  purchaseNotice.textContent = live
    ? 'Live production is intentionally locked. This platform has no isolated live campaign ledger or authenticated live-order queue yet, so no real-money order can be sent from this modal.'
    : historicalOverride
      ? `This is an explicit historical Paper approval for ${purchaseState.ticker}, based on the saved ${purchaseState.decision} review from ${purchaseState.analysisTime || 'an earlier run'}. The engine does not trust its old price: it rechecks the current Moomoo price, market hours, campaign slots, budget, sector concentration, correlation, spread, and duplicate orders before any order.`
    : watchOverride
      ? `WATCH means unresolved evidence or risk remains. Your confirmation is an explicit manual Paper override for ${purchaseState.ticker}; the engine still rechecks market hours, campaign slots, budget, correlation, spread, and current Moomoo prices before ordering.`
      : `Paper confirmation queues ${purchaseState.ticker} for the running Moomoo simulated-account engine. The engine rechecks market hours, campaign slots, budget, correlation, spread, and current broker prices before ordering.`;
  purchaseConfirm.textContent = live ? 'Open Live Preparation'
    : historicalOverride ? 'Confirm Historical Paper Buy'
    : watchOverride ? 'Confirm WATCH Paper Buy' : 'Queue Paper Buy';
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
                   price:btn.dataset.price || '', decision:btn.dataset.decision || 'PASS',
                   historical:btn.dataset.historical === '1',
                   analysisTime:btn.dataset.analysisTime || '', runId:btn.dataset.runId || '',
                   mode:'paper', trigger:btn};
  document.getElementById('purchaseModeTitle').textContent = purchaseState.historical
    ? `Review historical candidate ${purchaseState.ticker}`
    : purchaseState.decision === 'WATCH'
    ? `Review WATCH candidate ${purchaseState.ticker}` : `Buy ${purchaseState.ticker}?`;
  const shownPrice = purchaseState.price && Number.isFinite(Number(purchaseState.price))
    ? `$${Number(purchaseState.price).toFixed(2)}` : 'Verified again at order time';
  purchaseSummary.innerHTML = [
    ['Company', purchaseState.name || purchaseState.ticker],
    ['Review status', purchaseState.decision],
    ['First tranche', `${TRANCHE} shares`],
    [purchaseState.historical ? 'Historical price' : 'Analysis price', shownPrice]
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
    if (await queueCommand('APPROVE_BUY', purchaseState.ticker, CSRF, {
      analysis_source: purchaseState.historical ? 'historical' : 'current',
      analysis_run_id: purchaseState.runId
    })) {
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

// ---- Run-aware analysis overlay + original synthesized cues ----
let analysisAudioContext = null;
function primeAnalysisSounds() {
  try {
    const AudioContext = window.AudioContext || window.webkitAudioContext;
    if (!AudioContext) return;
    analysisAudioContext = analysisAudioContext || new AudioContext();
    if (analysisAudioContext.state === 'suspended') analysisAudioContext.resume();
  } catch (e) {}
}
function withAnalysisAudio(build) {
  const ctx = analysisAudioContext;
  if (!ctx) return;
  const play = () => {
    const master = ctx.createGain();
    const compressor = ctx.createDynamicsCompressor();
    master.gain.setValueAtTime(0.48, ctx.currentTime);
    compressor.threshold.value = -16; compressor.knee.value = 14;
    compressor.ratio.value = 5; compressor.attack.value = 0.003;
    compressor.release.value = 0.22;
    master.connect(compressor).connect(ctx.destination);
    build(ctx, master);
  };
  if (ctx.state === 'suspended') ctx.resume().then(play).catch(() => {}); else play();
}
function playAnalysisTriumph() {
  withAnalysisAudio((ctx, out) => {
    const start = ctx.currentTime + 0.04;
    // A clear three-part brass-style fanfare: C major rise, then a sustained triumph chord.
    [[523.25,0,.30],[659.25,.20,.30],[783.99,.40,.34],
     [1046.5,.63,.70],[1318.5,.63,.70],[1567.98,.63,.70]].forEach(([hz, delay, length], i) => {
      const osc = ctx.createOscillator(), gain = ctx.createGain();
      osc.type = i < 3 ? 'sawtooth' : 'triangle';
      osc.frequency.setValueAtTime(hz, start + delay);
      gain.gain.setValueAtTime(.0001, start + delay);
      gain.gain.exponentialRampToValueAtTime(i < 3 ? .14 : .11, start + delay + .025);
      gain.gain.exponentialRampToValueAtTime(.0001, start + delay + length);
      osc.connect(gain).connect(out); osc.start(start + delay); osc.stop(start + delay + length + .03);
    });
  });
}
function playAnalysisFailureCrash() {
  withAnalysisAudio((ctx, out) => {
    const start = ctx.currentTime + .03;
    // Dramatic descending alarm and impact, synthesized locally at a capped level.
    [0, .16].forEach(offset => {
      const siren = ctx.createOscillator(), gain = ctx.createGain();
      siren.type = 'sawtooth';
      siren.frequency.setValueAtTime(620, start + offset);
      siren.frequency.exponentialRampToValueAtTime(75, start + offset + 1.05);
      gain.gain.setValueAtTime(.0001, start + offset);
      gain.gain.exponentialRampToValueAtTime(.16, start + offset + .025);
      gain.gain.exponentialRampToValueAtTime(.0001, start + offset + 1.12);
      siren.connect(gain).connect(out); siren.start(start + offset); siren.stop(start + offset + 1.14);
    });
    const length = Math.floor(ctx.sampleRate * 1.15), buffer = ctx.createBuffer(1, length, ctx.sampleRate);
    const data = buffer.getChannelData(0);
    for (let i = 0; i < length; i++) data[i] = (Math.random() * 2 - 1) * Math.exp(-i / (length * .17));
    const noise = ctx.createBufferSource(), filter = ctx.createBiquadFilter(), impact = ctx.createGain();
    noise.buffer = buffer; filter.type = 'lowpass'; filter.frequency.value = 310;
    impact.gain.setValueAtTime(.0001, start + .88);
    impact.gain.exponentialRampToValueAtTime(.42, start + .90);
    impact.gain.exponentialRampToValueAtTime(.0001, start + 1.95);
    noise.connect(filter).connect(impact).connect(out); noise.start(start + .88);
  });
}
function formatAnalysisElapsed(seconds) {
  const total = Math.max(0, Number(seconds) || 0), hours = Math.floor(total / 3600);
  const minutes = Math.floor((total % 3600) / 60), secs = Math.floor(total % 60);
  return (hours ? String(hours).padStart(2, '0') + ':' : '') +
    String(minutes).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
}
async function queueAnalysisRun() {
  const response = await fetch('api/command.php', {method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({action:'RUN_ANALYSIS',ticker:'ALL',csrf:CSRF})});
  const data = await response.json();
  if (!response.ok || data.ok !== true || !data.run_id) throw new Error(data.error || 'queue failed');
  return data;
}

const anBtn = document.getElementById('analyzeBtn');
if (anBtn) anBtn.addEventListener('click', async () => {
  primeAnalysisSounds();
  const ov = document.getElementById('anOverlay'), fill = document.getElementById('anFill');
  const pct = document.getElementById('anPct'), stage = document.getElementById('anStage');
  const hint = document.getElementById('anHint'), stateEl = document.getElementById('anState');
  const elapsedEl = document.getElementById('anElapsed');
  anBtn.disabled = true;
  let run;
  try { run = await queueAnalysisRun(); }
  catch (e) { anBtn.disabled = false; alert('Could not queue the analysis. No run was started.'); return; }
  ov.classList.add('open'); ov.setAttribute('aria-hidden', 'false');
  let terminal = false, lastStatus = null, statusRenderedAt = Date.now();
  const render = status => {
    lastStatus = status; statusRenderedAt = Date.now(); const sec = status.elapsed_seconds || 0;
    elapsedEl.textContent = formatAnalysisElapsed(sec);
    stateEl.textContent = String(status.state || 'queued').replaceAll('_', ' ').toUpperCase();
    const floors = {queued:5,starting:12,running:22,completed:100,completed_no_pick:100,failed:0};
    let progress = floors[status.state] ?? 5;
    if (status.state === 'running') {
      const reported = Number(status.progress_percent);
      progress = Number.isFinite(reported) && reported > 0
        ? Math.min(98, reported)
        : Math.min(94, 22 + 72 * (1 - Math.exp(-sec / 180)));
    }
    fill.style.width = progress + '%'; pct.textContent = status.state === 'failed' ? '' : Math.round(progress) + '%';
    stage.textContent = status.message || 'Waiting for engine status…';
    if (['queued','starting','running'].includes(status.state) && status.engine && !status.engine.online) {
      stage.textContent = status.state === 'queued'
        ? 'Queued safely — the PC engine heartbeat is currently stale.'
        : 'PC engine heartbeat is stale — preserving the last known run state.';
      hint.textContent = status.state === 'queued'
        ? 'The command remains pending. Start python -X utf8 runner/service.py on the PC; no monitoring or analysis runs while it is offline.'
        : 'Monitoring and analysis cannot be claimed while the PC heartbeat is stale. Restart the service if it does not recover.';
      stateEl.textContent = 'ENGINE OFFLINE';
    } else if (status.state === 'running' && sec > 420) {
      hint.textContent = 'This run is still active. Paper monitoring continues independently; the engine allows up to 30 minutes before its protection limit stops the worker.';
    } else if (status.state === 'starting') {
      hint.textContent = 'The engine accepted this exact run and is starting its isolated low-priority analysis worker.';
    }
  };
  const finish = status => {
    terminal = true; clearInterval(poll); clearInterval(clock); render(status);
    if (status.state === 'completed') {
      stage.textContent = 'Done — new qualified picks are ready!'; playAnalysisTriumph();
      hint.textContent = 'The complete result was published for this run.';
    } else if (status.state === 'completed_no_pick') {
      stage.textContent = 'Complete — no stock passed every required gate today.';
      hint.textContent = 'This is an honest no-pick result, not a service failure. No candidate was fabricated.';
    } else {
      stage.textContent = 'Analysis failed — ' + (status.reason || status.message || 'see the diagnostic');
      hint.textContent = 'No silent fallback was used. The engine continued Paper monitoring.';
      playAnalysisFailureCrash();
    }
    setTimeout(() => location.reload(), status.state === 'failed' ? 3200 : 1800);
  };
  const check = async () => {
    try {
      const response = await fetch('api/analysis_run.php?run_id=' + encodeURIComponent(run.run_id), {cache:'no-store'});
      const status = await response.json();
      if (!response.ok || status.ok !== true) throw new Error(status.error || 'status unavailable');
      render(status);
      if (['completed','completed_no_pick','failed'].includes(status.state)) finish(status);
    } catch (e) {
      stage.textContent = 'The website could not read run status — retrying…';
      hint.textContent = 'This is not evidence that the PC engine stopped. The page will keep checking this run.';
      stateEl.textContent = 'STATUS RETRY';
    }
  };
  const poll = setInterval(check, 2500);
  const clock = setInterval(() => {
    if (!terminal && lastStatus) elapsedEl.textContent = formatAnalysisElapsed(
      (lastStatus.elapsed_seconds || 0) + Math.floor((Date.now() - statusRenderedAt) / 1000));
  }, 1000);
  check();
});
</script>
</body>
</html>
