<?php
/**
 * Web Push 알림 발송
 * 새 글이 게시될 때 모든 구독자에게 푸시 발송
 */
$projectRoot = dirname(__DIR__, 3);
require_once $projectRoot . '/vendor/autoload.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

/**
 * @param PDO $db
 * @param string $title 알림 제목
 * @param string $url 클릭 시 이동할 URL (선택)
 * @param string $body 알림 본문 (선택)
 */
function sendPushToAllSubscribers(PDO $db, string $title, string $url = '', string $body = ''): void {
    global $projectRoot;
    $root = $projectRoot ?? dirname(__DIR__, 3);
    $vapidPath = $root . '/config/vapid.php';
    if (!file_exists($vapidPath)) {
        return;
    }
    $vapid = require $vapidPath;
    if (empty($vapid['publicKey']) || empty($vapid['privateKey'])) {
        return;
    }

    $checkTable = $db->query("SHOW TABLES LIKE 'push_subscriptions'");
    if ($checkTable->rowCount() === 0) {
        return;
    }

    $stmt = $db->query("SELECT endpoint, p256dh, auth FROM push_subscriptions");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        return;
    }

    $auth = [
        'VAPID' => [
            'subject' => 'mailto:support@thegist.kr',
            'publicKey' => $vapid['publicKey'],
            'privateKey' => $vapid['privateKey'],
        ],
    ];

    try {
        $webPush = new WebPush($auth);
        $payload = json_encode([
            'title' => $title,
            'body' => $body ?: '새 글이 올라왔습니다.',
            'url' => $url ?: '/',
        ], JSON_UNESCAPED_UNICODE);

        foreach ($rows as $row) {
            try {
                $subscription = Subscription::create([
                    'endpoint' => $row['endpoint'],
                    'keys' => [
                        'p256dh' => $row['p256dh'],
                        'auth' => $row['auth'],
                    ],
                ]);
                $webPush->queueNotification($subscription, $payload);
            } catch (Throwable $e) {
                // 개별 구독 실패 시 무시 (만료된 endpoint 등)
            }
        }
        $webPush->flush();
    } catch (Throwable $e) {
        // 푸시 발송 실패 시 무시 (알림은 부가 기능)
        error_log('Push send error: ' . $e->getMessage());
    }
}
