<?php
define('APP', 1);
require __DIR__ . '/inc/auth.php';
require_login();
$configPath = __DIR__ . '/config/config.php';
$cfg  = file_exists($configPath) ? require $configPath : null;
require_once __DIR__ . '/inc/db.php';   // auth.php already loads it; once-guard

$dbOk = false; $tableCount = 0; $status = 'not_configured'; $detail = '';
if ($cfg) {
    $pdo = db_connect($cfg);
    if ($pdo) {
        $dbOk = true; $status = 'online';
        try {
            $tableCount = (int) $pdo->query(
                "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()"
            )->fetchColumn();
        } catch (Throwable $e) { /* schema not imported yet */ }
    } else {
        $status = 'db_error'; $detail = 'Database connection failed — check config.php.';
    }
} else {
    $detail = 'No config.php yet — copy config.sample.php and add your DB details.';
}
$appName = $cfg['app_name'] ?? 'TradingAI168';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Trading AI Horizon — Platform</title>
<link rel="stylesheet" href="assets/css/app.css?v=4">
</head>
<body>
<div class="bg"></div>
<main class="hero">
  <nav class="nav">
    <a href="index.php" class="on">Dashboard</a><a href="settings.php">Settings</a>
    <a href="logout.php">Log out</a>
  </nav>
  <div class="badge">PAPER-FIRST · PERSONAL</div>
  <div class="brand">
    <svg class="mark" width="48" height="48" viewBox="0 0 48 48" aria-hidden="true">
      <defs><linearGradient id="hz" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0" stop-color="#6366f1"/><stop offset="1" stop-color="#22d3ee"/></linearGradient></defs>
      <rect x="3" y="3" width="42" height="42" rx="12" fill="none" stroke="url(#hz)" stroke-width="2"/>
      <circle cx="24" cy="27" r="7" fill="url(#hz)" opacity="0.9"/>
      <line x1="12" y1="33" x2="36" y2="33" stroke="#0b0f1a" stroke-width="3"/>
      <line x1="12" y1="33" x2="36" y2="33" stroke="url(#hz)" stroke-width="1.5"/>
    </svg>
    <h1 class="wordmark"><span class="wm-top">TRADING&nbsp;AI</span><span class="wm-main">HORIZON</span></h1>
  </div>
  <p class="tag">AI-assisted options platform — <em>you approve, it executes.</em></p>

  <section class="card">
    <div class="card-head">
      <span class="pulse <?= $dbOk ? 'ok' : ($status==='db_error' ? 'bad' : 'wait') ?>"></span>
      <h2>System Status</h2>
    </div>
    <ul class="status">
      <li><span>Web app</span><b class="ok">online ✓</b></li>
      <li><span>Database</span>
        <b class="<?= $dbOk ? 'ok' : ($status==='db_error' ? 'bad' : 'wait') ?>">
          <?= $dbOk ? 'connected ✓' : ($status==='db_error' ? 'error ✕' : 'not configured') ?>
        </b></li>
      <li><span>Schema tables</span>
        <b class="<?= $tableCount >= 6 ? 'ok' : 'wait' ?>">
          <?= $dbOk ? ($tableCount . ' found' . ($tableCount>=6?' ✓':' — import schema.sql')) : '—' ?>
        </b></li>
      <li><span>Trading engine</span><b class="wait">connect in Sprint&nbsp;1</b></li>
    </ul>
    <?php if ($detail): ?><p class="hint"><?= htmlspecialchars($detail) ?></p><?php endif; ?>
  </section>

  <footer class="foot">Sprint&nbsp;1 · built one honest day at a time · not financial advice</footer>
</main>
</body>
</html>
