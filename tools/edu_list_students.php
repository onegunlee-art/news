<?php
declare(strict_types=1);
$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
$rows = eduSupabase()->select('edu_students', 'order=last_active_at.desc&select=id,display_name,status,created_at,last_active_at', 30);
echo "=== edu_students (recent 30) ===\n\n";
foreach ($rows as $s) {
    echo ($s['display_name'] ?? '?') . " status=" . ($s['status'] ?? '?') . " id=" . ($s['id'] ?? '') . "\n";
}
