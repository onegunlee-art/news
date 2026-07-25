<?php
declare(strict_types=1);

require_once __DIR__ . '/autoload.php';

function discoveryFindProjectRoot(): string
{
    $candidates = [__DIR__ . '/../../', __DIR__ . '/../../../'];
    foreach ($candidates as $raw) {
        $path = realpath($raw);
        if ($path && file_exists($path . '/config/discovery.php')) {
            return rtrim($path, '/\\') . '/';
        }
    }
    throw new RuntimeException('Project root not found for discovery');
}

function discoveryLoadEnv(string $projectRoot): void
{
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

function discoveryGetDb(string $projectRoot): PDO
{
    $dbConfig = require $projectRoot . 'config/database.php';
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $dbConfig['host'],
        $dbConfig['port'] ?? 3306,
        $dbConfig['database']
    );
    return new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function discoveryConfig(string $projectRoot): array
{
    return require $projectRoot . 'config/discovery.php';
}

function discoveryEnsureEnabled(string $projectRoot): void
{
    $config = discoveryConfig($projectRoot);
    if (empty($config['enabled'])) {
        throw new RuntimeException('Discovery feature is disabled (ENABLE_DISCOVERY=false)');
    }
}

function discoveryEnsureTables(PDO $pdo, string $projectRoot): void
{
    $sqlFile = $projectRoot . 'database/discovery_schema.sql';
    if (!is_file($sqlFile)) {
        throw new RuntimeException('discovery_schema.sql not found');
    }
    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        throw new RuntimeException('Failed to read discovery_schema.sql');
    }
    foreach (array_filter(array_map('trim', preg_split('/;\s*\n/', $sql) ?: [])) as $statement) {
        if ($statement !== '' && stripos($statement, 'CREATE TABLE') !== false) {
            $pdo->exec($statement);
        }
    }
}

function discoveryJson(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function discoveryError(string $message, int $code = 400): never
{
    discoveryJson(['success' => false, 'error' => $message], $code);
}
