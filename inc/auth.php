<?php
// Auth + settings core. Single-user platform: first registered account = owner,
// registration locks afterward. Tables auto-create so setup order never matters.
if (!defined('APP')) { http_response_code(403); exit('Forbidden'); }

require_once __DIR__ . '/db.php';

function cfg(): array {
    static $c = null;
    if ($c === null) { $c = require __DIR__ . '/../config/config.php'; }
    return $c;
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = db_connect(cfg());
        if (!$pdo) { http_response_code(500); exit('Database unavailable'); }
        ensure_tables($pdo);
    }
    return $pdo;
}

function ensure_tables(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        username VARCHAR(128) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id), UNIQUE KEY uq_users_username (username)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS app_settings (
        k VARCHAR(64) NOT NULL, v TEXT NOT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (k)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// ---- settings (mirror of the Python engine's DEFAULTS) ---------------------
function default_settings(): array {
    return [
        'budget_usd' => 15000, 'target_shares' => 100,
        'tranche_base' => 20, 'tranche_step' => 5, 'dca_gap_bdays' => 5,
        'profit_alert_pct' => 0.15, 'loss_alert_usd' => 1500,
        'loss_urgent_usd' => 2250, 'fill_wait_s' => 45,
        'ai_model' => 'gpt-oss:20b', 'ollama_host' => 'cloud',
    ];
}

function get_settings(): array {
    $out = default_settings();
    foreach (db()->query("SELECT k, v FROM app_settings")->fetchAll() as $r) {
        $out[$r['k']] = json_decode($r['v'], true);
    }
    return $out;
}

function save_settings(array $kv): void {
    $st = db()->prepare("INSERT INTO app_settings (k, v) VALUES (?, ?)
                         ON DUPLICATE KEY UPDATE v = VALUES(v)");
    foreach ($kv as $k => $v) { $st->execute([$k, json_encode($v)]); }
}

function api_token(bool $regen = false): string {
    $s = get_settings();
    if ($regen || empty($s['api_token'])) {
        $t = bin2hex(random_bytes(24));
        save_settings(['api_token' => $t]);
        return $t;
    }
    return $s['api_token'];
}

// ---- auth -------------------------------------------------------------------
function boot_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax',
                                   'secure' => !empty($_SERVER['HTTPS'])]);
        session_start();
    }
}

function user_count(): int {
    return (int) db()->query("SELECT COUNT(*) FROM users")->fetchColumn();
}

function create_owner(string $email, string $pwd): bool {
    if (user_count() > 0) { return false; }            // registration locked
    $st = db()->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
    return $st->execute([strtolower(trim($email)), password_hash($pwd, PASSWORD_DEFAULT)]);
}

function verify_login(string $email, string $pwd): bool {
    $st = db()->prepare("SELECT id, password_hash FROM users WHERE username = ?");
    $st->execute([strtolower(trim($email))]);
    $u = $st->fetch();
    if ($u && password_verify($pwd, $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $u['id'];
        $_SESSION['email'] = strtolower(trim($email));
        return true;
    }
    sleep(1);                                          // slow brute force
    return false;
}

function update_credentials(?string $new_email, ?string $new_pwd): void {
    if ($new_email) {
        db()->prepare("UPDATE users SET username = ? WHERE id = ?")
            ->execute([strtolower(trim($new_email)), $_SESSION['uid']]);
        $_SESSION['email'] = strtolower(trim($new_email));
    }
    if ($new_pwd) {
        db()->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
            ->execute([password_hash($new_pwd, PASSWORD_DEFAULT), $_SESSION['uid']]);
    }
}

function require_login(): void {
    boot_session();
    if (empty($_SESSION['uid'])) { header('Location: login.php'); exit; }
}

function csrf_token(): string {
    boot_session();
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
    return $_SESSION['csrf'];
}

function csrf_check(): void {
    boot_session();
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? null)) {
        http_response_code(403); exit('Bad CSRF token');
    }
}
