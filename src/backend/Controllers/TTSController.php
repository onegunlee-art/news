<?php
/**
 * TTS (Text-to-Speech) API 컨트롤러
 *
 * 홈/기사 Listen 재생용 Google TTS 생성. Admin 설정 보이스 사용.
 *
 * @author News Context Analysis Team
 * @version 1.1.0
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
     * Returns: { "success": true, "data": { "url": "/storage/audio/xxx.mp3", "voice": "ko-KR-Standard-B" } }
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

        // 1) DB에서 Admin이 설정한 voice 읽기
        $ttsVoice = $this->getSetting('tts_voice');

        // 2) config 파일 로드
        $config = file_exists($projectRoot . '/config/google_tts.php')
            ? require $projectRoot . '/config/google_tts.php'
            : [];

        // 3) voice 결정: DB 설정 > config default > 하드코딩 default
        $ttsVoice = $ttsVoice ?: ($config['default_voice'] ?? 'ko-KR-Standard-A');

        // 디버그 로그
        error_log("[TTS] voice from DB: " . ($this->getSetting('tts_voice') ?? 'null') . ", final voice: {$ttsVoice}");
        error_log("[TTS] API key configured: " . ((!empty($config['api_key'])) ? 'yes' : 'no'));

        if (!file_exists($projectRoot . '/src/agents/services/GoogleTTSService.php')) {
            return Response::error('TTS 서비스 파일을 찾을 수 없습니다.', 503);
        }

        require_once $projectRoot . '/src/agents/services/GoogleTTSService.php';

        // config에 voice도 넣어서 GoogleTTSService의 defaultVoice도 올바르게 설정
        $config['default_voice'] = $ttsVoice;
        $service = new \Agents\Services\GoogleTTSService($config);

        if (!$service->isConfigured()) {
            return Response::error('Google TTS API 키가 설정되지 않았습니다. 서버 .env 파일을 확인해주세요.', 503);
        }

        $url = $service->textToSpeech($text, ['voice' => $ttsVoice]);

        if ($url === null || $url === '') {
            return Response::error('오디오 생성에 실패했습니다. 서버 로그를 확인해주세요.', 500);
        }

        return Response::success([
            'url' => $url,
            'voice' => $ttsVoice,
        ], '오디오가 생성되었습니다.');
    }

    /**
     * DB settings 테이블에서 값 조회
     */
    private function getSetting(string $key): ?string
    {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT `value` FROM settings WHERE `key` = ?");
            $stmt->execute([$key]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ? (string) $row['value'] : null;
        } catch (\Throwable $e) {
            error_log("[TTS] getSetting error: " . $e->getMessage());
            return null;
        }
    }
}
