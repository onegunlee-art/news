<?php
/**
 * 기사 메타데이터 컬럼 추가 스크립트
 * 브라우저에서 한 번 실행하면 됩니다.
 */

header('Content-Type: text/html; charset=utf-8');

echo "<h1>기사 메타데이터 컬럼 추가</h1>";

// 데이터베이스 설정
$dbConfig = [
    'host' => 'localhost',
    'dbname' => 'ailand',
    'username' => 'ailand',
    'password' => 'romi4120!',
    'charset' => 'utf8mb4'
];

try {
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    echo "<p>데이터베이스 연결 성공!</p>";
    
    // 컬럼 추가 (이미 존재하면 무시)
    $columns = [
        ['original_source', 'VARCHAR(255) DEFAULT NULL', 'source'],
        ['author', 'VARCHAR(255) DEFAULT NULL', 'original_source'],
        ['published_at', 'VARCHAR(100) DEFAULT NULL', 'author'],
    ];
    
    foreach ($columns as $col) {
        $colName = $col[0];
        $colDef = $col[1];
        $afterCol = $col[2];
        
        // 컬럼 존재 여부 확인
        $checkStmt = $db->query("SHOW COLUMNS FROM news LIKE '$colName'");
        if ($checkStmt->rowCount() > 0) {
            echo "<p style='color: orange;'>⚠️ '$colName' 컬럼이 이미 존재합니다.</p>";
        } else {
            try {
                $db->exec("ALTER TABLE news ADD COLUMN $colName $colDef AFTER $afterCol");
                echo "<p style='color: green;'>✅ '$colName' 컬럼이 추가되었습니다!</p>";
            } catch (PDOException $e) {
                // AFTER 컬럼이 없을 경우 그냥 끝에 추가
                try {
                    $db->exec("ALTER TABLE news ADD COLUMN $colName $colDef");
                    echo "<p style='color: green;'>✅ '$colName' 컬럼이 추가되었습니다!</p>";
                } catch (PDOException $e2) {
                    echo "<p style='color: red;'>❌ '$colName' 컬럼 추가 실패: " . $e2->getMessage() . "</p>";
                }
            }
        }
    }
    
    echo "<hr>";
    echo "<p style='color: blue;'>작업 완료! 이제 이 파일을 삭제해도 됩니다.</p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>데이터베이스 연결 실패: " . $e->getMessage() . "</p>";
}
?>
