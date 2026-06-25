<?php
/**
 * 기업 고객 일괄 등록용 DB 마이그레이션 (1회 실행)
 * GET /api/admin/run-corporate-users-migration.php
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$projectRoot = dirname(__DIR__, 3) . '/';
if (!is_file($projectRoot . 'database/migrations/add_corporate_users.sql')) {
    $projectRoot = dirname(__DIR__, 2) . '/';
}

require_once __DIR__ . '/../lib/admin_auth.php';

$cfg = ['host' => 'localhost', 'database' => 'ailand', 'username' => 'ailand', 'password' => '', 'charset' => 'utf8mb4'];

if (is_file($projectRoot . '.env')) {
    foreach (file($projectRoot . '.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || ($line[0] ?? '') === '#') {
            continue;
        }
        if (strpos($line, '=') !== false) {
            [$k, $v] = explode('=', $line, 2);
            putenv(trim($k) . '=' . trim($v, " \t\"'"));
        }
    }
}

$cfg['host'] = getenv('DB_HOST') ?: $cfg['host'];
$cfg['database'] = getenv('DB_DATABASE') ?: $cfg['database'];
$cfg['username'] = getenv('DB_USERNAME') ?: $cfg['username'];
$cfg['password'] = getenv('DB_PASSWORD') ?: $cfg['password'];

if (is_file($projectRoot . 'config/database.php')) {
    $dbCfg = require $projectRoot . 'config/database.php';
    if (is_array($dbCfg)) {
        $cfg['host'] = $dbCfg['host'] ?? $cfg['host'];
        $cfg['database'] = $dbCfg['database'] ?? $dbCfg['dbname'] ?? $cfg['database'];
        $cfg['username'] = $dbCfg['username'] ?? $cfg['username'];
        $cfg['password'] = $dbCfg['password'] ?? $cfg['password'];
    }
}

$steps = [];

try {
    $pdo = new PDO(
        "mysql:host={$cfg['host']};dbname={$cfg['database']};charset={$cfg['charset']}",
        $cfg['username'],
        $cfg['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    requireAdminApi($pdo);

    $hasCompanyTag = (bool) $pdo->query("SHOW COLUMNS FROM users LIKE 'company_tag'")->fetch(PDO::FETCH_ASSOC);
    if (!$hasCompanyTag) {
        $pdo->exec("
            ALTER TABLE `users`
              ADD COLUMN `company_tag` VARCHAR(50) NULL
              COMMENT '기업 고객 소속 (예: hyundai, samsung)'
              AFTER `profile_image`
        ");
        $steps[] = 'users.company_tag added';
    } else {
        $steps[] = 'users.company_tag already exists';
    }

    $hasIndex = (bool) $pdo->query("SHOW INDEX FROM users WHERE Key_name = 'idx_users_company_tag'")->fetch(PDO::FETCH_ASSOC);
    if (!$hasIndex) {
        try {
            $pdo->exec('CREATE INDEX `idx_users_company_tag` ON `users` (`company_tag`)');
            $steps[] = 'idx_users_company_tag created';
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate key name')) {
                $steps[] = 'idx_users_company_tag already exists';
            } else {
                throw $e;
            }
        }
    } else {
        $steps[] = 'idx_users_company_tag already exists';
    }

    $hasTable = (bool) $pdo->query("SHOW TABLES LIKE 'corporate_otp_skip'")->fetch(PDO::FETCH_NUM);
    if (!$hasTable) {
        $pdo->exec("
            CREATE TABLE `corporate_otp_skip` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK',
                `email` VARCHAR(255) NOT NULL COMMENT 'OTP 생략 이메일 (소문자)',
                `company_tag` VARCHAR(50) NOT NULL COMMENT '기업 태그',
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '등록 시각',
                `created_by` INT UNSIGNED NULL COMMENT '등록한 admin user id',
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_corporate_otp_skip_email` (`email`),
                KEY `idx_corporate_otp_skip_company` (`company_tag`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='기업 고객 로그인 OTP 생략 목록'
        ");
        $steps[] = 'corporate_otp_skip table created';
    } else {
        $steps[] = 'corporate_otp_skip already exists';
    }

    echo json_encode([
        'success' => true,
        'message' => '기업 고객 마이그레이션 완료',
        'steps' => $steps,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '마이그레이션 실패: ' . $e->getMessage(),
        'steps' => $steps,
    ], JSON_UNESCAPED_UNICODE);
}
