<?php if (!defined('APP')) { http_response_code(403); exit('Forbidden'); } ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>AI Auto Trade · Live — Trading AI Horizon</title>
<link rel="icon" type="image/png" href="favicon.png?v=2">
<link rel="stylesheet" href="assets/css/app.css?v=38">
</head>
<body>
<div class="bg"></div>
<?php require __DIR__ . '/nav.php'; ?>
<main class="hero" style="max-width:680px">
  <div class="badge"><span class="livedot bad-dot"></span> LIVE AUTO TRADE · PREPARATION ONLY</div>
  <h1 class="pagetitle" style="font-size:32px">AI Auto Trade · Live</h1>
  <section class="card live-prep">
    <div class="live-prep-icon">⌁</div>
    <div>
      <h2>Real-money execution is not enabled</h2>
      <p class="muted">This is the dedicated production workspace. It has no Moomoo live-trade connection,
      no trade unlock, and cannot place or queue real orders.</p>
    </div>
  </section>
  <section class="card" style="margin-top:16px">
    <h2>Production readiness</h2>
    <ul class="live-checks">
      <li><span>01</span><div><b>Paper track record</b><small>Validate the strategy and its guardrails over a meaningful SIM period.</small></div></li>
      <li><span>02</span><div><b>Explicit live controls</b><small>Separate live settings, trade unlock, confirmations, and order limits.</small></div></li>
      <li><span>03</span><div><b>Failure protection</b><small>Verify engine-offline detection, recovery checks, and broker reconciliation.</small></div></li>
      <li><span>04</span><div><b>Go-live approval</b><small>Enable only after you explicitly approve the final production safeguards.</small></div></li>
    </ul>
  </section>
  <p class="foot">Paper and Live are deliberately separated. This page is informational until production development is approved.</p>
  <?php require __DIR__ . '/brand_footer.php'; ?>
</main>
</body>
</html>
