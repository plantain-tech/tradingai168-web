<?php
define('APP', 1);
require __DIR__ . '/inc/auth.php';
require_login();

$msg = $err = '';
$FREE_MODELS = ['gpt-oss:20b', 'gpt-oss:120b', 'deepseek-v3.1:671b',
                'qwen3-coder:480b', 'kimi-k2:1t'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';
    if ($act === 'trading') {
        $f = fn($k, $d) => is_numeric($_POST[$k] ?? null) ? (float) $_POST[$k] : $d;
        $s = [
            'budget_usd'       => max(100, $f('budget_usd', 15000)),
            'max_concurrent'   => max(1, min(10, (int) $f('max_concurrent', 3))),
            'tranche_base'     => max(1, (int) $f('tranche_base', 20)),
            'tranche_step'     => max(0, (int) $f('tranche_step', 5)),
            'dca_gap_bdays'    => max(1, (int) $f('dca_gap_bdays', 5)),
            'profit_alert_pct' => max(0.01, $f('profit_alert_pct', 15) / 100.0),
            'loss_alert_usd'   => max(1, $f('loss_alert_usd', 1500)),
            'loss_urgent_usd'  => max(1, $f('loss_urgent_usd', 2250)),
            'fill_wait_s'      => max(5, (int) $f('fill_wait_s', 45)),
        ];
        if ($s['loss_urgent_usd'] < $s['loss_alert_usd']) {
            $err = 'Urgent loss level must be >= the alert level.';
        } else { save_settings($s); $msg = 'Trading & risk settings saved.'; }
    } elseif ($act === 'ai') {
        $model = trim($_POST['ai_model_custom'] ?: ($_POST['ai_model'] ?? 'gpt-oss:20b'));
        save_settings(['ai_model' => $model,
                       'ollama_host' => ($_POST['ollama_host'] ?? 'cloud') === 'cloud'
                                        ? 'cloud' : trim($_POST['ollama_host_custom'] ?? 'cloud')]);
        $msg = "AI model set to {$model}.";
    } elseif ($act === 'account') {
        $ne = trim($_POST['new_email'] ?? '');
        $np = $_POST['new_password'] ?? '';
        if (!verify_login($_SESSION['email'], $_POST['current_password'] ?? '')) {
            $err = 'Current password is wrong.';
        } elseif ($ne && !filter_var($ne, FILTER_VALIDATE_EMAIL)) {
            $err = 'New email is not valid.';
        } elseif ($np && strlen($np) < 10) {
            $err = 'New password must be at least 10 characters.';
        } else {
            update_credentials($ne ?: null, $np ?: null);
            $msg = 'Account updated.';
        }
    } elseif ($act === 'token') {
        api_token(true);
        $msg = 'API token regenerated — update it on the engine side too.';
    }
}
$s = get_settings();
$token = api_token();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Settings — Trading AI Horizon</title>
<link rel="stylesheet" href="assets/css/app.css?v=11">
</head>
<body>
<div class="bg"></div>
<?php $NAV_ACTIVE = 'set'; require __DIR__ . '/inc/nav.php'; ?>
<main class="hero" style="max-width:640px">
  <h1 class="pagetitle">Settings</h1>
  <?php if ($msg): ?><p class="alert-ok"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
  <?php if ($err): ?><p class="alert-bad"><?= htmlspecialchars($err) ?></p><?php endif; ?>

  <section class="card">
    <h2>Trading &amp; Risk</h2>
    <form method="post" class="grid2">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="trading">
      <div><label>Budget cap ($)</label>
        <input class="in" name="budget_usd" type="number" step="1" value="<?= $s['budget_usd'] ?>"></div>
      <div><label>Max concurrent stocks</label>
        <input class="in" name="max_concurrent" type="number" min="1" max="10"
               value="<?= $s['max_concurrent'] ?? 3 ?>"></div>
      <div><label>Tranche base (shares)</label>
        <input class="in" name="tranche_base" type="number" value="<?= $s['tranche_base'] ?>"></div>
      <div><label>Tranche step (&plusmn;)</label>
        <input class="in" name="tranche_step" type="number" value="<?= $s['tranche_step'] ?>"></div>
      <div><label>DCA gap (business days)</label>
        <input class="in" name="dca_gap_bdays" type="number" value="<?= $s['dca_gap_bdays'] ?>"></div>
      <div><label>Profit alert (%)</label>
        <input class="in" name="profit_alert_pct" type="number" step="0.5"
               value="<?= round($s['profit_alert_pct'] * 100, 2) ?>"></div>
      <div><label>Loss alert ($)</label>
        <input class="in" name="loss_alert_usd" type="number" value="<?= $s['loss_alert_usd'] ?>"></div>
      <div><label>Urgent loss ($)</label>
        <input class="in" name="loss_urgent_usd" type="number" value="<?= $s['loss_urgent_usd'] ?>"></div>
      <div><label>Fill wait (seconds)</label>
        <input class="in" name="fill_wait_s" type="number" value="<?= $s['fill_wait_s'] ?>"></div>
      <div class="full"><button class="btn">Save trading settings</button></div>
    </form>
  </section>

  <section class="card">
    <h2>AI Model (Ollama — plug in / out freely)</h2>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="ai">
      <label>Free cloud model</label>
      <select class="in" name="ai_model">
        <?php foreach ($FREE_MODELS as $m): ?>
          <option value="<?= $m ?>" <?= $s['ai_model'] === $m ? 'selected' : '' ?>><?= $m ?></option>
        <?php endforeach; ?>
      </select>
      <label>…or custom model name (overrides the dropdown)</label>
      <input class="in" name="ai_model_custom" placeholder="e.g. llama3.3:70b"
             value="<?= in_array($s['ai_model'], $FREE_MODELS) ? '' : htmlspecialchars($s['ai_model']) ?>">
      <label>Host</label>
      <select class="in" name="ollama_host">
        <option value="cloud" <?= ($s['ollama_host'] ?? 'cloud') === 'cloud' ? 'selected' : '' ?>>Ollama Cloud</option>
        <option value="custom" <?= ($s['ollama_host'] ?? 'cloud') !== 'cloud' ? 'selected' : '' ?>>Custom / local URL</option>
      </select>
      <input class="in" name="ollama_host_custom" placeholder="http://localhost:11434"
             value="<?= ($s['ollama_host'] ?? 'cloud') === 'cloud' ? '' : htmlspecialchars($s['ollama_host']) ?>">
      <button class="btn">Save AI settings</button>
    </form>
  </section>

  <section class="card">
    <h2>Account</h2>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="account">
      <label>Current password (required)</label>
      <input class="in" type="password" name="current_password" required>
      <label>New email (blank = keep <?= htmlspecialchars($_SESSION['email']) ?>)</label>
      <input class="in" type="email" name="new_email">
      <label>New password (blank = keep; min 10 chars)</label>
      <input class="in" type="password" name="new_password" minlength="10">
      <button class="btn">Update account</button>
    </form>
  </section>

  <section class="card">
    <h2>Engine API token</h2>
    <p class="muted">The Python engine uses this to pull these settings
      (<code>scripts/sync_settings.py</code>). Keep it secret.</p>
    <code class="tokenbox"><?= htmlspecialchars($token) ?></code>
    <form method="post" style="margin-top:10px">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="token">
      <button class="btn ghost">Regenerate token</button>
    </form>
  </section>
  <footer class="foot">changes here are pulled by the engine before each tick</footer>
</main>
</body>
</html>
