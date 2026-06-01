<?php
declare(strict_types=1);

// Composer autoload (dompdf 등 외부 라이브러리용)
$composerAutoload = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

function intelligenceFindProjectRoot(): string
{
    $candidates = [__DIR__ . '/../../', __DIR__ . '/../../../'];
    foreach ($candidates as $raw) {
        $path = realpath($raw);
        if ($path && file_exists($path . '/src/agents/autoload.php')) {
            return rtrim($path, '/\\') . '/';
        }
    }
    throw new RuntimeException('Project root not found');
}

function intelligenceLoadEnv(string $projectRoot): void
{
    // FPM(auto_prepend)은 .env만 읽음. CLI는 env.txt → .env 순으로 병합(뒤쪽이 우선).
    foreach ([$projectRoot . 'env.txt', $projectRoot . '.env', $projectRoot . '.env.production'] as $file) {
        if (!is_file($file) || !is_readable($file)) {
            continue;
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (strpos($line, '=') === false) {
                continue;
            }
            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \t\"'");
            if ($name !== '') {
                putenv("$name=$value");
                $_ENV[$name] = $value;
            }
        }
    }
}

/** @return array<string, mixed> */
function intelligenceEnvDiagnostics(string $projectRoot): array
{
    $files = [];
    foreach ([$projectRoot . 'env.txt', $projectRoot . '.env', $projectRoot . '.env.production'] as $file) {
        $files[] = [
            'file' => basename($file),
            'exists' => is_file($file),
            'readable' => is_file($file) && is_readable($file),
        ];
    }

    $readKey = static function (string $name): array {
        $raw = $_ENV[$name] ?? getenv($name);
        $value = is_string($raw) ? trim($raw) : '';
        if ($name === 'GUARDIAN_API_KEY' && $value !== '' && ($value[0] === '{' || str_starts_with($value, '{"response"'))) {
            return ['set' => false, 'len' => 0, 'invalid' => 'json_response_not_key'];
        }
        return ['set' => $value !== '', 'len' => strlen($value)];
    };

    return [
        'user' => get_current_user(),
        'files' => $files,
        'keys' => [
            'NYT_API_KEY' => $readKey('NYT_API_KEY'),
            'GUARDIAN_API_KEY' => $readKey('GUARDIAN_API_KEY'),
            'OPENAI_API_KEY' => $readKey('OPENAI_API_KEY'),
        ],
    ];
}

function intelligenceGetDb(string $projectRoot): PDO
{
    $dbConfig = require $projectRoot . 'config/database.php';
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $dbConfig['host'], $dbConfig['port'] ?? 3306, $dbConfig['database']);
    return new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function intelligenceEnsureTables(PDO $pdo): void
{
    $sqlFile = intelligenceFindProjectRoot() . 'database/migrations/add_strategic_intelligence.sql';
    if (!is_file($sqlFile)) {
        return;
    }
    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        return;
    }
    foreach (array_filter(array_map('trim', preg_split('/;\s*\n/', $sql) ?: [])) as $statement) {
        if ($statement !== '' && stripos($statement, 'CREATE TABLE') !== false) {
            $pdo->exec($statement);
        }
    }
}

/** @return array<string, mixed> */
function intelligenceDbSnapshot(PDO $pdo): array
{
    try {
        $total = (int) $pdo->query('SELECT COUNT(*) FROM intelligence_source_items')->fetchColumn();
        $bySource = $pdo->query(
            'SELECT source_api, COUNT(*) AS cnt FROM intelligence_source_items GROUP BY source_api'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $byEmbed = $pdo->query(
            'SELECT embed_status, COUNT(*) AS cnt FROM intelligence_source_items GROUP BY embed_status'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $pending = (int) $pdo->query(
            "SELECT COUNT(*) FROM intelligence_source_items
             WHERE embed_status IN ('pending', 'failed') AND duplicate_of IS NULL"
        )->fetchColumn();
        return [
            'total' => $total,
            'by_source' => $bySource,
            'by_embed_status' => $byEmbed,
            'pipeline_pending' => $pending,
        ];
    } catch (Throwable $e) {
        return ['error' => $e->getMessage()];
    }
}

/**
 * StrategicReportService with RAGService wired (verifyScqa critique_grounding, judgment feedback).
 * Requires src/agents/autoload.php loaded first.
 */
function intelligenceCreateStrategicReportService(PDO $pdo): \App\Services\StrategicReportService
{
    if (!class_exists(\Agents\Services\OpenAIService::class)) {
        throw new RuntimeException('Agents autoload required before intelligenceCreateStrategicReportService');
    }
    $projectRoot = intelligenceFindProjectRoot();
    $openaiConfig = require $projectRoot . 'config/openai.php';
    $openai = new \Agents\Services\OpenAIService($openaiConfig);
    $supabase = new \Agents\Services\SupabaseService([]);
    $rag = null;
    if ($openai->isConfigured() && $supabase->isConfigured()) {
        $rag = new \Agents\Services\RAGService($openai, $supabase);
    }
    $lessons = new \App\Services\JudgmentLessonService($pdo, $openai, $rag);
    $depth = new \App\Services\NarrativeDepthService($openai);
    return new \App\Services\StrategicReportService($pdo, $openai, null, $rag, $lessons, null, $depth);
}

function intelligenceCreateNarrativeDepthService(): \App\Services\NarrativeDepthService
{
    if (!class_exists(\Agents\Services\OpenAIService::class)) {
        throw new RuntimeException('Agents autoload required before intelligenceCreateNarrativeDepthService');
    }
    $projectRoot = intelligenceFindProjectRoot();
    $openaiConfig = require $projectRoot . 'config/openai.php';
    $openai = new \Agents\Services\OpenAIService($openaiConfig);
    return new \App\Services\NarrativeDepthService($openai);
}

function intelligenceEnsureMoatTables(PDO $pdo): void
{
    $sqlFile = intelligenceFindProjectRoot() . 'database/migrations/add_judgment_moat.sql';
    if (!is_file($sqlFile)) {
        return;
    }
    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        return;
    }
    foreach (array_filter(array_map('trim', preg_split('/;\s*\n/', $sql) ?: [])) as $statement) {
        if ($statement !== '' && stripos($statement, 'CREATE TABLE') !== false) {
            try {
                $pdo->exec($statement);
            } catch (Throwable $e) {
                error_log('intelligenceEnsureMoatTables: ' . $e->getMessage());
            }
        }
    }
}
