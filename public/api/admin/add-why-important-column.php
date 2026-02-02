<?php
/**
 * DB 마이그레이션: why_important 컬럼 추가
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
    
    // why_important 컬럼 존재 여부 확인
    $checkCol = $db->query("SHOW COLUMNS FROM news LIKE 'why_important'");
    if ($checkCol->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'why_important 컬럼이 이미 존재합니다.'
        ]);
        exit;
    }
    
    // why_important 컬럼 추가 (content 뒤에)
    $db->exec("ALTER TABLE news ADD COLUMN why_important TEXT NULL AFTER content");
    
    echo json_encode([
        'success' => true,
        'message' => 'why_important 컬럼이 추가되었습니다.'
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '오류: ' . $e->getMessage()
    ]);
}
