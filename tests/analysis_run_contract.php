<?php
// Lightweight regression contract for analysis outcome classification and
// durable per-run document keys. It deliberately performs no database access.
define('APP', 1);
require __DIR__ . '/../inc/engine.php';

function expect_true(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }
    echo "[ok] {$message}\n";
}

expect_true(
    analysis_is_no_pick([
        'stage' => 'quantitative candidate selection',
        'reason' => 'no stock passed momentum, liquidity, and risk gates',
    ]),
    'a legitimate empty quantitative screen is completed with no pick'
);

expect_true(
    analysis_is_no_pick([
        'stage' => 'AI due diligence',
        'reason' => 'no shortlisted stock earned an evidence-supported PASS',
    ]),
    'an all-WATCH due-diligence result is completed with no pick'
);

expect_true(
    !analysis_is_no_pick([
        'stage' => 'analysis worker',
        'reason' => 'unexpected worker failure',
    ]),
    'an infrastructure failure is not disguised as a no-pick result'
);

$runId = 'analysis-20260720-142501-a84f0192de';
$key = analysis_run_doc_key($runId);
expect_true($key === 'analysis_run_' . $runId,
            'a valid run receives a durable document key');
expect_true(strlen($key) <= 64, 'the durable run key fits the engine-doc schema');
expect_true(analysis_run_doc_key('../../unsafe') === null,
            'unsafe run identifiers are rejected');

echo "Analysis run web contracts verified.\n";
