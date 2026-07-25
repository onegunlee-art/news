<?php
declare(strict_types=1);

/**
 * Discovery public API verification (§9).
 *
 * 기본(운영 DB): read-only 검증만 — INSERT/UPDATE/DELETE 0건 (MySQL READ ONLY transaction).
 * Write 검증: DISCOVERY_TEST_DATABASE 지정 + `--write` 플래그 필요. 운영 DB 이름과 같으면 거부.
 *
 * Usage:
 *   php tools/discovery_public_verify.php
 *   DISCOVERY_TEST_DATABASE=discovery_test php tools/discovery_public_verify.php --write
 */
require_once __DIR__ . '/../src/discovery/bootstrap.php';

function runReadOnlyVerification(
    PDO $pdo,
    Discovery\DiscoveryPublicService $public,
    array $config,
): void {
    echo "mode=read_only\n";
    echo "ENABLE_DISCOVERY_PUBLIC=" . ($config['public_enabled'] ? 'true' : 'false') . "\n";

    $today = $public->getToday(null);
    echo "today_kst=" . discoveryTodayKst() . "\n";
    echo "today.display_mode=" . ($today['meta']['display_mode'] ?? 'n/a') . "\n";

    $editionStatus = $today['edition']['status'] ?? null;
    echo "today.published_only=" . ($editionStatus === null || $editionStatus === 'published' ? 'ok' : 'fail') . "\n";

    $verifiedOk = true;
    foreach ($today['changes'] as $row) {
        if (($row['status'] ?? '') !== 'verified') {
            $verifiedOk = false;
            break;
        }
    }
    echo "change.verified_only=" . ($verifiedOk ? 'ok' : 'fail') . "\n";

    $viewer = $today['viewer'] ?? [];
    echo "viewer.schema="
        . (array_key_exists('has_voted', $viewer) && array_key_exists('option_idx', $viewer) ? 'ok' : 'fail') . "\n";

    $draftStmt = $pdo->query(
        "SELECT edition_date FROM discovery_editions WHERE status = 'draft' ORDER BY edition_date DESC LIMIT 1"
    );
    $draftRow = $draftStmt ? $draftStmt->fetch() : false;
    if ($draftRow) {
        $draftDate = (string) $draftRow['edition_date'];
        $blocked = $public->getEditionByDate($draftDate, null);
        echo "draft.blocked=" . ($blocked === null ? 'ok' : 'fail') . "\n";
    } else {
        echo "draft.blocked=skip_no_draft\n";
    }

    $generatingStmt = $pdo->query(
        "SELECT edition_date FROM discovery_editions WHERE status = 'generating' ORDER BY edition_date DESC LIMIT 1"
    );
    $generatingRow = $generatingStmt ? $generatingStmt->fetch() : false;
    if ($generatingRow) {
        $genDate = (string) $generatingRow['edition_date'];
        $blocked = $public->getEditionByDate($genDate, null);
        echo "generating.blocked=" . ($blocked === null ? 'ok' : 'fail') . "\n";
    } else {
        echo "generating.blocked=skip_no_generating\n";
    }

    $list = $public->listEditions(null, 5);
    echo "editions.cursor=" . (isset($list['items'], $list['has_more']) ? 'ok' : 'fail') . "\n";

    foreach ($today['changes'] as $change) {
        if (!empty($change['poll']['id'])) {
            $pollBundle = $public->getPoll((int) $change['poll']['id'], null);
            echo "poll.viewer_schema="
                . (isset($pollBundle['viewer']['has_voted'], $pollBundle['viewer']['option_idx']) ? 'ok' : 'fail') . "\n";
            break;
        }
    }
}

function runWriteVerification(PDO $pdo, Discovery\DiscoveryPublicService $public, Discovery\DiscoveryRepository $repo): void
{
    echo "mode=write_test_db\n";

    $pollId = null;
    $today = $public->getToday(null);
    foreach ($today['changes'] as $change) {
        if (!empty($change['poll']['id'])) {
            $pollId = (int) $change['poll']['id'];
            break;
        }
    }

    if (!$pollId) {
        echo "write.skip=no_published_poll\n";
        return;
    }

    $deviceA = '__test_' . bin2hex(random_bytes(8));
    $deviceB = '__test_' . bin2hex(random_bytes(8));

    $pdo->beginTransaction();
    try {
        $before = $public->getPoll($pollId, $deviceA);
        echo "write.viewer_before=" . (($before['viewer']['has_voted'] ?? false) ? 'fail' : 'ok') . "\n";

        $vote = $public->castVote($deviceA, $pollId, 0);
        echo "write.vote=" . ($vote['viewer']['has_voted'] ? 'ok' : 'fail') . "\n";

        try {
            $public->castVote($deviceA, $pollId, 1);
            echo "write.duplicate_block=fail\n";
        } catch (RuntimeException $e) {
            echo "write.duplicate_block=" . ($e->getCode() === 409 ? 'ok' : 'fail') . "\n";
        }

        $other = $public->getPoll($pollId, $deviceB);
        echo "write.device_split=" . (($other['viewer']['has_voted'] ?? false) ? 'fail' : 'ok') . "\n";

        $comment = $public->addComment($deviceA, $pollId, '__test_comment__');
        $commentId = (int) ($comment['comment']['id'] ?? 0);
        echo "write.comment=" . ($commentId > 0 ? 'ok' : 'fail') . "\n";

        $repo->softDeleteComment($commentId);
        $listed = $public->listComments($pollId, null, 50);
        $visible = array_filter($listed['items'], static fn($r) => (int) $r['id'] === $commentId);
        echo "write.soft_delete_hidden=" . ($visible === [] ? 'ok' : 'fail') . "\n";

        $pdo->rollBack();
        echo "write.rolled_back=ok\n";
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

$writeMode = in_array('--write', $argv ?? [], true);

$root = discoveryFindProjectRoot();
discoveryLoadEnv($root);

if ($writeMode) {
    $testDb = trim((string) (getenv('DISCOVERY_TEST_DATABASE') ?: ($_ENV['DISCOVERY_TEST_DATABASE'] ?? '')));
    if ($testDb === '') {
        fwrite(STDERR, "ERROR: DISCOVERY_TEST_DATABASE is required for --write mode.\n");
        exit(1);
    }
}

$dbConfig = require $root . 'config/database.php';
$productionDb = (string) ($dbConfig['database'] ?? '');

if ($writeMode) {
    if ($testDb === $productionDb) {
        fwrite(STDERR, "ERROR: DISCOVERY_TEST_DATABASE must not equal production database ({$productionDb}).\n");
        exit(1);
    }

    $pdo = discoveryGetDbByName($root, $testDb);
    discoveryEnsureTables($pdo, $root);
    discoveryEnsurePublicMigration($pdo, $root);

    $config = discoveryConfig($root);
    $repo = new Discovery\DiscoveryRepository($pdo);
    $rate = new Discovery\DiscoveryRateLimiter($pdo);
    $public = new Discovery\DiscoveryPublicService($repo, $rate, $config);

    runWriteVerification($pdo, $public, $repo);
    echo "write_test_db_verification=ok\n";
    echo "done\n";
    exit(0);
}

$pdo = discoveryGetDb($root);
$config = discoveryConfig($root);
$repo = new Discovery\DiscoveryRepository($pdo);
$rate = new Discovery\DiscoveryRateLimiter($pdo);
$public = new Discovery\DiscoveryPublicService($repo, $rate, $config);

$pdo->exec('START TRANSACTION READ ONLY');
try {
    runReadOnlyVerification($pdo, $public, $config);
    echo "operational_write_sql_count=0\n";
    echo "read_only_verification=ok\n";
} catch (PDOException $e) {
    echo "read_only_verification=fail\n";
    echo "error=" . $e->getMessage() . "\n";
    exit(1);
} finally {
    if ($pdo->inTransaction()) {
        $pdo->exec('ROLLBACK');
    }
}

echo "done\n";
