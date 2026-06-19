<?php
/**
 * GIST EDU smoke test — verifies EDU additions without touching news hotpath contracts.
 *
 * Usage: php tests/edu_smoke_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

function smokeAssert(bool $ok, string $msg): void
{
    global $errors;
    if (!$ok) {
        $errors[] = $msg;
        fwrite(STDERR, "FAIL: {$msg}\n");
    } else {
        echo "OK: {$msg}\n";
    }
}

$requiredFiles = [
    'src/backend/Services/edu/EduQuestFactory.php',
    'src/backend/Services/edu/EduRagService.php',
    'public/api/edu/session/chat.php',
    'public/api/edu/internal/quest-candidate.php',
    'public/api/edu/lib/eduConfig.php',
    'cron/edu_quest_curator.php',
    'database/migrations/edu_quest_articles_snapshot.sql',
];

foreach ($requiredFiles as $rel) {
    smokeAssert(is_file($root . '/' . $rel), "file exists: {$rel}");
}

require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduConfig.php';
require_once $root . '/public/api/edu/lib/eduAgents.php';

eduLoadAgents();
smokeAssert(class_exists('Services\\Edu\\EduQuestFactory'), 'EduQuestFactory autoload');
smokeAssert(class_exists('Services\\Edu\\EduRagService'), 'EduRagService autoload');
smokeAssert(eduUseChatEngine() === true, 'EDU_USE_CHAT_ENGINE default true');

$newsFiles = [
    'public/api/admin/news.php',
    'public/api/admin/ai-analyze.php',
    'src/agents/services/RAGService.php',
];
foreach ($newsFiles as $rel) {
    $path = $root . '/' . $rel;
    smokeAssert(is_file($path), "news hotpath present: {$rel}");
    if (is_file($path)) {
        $contents = file_get_contents($path);
        smokeAssert(
            !str_contains((string) $contents, 'EduQuestFactory'),
            "news file isolated from EDU: {$rel}"
        );
    }
}

if ($errors !== []) {
    fwrite(STDERR, "\n" . count($errors) . " smoke test failure(s)\n");
    exit(1);
}

echo "\nAll EDU smoke checks passed.\n";
