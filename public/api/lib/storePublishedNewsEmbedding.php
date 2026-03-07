<?php
/**
 * 기사 게시 시 최종 output을 RAG(임베딩)에 자동 저장.
 * news.php에서 status=published로 저장/수정한 직후 호출.
 *
 * @param PDO $db   DB 연결 (news 테이블 접근)
 * @param int $newsId 기사 ID
 * @return void
 */
function storePublishedNewsEmbedding(PDO $db, int $newsId): void
{
    $stmt = $db->prepare("
        SELECT id, url, status, narration, why_important, description, content
        FROM news
        WHERE id = ?
    ");
    $stmt->execute([$newsId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || ($row['status'] ?? '') !== 'published') {
        return;
    }

    $projectRoot = null;
    $candidates = [
        __DIR__ . '/../../../',
        __DIR__ . '/../../../../',
        __DIR__ . '/../../',
    ];
    foreach ($candidates as $raw) {
        $path = realpath($raw);
        if ($path === false) {
            $path = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $raw), DIRECTORY_SEPARATOR);
        }
        if ($path && file_exists($path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'agents' . DIRECTORY_SEPARATOR . 'autoload.php')) {
            $projectRoot = rtrim($path, '/\\') . '/';
            break;
        }
    }
    if (!$projectRoot) {
        return;
    }

    if (!file_exists($projectRoot . 'src/agents/autoload.php')) {
        return;
    }
    require_once $projectRoot . 'src/agents/autoload.php';

    $supabaseConfig = [];
    if (file_exists($projectRoot . 'config/supabase.php')) {
        $supabaseConfig = require $projectRoot . 'config/supabase.php';
    }
    $openaiConfig = [];
    if (file_exists($projectRoot . 'config/openai.php')) {
        $openaiConfig = require $projectRoot . 'config/openai.php';
    }
    if (file_exists($projectRoot . 'config/agents.php')) {
        $agentsConfig = require $projectRoot . 'config/agents.php';
        $openaiConfig = array_merge($openaiConfig, $agentsConfig['agents']['analysis'] ?? []);
    }

    try {
        $openai = new \Agents\Services\OpenAIService($openaiConfig);
        $supabase = new \Agents\Services\SupabaseService($supabaseConfig);
        $rag = new \Agents\Services\RAGService($openai, $supabase);
        if (!$rag->isConfigured()) {
            return;
        }

        $url = $row['url'] ?? '';
        $narration = trim((string) ($row['narration'] ?? ''));
        $whyImportant = trim((string) ($row['why_important'] ?? ''));
        $description = trim(strip_tags((string) ($row['description'] ?? '')));
        $content = trim(strip_tags((string) ($row['content'] ?? '')));
        $descriptionOrContent = $description !== '' ? $description : mb_substr($content, 0, 2000);

        $rag->storePublishedArticleEmbedding(
            (int) $row['id'],
            $url,
            $narration,
            $whyImportant,
            $descriptionOrContent
        );
    } catch (Throwable $e) {
        error_log('[storePublishedNewsEmbedding] news_id=' . $newsId . ' error: ' . $e->getMessage());
    }
}
