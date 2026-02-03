<?php
/**
 * bookmarks 테이블 한 번 생성 (즐겨찾기 기능 활성화)
 * 
 * 브라우저에서 이 파일을 한 번만 실행하세요.
 * 예: https://ailand.dothome.co.kr/setup_bookmarks.php
 * 완료 후 보안을 위해 이 파일을 삭제하거나 이름을 변경하세요.
 */
header('Content-Type: application/json; charset=utf-8');

$configPath = null;
if (file_exists(__DIR__ . '/config/database.php')) {
    $configPath = __DIR__ . '/config/database.php';  // 서버: html/config
} elseif (file_exists(__DIR__ . '/../config/database.php')) {
    $configPath = __DIR__ . '/../config/database.php';  // 로컬: project/config
}

if (!$configPath || !file_exists($configPath)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'config/database.php를 찾을 수 없습니다.',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$config = require $configPath;
$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
    $config['host'] ?? 'localhost',
    $config['port'] ?? '3306',
    $config['database'] ?? 'ailand',
    $config['charset'] ?? 'utf8mb4'
);

try {
    $pdo = new PDO(
        $dsn,
        $config['username'] ?? '',
        $config['password'] ?? '',
        $config['options'] ?? [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ]
    );

    $sql = "CREATE TABLE IF NOT EXISTS `bookmarks` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '북마크 고유 ID',
        `user_id` INT UNSIGNED NOT NULL COMMENT '사용자 ID',
        `news_id` INT UNSIGNED NOT NULL COMMENT '뉴스 ID',
        `memo` TEXT NULL COMMENT '사용자 메모',
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성 시간',
        PRIMARY KEY (`id`),
        UNIQUE INDEX `idx_bookmarks_user_news` (`user_id`, `news_id`),
        INDEX `idx_bookmarks_user_id` (`user_id`),
        INDEX `idx_bookmarks_news_id` (`news_id`),
        INDEX `idx_bookmarks_created_at` (`created_at`),
        CONSTRAINT `fk_bookmarks_user` FOREIGN KEY (`user_id`)
            REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT `fk_bookmarks_news` FOREIGN KEY (`news_id`)
            REFERENCES `news` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='사용자 북마크 테이블'";

    $pdo->exec($sql);

    echo json_encode([
        'success' => true,
        'message' => 'bookmarks 테이블이 생성되었습니다. 즐겨찾기를 사용할 수 있습니다. 이 파일을 삭제하거나 이름을 변경하세요.',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '테이블 생성 실패: ' . $e->getMessage(),
        'hint' => 'users, news 테이블이 먼저 있어야 합니다. database/migrations/add_bookmarks_table.sql 을 phpMyAdmin에서 실행해 보세요.',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
