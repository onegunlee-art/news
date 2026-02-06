<?php
/**
 * DB 마이그레이션: narration 컬럼 추가
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
    
    // narration 컬럼 존재 여부 확인
    $checkCol = $db->query("SHOW COLUMNS FROM news LIKE 'narration'");
    if ($checkCol->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'narration 컬럼이 이미 존재합니다.'
        ]);
        exit;
    }
    
    // why_important 컬럼 존재 여부 확인 (narration은 why_important 뒤에 추가)
    $checkWhyImportant = $db->query("SHOW COLUMNS FROM news LIKE 'why_important'");
    if ($checkWhyImportant->rowCount() > 0) {
        // why_important 뒤에 추가
        $db->exec("ALTER TABLE news ADD COLUMN narration TEXT NULL AFTER why_important");
    } else {
        // why_important가 없으면 content 뒤에 추가
        $db->exec("ALTER TABLE news ADD COLUMN narration TEXT NULL AFTER content");
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'narration 컬럼이 추가되었습니다.'
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '오류: ' . $e->getMessage()
    ]);
}
