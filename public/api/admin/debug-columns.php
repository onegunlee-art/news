<?php
/**
 * 디버그: 컬럼 존재 여부 확인
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
    
    // why_important 컬럼 확인
    $checkCol1 = $db->query("SHOW COLUMNS FROM news LIKE 'why_important'");
    $hasWhyImportant = $checkCol1->rowCount() > 0;
    $whyImportantData = $checkCol1->fetch();
    
    // source_url 컬럼 확인
    $checkCol2 = $db->query("SHOW COLUMNS FROM news LIKE 'source_url'");
    $hasSourceUrl = $checkCol2->rowCount() > 0;
    $sourceUrlData = $checkCol2->fetch();
    
    // 모든 컬럼 목록
    $allCols = $db->query("SHOW COLUMNS FROM news");
    $allColumns = $allCols->fetchAll();
    
    echo json_encode([
        'success' => true,
        'hasWhyImportant' => $hasWhyImportant,
        'whyImportantData' => $whyImportantData,
        'hasSourceUrl' => $hasSourceUrl,
        'sourceUrlData' => $sourceUrlData,
        'allColumns' => array_column($allColumns, 'Field')
    ], JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '오류: ' . $e->getMessage()]);
}
