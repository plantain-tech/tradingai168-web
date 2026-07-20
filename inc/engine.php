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
    $st = db()->prepare("SELECT v, updated_at, UNIX_TIMESTAMP(updated_at) AS updated_epoch
                         FROM engine_docs WHERE k = ?");
    $st->execute([$k]);
    $r = $st->fetch();
    if (!$r) { return $default; }
    $v = json_decode($r['v'], true);
    return ['data' => $v, 'updated_at' => $r['updated_at'],
            'updated_epoch' => (int) $r['updated_epoch']];
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

function command_create(string $action, string $ticker, string $note = ''): array {
    ensure_engine_tables();
    // Idempotent: don't stack duplicate pending commands for same action+ticker.
    $st = db()->prepare("SELECT id, note, created_at FROM commands
                         WHERE status='pending' AND action=? AND ticker=?
                         ORDER BY id DESC LIMIT 1");
    $st->execute([$action, $ticker]);
    $existing = $st->fetch();
    if ($existing) {
        return ['id' => (int) $existing['id'], 'created' => false,
                'note' => (string) ($existing['note'] ?? ''),
                'created_at' => $existing['created_at']];
    }
    db()->prepare("INSERT INTO commands (action, ticker, note) VALUES (?,?,?)")
        ->execute([$action, $ticker, $note]);
    return ['id' => (int) db()->lastInsertId(), 'created' => true,
            'note' => $note, 'created_at' => gmdate('Y-m-d H:i:s')];
}

function command_get(int $id): ?array {
    ensure_engine_tables();
    $st = db()->prepare("SELECT id, action, ticker, note, status, created_at, done_at
                         FROM commands WHERE id=?");
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
}

function analysis_is_no_pick(?array $error): bool {
    if (!$error) { return false; }
    $stage = strtolower((string) ($error['stage'] ?? ''));
    $reason = strtolower((string) ($error['reason'] ?? ''));
    $knownStages = ['quantitative candidate selection', 'earnings candidate selection',
                    'stage-2 screen', 'ai due diligence'];
    if (!in_array($stage, $knownStages, true)) { return false; }
    foreach (['no stock passed', 'all quantitatively ranked stocks were excluded',
              'no shortlisted stock earned'] as $phrase) {
        if (str_contains($reason, $phrase)) { return true; }
    }
    return false;
}

function analysis_run_doc_key(string $runId): ?string {
    if (!preg_match('/^analysis-[a-zA-Z0-9-]{12,48}$/', $runId)) { return null; }
    $key = 'analysis_run_' . $runId;
    return strlen($key) <= 64 ? $key : null;
}

function prune_analysis_run_docs(int $keep = 60): void {
    // Run records are immutable while retained, but storage must remain bounded
    // for the small Hostinger database. Delete at most one bounded page of the
    // oldest records whenever a new run is created.
    ensure_engine_tables();
    $keep = max(10, min(200, $keep));
    $st = db()->query("SELECT k FROM engine_docs
                       WHERE k LIKE 'analysis\\_run\\_%' ESCAPE '\\\\'
                       ORDER BY updated_at DESC, k DESC LIMIT 100 OFFSET {$keep}");
    $keys = array_column($st->fetchAll(), 'k');
    if (!$keys) { return; }
    $del = db()->prepare('DELETE FROM engine_docs WHERE k=?');
    foreach ($keys as $key) { $del->execute([$key]); }
}

function commands_pending(): array {
    ensure_engine_tables();
    return db()->query("SELECT id, action, ticker, note, created_at,
                               UNIX_TIMESTAMP(created_at) AS created_epoch FROM commands
                        WHERE status='pending' ORDER BY id")->fetchAll();
}

function command_done(int $id): void {
    db()->prepare("UPDATE commands SET status='done', done_at=NOW() WHERE id=?")
        ->execute([$id]);
}

function command_expire(int $id): void {
    db()->prepare("UPDATE commands SET status='expired', done_at=NOW() WHERE id=?")
        ->execute([$id]);
}

function bearer_ok(): bool {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $given = preg_match('/Bearer\s+(\S+)/', $auth, $m) ? $m[1] : '';
    $s = get_settings();
    return !empty($s['api_token']) && hash_equals($s['api_token'], $given);
}
