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
    return discoveryConnectDb($dbConfig);
}

function discoveryGetDbByName(string $projectRoot, string $database): PDO
{
    $dbConfig = require $projectRoot . 'config/database.php';
    $dbConfig['database'] = $database;
    return discoveryConnectDb($dbConfig);
}

/** @param array<string, mixed> $dbConfig */
function discoveryConnectDb(array $dbConfig): PDO
{
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

function discoveryTodayKst(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('Asia/Seoul')))->format('Y-m-d');
}

function discoveryEnsurePublicEnabled(string $projectRoot): void
{
    discoveryEnsureEnabled($projectRoot);
    $config = discoveryConfig($projectRoot);
    if (empty($config['public_enabled'])) {
        discoveryError('Discovery public service is disabled (ENABLE_DISCOVERY_PUBLIC=false)', 503);
    }
}

function discoveryEnsurePublicMigration(PDO $pdo, string $projectRoot): void
{
    $sqlFile = $projectRoot . 'database/discovery_public_migration.sql';
    if (!is_file($sqlFile)) {
        return;
    }
    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        return;
    }
    foreach (array_filter(array_map('trim', preg_split('/;\s*\n/', $sql) ?: [])) as $statement) {
        if ($statement === '' || stripos($statement, 'SELECT 1') === 0) {
            continue;
        }
        try {
            $pdo->exec($statement);
        } catch (Throwable $e) {
            // Prepared migration blocks may noop on repeat runs.
            if (!str_contains($e->getMessage(), 'Duplicate')) {
                error_log('discovery public migration: ' . $e->getMessage());
            }
        }
    }
}

function discoveryEnsureSeedMigration(PDO $pdo, string $projectRoot): void
{
    $sqlFile = $projectRoot . 'database/discovery_seed_migration.sql';
    if (!is_file($sqlFile)) {
        return;
    }
    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        return;
    }
    foreach (array_filter(array_map('trim', preg_split('/;\s*\n/', $sql) ?: [])) as $statement) {
        if ($statement === '' || stripos($statement, 'SELECT 1') === 0) {
            continue;
        }
        try {
            $pdo->exec($statement);
        } catch (Throwable $e) {
            if (!str_contains($e->getMessage(), 'Duplicate')) {
                error_log('discovery seed migration: ' . $e->getMessage());
            }
        }
    }
}

function discoveryDeviceKey(): ?string
{
    $key = trim((string) ($_SERVER['HTTP_X_DISCOVERY_DEVICE_KEY'] ?? ''));
    if ($key === '' || strlen($key) > 64 || !preg_match('/^[a-zA-Z0-9\-_]+$/', $key)) {
        return null;
    }

    return $key;
}

function discoveryRequireDeviceKey(): string
{
    $key = discoveryDeviceKey();
    if (!$key) {
        discoveryError('X-Discovery-Device-Key header required', 401);
    }

    return $key;
}

function discoveryPublicException(Throwable $e): never
{
    $code = 400;
    if ($e instanceof RuntimeException && $e->getCode() >= 400 && $e->getCode() < 600) {
        $code = (int) $e->getCode();
    } elseif ($e instanceof \InvalidArgumentException) {
        $code = 422;
    }
    discoveryError($e->getMessage(), $code);
}

function discoveryError(string $message, int $code = 400): never
{
    discoveryJson(['success' => false, 'error' => $message], $code);
}
