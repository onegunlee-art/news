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
