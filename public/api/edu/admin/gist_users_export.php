<?php
/**
 * GET /api/edu/admin/gist_users_export.php
 * READ ONLY — GIST MySQL users for EDU seed (X-Edu-Admin-Key)
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/eduAdminAuth.php';
require_once __DIR__ . '/../lib/eduMysql.php';
require_once __DIR__ . '/../lib/eduGistUserExport.php';

eduRequireAdminKey();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    eduSendError('GET only', 405);
}

try {
    $pdo = eduMysql();
    $users = eduGistExportFromPdo($pdo);
} catch (Throwable $e) {
    error_log('gist_users_export: ' . $e->getMessage());
    eduSendError('Export failed', 500);
}

eduSendJson([
    'success' => true,
    'count' => count($users),
    'users' => $users,
]);
