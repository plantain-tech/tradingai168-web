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

$dashboardSource = file_get_contents(__DIR__ . '/../index.php');
$commandSource = file_get_contents(__DIR__ . '/../api/command.php');
$modalSource = file_get_contents(__DIR__ . '/../inc/modal.php');
$monitorSource = file_get_contents(__DIR__ . '/../monitor.php');
expect_true(strpos($dashboardSource, 'data-historical="<?= $pickHistorical ? \'1\' : \'0\' ?>"') !== false,
            'historical PASS and WATCH candidates carry an explicit purchase source');
expect_true(strpos($dashboardSource, 'Historical result — rerun before buying') === false,
            'historical results are no longer unconditionally disabled');
expect_true(strpos($dashboardSource, 'current-challenger-state') !== false
            && strpos($dashboardSource, 'Earlier saved Qwen') !== false
            && strpos($dashboardSource, "'OLDER RUN ' . \$qwenVerdict") !== false,
            'current challenger health is separated from saved historical failures');
expect_true(strpos($commandSource, 'analysis_source=') !== false
            && strpos($commandSource, 'analysis_run_id=') !== false,
            'historical buy approvals retain their analysis provenance in the command audit');
expect_true(strpos($modalSource, '...metadata') !== false,
            'the shared authenticated command helper carries bounded provenance metadata');
expect_true(strpos($commandSource, 'requested_qty=') !== false
            && strpos($commandSource, "'min_range' => 1") !== false,
            'DCA share quantity is authenticated and validated before queueing');
expect_true(strpos($monitorSource, 'dca-qty-input') !== false
            && strpos($monitorSource, "{quantity}") !== false
            && strpos($monitorSource, 'AI checkpoint evidence') !== false,
            'due checkpoints expose the adjustable quantity and complete evidence status');
expect_true(strpos($monitorSource, 'Moomoo analyst high target') !== false
            && strpos($monitorSource, 'sell-alert level') !== false,
            'Monitor presents the broker forecast high and configured selling-alert level');

echo "Analysis run web contracts verified.\n";
