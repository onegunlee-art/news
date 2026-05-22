<?php
declare(strict_types=1);

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
    foreach ([$projectRoot . 'env.txt', $projectRoot . '.env', $projectRoot . '.env.production'] as $file) {
        if (!is_file($file)) {
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
        break;
    }
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
