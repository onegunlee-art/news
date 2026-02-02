<?php
/**
 * 테스트: why_important 필드 직접 업데이트
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
    $db = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    
    $testContent = "테스트: 이 사건은 중국 권력 구조의 본질적 변화를 보여줍니다.";
    
    // 직접 업데이트
    $stmt = $db->prepare("UPDATE news SET why_important = ? WHERE id = 10");
    $result = $stmt->execute([$testContent]);
    
    // 확인
    $stmt = $db->prepare("SELECT id, title, why_important FROM news WHERE id = 10");
    $stmt->execute();
    $news = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'update_result' => $result,
        'rows_affected' => $stmt->rowCount(),
        'news' => $news
    ], JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '오류: ' . $e->getMessage()]);
}
