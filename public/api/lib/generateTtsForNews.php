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

/**
 * 프론트 formatSourceDisplayName 과 동일 (맨 뒤 " Magazine" 제거, 대소문자 무시)
 */
function _formatSourceDisplayNameForTts(string $source): string
{
    $trimmed = trim($source);
    if ($trimmed === '') {
        return '';
    }
    $len = mb_strlen($trimmed, 'UTF-8');
    $suffix = ' magazine';
    $suffixLen = mb_strlen($suffix, 'UTF-8');
    if ($len >= $suffixLen) {
        $tail = mb_strtolower(mb_substr($trimmed, $len - $suffixLen, null, 'UTF-8'), 'UTF-8');
        if ($tail === $suffix) {
            return trim(mb_substr($trimmed, 0, $len - $suffixLen, 'UTF-8'));
        }
    }
    return $trimmed;
}

/**
 * 프론트 stripHtml 과 유사: 태그 제거·엔티티 디코드·공백 정리 (TTS용 평문)
 */
function _stripHtmlForTts(?string $text): string
{
    if ($text === null || $text === '') {
        return '';
    }
    $s = (string) $text;
    $s = html_entity_decode($s, ENT_HTML5 | ENT_QUOTES, 'UTF-8');
    // 스마트 따옴표 → 직선 따옴표 (sanitizeHtml.ts normalizeQuotes)
    $smartDouble = ["\u{201C}", "\u{201D}", "\u{201E}", "\u{201F}", "\u{2033}", "\u{2036}", "\u{00AB}", "\u{00BB}"];
    $smartSingle = ["\u{2018}", "\u{2019}", "\u{201A}", "\u{201B}", "\u{2032}", "\u{2035}"];
    $s = str_replace($smartDouble, '"', $s);
    $s = str_replace($smartSingle, "'", $s);
    $s = preg_replace('/<br\s*\/?>/iu', ' ', $s);
    $s = preg_replace('/<\/(p|div|li|h[1-6])>/iu', ' ', $s);
    $s = strip_tags($s);
    $s = str_ireplace('&nbsp;', ' ', $s);
    $s = preg_replace('/\s{2,}/u', ' ', $s);
    return trim($s);
}

function _buildListenParts(array $row): ?array
{
    // SSML 첫 구간: 사이트에 표시되는 기사 제목 (NewsDetailPage openAndPlay 의 data.title)
    $speakTitle = trim($row['title'] ?? '');
    if ($speakTitle === '') {
        $speakTitle = '제목 없음';
    }

    $sourceUrl = trim($row['source_url'] ?? $row['url'] ?? '');
    $originalTitleMeta = trim($row['original_title'] ?? '');
    if ($originalTitleMeta === '' && $sourceUrl !== '') {
        $originalTitleMeta = _extractTitleFromUrlSimple($sourceUrl) ?? '';
    }
    if ($originalTitleMeta === '') {
        $originalTitleMeta = '원문';
    }

    // NewsDetailPage: rawSource → formatSourceDisplayName → buildEditorialLine
    $rawSource = trim($row['original_source'] ?? '');
    if ($rawSource === '') {
        $rawSource = (($row['source'] ?? '') === 'Admin')
            ? 'the gist.'
            : trim((string) ($row['source'] ?? ''));
        if ($rawSource === '') {
            $rawSource = 'the gist.';
        }
    }
    $sourceDisplay = _formatSourceDisplayNameForTts($rawSource);
    if ($sourceDisplay === '') {
        $sourceDisplay = 'the gist.';
    }

    $meta = sprintf(
        '이 글은 %s에 게재된 %s 글의 시각을 참고하였습니다.',
        $sourceDisplay,
        $originalTitleMeta
    );

    $narrationRaw = trim($row['narration'] ?? '') ?: trim($row['content'] ?? '') ?: trim($row['description'] ?? '');
    $narration = _stripHtmlForTts($narrationRaw);
    $critiquePart = _stripHtmlForTts(trim($row['why_important'] ?? ''));

    if ($narration === '' && $critiquePart === '') {
        return null;
    }

    return [$speakTitle, $meta, $narration, $critiquePart];
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
