<?php
declare(strict_types=1);

/**
 * Discovery 이메일 테스트 발송
 * Usage: php tools/discovery_test_email.php [email@example.com]
 */

require_once __DIR__ . '/../src/discovery/bootstrap.php';
require_once __DIR__ . '/../src/backend/autoload.php';

use App\Services\MailService;

$root = discoveryFindProjectRoot();
discoveryLoadEnv($root);

$to = $argv[1] ?? ($_ENV['DISCOVERY_NOTIFY_EMAIL'] ?? getenv('DISCOVERY_NOTIFY_EMAIL') ?: 'onegunlee@gmail.com');

echo "=== Discovery Email Test ===\n";
echo "To: {$to}\n";

try {
    $mail = new MailService();
    echo "MailService: initialized\n";
    echo "Resend configured: " . ($mail->isResendConfigured() ? 'yes' : 'no') . "\n";
} catch (Throwable $e) {
    echo "ERROR: MailService init failed: {$e->getMessage()}\n";
    exit(1);
}

$subject = "[Discovery] 테스트 이메일 - " . date('Y-m-d H:i:s');
$textBody = <<<TEXT
=== Discovery 자동화 테스트 ===

이 이메일은 Discovery 자동화의 이메일 알림 테스트입니다.

테스트 시각: {date('Y-m-d H:i:s')} KST
수신 주소: {$to}

이 이메일이 도착했다면 알림 시스템이 정상 작동합니다.

---
킬스위치: ENABLE_DISCOVERY_CRON=false
TEXT;

$textBody = str_replace('{date(\'Y-m-d H:i:s\')}', date('Y-m-d H:i:s'), $textBody);

echo "Sending test email...\n";

try {
    $sent = $mail->send($to, $subject, $textBody);
    if ($sent) {
        echo "✅ SUCCESS: Test email sent to {$to}\n";
        echo "이메일을 확인해주세요.\n";
        exit(0);
    } else {
        echo "❌ FAILED: mail() returned false\n";
        exit(1);
    }
} catch (Throwable $e) {
    echo "❌ ERROR: {$e->getMessage()}\n";
    exit(1);
}
