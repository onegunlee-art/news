<?php
/**
 * P2-A1 — 경첩 검수 기록 (승인 / 수정 → reviews.jsonl)
 *
 * Usage:
 *   php tools/edu_hinge_review.php approve 630
 *   php tools/edu_hinge_review.php approve 630 --reviewer=iwg
 *   php tools/edu_hinge_review.php edit 630 --side_a="..." --side_b="..."
 *   php tools/edu_hinge_review.php edit 630 --file=path/to/edited.json
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/public/api/lib/env_bootstrap.php';
require_once $root . '/public/api/edu/lib/bootstrap.php';
require_once $root . '/public/api/edu/lib/eduHingeExtract.php';

function hingeReviewParseArg(string $prefix): ?string
{
    global $argv;
    foreach ($argv ?? [] as $arg) {
        if (str_starts_with($arg, $prefix)) {
            return substr($arg, strlen($prefix));
        }
    }
    return null;
}

$action = $argv[1] ?? '';
$newsId = isset($argv[2]) && is_numeric($argv[2]) ? (int) $argv[2] : 0;
$reviewer = hingeReviewParseArg('--reviewer=') ?? getenv('USER') ?: getenv('USERNAME') ?: 'reviewer';

if (!in_array($action, ['approve', 'edit'], true) || $newsId <= 0) {
    fwrite(STDERR, "Usage:\n");
    fwrite(STDERR, "  php tools/edu_hinge_review.php approve <news_id> [--reviewer=name]\n");
    fwrite(STDERR, "  php tools/edu_hinge_review.php edit <news_id> [--hinge=...] [--side_a=...] ...\n");
    fwrite(STDERR, "  php tools/edu_hinge_review.php edit <news_id> --file=edited.json\n");
    exit(1);
}

$extraction = eduHingeLoadExtraction($newsId);
if ($extraction === null) {
    fwrite(STDERR, "No extraction at " . eduHingeExtractionPath($newsId) . "\n");
    fwrite(STDERR, "Run: php tools/edu_gist_hinge_extract.php {$newsId}\n");
    exit(1);
}

$llmConfidence = $extraction['confidence'] ?? null;
$finalHinge = eduHingePickFields($extraction, array_merge(eduHingeHingeFieldNames(), ['news_id', 'title', 'confidence']));
$editedFields = [];

if ($action === 'edit') {
    $filePath = hingeReviewParseArg('--file=');
    if ($filePath !== null) {
        if (!is_file($filePath)) {
            fwrite(STDERR, "File not found: {$filePath}\n");
            exit(1);
        }
        $fromFile = json_decode((string) file_get_contents($filePath), true);
        if (!is_array($fromFile)) {
            fwrite(STDERR, "Invalid JSON in {$filePath}\n");
            exit(1);
        }
        foreach (eduHingeHingeFieldNames() as $field) {
            if (array_key_exists($field, $fromFile)) {
                $finalHinge[$field] = $fromFile[$field];
            }
        }
    } else {
        foreach (eduHingeHingeFieldNames() as $field) {
            $val = hingeReviewParseArg('--' . $field . '=');
            if ($val !== null) {
                $finalHinge[$field] = $val;
            }
        }
        $confOverride = hingeReviewParseArg('--confidence=');
        if ($confOverride !== null) {
            $finalHinge['confidence'] = $confOverride;
        }
    }

    if ($finalHinge['hinge'] === '') {
        $finalHinge['hinge'] = null;
    }

    $editedFields = eduHingeDiffFields($extraction, $finalHinge);
    if ($editedFields === []) {
        fwrite(STDERR, "No fields changed. Pass --field=value or --file=...\n");
        exit(1);
    }
}

$review = [
    'news_id' => $newsId,
    'llm_confidence' => $llmConfidence,
    'review_action' => $action === 'approve' ? 'approve' : 'edit',
    'edited_fields' => $editedFields,
    'final_hinge' => eduHingePickFields($finalHinge, eduHingeHingeFieldNames()),
    'reviewed_at' => date('c'),
    'reviewer' => $reviewer,
    'extraction_extracted_at' => $extraction['extracted_at'] ?? null,
];

$path = eduHingeAppendReview($review);

echo "Recorded {$review['review_action']} for news_id={$newsId}\n";
echo "  llm_confidence: " . ($llmConfidence ?? 'null') . "\n";
if ($editedFields !== []) {
    echo '  edited_fields: ' . implode(', ', $editedFields) . "\n";
}
echo "  → {$path}\n";
