<?php
// Engine <-> platform data plumbing: JSON docs pushed by the Python engine and
// a command queue for one-click approvals flowing back.
if (!defined('APP')) { http_response_code(403); exit('Forbidden'); }
require_once __DIR__ . '/auth.php';

function ensure_engine_tables(): void {
    db()->exec("CREATE TABLE IF NOT EXISTS engine_docs (
        k VARCHAR(64) NOT NULL, v MEDIUMTEXT NOT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (k)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db()->exec("CREATE TABLE IF NOT EXISTS commands (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        action VARCHAR(32) NOT NULL,           -- APPROVE_BUY | APPROVE_SELL_ALL
        ticker VARCHAR(16) NOT NULL,
        note VARCHAR(255) DEFAULT NULL,
        status ENUM('pending','done','expired') NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        done_at TIMESTAMP NULL DEFAULT NULL,
        PRIMARY KEY (id), KEY idx_cmd_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function doc_set(string $k, $value): void {
    ensure_engine_tables();
    db()->prepare("INSERT INTO engine_docs (k, v) VALUES (?, ?)
                   ON DUPLICATE KEY UPDATE v = VALUES(v)")
        ->execute([$k, json_encode($value)]);
}

function doc_get(string $k, $default = null) {
    ensure_engine_tables();
    $st = db()->prepare("SELECT v, updated_at FROM engine_docs WHERE k = ?");
    $st->execute([$k]);
    $r = $st->fetch();
    if (!$r) { return $default; }
    $v = json_decode($r['v'], true);
    return ['data' => $v, 'updated_at' => $r['updated_at']];
}

function docs_all(string $prefix): array {
    ensure_engine_tables();
    $st = db()->prepare("SELECT k, v, updated_at FROM engine_docs WHERE k LIKE ?");
    $st->execute([$prefix . '%']);
    $out = [];
    foreach ($st->fetchAll() as $r) {
        $out[$r['k']] = ['data' => json_decode($r['v'], true),
                         'updated_at' => $r['updated_at']];
    }
    return $out;
}

function command_create(string $action, string $ticker, string $note = ''): void {
    ensure_engine_tables();
    // Idempotent: don't stack duplicate pending commands for same action+ticker.
    $st = db()->prepare("SELECT COUNT(*) FROM commands
                         WHERE status='pending' AND action=? AND ticker=?");
    $st->execute([$action, $ticker]);
    if ((int) $st->fetchColumn() === 0) {
        db()->prepare("INSERT INTO commands (action, ticker, note) VALUES (?,?,?)")
            ->execute([$action, $ticker, $note]);
    }
}

function commands_pending(): array {
    ensure_engine_tables();
    return db()->query("SELECT id, action, ticker, note, created_at FROM commands
                        WHERE status='pending' ORDER BY id")->fetchAll();
}

function command_done(int $id): void {
    db()->prepare("UPDATE commands SET status='done', done_at=NOW() WHERE id=?")
        ->execute([$id]);
}

function bearer_ok(): bool {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $given = preg_match('/Bearer\s+(\S+)/', $auth, $m) ? $m[1] : '';
    $s = get_settings();
    return !empty($s['api_token']) && hash_equals($s['api_token'], $given);
}
