<?php
/**
 * 기사 1건에 대해 Listen용 구조화 TTS를 선생성
 * 게시/수정 시 호출하여 캐시 파일을 미리 만들어 둠
 * 실패해도 예외를 던지지 않음 — 기사 저장에 영향 없음
 *
 * @param array $row news 테이블 row (title, narration, why_important, original_title, source_url, original_source, source, published_at 등)
 * @return string|null 생성된 audio_url (실패 시 null)
 */
function generateTtsForNews(array $row): ?string
{
    $candidate = dirname(__DIR__, 2);
    $projectRoot = is_dir($candidate . '/config') ? $candidate : dirname(__DIR__, 3);

    try {
        $parts = _buildListenParts($row);
        if ($parts === null) return null;

        [$title, $meta, $narration, $critiquePart] = $parts;

        $ttsVoice = _getTtsVoice($projectRoot);
        $config = file_exists($projectRoot . '/config/google_tts.php')
            ? require $projectRoot . '/config/google_tts.php'
            : [];
        $apiKey = $_ENV['GOOGLE_TTS_API_KEY'] ?? getenv('GOOGLE_TTS_API_KEY');
        if (is_string($apiKey) && $apiKey !== '') {
            $config['api_key'] = $apiKey;
        }
        $config['default_voice'] = $ttsVoice;

        $fullPayload = $title . '|' . $meta . '|' . $critiquePart . '|' . $narration . '|' . $ttsVoice;
        $cacheHash = hash('sha256', $fullPayload);

        $storageDir = $projectRoot . '/storage/audio';
        $safeHash = preg_replace('/[^a-f0-9]/', '', $cacheHash);
        if (is_file($storageDir . '/tts_' . $safeHash . '.wav')) {
            return '/storage/audio/tts_' . $safeHash . '.wav';
        }

        require_once $projectRoot . '/src/agents/services/GoogleTTSService.php';
        $service = new \Agents\Services\GoogleTTSService($config);
        if (!$service->isConfigured()) {
            error_log('[generateTtsForNews] Google TTS API key not configured');
            return null;
        }

        $url = $service->textToSpeechStructured(
            $title ?: '제목 없음',
            $meta ?: ' ',
            $narration,
            ['voice' => $ttsVoice, 'cache_hash' => $cacheHash],
            $critiquePart
        );

        if ($url === null || $url === '') {
            error_log('[generateTtsForNews] TTS generation failed: ' . $service->getLastError());
            return null;
        }

        $newsId = (int) ($row['id'] ?? 0);
        _saveTtsToSupabase($projectRoot, $newsId, $url, $cacheHash, $ttsVoice);

        return $url;
    } catch (\Throwable $e) {
        error_log('[generateTtsForNews] ' . $e->getMessage());
        return null;
    }
}

/**
 * 기사 ID로 캐시된 TTS audio_url 조회 (파일 캐시 → Supabase)
 * detail API에서 호출하여 응답에 audio_url 포함
 *
 * @return string|null
 */
function getTtsCachedUrl(array $row): ?string
{
    $candidate = dirname(__DIR__, 2);
    $projectRoot = is_dir($candidate . '/config') ? $candidate : dirname(__DIR__, 3);

    try {
        $parts = _buildListenParts($row);
        if ($parts === null) return null;

        [$title, $meta, $narration, $critiquePart] = $parts;
        $ttsVoice = _getTtsVoice($projectRoot);

        $fullPayload = $title . '|' . $meta . '|' . $critiquePart . '|' . $narration . '|' . $ttsVoice;
        $cacheHash = hash('sha256', $fullPayload);
        $safeHash = preg_replace('/[^a-f0-9]/', '', $cacheHash);

        $storageDir = $projectRoot . '/storage/audio';
        if (is_file($storageDir . '/tts_' . $safeHash . '.wav')) {
            return '/storage/audio/tts_' . $safeHash . '.wav';
        }
    } catch (\Throwable $e) {
        // 조회 실패 시 null — 프론트에서 fallback
    }

    return null;
}

function _getTtsVoice(string $projectRoot): string
{
    static $voice = null;
    if ($voice !== null) return $voice;

    try {
        $dbConfigPath = $projectRoot . '/config/database.php';
        $dbConfig = file_exists($dbConfigPath) ? require $dbConfigPath : [];
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $dbConfig['host'] ?? 'localhost',
            $dbConfig['database'] ?? $dbConfig['dbname'] ?? 'ailand',
            $dbConfig['charset'] ?? 'utf8mb4'
        );
        $pdo = new PDO($dsn, $dbConfig['username'] ?? 'ailand', $dbConfig['password'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $stmt = $pdo->prepare("SELECT `value` FROM settings WHERE `key` = 'tts_voice'");
        $stmt->execute();
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        $voice = ($r && $r['value']) ? $r['value'] : 'ko-KR-Standard-A';
    } catch (\Throwable $e) {
        $voice = 'ko-KR-Standard-A';
    }

    return $voice;
}

function _buildListenParts(array $row): ?array
{
    $titleRaw = trim($row['title'] ?? '');
    $originalTitle = trim($row['original_title'] ?? '');
    $sourceUrl = trim($row['source_url'] ?? '');

    $title = ($originalTitle !== '') ? $originalTitle : $titleRaw;
    if ($title === '' && $sourceUrl !== '') {
        $title = _extractTitleFromUrlSimple($sourceUrl) ?? $titleRaw;
    }
    if ($title === '') $title = $titleRaw ?: '제목 없음';

    $dateStr = '';
    $ref = $row['published_at'] ?? $row['updated_at'] ?? $row['created_at'] ?? null;
    if ($ref) {
        $t = strtotime($ref);
        if ($t) {
            $dateStr = date('Y', $t) . '년 ' . (int) date('n', $t) . '월 ' . (int) date('j', $t) . '일';
        }
    }

    $rawSource = trim($row['original_source'] ?? '') ?: (($row['source'] ?? '') === 'Admin' ? 'The Gist' : ($row['source'] ?? 'The Gist'));
    $sourceDisplay = $rawSource ?: 'The Gist';

    $meta = $dateStr
        ? "{$dateStr}자 {$sourceDisplay} 저널의 \"{$title}\"을 AI 번역, 요약하고 The Gist에서 일부 편집한 글입니다."
        : "{$sourceDisplay} 저널의 \"{$title}\"을 AI 번역, 요약하고 The Gist에서 일부 편집한 글입니다.";

    $narration = trim($row['narration'] ?? '') ?: trim($row['content'] ?? '') ?: trim($row['description'] ?? '');
    $whyImportant = trim($row['why_important'] ?? '');
    $critiquePart = $whyImportant !== '' ? "The Gist's Critique. " . $whyImportant : '';

    if ($narration === '' && $critiquePart === '') return null;

    return [$title, $meta, $narration, $critiquePart];
}

function _extractTitleFromUrlSimple(string $url): ?string
{
    $trimmed = trim($url);
    if ($trimmed === '') return null;
    if (!preg_match('#^https?://#i', $trimmed)) $trimmed = 'https://' . $trimmed;
    $parsed = parse_url($trimmed);
    if ($parsed === false || !isset($parsed['path'])) return null;
    $segments = array_filter(explode('/', trim($parsed['path'], '/')));
    if (empty($segments)) return null;
    $slug = preg_replace('/\.(html?|php|aspx?)$/i', '', end($segments));
    if ($slug === '') return null;
    $words = array_filter(explode('-', $slug));
    if (empty($words)) return null;
    $result = [];
    foreach ($words as $w) $result[] = ucfirst(strtolower($w));
    return implode(' ', $result) ?: null;
}

function _saveTtsToSupabase(string $projectRoot, int $newsId, string $url, string $hash, string $voice): void
{
    $path = $projectRoot . '/src/agents/services/SupabaseService.php';
    if (!file_exists($path)) return;

    try {
        require_once $path;
        $cfg = file_exists($projectRoot . '/config/supabase.php') ? require $projectRoot . '/config/supabase.php' : [];
        $supabase = new \Agents\Services\SupabaseService($cfg);
        if ($supabase->isConfigured() && $newsId > 0) {
            $supabase->insert('media_cache', [
                'news_id' => $newsId,
                'media_type' => 'tts',
                'file_url' => $url,
                'generation_params' => ['hash' => $hash, 'voice' => $voice],
            ]);
        }
    } catch (\Throwable $e) {
        error_log('[generateTtsForNews] Supabase save failed: ' . $e->getMessage());
    }
}
