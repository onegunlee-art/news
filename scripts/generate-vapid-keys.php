<?php
/**
 * VAPID 키 생성 (Web Push용)
 * 실행: php scripts/generate-vapid-keys.php
 * 출력된 값을 config/vapid.php에 붙여넣으세요.
 */
require_once __DIR__ . '/../vendor/autoload.php';

use Minishlink\WebPush\VAPID;

$keys = VAPID::createVapidKeys();

echo "=== VAPID 키 생성 완료 ===\n\n";
echo "1. config/vapid.example.php 내용을 config/vapid.php로 복사한 뒤 아래 값을 붙여넣으세요.\n\n";
echo "publicKey:  " . $keys['publicKey'] . "\n";
echo "privateKey: " . $keys['privateKey'] . "\n\n";
echo "또는 아래 PHP 배열을 config/vapid.php에 넣으세요:\n\n";
echo "<?php\nreturn [\n    'publicKey'  => '" . $keys['publicKey'] . "',\n    'privateKey' => '" . $keys['privateKey'] . "',\n];\n";
