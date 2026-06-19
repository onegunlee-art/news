<?php
/**
 * P1-3 — legacy boolean call-site audit (production paths only)
 *
 * Confirms eduIsMythBustQuest / eduIsDecisionInquiryQuest / eduIsConvergentQuest
 * are not referenced outside tools/tests after P1-3 removal.
 *
 * Usage: php tools/edu_p1_3_legacy_callsite_audit.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduQuestConfig.php';

$pass = 0;
$fail = 0;

function ok(string $label, bool $cond): void
{
    global $pass, $fail;
    if ($cond) {
        echo "PASS {$label}\n";
        $pass++;
        return;
    }
    echo "FAIL {$label}\n";
    $fail++;
}

$targets = [
    'eduIsMythBustQuest',
    'eduIsDecisionInquiryQuest',
    'eduIsConvergentQuest',
];

$scanDirs = [
    $root . '/public/api/edu',
    $root . '/src/backend/Services/edu',
    $root . '/src/frontend/src',
];

$allowedDef = $root . '/public/api/edu/lib/eduQuest.php';

foreach ($targets as $fn) {
    $hits = [];
    foreach ($scanDirs as $dir) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($it as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $path = $file->getPathname();
            if (!preg_match('/\.(php|tsx?)$/', $path)) {
                continue;
            }
            $content = (string) file_get_contents($path);
            if (str_contains($content, $fn . '(')) {
                $hits[] = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
            }
        }
    }
    ok("{$fn} production call sites 0", $hits === []);
    if ($hits !== []) {
        foreach ($hits as $h) {
            echo "  found: {$h}\n";
        }
    }
}

ok('turn.php removed', !is_file($root . '/public/api/edu/session/turn.php'));
ok('QuestFlowLegacy removed', !is_file($root . '/src/frontend/src/pages/edu/QuestFlowLegacy.tsx'));
ok('eduQuestHammerMode exists', function_exists('eduQuestHammerMode'));

echo "\n=== Summary: {$pass} pass, {$fail} fail ===\n";
exit($fail > 0 ? 1 : 0);
