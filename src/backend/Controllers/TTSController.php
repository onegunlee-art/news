<?php
/**
 * TTS (Text-to-Speech) API 컨트롤러
 *
 * 홈/기사 Listen 재생용 Google TTS 생성. Admin 설정 보이스 사용.
 *
 * @author News Context Analysis Team
 * @version 1.0.0
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;

final class TTSController
{
    /** 요청당 최대 텍스트 길이 (글자) */
    private const MAX_TEXT_LENGTH = 50000;

    /**
     * POST /api/tts/generate
     * Body: { "text": "..." }
     * Returns: { "success": true, "data": { "url": "/storage/audio/xxx.mp3" } }
     */
    public function generate(Request $request): Response
    {
        $body = $request->json();
        $text = isset($body['text']) ? trim((string) $body['text']) : '';

        if ($text === '') {
            return Response::error('text 필드가 비어 있습니다.', 400);
        }

        if (mb_strlen($text) > self::MAX_TEXT_LENGTH) {
            return Response::error('텍스트가 너무 깁니다. ' . self::MAX_TEXT_LENGTH . '자 이하로 보내주세요.', 400);
        }

        $projectRoot = dirname(__DIR__, 3);
        $ttsVoice = $this->getSetting('tts_voice');
        $config = file_exists($projectRoot . '/config/google_tts.php')
            ? require $projectRoot . '/config/google_tts.php'
            : [];
        $ttsVoice = $ttsVoice ?? $config['default_voice'] ?? 'ko-KR-Standard-A';

        if (!file_exists($projectRoot . '/src/agents/services/GoogleTTSService.php')) {
            return Response::error('TTS 서비스를 사용할 수 없습니다.', 503);
        }

        require_once $projectRoot . '/src/agents/services/GoogleTTSService.php';
        $service = new \Agents\Services\GoogleTTSService($config);
        $url = $service->textToSpeech($text, ['voice' => $ttsVoice]);

        if ($url === null || $url === '') {
            return Response::error('오디오 생성에 실패했습니다.', 500);
        }

        return Response::success(['url' => $url], '오디오가 생성되었습니다.');
    }

    private function getSetting(string $key): ?string
    {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT `value` FROM settings WHERE `key` = ?");
            $stmt->execute([$key]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ? (string) $row['value'] : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
