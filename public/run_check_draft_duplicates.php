<?php
/**
 * Draft 중복 점검 스크립트 (1회용)
 *
 * 시나리오:
 *  - 임시저장(POST) 첫 클릭이 네트워크 끊김으로 "Failed to fetch" → 서버는 이미 INSERT 완료
 *  - 사용자가 재클릭 → 같은 내용의 draft가 두 번째로 또 INSERT 됨
 *
 * 사용법:
 *   브라우저: https://thegist.co.kr/run_check_draft_duplicates.php?key=draftcheck-2026
 *   또는 CLI: php public/run_check_draft_duplicates.php
 *
 * 안전:
 *  - 읽기 전용(SELECT만 수행). DELETE/UPDATE 없음.
 *  - 간단한 키 보호로 외부 노출 차단(원하면 수정/삭제 가능).
 *
 * 점검이 끝나면 이 파일은 삭제하시는 것을 권장합니다.
 */

// ── 안전 키 (원하시는 값으로 바꾸셔도 되고, CLI로 돌리면 무시됨) ──
$ACCESS_KEY = 'draftcheck-2026';

$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
    if (($_GET['key'] ?? '') !== $ACCESS_KEY) {
        http_response_code(403);
        echo "Forbidden: access key required.\n";
        exit;
    }
}

// ── DB 설정 로드 (.env → config/database.php → 기본값 순) ──
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

// ── 컬럼 존재 여부 확인 (status, source_url, original_title) ──
$cols = [];
foreach ($pdo->query("DESCRIBE news") as $row) {
    $cols[$row['Field']] = true;
}
$hasStatus       = isset($cols['status']);
$hasSourceUrl    = isset($cols['source_url']);
$hasOriginalTitle = isset($cols['original_title']);
$hasUpdatedAt    = isset($cols['updated_at']);

if (!$hasStatus) {
    echo "이 DB에는 status 컬럼이 없어 draft 식별이 불가합니다. 마이그레이션 후 다시 실행하세요.\n";
    exit(1);
}

$line = function (string $title) { echo "\n==== {$title} ====\n"; };
$dump = function (array $rows) {
    if (empty($rows)) { echo "(해당 결과 없음)\n"; return; }
    foreach ($rows as $r) {
        echo str_pad((string)($r['id'] ?? ''), 8) . ' | ';
        echo str_pad((string)($r['created_at'] ?? ''), 21) . ' | ';
        if (isset($r['updated_at'])) echo str_pad((string)$r['updated_at'], 21) . ' | ';
        if (isset($r['status']))     echo str_pad((string)$r['status'], 9) . ' | ';
        $title = mb_substr((string)($r['title'] ?? ''), 0, 60, 'UTF-8');
        echo $title;
        if (isset($r['source_url']) && $r['source_url'] !== null && $r['source_url'] !== '') {
            echo ' | ' . mb_substr((string)$r['source_url'], 0, 80, 'UTF-8');
        }
        if (isset($r['url']) && (!isset($r['source_url']) || $r['source_url'] === null || $r['source_url'] === '')) {
            echo ' | ' . mb_substr((string)$r['url'], 0, 80, 'UTF-8');
        }
        if (isset($r['cnt'])) echo ' | cnt=' . $r['cnt'];
        if (isset($r['gap_seconds'])) echo ' | gap=' . $r['gap_seconds'] . 's';
        echo "\n";
    }
};

echo "Draft 중복 점검 시작 (DB: {$cfg['database']} / host: {$cfg['host']})\n";
echo "기준 시각: " . date('Y-m-d H:i:s') . "\n";

// ── 1) 최근 24시간 동안 생성된 draft 전체 ──
$line('① 최근 24시간 내 status=draft 전체');
$selBase = "id, title, " . ($hasSourceUrl ? "source_url, " : "") . "url, status, created_at" . ($hasUpdatedAt ? ", updated_at" : "");
$rows = $pdo->query("
    SELECT {$selBase}
    FROM news
    WHERE status = 'draft'
      AND created_at > NOW() - INTERVAL 1 DAY
    ORDER BY created_at DESC, id DESC
    LIMIT 100
")->fetchAll();
$dump($rows);
echo "총 " . count($rows) . "건\n";

// ── 2) source_url 중복 (가장 강력한 신호) ──
if ($hasSourceUrl) {
    $line('② source_url 중복 (draft 또는 published 무관, 같은 source_url가 2건 이상)');
    $stmt = $pdo->query("
        SELECT source_url, COUNT(*) AS cnt, GROUP_CONCAT(id ORDER BY id) AS ids,
               MIN(created_at) AS first_at, MAX(created_at) AS last_at
        FROM news
        WHERE source_url IS NOT NULL AND source_url <> ''
        GROUP BY source_url
        HAVING cnt >= 2
        ORDER BY last_at DESC
        LIMIT 50
    ");
    $rowsDup = $stmt->fetchAll();
    if (empty($rowsDup)) {
        echo "(source_url 중복 없음)\n";
    } else {
        foreach ($rowsDup as $r) {
            echo "ids=[{$r['ids']}] cnt={$r['cnt']} first={$r['first_at']} last={$r['last_at']}\n  url={$r['source_url']}\n";
        }
    }
}

// ── 3) "동일 title + 짧은 시간차" — 이번 사건에 가장 직접적인 신호 ──
$line('③ 같은 title이 10분 이내에 2건 이상 생성된 draft 후보');
$sql3 = "
    SELECT a.id AS id1, b.id AS id2,
           a.title, a.created_at AS at1, b.created_at AS at2,
           TIMESTAMPDIFF(SECOND, a.created_at, b.created_at) AS gap_seconds,
           a.status AS s1, b.status AS s2
           " . ($hasSourceUrl ? ", a.source_url AS source_url" : "") . "
    FROM news a
    INNER JOIN news b
      ON a.id < b.id
     AND a.title = b.title
     AND b.created_at BETWEEN a.created_at AND a.created_at + INTERVAL 10 MINUTE
    WHERE a.created_at > NOW() - INTERVAL 7 DAY
      AND (a.status = 'draft' OR b.status = 'draft')
      AND a.title <> ''
    ORDER BY a.created_at DESC
    LIMIT 50
";
$rowsTitleDup = $pdo->query($sql3)->fetchAll();
if (empty($rowsTitleDup)) {
    echo "(동일 title 10분이내 중복 후보 없음)\n";
} else {
    foreach ($rowsTitleDup as $r) {
        echo "ids=[{$r['id1']}, {$r['id2']}] gap={$r['gap_seconds']}s status=[{$r['s1']}, {$r['s2']}] at=[{$r['at1']} → {$r['at2']}]\n  title=" . mb_substr((string)$r['title'], 0, 80, 'UTF-8') . "\n";
        if (!empty($r['source_url'])) echo "  source_url={$r['source_url']}\n";
    }
}

// ── 4) original_title 기반 ──
if ($hasOriginalTitle) {
    $line('④ 같은 original_title이 10분 이내에 2건 이상 생성된 후보');
    $sql4 = "
        SELECT a.id AS id1, b.id AS id2,
               a.original_title, a.created_at AS at1, b.created_at AS at2,
               TIMESTAMPDIFF(SECOND, a.created_at, b.created_at) AS gap_seconds,
               a.status AS s1, b.status AS s2
        FROM news a
        INNER JOIN news b
          ON a.id < b.id
         AND a.original_title IS NOT NULL AND a.original_title <> ''
         AND a.original_title = b.original_title
         AND b.created_at BETWEEN a.created_at AND a.created_at + INTERVAL 10 MINUTE
        WHERE a.created_at > NOW() - INTERVAL 7 DAY
          AND (a.status = 'draft' OR b.status = 'draft')
        ORDER BY a.created_at DESC
        LIMIT 50
    ";
    $rowsOrigDup = $pdo->query($sql4)->fetchAll();
    if (empty($rowsOrigDup)) {
        echo "(동일 original_title 10분이내 중복 후보 없음)\n";
    } else {
        foreach ($rowsOrigDup as $r) {
            echo "ids=[{$r['id1']}, {$r['id2']}] gap={$r['gap_seconds']}s status=[{$r['s1']}, {$r['s2']}] at=[{$r['at1']} → {$r['at2']}]\n  original_title=" . mb_substr((string)$r['original_title'], 0, 80, 'UTF-8') . "\n";
        }
    }
}

// ── 5) admin:// 더미 URL을 가진 draft (source_url 미입력 + 자동 생성 URL) ──
$line('⑤ admin:// 더미 URL draft (참고용 - 같은 source_url이 없어도 빈 source_url 케이스)');
$rowsAdmin = $pdo->query("
    SELECT {$selBase}
    FROM news
    WHERE status = 'draft'
      AND url LIKE 'admin://%'
      AND created_at > NOW() - INTERVAL 7 DAY
    ORDER BY created_at DESC, id DESC
    LIMIT 50
")->fetchAll();
$dump($rowsAdmin);
echo "총 " . count($rowsAdmin) . "건\n";

echo "\n==== 점검 종료 ====\n";
echo "→ ②③④에서 동일 source_url/title/original_title이 짧은 간격으로 2건 잡혔다면, 첫 클릭이 네트워크 단절로 응답을 못 받았을 뿐 서버에서는 이미 저장된 케이스입니다.\n";
echo "→ 정리할 때는 둘 중 더 늦게(or 더 일찍) 생성된 id 한쪽만 Admin UI에서 삭제하시면 됩니다.\n";
echo "→ 이 스크립트는 SELECT만 수행하므로 데이터에 영향이 없습니다. 점검 후 파일을 삭제하세요.\n";
