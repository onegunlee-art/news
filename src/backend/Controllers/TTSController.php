<?php
/**
 * TTS (Text-to-Speech) API 컨트롤러
 *
 * Listen: 구조화 TTS (제목 pause 매체설명 pause 내레이션 pause Critique)
 * 파일 기반 캐시 + Supabase → 2번째 Listen 시 즉시 재생 (로딩 없음)
 *
 * @author News Context Analysis Team
 * @version 1.3.0
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;

final class TTSController
{
    private const MAX_TEXT_LENGTH = 50000;

    /**
     * POST /api/tts/generate
     * Body (구조화): { "title","meta","narration","critique_part","news_id"? }
     * Body (단순): { "text" } - Admin 미리듣기용
     */
    public function generate(Request $request): Response
    {
        set_time_limit(2700);

        $body = $request->json();
        $projectRoot = dirname(__DIR__, 3);
        $storageDir = $projectRoot . '/storage/audio';

        // 1) voice
        $ttsVoice = $this->getSetting('tts_voice');
        $config = file_exists($projectRoot . '/config/google_tts.php')
            ? require $projectRoot . '/config/google_tts.php'
            : [];
        $apiKey = $_ENV['GOOGLE_TTS_API_KEY'] ?? getenv('GOOGLE_TTS_API_KEY');
        if (is_string($apiKey) && $apiKey !== '') {
            $config['api_key'] = $apiKey;
        }
        $config['default_voice'] = $ttsVoice ?: ($config['default_voice'] ?? 'ko-KR-Standard-A');
        $ttsVoice = $config['default_voice'];

        $newsId = isset($body['news_id']) && (is_int($body['news_id']) || ctype_digit((string) $body['news_id']))
            ? (int) $body['news_id']
            : null;

        // ── 구조화 모드 (Listen) ──
        $title = isset($body['title']) ? trim((string) $body['title']) : '';
        $meta = isset($body['meta']) ? trim((string) $body['meta']) : '';
        $narration = isset($body['narration']) ? trim((string) $body['narration']) : '';
        $critiquePart = isset($body['critique_part']) ? trim((string) $body['critique_part']) : '';

        if ($title !== '' || $meta !== '' || $narration !== '' || $critiquePart !== '') {
            return $this->generateStructured($title, $meta, $narration, $critiquePart, $ttsVoice, $config, $projectRoot, $storageDir, $newsId);
        }

        // ── 단순 텍스트 모드 (Admin) ──
        $text = isset($body['text']) ? trim((string) $body['text']) : '';
        if ($text === '') {
            return Response::error('text 또는 title/meta/narration이 필요합니다.', 400);
        }
        if (mb_strlen($text) > self::MAX_TEXT_LENGTH) {
            return Response::error('텍스트가 너무 깁니다.', 400);
        }

        $cacheHash = hash('sha256', $text . '|' . $ttsVoice);

        // 파일 캐시 (Supabase 없어도 2번째부터 즉시)
        $cachedUrl = $this->getCachedFileUrl($storageDir, $cacheHash);
        if ($cachedUrl !== null) {
            return Response::success(['url' => $cachedUrl, 'voice' => $ttsVoice], '캐시된 오디오입니다.');
        }

        $supabase = $this->getSupabaseService($projectRoot);
        if ($supabase !== null && $supabase->isConfigured()) {
            $cached = $this->getTtsCacheFromSupabase($supabase, $cacheHash, $projectRoot);
            if ($cached !== null) {
                return Response::success(['url' => $cached['file_url'], 'voice' => $ttsVoice], '캐시된 오디오입니다.');
            }
        }

        require_once $projectRoot . '/src/agents/services/GoogleTTSService.php';
        $service = new \Agents\Services\GoogleTTSService($config);
        if (!$service->isConfigured()) {
            return Response::error('Google TTS API 키가 설정되지 않았습니다.', 503);
        }

        $url = $service->textToSpeech($text, ['voice' => $ttsVoice, 'cache_hash' => $cacheHash]);

        if ($url === null || $url === '') {
            return Response::error('오디오 생성 실패: ' . $service->getLastError(), 500);
        }

        $this->saveToSupabase($supabase, $newsId, $url, $cacheHash, $ttsVoice);
        return Response::success(['url' => $url, 'voice' => $ttsVoice], '오디오가 생성되었습니다.');
    }

    private function generateStructured(
        string $title,
        string $meta,
        string $narration,
        string $critiquePart,
        string $ttsVoice,
        array $config,
        string $projectRoot,
        string $storageDir,
        ?int $newsId
    ): Response {
        $fullPayload = $title . '|' . $meta . '|' . $narration . '|' . $critiquePart . '|' . $ttsVoice;
        $cacheHash = hash('sha256', $fullPayload);

        $this->logTtsDebug('structured_request', [
            'news_id' => $newsId,
            'title_preview' => mb_substr($title, 0, 60),
            'meta_preview' => mb_substr($meta, 0, 80),
            'cache_hash' => $cacheHash,
        ], $projectRoot);

        // 파일 캐시
        $cachedUrl = $this->getCachedFileUrl($storageDir, $cacheHash);
        if ($cachedUrl !== null) {
            $this->logTtsDebug('cache_hit', ['source' => 'file', 'hash' => $cacheHash], $projectRoot);
            return Response::success(['url' => $cachedUrl, 'voice' => $ttsVoice], '캐시된 오디오입니다.');
        }

        $supabase = $this->getSupabaseService($projectRoot);
        if ($supabase !== null && $supabase->isConfigured()) {
            $cached = $this->getTtsCacheFromSupabase($supabase, $cacheHash, $projectRoot);
            if ($cached !== null) {
                $this->logTtsDebug('cache_hit', ['source' => 'supabase', 'hash' => $cacheHash], $projectRoot);
                return Response::success(['url' => $cached['file_url'], 'voice' => $ttsVoice], '캐시된 오디오입니다.');
            }
        }

        $this->logTtsDebug('generating', ['hash' => $cacheHash], $projectRoot);
        require_once $projectRoot . '/src/agents/services/GoogleTTSService.php';
        $service = new \Agents\Services\GoogleTTSService($config);
        if (!$service->isConfigured()) {
            return Response::error('Google TTS API 키가 설정되지 않았습니다.', 503);
        }

        $url = $service->textToSpeechStructured(
            $title ?: '제목 없음',
            $meta ?: ' ',
            $narration,
            ['voice' => $ttsVoice, 'cache_hash' => $cacheHash],
            $critiquePart
        );

        if ($url === null || $url === '') {
            return Response::error('오디오 생성 실패: ' . $service->getLastError(), 500);
        }

        $this->saveToSupabase($supabase, $newsId, $url, $cacheHash, $ttsVoice);
        return Response::success(['url' => $url, 'voice' => $ttsVoice], '오디오가 생성되었습니다.');
    }

    /**
     * Supabase에서 TTS 캐시 조회. RPC 우선, 실패 시 REST 필터 사용 + 해시 검증.
     * @return array{file_url: string}|null
     */
    private function getTtsCacheFromSupabase(\Agents\Services\SupabaseService $supabase, string $cacheHash, string $projectRoot): ?array
    {
        // 1) RPC 함수 사용 (JSONB 필터보다 안정적)
        $rpcResult = $supabase->rpc('get_tts_cache_by_hash', ['p_hash' => $cacheHash]);
        if (is_array($rpcResult) && !empty($rpcResult[0]['file_url'])) {
            return $rpcResult[0];
        }

        // 2) REST 필터 폴백 (PostgREST JSONB)
        $cacheQuery = 'media_type=eq.tts&generation_params->>hash=eq.' . rawurlencode($cacheHash);
        $cached = $supabase->select('media_cache', $cacheQuery, 1);
        if (!empty($cached) && is_array($cached) && !empty($cached[0]['file_url'])) {
            $rowHash = $cached[0]['generation_params']['hash'] ?? null;
            if ($rowHash === $cacheHash) {
                return $cached[0];
            }
            $this->logTtsDebug('cache_mismatch', [
                'expected_hash' => $cacheHash,
                'row_hash' => $rowHash,
                'query' => $cacheQuery,
            ], $projectRoot);
        } else {
            $this->logTtsDebug('supabase_miss', ['hash' => $cacheHash, 'query' => $cacheQuery], $projectRoot);
        }
        return null;
    }

    private function getCachedFileUrl(string $storageDir, string $hash): ?string
    {
        $safeHash = preg_replace('/[^a-f0-9]/', '', $hash);
        $path = $storageDir . '/tts_' . $safeHash . '.wav';
        return (is_file($path) && is_readable($path)) ? '/storage/audio/tts_' . $safeHash . '.wav' : null;
    }

    private function saveToSupabase(?\Agents\Services\SupabaseService $supabase, ?int $newsId, string $url, string $hash, string $voice): void
    {
        if ($supabase === null || !$supabase->isConfigured()) {
            return;
        }
        $supabase->insert('media_cache', [
            'news_id' => $newsId,
            'media_type' => 'tts',
            'file_url' => $url,
            'generation_params' => ['hash' => $hash, 'voice' => $voice],
        ]);
    }

    private function getSupabaseService(string $projectRoot): ?\Agents\Services\SupabaseService
    {
        $path = $projectRoot . '/src/agents/services/SupabaseService.php';
        if (!file_exists($path)) {
            return null;
        }
        require_once $path;
        $cfg = file_exists($projectRoot . '/config/supabase.php') ? require $projectRoot . '/config/supabase.php' : [];
        return new \Agents\Services\SupabaseService($cfg);
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
            error_log("[TTS] getSetting error: " . $e->getMessage());
            return null;
        }
    }

    /** TTS 디버깅 로그 (storage/logs/tts_debug.log) */
    private function logTtsDebug(string $event, array $data, string $projectRoot): void
    {
        $logDir = $projectRoot . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $logPath = $logDir . '/tts_debug.log';
        $line = json_encode([
            'ts' => date('Y-m-d H:i:s'),
            'event' => $event,
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE) . "\n";
        @file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX);
    }
}
