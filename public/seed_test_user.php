<?php
/**
 * 테스트 계정 생성 (웹에서 1회 실행)
 * 브라우저에서 https://사이트주소/seed_test_user.php 접속
 * 완료 후 보안을 위해 이 파일을 삭제하세요.
 */
declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

$projectRoot = dirname(__DIR__);

spl_autoload_register(function (string $class) use ($projectRoot): void {
    $prefix = 'App\\';
    $baseDir = $projectRoot . '/src/backend/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require $file;
});

$messages = [];
$error = null;

try {
    $db = App\Core\Database::getInstance();
} catch (Throwable $e) {
    $error = 'DB 연결 실패: ' . htmlspecialchars($e->getMessage());
}

if (!$error) {
    try {
        $db->executeQuery("ALTER TABLE `users` ADD COLUMN `password_hash` VARCHAR(255) NULL COMMENT '비밀번호 해시' AFTER `profile_image`");
        $messages[] = 'users 테이블에 password_hash 컬럼을 추가했습니다.';
    } catch (Throwable $e) {
        if (strpos($e->getMessage(), 'Duplicate column') === false) {
            $messages[] = 'ALTER TABLE: ' . htmlspecialchars($e->getMessage());
        }
    }

    $email = 'test@test.com';
    $password = 'Test1234!';
    $nickname = '테스트유저';

    $existing = $db->fetchOne("SELECT id FROM users WHERE email = :email", ['email' => $email]);
    if ($existing) {
        $messages[] = '이미 test@test.com 계정이 존재합니다.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $db->insert('users', [
            'email' => $email,
            'password_hash' => $hash,
            'nickname' => $nickname,
            'role' => 'user',
            'status' => 'active',
        ]);
        $messages[] = '테스트 계정이 생성되었습니다.';
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>테스트 계정 생성</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; max-width: 480px; margin: 2rem auto; padding: 0 1rem; }
        h1 { font-size: 1.25rem; }
        .box { background: #f5f5f5; border-radius: 8px; padding: 1rem; margin: 1rem 0; }
        .error { background: #fee; color: #c00; }
        .cred { font-family: monospace; background: #e8f5e9; padding: 0.25rem 0.5rem; border-radius: 4px; }
        .warn { background: #fff3e0; padding: 0.75rem; border-radius: 8px; margin-top: 1rem; font-size: 0.9rem; }
    </style>
</head>
<body>
    <h1>테스트 계정 생성</h1>
    <?php if ($error): ?>
        <div class="box error"><?= $error ?></div>
    <?php else: ?>
        <div class="box">
            <?php foreach ($messages as $m): ?>
                <p><?= htmlspecialchars($m) ?></p>
            <?php endforeach; ?>
            <p><strong>테스트 로그인 정보</strong></p>
            <p>이메일: <span class="cred">test@test.com</span></p>
            <p>비밀번호: <span class="cred">Test1234!</span></p>
            <p><a href="/login">로그인 페이지로 이동</a></p>
        </div>
        <div class="warn">
            ⚠️ 보안: 사용 후 <code>public/seed_test_user.php</code> 파일을 삭제하세요.
        </div>
    <?php endif; ?>
</body>
</html>
