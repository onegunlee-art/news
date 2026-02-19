<?php
/**
 * 기사 수정 시 TTS 캐시 무효화
 * 1) Supabase media_cache에서 해당 news_id의 tts 레코드 조회 → hash 추출
 * 2) 로컬 storage/audio/tts_{hash}.wav 파일 삭제
 * 3) Supabase media_cache 레코드 삭제
 *
 * @param int $newsId
 * @return void
 */
function invalidateTtsCacheForNews(int $newsId): void
{
    $projectRoot = dirname(__DIR__, 3) . '/';
    if (file_exists(__DIR__ . '/../../config/supabase.php')) {
        $projectRoot = dirname(__DIR__, 2) . '/';
    }

    $cfg = [];
    if (file_exists($projectRoot . 'config/supabase.php')) {
        $cfg = require $projectRoot . 'config/supabase.php';
    }
    $url = rtrim($cfg['url'] ?? '', '/');
    $serviceRoleKey = $cfg['service_role_key'] ?? '';

    $storageDir = $projectRoot . 'storage/audio';

    $path = $projectRoot . 'src/agents/services/SupabaseService.php';
    if (!file_exists($path)) {
        return;
    }
    require_once $path;

    try {
        $supabase = new \Agents\Services\SupabaseService($cfg);
        if ($supabase->isConfigured()) {
            $query = 'news_id=eq.' . (int) $newsId . '&media_type=eq.tts';
            $rows = $supabase->select('media_cache', $query, 100);
            if (is_array($rows) && !empty($rows)) {
                foreach ($rows as $row) {
                    $hash = $row['generation_params']['hash'] ?? null;
                    if ($hash !== null && $hash !== '') {
                        $safeHash = preg_replace('/[^a-f0-9]/', '', $hash);
                        if ($safeHash !== '') {
                            $filePath = $storageDir . '/tts_' . $safeHash . '.wav';
                            if (is_file($filePath) && is_writable($filePath)) {
                                @unlink($filePath);
                            }
                        }
                    }
                }
            }
            $supabase->delete('media_cache', $query);
        }
    } catch (Throwable $e) {
        error_log('[invalidateTtsCache] ' . $e->getMessage());
    }
}
