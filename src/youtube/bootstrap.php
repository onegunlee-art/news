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
    $dbConfigPath = $root . '/config/database.php';
    $dbConfig = file_exists($dbConfigPath) ? require $dbConfigPath : [];
    
    $host = $dbConfig['host'] ?? $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
    $port = $dbConfig['port'] ?? $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '3306';
    $name = $dbConfig['name'] ?? $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'thegist';
    $user = $dbConfig['user'] ?? $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'root';
    $pass = $dbConfig['password'] ?? $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '';

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    
    return new \PDO($dsn, $user, $pass, [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function youtubeGetConfig(string $root): array
{
    $configPath = $root . '/config/youtube.php';
    if (!file_exists($configPath)) {
        throw new \RuntimeException('YouTube config not found: ' . $configPath);
    }
    return require $configPath;
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
