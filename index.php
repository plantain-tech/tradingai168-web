<?php
define('APP', 1);
$configPath = __DIR__ . '/config/config.php';
$cfg  = file_exists($configPath) ? require $configPath : null;
require __DIR__ . '/inc/db.php';

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
<title><?= htmlspecialchars($appName) ?> — Platform</title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<div class="bg"></div>
<main class="hero">
  <div class="badge">PAPER TRADING · PERSONAL</div>
  <h1><?= htmlspecialchars($appName) ?><span class="dot">.</span></h1>
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
