<?php
/**
 * 마이그레이션 러너: news.ai_original_snapshot JSON 컬럼 추가 + 스키마 캐시 무효화 (1회용)
 *
 * 배경:
 *  - 임시저장(draft) → 게시(published) 워크플로우에서 기존 코드는 ai_original을 보내지 않음
 *    → Judgement Layer가 거의 작동하지 않아 judgement_records가 수집되지 않음.
 *  - 임시저장 시 ai_original을 news.ai_original_snapshot 컬럼에 보존해 두면,
 *    게시 PUT 시점에 storeJudgementRecord가 그 스냅샷을 읽어 비교에 사용.
 *
 * 사용법:
 *   브라우저: https://thegist.co.kr/run_add_ai_original_snapshot.php?key=judgement-snapshot-2026
 *   또는 CLI: php public/run_add_ai_original_snapshot.php
 *
 * 동작:
 *  - news 테이블에 ai_original_snapshot JSON NULL 컬럼 추가 (이미 있으면 skip)
 *  - storage/cache/news_schema.json 삭제 → news.php가 다음 요청에서 새 컬럼을 인식
 *
 * 안전:
 *  - ALTER 1회 + 캐시 파일 삭제만 수행. 데이터 변형 없음.
 *  - 한 번 실행 후 파일 삭제 권장.
 */

$ACCESS_KEY = 'judgement-snapshot-2026';

$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
    if (($_GET['key'] ?? '') !== $ACCESS_KEY) {
        http_response_code(403);
        echo "Forbidden: access key required.\n";
        exit;
    }
}

$projectRoot = dirname(__DIR__) . '/';
if (file_exists($projectRoot . '.env')) {
    foreach (file($projectRoot . '.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') !== false) {
            [$k, $v] = explode('=', $line, 2);
            putenv(trim($k) . '=' . trim($v, " \t\"'"));
        }
    }
}

$cfg = [
    'host'     => getenv('DB_HOST')     ?: 'localhost',
    'database' => getenv('DB_DATABASE') ?: 'ailand',
    'username' => getenv('DB_USERNAME') ?: 'ailand',
    'password' => getenv('DB_PASSWORD') ?: 'romi4120!',
    'charset'  => 'utf8mb4',
];

$cfgPath = $projectRoot . 'config/database.php';
if (file_exists($cfgPath)) {
    $content = file_get_contents($cfgPath);
    if (preg_match("/'host'\s*=>\s*(?:getenv\('DB_HOST'\)\s*\?\:\s*)?'([^']*)'/", $content, $m))     $cfg['host']     = $m[1];
    if (preg_match("/'database'\s*=>\s*(?:getenv\('DB_DATABASE'\)\s*\?\:\s*)?'([^']*)'/", $content, $m)) $cfg['database'] = $m[1];
    if (preg_match("/'username'\s*=>\s*(?:getenv\('DB_USERNAME'\)\s*\?\:\s*)?'([^']*)'/", $content, $m)) $cfg['username'] = $m[1];
    if (preg_match("/'password'\s*=>\s*(?:getenv\('DB_PASSWORD'\)\s*\?\:\s*)?'([^']*)'/", $content, $m)) $cfg['password'] = $m[1];
}

$dsn = "mysql:host={$cfg['host']};dbname={$cfg['database']};charset={$cfg['charset']}";

try {
    $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    ]);
} catch (Exception $e) {
    echo "DB connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "마이그레이션 시작 (DB: {$cfg['database']} / host: {$cfg['host']})\n";
echo "기준 시각: " . date('Y-m-d H:i:s') . "\n\n";

// 1) 컬럼 존재 여부 확인
$exists = false;
foreach ($pdo->query("DESCRIBE news") as $row) {
    if (($row['Field'] ?? '') === 'ai_original_snapshot') {
        $exists = true;
        break;
    }
}

if ($exists) {
    echo "[1/2] ai_original_snapshot 컬럼: 이미 존재함 → ALTER skip\n";
} else {
    echo "[1/2] ai_original_snapshot 컬럼 추가 중...\n";
    try {
        $pdo->exec("
            ALTER TABLE news
            ADD COLUMN ai_original_snapshot JSON NULL
            COMMENT 'GPT 분석 원본 스냅샷 - Judgement Layer 비교용 (임시저장 시 보존)'
        ");
        echo "       → ALTER 성공\n";
    } catch (Exception $e) {
        echo "       → ALTER 실패: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// 2) 스키마 캐시 무효화
$cacheFile = $projectRoot . 'storage/cache/news_schema.json';
if (file_exists($cacheFile)) {
    if (@unlink($cacheFile)) {
        echo "[2/2] storage/cache/news_schema.json 삭제 → 다음 요청에서 새 컬럼 자동 인식\n";
    } else {
        echo "[2/2] storage/cache/news_schema.json 삭제 실패 (권한 확인 필요). 수동 삭제하거나 1시간 후 자동 갱신됩니다.\n";
    }
} else {
    echo "[2/2] storage/cache/news_schema.json 없음 → skip (다음 요청에서 새로 생성됨)\n";
}

// 3) 검증
echo "\n검증:\n";
$cols = [];
foreach ($pdo->query("DESCRIBE news") as $row) {
    $cols[$row['Field']] = $row['Type'];
}
if (isset($cols['ai_original_snapshot'])) {
    echo "  ✓ news.ai_original_snapshot 존재 (Type: {$cols['ai_original_snapshot']})\n";
} else {
    echo "  ✗ news.ai_original_snapshot 미존재 — ALTER가 실패했을 가능성. 위 메시지를 확인하세요.\n";
    exit(1);
}

echo "\n==== 마이그레이션 종료 ====\n";
echo "→ 이제 임시저장 시 AdminPage가 ai_original을 함께 보내고, news.php가 ai_original_snapshot 컬럼에 보존합니다.\n";
echo "→ 이 게시본에서 PUT published가 일어날 때, news.php가 자동으로 스냅샷을 읽어 storeJudgementRecord에 비교 입력으로 사용합니다.\n";
echo "→ 이 스크립트는 1회용이므로, 실행 확인 후 파일을 삭제하시는 것을 권장합니다.\n";
