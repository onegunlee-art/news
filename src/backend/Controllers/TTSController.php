<?php
/**
 * TTS (Text-to-Speech) API 컨트롤러
 *
 * 홈/기사 Listen 재생용 Google TTS 생성. Admin 설정 보이스 사용.
 * Supabase media_cache 있으면 캐시된 URL 반환 (Listen 시 매번 TTS 과금 방지).
 *
 * @author News Context Analysis Team
 * @version 1.2.0
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
     * Body: { "text": "...", "news_id"?: number }
     * Returns: { "success": true, "data": { "url": "/storage/audio/xxx.wav", "voice": "ko-KR-Standard-B" } }
     */
    public function generate(Request $request): Response
    {
        // 여러 청크 합성 시 시간이 오래 걸릴 수 있으므로 제한 해제
        set_time_limit(300);

        $body = $request->json();
        $text = isset($body['text']) ? trim((string) $body['text']) : '';

        if ($text === '') {
            return Response::error('text 필드가 비어 있습니다.', 400);
        }

        if (mb_strlen($text) > self::MAX_TEXT_LENGTH) {
            return Response::error('텍스트가 너무 깁니다. ' . self::MAX_TEXT_LENGTH . '자 이하로 보내주세요.', 400);
        }

        $newsId = isset($body['news_id']) && (is_int($body['news_id']) || ctype_digit((string) $body['news_id']))
            ? (int) $body['news_id']
            : null;

        $projectRoot = dirname(__DIR__, 3);

        // 1) DB에서 Admin이 설정한 voice 읽기
        $ttsVoice = $this->getSetting('tts_voice');

        // 2) config 파일 로드 및 API 키 명시적 주입
        $config = file_exists($projectRoot . '/config/google_tts.php')
            ? require $projectRoot . '/config/google_tts.php'
            : [];
        $apiKey = $_ENV['GOOGLE_TTS_API_KEY'] ?? getenv('GOOGLE_TTS_API_KEY');
        if (is_string($apiKey) && $apiKey !== '') {
            $config['api_key'] = $apiKey;
        }
        $config['default_voice'] = $ttsVoice ?: ($config['default_voice'] ?? 'ko-KR-Standard-A');
        $ttsVoice = $config['default_voice'];

        // 3) 캐시 키: hash(text, voice) → 동일 입력이면 캐시 사용 (Listen 시 로딩·과금 없음)
        $cacheHash = hash('sha256', $text . '|' . $ttsVoice);

        $supabase = $this->getSupabaseService($projectRoot);
        if ($supabase !== null && $supabase->isConfigured()) {
            $cacheQuery = 'media_type=eq.tts&generation_params->>hash=eq.' . rawurlencode($cacheHash);
            $cached = $supabase->select('media_cache', $cacheQuery, 1);
            if (!empty($cached) && is_array($cached) && !empty($cached[0]['file_url'])) {
                return Response::success([
                    'url' => $cached[0]['file_url'],
                    'voice' => $ttsVoice,
                ], '캐시된 오디오입니다.');
            }
        }

        if (!file_exists($projectRoot . '/src/agents/services/GoogleTTSService.php')) {
            return Response::error('TTS 서비스 파일을 찾을 수 없습니다.', 503);
        }

        require_once $projectRoot . '/src/agents/services/GoogleTTSService.php';
        $service = new \Agents\Services\GoogleTTSService($config);

        if (!$service->isConfigured()) {
            return Response::error('Google TTS API 키가 설정되지 않았습니다. 서버 .env 파일을 확인해주세요.', 503);
        }

        $url = $service->textToSpeech($text, ['voice' => $ttsVoice]);

        if ($url === null || $url === '') {
            $detail = $service->getLastError();
            $msg = $detail !== '' ? ('오디오 생성 실패: ' . $detail) : '오디오 생성에 실패했습니다. 서버 로그를 확인해주세요.';
            return Response::error($msg, 500);
        }

        // Supabase 있으면 media_cache에 저장 (다음 Listen 시 캐시 사용)
        if ($supabase !== null && $supabase->isConfigured()) {
            $supabase->insert('media_cache', [
                'news_id' => $newsId,
                'media_type' => 'tts',
                'file_url' => $url,
                'generation_params' => [
                    'hash' => $cacheHash,
                    'voice' => $ttsVoice,
                ],
            ]);
        }

        return Response::success([
            'url' => $url,
            'voice' => $ttsVoice,
        ], '오디오가 생성되었습니다.');
    }

    private function getSupabaseService(string $projectRoot): ?\Agents\Services\SupabaseService
    {
        $path = $projectRoot . '/src/agents/services/SupabaseService.php';
        if (!file_exists($path)) {
            return null;
        }
        require_once $path;
        $supabaseConfig = file_exists($projectRoot . '/config/supabase.php')
            ? require $projectRoot . '/config/supabase.php'
            : [];
        return new \Agents\Services\SupabaseService($supabaseConfig);
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
