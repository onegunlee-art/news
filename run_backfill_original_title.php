<?php
/**
 * CLI 실행: php run_backfill_original_title.php [dry_run] [limit]
 * 예: php run_backfill_original_title.php dry_run 5
 */
$_SERVER['REQUEST_METHOD'] = 'GET';
if (isset($argv[1]) && $argv[1] === 'dry_run') {
    $_GET['dry_run'] = '1';
}
if (isset($argv[2]) && is_numeric($argv[2])) {
    $_GET['limit'] = $argv[2];
}
chdir(__DIR__);
require __DIR__ . '/public/api/admin/backfill-original-title-from-html.php';
