<?php
/**
 * compose.php 등 — eduLoadAgents() 호출 전 eduAgents.php require 여부 정적 검사
 *
 * Usage: php tools/edu_compose_bootstrap_require_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$targets = [
    'public/api/edu/session/compose.php',
    'public/api/edu/session/chat.php',
    'public/api/edu/internal/quest-candidate.php',
];

$fail = 0;
foreach ($targets as $rel) {
    $path = $root . '/' . $rel;
    if (!is_file($path)) {
        echo "SKIP missing {$rel}\n";
        continue;
    }
    $src = (string) file_get_contents($path);
    $usesAgents = str_contains($src, 'eduLoadAgents(');
    $requiresAgents = (bool) preg_match('/require_once\s+[^;]*eduAgents\.php/', $src);
    if ($usesAgents && !$requiresAgents) {
        echo "FAIL {$rel}: calls eduLoadAgents() without require eduAgents.php\n";
        $fail++;
        continue;
    }
    echo "OK {$rel}\n";
}

if ($fail > 0) {
    exit(1);
}

echo "\n=== bootstrap require test PASS ===\n";
