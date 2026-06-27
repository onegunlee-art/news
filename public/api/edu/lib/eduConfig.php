<?php
/**
 * GIST EDU feature flags (env-based, news pipeline isolated)
 */
declare(strict_types=1);

function eduUseTurnFsm(): bool
{
    $v = getenv('EDU_USE_TURN_FSM');
    if ($v === false || $v === '') {
        return true;
    }
    return !in_array(strtolower((string) $v), ['0', 'false', 'no', 'off'], true);
}

function eduMixupRagEnabled(): bool
{
    $v = getenv('EDU_MIXUP_RAG');
    if ($v === false || $v === '') {
        return true;
    }
    return !in_array(strtolower((string) $v), ['0', 'false', 'no', 'off'], true);
}

function eduJudgmentWritingEnabled(): bool
{
    $v = getenv('EDU_JUDGMENT_WRITING');
    if ($v === false || $v === '') {
        return true;
    }
    return !in_array(strtolower((string) $v), ['0', 'false', 'no', 'off'], true);
}

function eduUseChatEngine(): bool
{
    $v = getenv('EDU_USE_CHAT_ENGINE');
    if ($v === false || $v === '') {
        return true;
    }
    return !in_array(strtolower((string) $v), ['0', 'false', 'no', 'off'], true);
}

function eduLlmProvider(): string
{
    $v = getenv('EDU_LLM_PROVIDER');
    if ($v === false || $v === '') {
        return 'openai';
    }
    return strtolower((string) $v);
}

/** 완주 화면 structure_insight 디버그 페이로드 허용 (내부 검증용) */
function eduStructureInsightDebugAllowed(?array $student = null): bool
{
    require_once __DIR__ . '/eduStudentInsights.php';

    $flag = eduStructureDiagnoseEnv('EDU_STRUCTURE_INSIGHT_DEBUG');
    if ($flag === '1' || $flag === 'true') {
        return true;
    }

    $idsRaw = eduStructureDiagnoseEnv('EDU_INSIGHT_DEBUG_STUDENT_IDS');
    if ($idsRaw === false || $idsRaw === '') {
        return false;
    }
    if ($student === null || ($student['id'] ?? '') === '') {
        return false;
    }
    $studentId = (string) $student['id'];
    foreach (explode(',', (string) $idsRaw) as $id) {
        if ($id !== '' && hash_equals(trim($id), $studentId)) {
            return true;
        }
    }

    return false;
}

/** 코치 레벨 테스트 스위치 — EDU_LEVEL_DEBUG=1 또는 EDU_LEVEL_DEBUG_STUDENT_IDS */
function eduLevelDebugAllowed(?array $student = null): bool
{
    require_once __DIR__ . '/eduStudentInsights.php';

    $flag = eduStructureDiagnoseEnv('EDU_LEVEL_DEBUG');
    if ($flag === '1' || $flag === 'true') {
        return true;
    }

    $idsRaw = eduStructureDiagnoseEnv('EDU_LEVEL_DEBUG_STUDENT_IDS');
    if ($idsRaw === false || $idsRaw === '') {
        return false;
    }
    if ($student === null || ($student['id'] ?? '') === '') {
        return false;
    }
    $studentId = (string) $student['id'];
    foreach (explode(',', (string) $idsRaw) as $id) {
        if ($id !== '' && hash_equals(trim($id), $studentId)) {
            return true;
        }
    }

    return false;
}
