<?php
/**
 * 테스트용 계정 생성 스크립트
 * 실행: 프로젝트 루트에서 php database/seed_test_user.php
 *
 * 생성 계정:
 *   이메일: test@test.com
 *   비밀번호: Test1234!
 */

declare(strict_types=1);

$projectRoot = dirname(__DIR__);

// Autoload
spl_autoload_register(function (string $class) use ($projectRoot): void {
    $prefix = 'App\\';
    $baseDir = $projectRoot . '/src/backend/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

try {
    $db = App\Core\Database::getInstance();
} catch (Throwable $e) {
    fwrite(STDERR, "DB 연결 실패: " . $e->getMessage() . "\n");
    exit(1);
}

// password_hash 컬럼 추가 (없으면)
try {
    $db->executeQuery("ALTER TABLE `users` ADD COLUMN `password_hash` VARCHAR(255) NULL COMMENT '비밀번호 해시' AFTER `profile_image`");
    echo "users 테이블에 password_hash 컬럼을 추가했습니다.\n";
} catch (Throwable $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        // 이미 있으면 무시
    } else {
        fwrite(STDERR, "ALTER TABLE 경고: " . $e->getMessage() . "\n");
    }
}

$email = 'test@test.com';
$password = 'Test1234!';
$nickname = '테스트유저';

// 이미 존재하는지 확인
$existing = $db->fetchOne("SELECT id FROM users WHERE email = :email", ['email' => $email]);
if ($existing) {
    echo "이미 test@test.com 계정이 존재합니다. 비밀번호를 Test1234! 로 변경하려면 DB에서 해당 유저의 password_hash를 수동으로 변경하세요.\n";
    echo "테스트 로그인: 이메일 test@test.com / 비밀번호 Test1234!\n";
    exit(0);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$db->insert('users', [
    'email' => $email,
    'password_hash' => $hash,
    'nickname' => $nickname,
    'role' => 'user',
    'status' => 'active',
]);

echo "테스트 계정이 생성되었습니다.\n";
echo "  이메일: test@test.com\n";
echo "  비밀번호: Test1234!\n";
echo "로그인 페이지에서 위 정보로 로그인할 수 있습니다.\n";
