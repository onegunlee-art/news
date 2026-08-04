<?php
declare(strict_types=1);

/**
 * YouTube Pipeline Bootstrap
 * Provides helper functions for CLI and API usage.
 */

require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/../agents/autoload.php';

function youtubeGetProjectRoot(): string
{
    return dirname(__DIR__, 2);
}

function youtubeLoadEnv(string $root): void
{
    $envPath = $root . '/env.txt';
    if (!file_exists($envPath)) {
        $envPath = $root . '/.env';
    }
    if (!file_exists($envPath)) {
        return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if (!isset($_ENV[$key])) {
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

function youtubeGetDb(string $root): \PDO
{
    // Reuse Discovery's DB connection (same config/database.php)
    require_once $root . '/src/discovery/bootstrap.php';
    return discoveryGetDb($root . '/');
}

function youtubeResolveFontPath(string $root, string $weight = 'bold'): string
{
    // 1. 시스템 폰트 우선 (Linux/Windows 차이 제거)
    $systemFonts = $weight === 'bold'
        ? [
            '/usr/share/fonts/opentype/noto/NotoSansCJK-Bold.ttc',
            '/usr/share/fonts/truetype/noto/NotoSansCJK-Bold.ttc',
            '/usr/share/fonts/noto-cjk/NotoSansCJK-Bold.ttc',
            '/usr/share/fonts/opentype/noto/NotoSansCJKkr-Bold.otf',
            '/usr/share/fonts/truetype/noto/NotoSansKR-Bold.ttf',
        ]
        : [
            '/usr/share/fonts/opentype/noto/NotoSansCJK-Regular.ttc',
            '/usr/share/fonts/truetype/noto/NotoSansCJK-Regular.ttc',
            '/usr/share/fonts/noto-cjk/NotoSansCJK-Regular.ttc',
            '/usr/share/fonts/opentype/noto/NotoSansCJKkr-Regular.otf',
            '/usr/share/fonts/truetype/noto/NotoSansKR-Regular.ttf',
        ];

    foreach ($systemFonts as $path) {
        if (is_file($path)) {
            return $path;
        }
    }

    // 2. 프로젝트 폰트 폴더 (EC2 hashed ttf 우선)
    $fontDir = $root . '/public/fonts/noto';
    $pattern = $weight === 'bold' ? 'noto_sans_kr_bold*.ttf' : 'noto_sans_kr_normal*.ttf';
    $matches = glob($fontDir . '/' . $pattern) ?: [];
    if ($matches !== []) {
        return $matches[0];
    }

    // 3. 프로젝트 폰트 개별 파일
    $candidates = $weight === 'bold'
        ? ['NotoSansKR-Bold.otf', 'NotoSansKR-Bold.ttf']
        : ['NotoSansKR-Regular.otf', 'NotoSansKR-Regular.ttf'];

    foreach ($candidates as $filename) {
        $path = $fontDir . '/' . $filename;
        if (is_file($path)) {
            return $path;
        }
    }

    return '';
}

function youtubeGetConfig(string $root): array
{
    $configPath = $root . '/config/youtube.php';
    if (!file_exists($configPath)) {
        throw new \RuntimeException('YouTube config not found: ' . $configPath);
    }

    $config = require $configPath;
    $config['fonts']['title'] = youtubeResolveFontPath($root, 'bold');
    $config['fonts']['body'] = youtubeResolveFontPath($root, 'regular');

    return $config;
}

function youtubeGetOpenAiApiKey(): string
{
    return $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY') ?: '';
}

function youtubeCreatePipeline(string $root, ?\PDO $pdo = null): \Youtube\Pipeline
{
    require_once $root . '/src/discovery/bootstrap.php';
    
    $config = youtubeGetConfig($root);
    $agentsConfig = require $root . '/config/discovery_agents.php';
    $apiKey = youtubeGetOpenAiApiKey();
    
    $discoveryRepo = new \Discovery\DiscoveryRepository($pdo ?? youtubeGetDb($root));
    $reader = new \Youtube\Reader($discoveryRepo);
    $llm = \Discovery\Agents\LLMClient::forAgent('briefer', $agentsConfig, $apiKey);
    
    return new \Youtube\Pipeline($reader, $llm, $config, $pdo);
}
