<?php
declare(strict_types=1);

/**
 * Discovery 자동 발행 + 이메일 알림 (매일 06:00 KST)
 * - 05시 생성분 중 검증 통과(1개 이상)를 published로 전환
 * - 0개/실패면 폴백 (전날 유지)
 * - 매일 이메일 요약 발송
 * 
 * Usage: php cron/discovery_publish.php [YYYY-MM-DD]
 */

require_once __DIR__ . '/../src/discovery/bootstrap.php';
require_once __DIR__ . '/../src/backend/autoload.php';

use App\Services\MailService;

$root = discoveryFindProjectRoot();
discoveryLoadEnv($root);

// Kill switch check
$cronEnabled = $_ENV['ENABLE_DISCOVERY_CRON'] ?? getenv('ENABLE_DISCOVERY_CRON');
if (is_string($cronEnabled) && strtolower(trim($cronEnabled)) === 'false') {
    echo "SKIP: ENABLE_DISCOVERY_CRON=false\n";
    exit(0);
}

discoveryEnsureEnabled($root);

$pdo = discoveryGetDb($root);
$config = discoveryConfig($root);
$repo = new Discovery\DiscoveryRepository($pdo);

$date = $argv[1] ?? discoveryTodayKst();
$notifyEmail = $_ENV['DISCOVERY_NOTIFY_EMAIL'] ?? getenv('DISCOVERY_NOTIFY_EMAIL') ?: 'onegunlee@gmail.com';

echo "=== Discovery Cron Publish date={$date} ===\n";

// Read last generate result
$lastGenPath = $root . 'storage/discovery_last_generate.json';
$lastGen = is_file($lastGenPath) ? json_decode(file_get_contents($lastGenPath), true) : null;

$edition = $repo->findEditionByDate($date);
$publishResult = [
    'date' => $date,
    'published' => false,
    'verified_count' => 0,
    'fallback' => false,
    'email_sent' => false,
    'error' => null,
];

try {
    if ($edition === null) {
        $publishResult['error'] = 'edition_not_found';
        $publishResult['fallback'] = true;
        echo "WARN: No edition for {$date}, fallback to previous\n";
    } else {
        $changeCount = (int) ($edition['change_count'] ?? 0);
        $publishResult['verified_count'] = $changeCount;

        if ($changeCount >= 1) {
            // Publish
            $repo->updateEditionStatus((int) $edition['id'], 'published');
            $publishResult['published'] = true;
            echo "OK: Published edition {$edition['id']} with {$changeCount} changes\n";
        } else {
            // Fallback - 0 changes
            $publishResult['error'] = 'zero_verified_changes';
            $publishResult['fallback'] = true;
            echo "WARN: 0 verified changes, fallback to previous\n";
        }
    }
} catch (Throwable $e) {
    $publishResult['error'] = $e->getMessage();
    $publishResult['fallback'] = true;
    fwrite(STDERR, "ERROR: {$e->getMessage()}\n");
}

// Load changes for email
$changes = [];
$discarded = [];
if ($edition !== null && $publishResult['published']) {
    $changes = $repo->findChangesByEditionId((int) $edition['id']);
}
if ($lastGen !== null && isset($lastGen['discarded'])) {
    $discarded = $lastGen['discarded'] ?? [];
}

// Send email notification
$emailResult = sendDiscoveryEmail(
    $notifyEmail,
    $date,
    $publishResult,
    $changes,
    $discarded,
    $lastGen
);
$publishResult['email_sent'] = $emailResult;

echo json_encode($publishResult, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

file_put_contents(
    $root . 'storage/discovery_last_publish.json',
    json_encode($publishResult, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
);

exit($publishResult['published'] || $publishResult['fallback'] ? 0 : 1);

// ========== Email Helper ==========

function sendDiscoveryEmail(
    string $to,
    string $date,
    array $publishResult,
    array $changes,
    array $discarded,
    ?array $lastGen
): bool {
    try {
        $mail = new MailService();
    } catch (Throwable $e) {
        echo "WARN: MailService init failed: {$e->getMessage()}\n";
        return false;
    }

    $verifiedCount = $publishResult['verified_count'] ?? 0;
    $published = $publishResult['published'] ?? false;
    $fallback = $publishResult['fallback'] ?? false;
    $error = $publishResult['error'] ?? null;

    $extractionFull = $lastGen['extraction_full'] ?? 0;
    $extractionSummary = $lastGen['extraction_summary_only'] ?? 0;
    $extractionTotal = $extractionFull + $extractionSummary;
    $extractionRate = $extractionTotal > 0 ? round($extractionFull / $extractionTotal * 100) : 0;

    // Subject
    if ($error !== null || $fallback) {
        $subject = "[Discovery] ⚠️ {$date} - 확인 필요 ({$verifiedCount}개, {$error})";
    } else {
        $subject = "[Discovery] ✅ {$date} 발행 완료 ({$verifiedCount}개)";
    }

    // Text body
    $lines = [
        "=== Discovery 자동화 결과 ({$date}) ===",
        "",
        "상태: " . ($published ? "✅ 발행됨" : ($fallback ? "⚠️ 폴백 (전날 유지)" : "❌ 실패")),
        "검증 통과: {$verifiedCount}개",
        "원문 추출률: {$extractionRate}% ({$extractionFull}/{$extractionTotal})",
        "",
    ];

    if ($error !== null) {
        $lines[] = "⚠️ 오류: {$error}";
        $lines[] = "";
    }

    if (count($changes) > 0) {
        $lines[] = "--- 발행된 Changes ---";
        foreach ($changes as $i => $c) {
            $title = $c['title'] ?? '(제목 없음)';
            $category = $c['category'] ?? '?';
            $sourceDomain = '?';
            $sources = $c['sources'] ?? [];
            if (is_string($sources)) {
                $sources = json_decode($sources, true) ?: [];
            }
            if (!empty($sources[0]['url'])) {
                $sourceDomain = parse_url($sources[0]['url'], PHP_URL_HOST) ?: '?';
            }
            $lines[] = sprintf("#%d [%s] %s (%s)", $i + 1, $category, $title, $sourceDomain);
        }
        $lines[] = "";
    }

    if (count($discarded) > 0) {
        $lines[] = "--- 폐기된 후보 ---";
        $byReason = [];
        foreach ($discarded as $d) {
            $reason = $d['reason'] ?? 'unknown';
            $byReason[$reason] = ($byReason[$reason] ?? 0) + 1;
        }
        foreach ($byReason as $reason => $count) {
            $lines[] = "- {$reason}: {$count}개";
        }
        $lines[] = "";
    }

    $lines[] = "---";
    $lines[] = "킬스위치: ENABLE_DISCOVERY_CRON=false";
    $lines[] = "Admin: https://www.thegist.co.kr/admin/discovery";

    $textBody = implode("\n", $lines);

    try {
        $sent = $mail->send($to, $subject, $textBody);
        echo "EMAIL: " . ($sent ? "sent to {$to}" : "failed") . "\n";
        return $sent;
    } catch (Throwable $e) {
        echo "EMAIL ERROR: {$e->getMessage()}\n";
        return false;
    }
}
