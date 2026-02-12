<?php
/**
 * 과거 기사 내레이션 "시청자 여러분" → "지스터 여러분" 일괄 치환
 * 실행: 브라우저에서 /run_narration_migration.php 또는 CLI: php run_narration_migration.php
 */
header('Content-Type: application/json; charset=utf-8');

$dbConfig = [
    'host' => 'localhost',
    'dbname' => 'ailand',
    'username' => 'ailand',
    'password' => 'romi4120!',
    'charset' => 'utf8mb4'
];

try {
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // narration 컬럼 존재 확인
    $chk = $pdo->query("SHOW COLUMNS FROM news LIKE 'narration'");
    if ($chk->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'narration 컬럼이 없습니다.']);
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE news
        SET narration = REPLACE(narration, '시청자 여러분', '지스터 여러분')
        WHERE narration LIKE :pattern
          AND narration IS NOT NULL
          AND narration != ''
    ");
    $stmt->execute(['pattern' => '%시청자 여러분%']);
    $updated = $stmt->rowCount();

    echo json_encode([
        'success' => true,
        'message' => "{$updated}건의 내레이션이 '지스터 여러분'으로 수정되었습니다.",
        'updated_count' => $updated
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB 오류: ' . $e->getMessage()]);
}
