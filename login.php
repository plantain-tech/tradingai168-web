<?php
define('APP', 1);
require __DIR__ . '/inc/auth.php';
boot_session();

$firstRun = user_count() === 0;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = $_POST['email'] ?? '';
    $pwd = $_POST['password'] ?? '';
    if ($firstRun) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid email address.';
        } elseif (strlen($pwd) < 10) {
            $error = 'Password must be at least 10 characters.';
        } elseif ($pwd !== ($_POST['password2'] ?? '')) {
            $error = 'Passwords do not match.';
        } elseif (create_owner($email, $pwd) && verify_login($email, $pwd)) {
            header('Location: index.php'); exit;
        } else { $error = 'Could not create the account.'; }
    } else {
        if (verify_login($email, $pwd)) { header('Location: index.php'); exit; }
        $error = 'Wrong email or password.';
    }
    $firstRun = user_count() === 0;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in — Trading AI Horizon</title>
<link rel="icon" type="image/png" href="favicon.png?v=2">
<link rel="stylesheet" href="assets/css/app.css?v=31">
</head>
<body>
<div class="bg"></div>
<main class="hero" style="max-width:420px">
  <div class="brand" style="margin-bottom:6px">
    <svg class="mark" width="40" height="40" viewBox="0 0 48 48" aria-hidden="true">
      <defs><linearGradient id="hz" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0" stop-color="#6366f1"/><stop offset="1" stop-color="#22d3ee"/></linearGradient></defs>
      <rect x="3" y="3" width="42" height="42" rx="12" fill="none" stroke="url(#hz)" stroke-width="2"/>
      <circle cx="24" cy="27" r="7" fill="url(#hz)" opacity="0.9"/>
      <line x1="12" y1="33" x2="36" y2="33" stroke="#0b0f1a" stroke-width="3"/>
      <line x1="12" y1="33" x2="36" y2="33" stroke="url(#hz)" stroke-width="1.5"/>
    </svg>
    <h1 class="wordmark"><span class="wm-top">TRADING&nbsp;AI</span><span class="wm-main" style="font-size:30px">HORIZON</span></h1>
  </div>

  <section class="card">
    <h2 style="font-size:16px;margin-bottom:4px">
      <?= $firstRun ? 'Create the owner account' : 'Sign in' ?></h2>
    <p class="muted" style="margin-bottom:16px">
      <?= $firstRun ? 'First run: this one account owns the platform; registration locks afterward.'
                    : 'Private platform — owner only.' ?></p>
    <?php if ($error): ?><p class="alert-bad"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <form method="post" autocomplete="on">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <label>Email (username)</label>
      <input class="in" type="email" name="email" required
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="you@example.com">
      <label>Password</label>
      <input class="in" type="password" name="password" required minlength="<?= $firstRun ? 10 : 1 ?>">
      <?php if ($firstRun): ?>
        <label>Repeat password</label>
        <input class="in" type="password" name="password2" required minlength="10">
      <?php endif; ?>
      <button class="btn" type="submit"><?= $firstRun ? 'Create & sign in' : 'Sign in' ?></button>
    </form>
  </section>
  <footer class="foot">paper-first · you approve, it executes</footer>
  <?php require __DIR__ . '/inc/brand_footer.php'; ?>
</main>
</body>
</html>
