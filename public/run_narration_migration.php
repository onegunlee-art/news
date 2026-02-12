<?php
/**
 * 과거 기사 내레이션 지스터 통일 (시청자/청취자 → 지스터)
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

    $chk = $pdo->query("SHOW COLUMNS FROM news LIKE 'narration'");
    if ($chk->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'narration 컬럼이 없습니다.']);
        exit;
    }

    $total = 0;
    // 시청자 여러분 → 지스터 여러분
    $stmt = $pdo->prepare("UPDATE news SET narration = REPLACE(narration, '시청자 여러분', '지스터 여러분') WHERE narration LIKE '%시청자 여러분%' AND narration IS NOT NULL AND narration != ''");
    $stmt->execute();
    $total += $stmt->rowCount();
    // 청취자가 → 지스터가
    $stmt = $pdo->prepare("UPDATE news SET narration = REPLACE(narration, '청취자가', '지스터가') WHERE narration LIKE '%청취자가%' AND narration IS NOT NULL AND narration != ''");
    $stmt->execute();
    $total += $stmt->rowCount();
    // 청취자에게 → 지스터에게
    $stmt = $pdo->prepare("UPDATE news SET narration = REPLACE(narration, '청취자에게', '지스터에게') WHERE narration LIKE '%청취자에게%' AND narration IS NOT NULL AND narration != ''");
    $stmt->execute();
    $total += $stmt->rowCount();

    echo json_encode([
        'success' => true,
        'message' => "{$total}건의 내레이션이 지스터로 통일되었습니다.",
        'updated_count' => $total
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB 오류: ' . $e->getMessage()]);
}
