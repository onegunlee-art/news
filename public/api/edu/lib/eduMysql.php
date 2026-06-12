<?php
/**
 * GIST EDU — MySQL READ ONLY (news table)
 */
declare(strict_types=1);

function eduMysql(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    require_once __DIR__ . '/../../lib/auth.php';
    $pdo = getDb();
    return $pdo;
}

/** @return list<string> */
function eduNewsColumns(PDO $pdo): array
{
    static $cols = null;
    if ($cols !== null) {
        return $cols;
    }
    $cols = [];
    foreach ($pdo->query('SHOW COLUMNS FROM news') as $row) {
        $cols[] = (string) $row['Field'];
    }
    return $cols;
}
