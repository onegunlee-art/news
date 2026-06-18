<?php
/**
 * Apply edu_session_abandoned.sql (Postgres / Supabase DDL)
 *
 * Usage: php tools/edu_apply_abandoned_stage_migration.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';

$sqlFile = $root . '/database/migrations/edu_session_abandoned.sql';
$sql = file_get_contents($sqlFile);
if ($sql === false || trim($sql) === '') {
    fwrite(STDERR, "Missing migration file: {$sqlFile}\n");
    exit(1);
}

$candidates = [
    getenv('SUPABASE_DB_URL') ?: '',
    getenv('DATABASE_URL') ?: '',
    getenv('SUPABASE_POSTGRES_URL') ?: '',
];
$dbUrl = '';
foreach ($candidates as $c) {
    if (is_string($c) && $c !== '' && str_contains($c, 'postgres')) {
        $dbUrl = $c;
        break;
    }
}

if ($dbUrl === '') {
    echo "No Postgres URL in env (SUPABASE_DB_URL / DATABASE_URL).\n";
    echo "Run this SQL in Supabase SQL Editor:\n\n";
    echo $sql . "\n";
    exit(2);
}

if (!extension_loaded('pdo_pgsql')) {
    fwrite(STDERR, "pdo_pgsql extension required.\n");
    exit(1);
}

try {
    $pdo = new PDO($dbUrl, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Throwable $e) {
    fwrite(STDERR, 'DB connect failed: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "Applying edu_session_abandoned.sql ...\n";
foreach (array_filter(array_map('trim', preg_split('/;\s*\n/', $sql) ?: [])) as $statement) {
    if ($statement === '' || str_starts_with($statement, '--')) {
        continue;
    }
    $pdo->exec($statement);
    echo "[OK] " . strtok($statement, "\n") . "\n";
}

echo "Done.\n";
