<?php
/**
 * article_chat_guest_sessions.sql 적용 (Windows 등 mysql CLI 없을 때)
 * 프로젝트 루트에서: php database/migrations/run_article_chat_guest_sessions.php
 */
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/public/api/lib/env_bootstrap.php';

$host = getenv('DB_HOST') ?: 'localhost';
$port = (int) (getenv('DB_PORT') ?: '3306');
$dbname = getenv('DB_DATABASE') ?: 'ailand';
$user = getenv('DB_USERNAME') ?: 'ailand';
$pass = getenv('DB_PASSWORD') !== false ? (string) getenv('DB_PASSWORD') : '';

$dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

$opts = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
];
if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
    $opts[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci';
}

try {
    $pdo = new PDO($dsn, $user, $pass, $opts);
} catch (PDOException $e) {
    fwrite(STDERR, "DB 연결 실패: " . $e->getMessage() . "\n");
    exit(1);
}

echo "Connected to {$dbname}@{$host}\n";

function runQuiet(PDO $pdo, string $sql, string $label): void
{
    try {
        $pdo->exec($sql);
        echo "[OK] {$label}\n";
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (stripos($msg, 'Duplicate') !== false
            || stripos($msg, 'check that column/key exists') !== false
            || stripos($msg, "Unknown key") !== false
            || stripos($msg, "Can't DROP") !== false
            || stripos($msg, "doesn't exist") !== false) {
            echo "[SKIP] {$label} — " . $msg . "\n";
            return;
        }
        fwrite(STDERR, "[FAIL] {$label}: {$msg}\n");
        exit(1);
    }
}

// 1) 빈 session_key
runQuiet(
    $pdo,
    "UPDATE `article_chat_sessions` SET `session_key` = CONCAT('legacy_', `id`, '_', UNIX_TIMESTAMP()) WHERE `session_key` = '' OR `session_key` IS NULL",
    'UPDATE empty session_key'
);

// 2) FK 제거 (이름이 서버마다 다를 수 있음)
$fkStmt = $pdo->query(
    "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'article_chat_sessions' AND CONSTRAINT_TYPE = 'FOREIGN KEY'"
);
$fks = $fkStmt ? $fkStmt->fetchAll(PDO::FETCH_COLUMN) : [];
foreach ($fks as $fkName) {
    $fkName = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $fkName);
    if ($fkName === '') {
        continue;
    }
    runQuiet($pdo, "ALTER TABLE `article_chat_sessions` DROP FOREIGN KEY `{$fkName}`", "DROP FK {$fkName}");
}

// 3) user_id NULL 허용
runQuiet(
    $pdo,
    "ALTER TABLE `article_chat_sessions` MODIFY `user_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'NULL = guest'",
    'MODIFY user_id NULL'
);

// 4) 기존 유니크 제거
runQuiet(
    $pdo,
    'ALTER TABLE `article_chat_sessions` DROP INDEX `uniq_article_chat_news_user`',
    'DROP INDEX uniq_article_chat_news_user'
);

// 5) 새 유니크 (이미 있으면 SKIP)
$idx = $pdo->query(
    "SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'article_chat_sessions' AND INDEX_NAME = 'uniq_article_chat_news_session' LIMIT 1"
);
if ($idx && $idx->fetchColumn()) {
    echo "[SKIP] UNIQUE uniq_article_chat_news_session already exists\n";
} else {
    runQuiet(
        $pdo,
        'ALTER TABLE `article_chat_sessions` ADD UNIQUE KEY `uniq_article_chat_news_session` (`news_id`, `session_key`)',
        'ADD UNIQUE uniq_article_chat_news_session'
    );
}

echo "\nDone. Verify: SHOW CREATE TABLE article_chat_sessions;\n";
