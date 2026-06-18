<?php
/**
 * POST /api/edu/session/chat — 가변 대화 harness (ConversationDirector)
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/eduAuth.php';
require_once __DIR__ . '/../lib/eduQuest.php';
require_once __DIR__ . '/../lib/eduConfig.php';
require_once __DIR__ . '/../lib/eduBlueprint.php';
require_once __DIR__ . '/../lib/eduAgents.php';
require_once __DIR__ . '/../lib/_llm.php';

$root = eduFindProjectRoot();
require_once $root . 'src/backend/autoload.php';
eduLoadAgents();

use Services\Edu\Agents\ConversationDirector;
use Services\Edu\Agents\GistStyleComposer;
use Services\Edu\Agents\Hammer;
use Services\Edu\Agents\Reflection;
use Services\Edu\Agents\SocraticCoach;
use Services\Edu\Agents\StanceScorer;
use Services\Edu\EduRagService;

handleOptionsRequest();
setCorsHeaders();
eduRequirePost();

if (!eduUseChatEngine()) {
    eduSendError('Chat engine disabled', 503);
}

$student = eduRequireStudent();
$supabase = eduSupabase();
$body = eduJsonBody();

$sessionId = trim((string) ($body['session_id'] ?? ''));
$message = trim((string) ($body['message'] ?? ''));
$action = trim((string) ($body['action'] ?? 'continue'));
$stanceInput = $body['stance'] ?? null;

if ($sessionId === '') {
    eduSendError('session_id required');
}

$sessions = $supabase->select('edu_quest_sessions', 'id=eq.' . $sessionId . '&student_id=eq.' . $student['id'], 1);
if (empty($sessions[0])) {
    eduSendError('Session not found', 404);
}
$session = $sessions[0];

if (($session['stage'] ?? '') === 'completed') {
    eduSendJson([
        'success' => true,
        'session_id' => $sessionId,
        'stage' => 'completed',
        'should_compose' => false,
        'progress_pct' => 100,
        'assistant_message' => '이미 완료된 세션이에요. 홈에서 결과를 확인해보세요!',
    ]);
}

$quests = $supabase->select('edu_daily_quests', 'id=eq.' . $session['quest_id'], 1);
$quest = $quests[0] ?? [];
$quest['articles'] = $supabase->select(
    'edu_quest_articles',
    'quest_id=eq.' . $session['quest_id'] . '&order=sort_order.asc',
    20
) ?? [];

$blueprint = eduLoadBlueprint($session);
$dialogue = eduLoadDialogue($session);
$llm = eduLlm();
$rag = new EduRagService($supabase);
$director = new ConversationDirector($llm);
$coach = new SocraticCoach($llm);

$response = [
    'success' => true,
    'session_id' => $sessionId,
    'progress_pct' => eduBlueprintProgress($blueprint),
    'phase' => $blueprint['phase'] ?? 'stance',
    'should_compose' => false,
];

// --- myth_bust opening (free text, no pro/con) ---
if ($action === 'submit_opening') {
    if (!eduIsMythBustQuest($quest)) {
        eduSendError('submit_opening is myth_bust only', 400);
    }
    if ($message === '') {
        eduSendError('message required');
    }
    if ((string) ($blueprint['phase'] ?? 'stance') !== 'stance') {
        eduSendError('opening already submitted', 400);
    }

    $hints = eduQuestHammerHints($quest);
    $hookFull = trim((string) ($hints['hook_full'] ?? ''));
    if ($hookFull !== '' && $dialogue === []) {
        $dialogue = eduAppendDialogue($dialogue, 'assistant', $hookFull, 'hook', 'stance');
    }

    $blueprint = eduMergeBlueprint($blueprint, [
        'reason' => $message,
        'opening_submitted' => true,
        'phase' => 'reasoning',
        'exchange_count' => (int) ($blueprint['exchange_count'] ?? 0) + 1,
    ]);

    $dialogue = eduAppendDialogue($dialogue, 'student', $message, null, (string) ($blueprint['phase'] ?? 'reasoning'));
    $openingEval = $coach->evaluateResponse('myth_bust', $message, $quest, 'reason');
    $studentTexts = $coach->collectStudentTexts($dialogue);

    if ($coach->shouldAdvanceReasoningMythBust($openingEval, $message, $studentTexts, 0)) {
        $advanced = eduChatAdvanceToEvidence($blueprint, $quest, $response);
        $blueprint = $advanced['blueprint'];
        $assistantMessage = $advanced['assistantMessage'];
        $response = $advanced['response'];
    } else {
        $followup = $coach->askOpeningFollowupMythBust($quest, $message);
        $question = trim((string) ($followup['question'] ?? ''));
        if ($question === '' || $coach->questionOverlapsStudentText($question, $studentTexts)) {
            $advanced = eduChatAdvanceToEvidence($blueprint, $quest, $response);
            $blueprint = $advanced['blueprint'];
            $assistantMessage = $advanced['assistantMessage'];
            $response = $advanced['response'];
        } else {
            $assistantMessage = $director->refinePrompt($question, $quest, eduBlueprintProgress($blueprint));
        }
    }
    $dialogue = eduAppendDialogue($dialogue, 'assistant', $assistantMessage, 'socratic', (string) ($blueprint['phase'] ?? 'reasoning'));
    eduSaveBlueprint($supabase, $sessionId, $blueprint, $dialogue);

    eduSendJson(array_merge($response, [
        'stage' => eduBlueprintStage($blueprint),
        'phase' => $blueprint['phase'] ?? 'reasoning',
        'assistant_message' => $assistantMessage,
        'progress_pct' => eduBlueprintProgress($blueprint),
        'ui_hint' => ($blueprint['phase'] ?? '') === 'evidence' ? 'ask_evidence' : 'opening_done',
        'blueprint' => $blueprint,
    ]));
}

// --- Stance selection (no message required) ---
if ($action === 'select_stance') {
    if (eduIsMythBustQuest($quest)) {
        eduSendError('myth_bust quests use submit_opening, not select_stance', 400);
    }
    if (!in_array($stanceInput, ['pro', 'con'], true)) {
        eduSendError('stance (pro|con) required');
    }

    $blueprint = eduMergeBlueprint($blueprint, [
        'stance' => $stanceInput,
        'final_stance' => $stanceInput,
        'phase' => 'reasoning',
        'exchange_count' => (int) ($blueprint['exchange_count'] ?? 0) + 1,
    ]);

    $supabase->insert('edu_hypothesis_versions', [
        'session_id' => $sessionId,
        'student_id' => $student['id'],
        'version' => 1,
        'stance' => $stanceInput,
        'confidence_level' => 3,
    ]);

    $question = $coach->askWhy($quest, $stanceInput);
    $assistantMessage = $director->refinePrompt(
        $question['question'] ?? '왜 그렇게 생각해요?',
        $quest,
        eduBlueprintProgress($blueprint)
    );

    $dialogue = eduAppendDialogue($dialogue, 'assistant', $assistantMessage, 'socratic', 'reasoning');
    eduSaveBlueprint($supabase, $sessionId, $blueprint, $dialogue);

    eduSendJson(array_merge($response, [
        'stage' => eduBlueprintStage($blueprint),
        'phase' => 'reasoning',
        'assistant_message' => $assistantMessage,
        'progress_pct' => eduBlueprintProgress($blueprint),
        'ui_hint' => '이유 말하기',
    ]));
}

if ($message === '' && $action !== 'confirm_reflection') {
    eduSendError('message required');
}

$eval = [];
$assistantMessage = '';
$decision = [];

/**
 * @param array<string, mixed> $blueprint
 * @param array<string, mixed> $quest
 * @param list<array<string, mixed>> $dialogue
 * @param array<string, mixed> $response
 * @return array{blueprint: array<string, mixed>, assistantMessage: string, response: array<string, mixed>, decision: array<string, mixed>}
 */
function eduChatApplyReflectionCompose(
    array $blueprint,
    array $quest,
    array $dialogue,
    $llm,
    EduRagService $rag,
    ConversationDirector $director,
    array $response = []
): array {
    $composer = new GistStyleComposer($llm, $rag);
    $structurePreview = $composer->previewStructure($blueprint, $quest, $dialogue);
    $structureFailed = isset($structurePreview['success']) && $structurePreview['success'] === false;

    $merge = [
        'reflection_confirmed' => true,
    ];
    if (!$structureFailed) {
        $merge['ready_for_compose'] = true;
        $merge['phase'] = 'compose';
        $merge['essay_structure'] = $structurePreview;
    } else {
        $merge['ready_for_compose'] = false;
        $merge['phase'] = 'reflection';
    }
    $blueprint = eduMergeBlueprint($blueprint, $merge);

    if ($structureFailed) {
        $assistantMessage = '생각 정리는 잘 됐어! 글 구조를 만드는 데 잠깐 문제가 생겼어. 잠시 후 「맞아」를 다시 눌러줘.';
    } else {
        $assistantMessage = '좋아! 아래 구조도대로 네 생각을 글로 정리해볼게. 잠시만 기다려줘.';
        $response['structure_preview'] = $structurePreview;
    }
    $decision = $director->decide($blueprint, $quest);
    $response['should_compose'] = !$structureFailed;
    $response['ui_hint'] = $structureFailed ? 'reflection_confirm' : 'compose';
    if ($structureFailed) {
        $response['compose_error'] = $structurePreview['error'] ?? 'compose_structure_failed';
    }

    return [
        'blueprint' => $blueprint,
        'assistantMessage' => $assistantMessage,
        'response' => $response,
        'decision' => $decision,
    ];
}

/**
 * @param array<string, mixed> $blueprint
 * @param array<string, mixed> $quest
 * @param array<string, mixed> $response
 * @return array{blueprint: array<string, mixed>, assistantMessage: string, response: array<string, mixed>}
 */
function eduChatAdvanceToEvidence(
    array $blueprint,
    array $quest,
    array $response = []
): array {
    $blueprint = eduMergeBlueprint($blueprint, ['phase' => 'evidence']);
    $publicArticles = [];
    foreach ($quest['articles'] as $a) {
        $publicArticles[] = eduPublicArticleRow($quest, $a);
    }
    $assistantMessage = eduBuildEvidenceBridgeMessage($blueprint);
    $response['articles'] = $publicArticles;
    $response['ui_hint'] = 'ask_evidence';

    return [
        'blueprint' => $blueprint,
        'assistantMessage' => $assistantMessage,
        'response' => $response,
    ];
}

// --- Process student message by phase ---
$phase = (string) ($blueprint['phase'] ?? 'reasoning');

// 정리 확인 버튼/액션 — reflection 단계에서만 compose 트리거
if ($action === 'confirm_reflection') {
    if ($phase !== 'reflection') {
        eduSendError('아직 정리 확인 단계가 아니에요. 반론에 먼저 답해줘.', 400);
    }
    $confirmText = $message !== '' ? $message : '맞아';
    $dialogue = eduAppendDialogue($dialogue, 'student', $confirmText, null, 'reflection');
    $blueprint['exchange_count'] = (int) ($blueprint['exchange_count'] ?? 0) + 1;

    $composed = eduChatApplyReflectionCompose($blueprint, $quest, $dialogue, $llm, $rag, $director, $response);
    $blueprint = $composed['blueprint'];
    $assistantMessage = $composed['assistantMessage'];
    $response = $composed['response'];
    $decision = $composed['decision'];

    if ($assistantMessage !== '') {
        $dialogue = eduAppendDialogue($dialogue, 'assistant', $assistantMessage, $decision['next_agent'] ?? 'composer', (string) ($blueprint['phase'] ?? 'reflection'));
    }
    eduSaveBlueprint($supabase, $sessionId, $blueprint, $dialogue);
    eduSendJson(array_merge($response, [
        'stage' => eduBlueprintStage($blueprint),
        'phase' => $blueprint['phase'] ?? $phase,
        'assistant_message' => $assistantMessage,
        'progress_pct' => (int) ($decision['progress_pct'] ?? eduBlueprintProgress($blueprint)),
        'blueprint' => $blueprint,
    ]));
}

$dialogue = eduAppendDialogue($dialogue, 'student', $message, null, $phase);
$blueprint['exchange_count'] = (int) ($blueprint['exchange_count'] ?? 0) + 1;

if ($phase === 'reasoning') {
    $stanceForEval = eduIsMythBustQuest($quest) ? 'myth_bust' : (string) ($blueprint['stance'] ?? 'pro');
    $eval = $coach->evaluateResponse($stanceForEval, $message, $quest, 'reason');
    $blueprint = eduMergeBlueprint($blueprint, [
        'reason' => $message,
        'reason_depth' => (int) ($eval['depth_score'] ?? 3),
    ]);

    $supabase->insert('edu_thinking_logs', [
        'session_id' => $sessionId,
        'student_id' => $student['id'],
        'turn_number' => (int) ($blueprint['exchange_count'] ?? 1),
        'agent_role' => 'socratic',
        'student_response' => $message,
        'ai_feedback' => $eval['feedback_hint'] ?? null,
    ]);
    if (!eduIsMythBustQuest($quest)) {
        $supabase->update('edu_hypothesis_versions', 'session_id=eq.' . $sessionId . '&version=eq.1', [
            'reason' => $message,
        ]);
    }

    $decision = $director->decide($blueprint, $quest, $eval);
    $studentTexts = $coach->collectStudentTexts($dialogue);
    $coachQuestions = $coach->collectCoachQuestions($dialogue);
    $followupCount = (int) ($blueprint['reason_followup_count'] ?? 0);
    $mythBustAdvance = eduIsMythBustQuest($quest)
        && $coach->shouldAdvanceReasoningMythBust($eval, $message, $studentTexts, $followupCount);

    if (($decision['action'] ?? '') === 'followup' && !$mythBustAdvance) {
        $blueprint['reason_followup_count'] = $followupCount + 1;
        if (eduIsMythBustQuest($quest)) {
            $followup = $coach->askReasonFollowupMythBust($quest, $message, $studentTexts, $coachQuestions);
            $question = trim((string) ($followup['question'] ?? ''));
            if ($question === '' || $coach->questionOverlapsStudentText($question, $studentTexts)) {
                $advanced = eduChatAdvanceToEvidence($blueprint, $quest, $response);
                $blueprint = $advanced['blueprint'];
                $assistantMessage = $advanced['assistantMessage'];
                $response = $advanced['response'];
            } else {
                $assistantMessage = $director->refinePrompt($question, $quest, (int) ($decision['progress_pct'] ?? 25));
            }
        } else {
            $stanceForCoach = (string) ($blueprint['stance'] ?? '');
            $followup = $coach->askWhy($quest, $stanceForCoach !== '' ? $stanceForCoach : 'pro', $message);
            $assistantMessage = $director->refinePrompt($followup['question'] ?? '조금 더 말해줄래?', $quest, (int) ($decision['progress_pct'] ?? 25));
        }
    } else {
        $advanced = eduChatAdvanceToEvidence($blueprint, $quest, $response);
        $blueprint = $advanced['blueprint'];
        $assistantMessage = $advanced['assistantMessage'];
        $response = $advanced['response'];
    }
} elseif ($phase === 'evidence') {
    $eval = $coach->evaluateResponse((string) ($blueprint['stance'] ?? 'pro'), $message, $quest, 'evidence');
    $blueprint = eduMergeBlueprint($blueprint, ['evidence' => $message]);

    $supabase->insert('edu_evidence_logs', [
        'session_id' => $sessionId,
        'student_id' => $student['id'],
        'evidence_text' => $message,
        'source_type' => 'student',
    ]);

    $decision = $director->decide($blueprint, $quest, $eval);

    if (($decision['action'] ?? '') === 'nudge_evidence') {
        $blueprint['evidence_nudge_count'] = (int) ($blueprint['evidence_nudge_count'] ?? 0) + 1;
        $assistantMessage = $director->refinePrompt(
            '기사에서 본 구체적인 사실 하나를 더 적어줘. 예를 들면 드론 공격, 인도·파키스탄, 한국 핵무장 같은 내용이면 좋아.',
            $quest,
            (int) ($decision['progress_pct'] ?? 45)
        );
    } else {
        $blueprint['phase'] = 'hammer';
        $stance = (string) ($blueprint['stance'] ?? 'pro');
        $reason = (string) ($blueprint['reason'] ?? '');
        $scorer = new StanceScorer($llm);
        $analysis = $scorer->scoreStance($stance, $reason . ' ' . $message, $quest);

        $mixup = eduBuildMixupContext($quest, $rag);
        $mixupContext = $mixup['mixup_context'];
        $mixupSources = $mixup['mixup_sources'];

        $hammer = new Hammer($llm);
        $strike = $hammer->strike(
            $stance,
            $reason . ' ' . $message,
            $quest,
            $analysis['recommended_hammer_intensity'] ?? 'medium',
            $analysis,
            $mixupContext
        );

        $counterRow = [
            'session_id' => $sessionId,
            'student_id' => $student['id'],
            'counter_argument' => $strike['counter_argument'],
        ];
        if ($mixupSources !== []) {
            $counterRow['mixup_sources'] = $mixupSources;
        }
        $supabase->insert('edu_counter_logs', $counterRow);

        $blueprint['counter_argument'] = $strike['counter_argument'];
        $assistantMessage = eduFormatHammerDelivery(
            (string) ($strike['counter_argument'] ?? ''),
            (string) ($strike['mode'] ?? '')
        );
        $response['counter_argument'] = $strike['counter_argument'];
        $response['mixup_sources'] = $mixupSources;
        $response['hammer_mode'] = $strike['mode'] ?? 'adversarial';
        if (($strike['mode'] ?? '') === 'convergent' || ($strike['mode'] ?? '') === 'convergent_meta_ask') {
            $response['student_axis'] = $strike['student_axis'] ?? null;
            $response['counter_axis'] = $strike['counter_axis'] ?? null;
            $response['pivot_question'] = $strike['pivot_question'] ?? null;
        }
    }
} elseif ($phase === 'hammer') {
    // "맞아"를 반론 답변으로 삼으면 정리 확인 턴이 한 박자 밀려 compose가 안 걸림
    if (eduIsReflectionConfirm($message)) {
        $assistantMessage = '아직 다른 시각에 답하지 않았어. 위에서 말한 다른 시각, 네 생각을 한두 문장으로 말해줘. "맞아"는 정리 확인할 때 눌러줘!';
        $response['ui_hint'] = 'hammer_rebuttal';
        $decision = ['progress_pct' => 72, 'next_agent' => 'hammer'];
    } else {
    $eval = $coach->evaluateResponse((string) ($blueprint['stance'] ?? 'pro'), $message, $quest, 'rebuttal');
    $stanceChanged = (bool) ($body['stance_changed'] ?? false);
    $newStance = $body['new_stance'] ?? $blueprint['stance'];
    $finalStance = $stanceChanged && in_array($newStance, ['pro', 'con'], true)
        ? $newStance
        : ($blueprint['stance'] ?? 'pro');

    $blueprint = eduMergeBlueprint($blueprint, [
        'rebuttal' => $message,
        'counter_handled' => true,
        'stance_changed' => $stanceChanged,
        'final_stance' => $finalStance,
        'phase' => 'reflection',
    ]);

    $matchedAxis = eduMatchStudentAxisFromText($message, $quest);
    if ($matchedAxis !== null) {
        $blueprint['student_axis'] = $matchedAxis['axis_id'];
    }

    $supabase->update('edu_counter_logs', 'session_id=eq.' . $sessionId, [
        'student_rebuttal' => $message,
        'led_to_stance_change' => $stanceChanged,
    ]);

    $supabase->insert('edu_hypothesis_versions', [
        'session_id' => $sessionId,
        'student_id' => $student['id'],
        'version' => 2,
        'stance' => $finalStance,
        'reason' => $message,
        'confidence_level' => 3,
    ]);

    $v1 = $supabase->select('edu_hypothesis_versions', 'session_id=eq.' . $sessionId . '&version=eq.1', 1);
    $counter = $supabase->select('edu_counter_logs', 'session_id=eq.' . $sessionId, 1);

    $reflection = new Reflection($llm);
    $initialStance = (string) ($v1[0]['stance'] ?? $blueprint['stance'] ?? '');
    $summary = $reflection->summarize(
        $initialStance,
        $v1[0]['reason'] ?? $blueprint['reason'],
        $counter[0]['counter_argument'] ?? $blueprint['counter_argument'],
        $message,
        $finalStance,
        $quest,
        $blueprint
    );

    $blueprint['reflection_lines'] = $summary['summary_lines'] ?? [];
    $supabase->insert('edu_reflections', [
        'session_id' => $sessionId,
        'student_id' => $student['id'],
        'summary_lines' => $summary['summary_lines'],
        'key_insight' => $summary['key_insight'] ?? '',
        'stance_change_reason' => $stanceChanged ? $message : null,
    ]);

    $lines = implode("\n", $summary['summary_lines'] ?? []);
    $assistantMessage = "지금까지 생각을 정리해볼게:\n{$lines}\n\n맞게 정리됐어? (맞아 / 조금 다르게 생각해)";
    $response['summary_lines'] = $summary['summary_lines'] ?? [];
    $response['stance_changed'] = $stanceChanged;
    $response['ui_hint'] = 'reflection_confirm';
    $decision = ['progress_pct' => 85];
    }
} elseif ($phase === 'reflection') {
    $wantsCompose = eduIsReflectionConfirm($message) || mb_strlen(trim($message)) >= 28;
    if (!$wantsCompose) {
        $assistantMessage = '정리가 조금 다르다면 어떻게 생각하는지 말해줘. 맞다면 "맞아"를 눌러줘.';
        $response['ui_hint'] = 'reflection_confirm';
        $decision = ['progress_pct' => 85, 'next_agent' => 'reflection'];
    } else {
        $composed = eduChatApplyReflectionCompose($blueprint, $quest, $dialogue, $llm, $rag, $director, $response);
        $blueprint = $composed['blueprint'];
        $assistantMessage = $composed['assistantMessage'];
        $response = $composed['response'];
        $decision = $composed['decision'];
    }
} else {
    $decision = $director->decide($blueprint, $quest);
    if (!empty($decision['should_compose'])) {
        $blueprint['ready_for_compose'] = true;
        $response['should_compose'] = true;
        $assistantMessage = $decision['prompt_hint'] ?? '이제 글을 만들어볼게.';
    }
}

if ($assistantMessage !== '') {
    $dialogue = eduAppendDialogue($dialogue, 'assistant', $assistantMessage, $decision['next_agent'] ?? 'director', (string) ($blueprint['phase'] ?? $phase));
}

eduSaveBlueprint($supabase, $sessionId, $blueprint, $dialogue);

eduSendJson(array_merge($response, [
    'stage' => eduBlueprintStage($blueprint),
    'phase' => $blueprint['phase'] ?? $phase,
    'assistant_message' => $assistantMessage,
    'progress_pct' => (int) ($decision['progress_pct'] ?? eduBlueprintProgress($blueprint)),
    'blueprint' => $blueprint,
    'needs_followup' => !empty($eval['needs_followup']),
    'feedback_hint' => $eval['feedback_hint'] ?? null,
]));
