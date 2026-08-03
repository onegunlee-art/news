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
    $fontDir = $root . '/public/fonts/noto';

    // GD/FreeType는 .ttf가 .otf보다 안정적 — EC2 hashed ttf 우선
    $pattern = $weight === 'bold' ? 'noto_sans_kr_bold*.ttf' : 'noto_sans_kr_normal*.ttf';
    $matches = glob($fontDir . '/' . $pattern) ?: [];
    if ($matches !== []) {
        return $matches[0];
    }

    $candidates = $weight === 'bold'
        ? ['NotoSansKR-Bold.otf', 'noto_sans_kr_bold_b1d8ccaef03cabe0c50be6a406ebee03.ttf']
        : ['NotoSansKR-Regular.otf', 'noto_sans_kr_normal_f720aac0493f6f2cdc1ac7555480ae45.ttf'];

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
