<?php
/**
 * GIST EDU — P2-B 진단 기반 XP (탐구 질, 정답/맞틀 무관)
 */
declare(strict_types=1);

const EDU_XP_PER_AXIS_ENGAGED = 10;
const EDU_XP_TENSION_BOTH_SIDES = 15;
const EDU_XP_CONCLUSION_CLEAR = 10;
const EDU_XP_EVIDENCE_LINKED = 10;
const EDU_XP_EVASION_COMPLETE = 5;
const EDU_XP_COMPLETE_FLOOR = 5;
const EDU_XP_COMPLETE_CAP = 65;

/**
 * @param array<string, mixed> $diag eduStructureDiagnoseSession 출력
 */
function eduXpFromStructureDiagnose(array $diag): int
{
    $axesCovered = is_array($diag['axes_covered'] ?? null) ? $diag['axes_covered'] : [];
    $xp = 0;

    foreach ($axesCovered as $axis) {
        if (!is_array($axis)) {
            continue;
        }
        if (!empty($axis['covered'])) {
            $xp += EDU_XP_PER_AXIS_ENGAGED;
        }
    }

    if (($diag['tension_engaged'] ?? '') === '양면') {
        $xp += EDU_XP_TENSION_BOTH_SIDES;
    }
    if (($diag['conclusion_clarity'] ?? '') === '명확') {
        $xp += EDU_XP_CONCLUSION_CLEAR;
    }
    if (($diag['evidence_linked'] ?? '') === 'yes') {
        $xp += EDU_XP_EVIDENCE_LINKED;
    }

    $hasSkipped = false;
    foreach ($axesCovered as $axis) {
        if (is_array($axis) && ($axis['status'] ?? '') === 'skipped') {
            $hasSkipped = true;
            break;
        }
    }
    if ($hasSkipped) {
        $xp += EDU_XP_EVASION_COMPLETE;
    }

    return max(EDU_XP_COMPLETE_FLOOR, min(EDU_XP_COMPLETE_CAP, $xp));
}
