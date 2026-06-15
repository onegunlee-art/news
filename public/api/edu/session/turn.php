<?php
/**
 * POST /api/edu/session/turn — 턴 기반 세션 진행 (소크라테스 FSM)
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/eduAuth.php';
require_once __DIR__ . '/../lib/eduQuest.php';
require_once __DIR__ . '/../lib/eduTier.php';
require_once __DIR__ . '/../lib/eduConfig.php';
require_once __DIR__ . '/../lib/eduAgents.php';
require_once __DIR__ . '/../lib/_llm.php';

$root = eduFindProjectRoot();
require_once $root . 'src/backend/autoload.php';
eduLoadAgents();

use Services\Edu\Agents\Hammer;
use Services\Edu\Agents\Reflection;
use Services\Edu\Agents\SocraticCoach;
use Services\Edu\Agents\StanceScorer;
use Services\Edu\Agents\WritingBuilder;
use Services\Edu\EduRagService;

handleOptionsRequest();
setCorsHeaders();
eduRequirePost();

$student = eduRequireStudent();
$supabase = eduSupabase();
$body = eduJsonBody();

$sessionId = trim((string) ($body['session_id'] ?? ''));
$turn = (int) ($body['turn'] ?? 0);
$input = $body['input'] ?? [];

if ($sessionId === '') {
    eduSendError('session_id required');
}

$sessions = $supabase->select('edu_quest_sessions', 'id=eq.' . $sessionId . '&student_id=eq.' . $student['id'], 1);
if (empty($sessions[0])) {
    eduSendError('Session not found', 404);
}
$session = $sessions[0];

$quests = $supabase->select('edu_daily_quests', 'id=eq.' . $session['quest_id'], 1);
$quest = $quests[0] ?? [];
$quest['articles'] = $supabase->select('edu_quest_articles', 'quest_id=eq.' . $session['quest_id'] . '&order=sort_order.asc', 20) ?? [];

$llm = eduLlm();
$rag = new EduRagService($supabase);
$response = [];

switch ($turn) {
    case 0:
        $stance = $input['stance'] ?? '';
        if (!in_array($stance, ['pro', 'con'], true)) {
            eduSendError('stance (pro|con) required');
        }

        $supabase->insert('edu_hypothesis_versions', [
            'session_id' => $sessionId,
            'student_id' => $student['id'],
            'version' => 1,
            'stance' => $stance,
            'confidence_level' => (int) ($input['confidence'] ?? 3),
        ]);

        $supabase->update('edu_quest_sessions', 'id=eq.' . $sessionId, [
            'stance' => $stance,
            'stage' => 'commit',
            'updated_at' => date('c'),
        ]);

        $coach = new SocraticCoach($llm);
        $question = $coach->askWhy($quest, $stance);

        $response = [
            'turn' => 1,
            'stage' => 'reasoning',
            'prompt' => $question['question'],
            'ui_label' => '이유 말하기',
        ];
        break;

    case 1:
        $reason = trim((string) ($input['reason'] ?? ''));
        if ($reason === '') {
            eduSendError('reason required');
        }

        $coach = new SocraticCoach($llm);
        $evaluation = $coach->evaluateReason($session['stance'] ?? 'pro', $reason, $quest);

        $supabase->insert('edu_thinking_logs', [
            'session_id' => $sessionId,
            'student_id' => $student['id'],
            'turn_number' => 1,
            'agent_role' => 'socratic',
            'student_response' => $reason,
            'ai_feedback' => $evaluation['feedback_hint'] ?? null,
        ]);

        $supabase->update('edu_hypothesis_versions', 'session_id=eq.' . $sessionId . '&version=eq.1', [
            'reason' => $reason,
        ]);

        if (!empty($evaluation['needs_followup']) && ($evaluation['depth_score'] ?? 5) < 3) {
            $followup = $coach->askWhy($quest, $session['stance'] ?? 'pro', $reason);
            $response = [
                'turn' => 1,
                'stage' => 'reasoning',
                'prompt' => $followup['question'],
                'needs_followup' => true,
                'ui_label' => '조금 더 깊이',
            ];
            break;
        }

        $publicArticles = [];
        foreach ($quest['articles'] as $a) {
            $publicArticles[] = [
                'news_id' => (int) ($a['news_id'] ?? 0),
                'role' => $a['role'] ?? 'context',
                'title' => $a['title'] ?? '',
                'gist_url' => $a['gist_url'] ?? '',
                'excerpt' => $a['excerpt'] ?? '',
                'why_important' => $a['why_important'] ?? '',
                'source_outlet' => $a['source_outlet'] ?? '',
            ];
        }

        $response = [
            'turn' => 2,
            'stage' => 'evidence',
            'prompt' => '기사들을 참고해서 네 주장을 뒷받침하는 근거를 찾아봐.',
            'articles' => $publicArticles,
            'ui_label' => '근거 찾기',
        ];
        break;

    case 2:
        $evidence = trim((string) ($input['evidence'] ?? ''));
        if ($evidence === '') {
            eduSendError('evidence required');
        }

        $supabase->insert('edu_evidence_logs', [
            'session_id' => $sessionId,
            'student_id' => $student['id'],
            'evidence_text' => $evidence,
            'source_type' => 'student',
        ]);

        $v1 = $supabase->select('edu_hypothesis_versions', 'session_id=eq.' . $sessionId . '&version=eq.1', 1);
        $stance = $v1[0]['stance'] ?? $session['stance'];
        $reason = $v1[0]['reason'] ?? '';

        $scorer = new StanceScorer($llm);
        $analysis = $scorer->scoreStance($stance, $reason . ' ' . $evidence, $quest);

        $mixup = eduBuildMixupContext($quest, $rag);
        $mixupContext = $mixup['mixup_context'];
        $mixupSources = $mixup['mixup_sources'];

        $hammer = new Hammer($llm);
        $strike = $hammer->strike(
            $stance,
            $reason . ' ' . $evidence,
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

        $supabase->update('edu_quest_sessions', 'id=eq.' . $sessionId, [
            'stage' => 'hammer',
            'hammer_payload' => $strike,
            'updated_at' => date('c'),
        ]);

        $response = [
            'turn' => 3,
            'stage' => 'hammer',
            'counter_argument' => $strike['counter_argument'],
            'counter_stance' => $strike['counter_stance'] ?? ($stance === 'pro' ? 'con' : 'pro'),
            'mixup_sources' => $mixupSources,
            'hammer_mode' => $strike['mode'] ?? 'adversarial',
            'prompt' => '이 반론에 대해 어떻게 생각해? 네 입장이 바뀌었어, 아니면 유지해?',
            'ui_label' => '반론 듣기',
        ];
        if (($strike['mode'] ?? '') === 'convergent' || ($strike['mode'] ?? '') === 'convergent_meta_ask') {
            $response['student_axis'] = $strike['student_axis'] ?? null;
            $response['counter_axis'] = $strike['counter_axis'] ?? null;
            $response['pivot_question'] = $strike['pivot_question'] ?? null;
        }
        break;

    case 3:
        $rebuttal = trim((string) ($input['rebuttal'] ?? ''));
        if ($rebuttal === '') {
            eduSendError('rebuttal required');
        }
        $stanceChanged = (bool) ($input['stance_changed'] ?? false);
        $newStance = $input['new_stance'] ?? $session['stance'];

        $supabase->update('edu_counter_logs', 'session_id=eq.' . $sessionId, [
            'student_rebuttal' => $rebuttal,
            'led_to_stance_change' => $stanceChanged,
        ]);

        $finalStance = $stanceChanged && in_array($newStance, ['pro', 'con'], true)
            ? $newStance
            : ($session['stance'] ?? 'pro');

        $supabase->insert('edu_hypothesis_versions', [
            'session_id' => $sessionId,
            'student_id' => $student['id'],
            'version' => 2,
            'stance' => $finalStance,
            'reason' => $rebuttal,
            'confidence_level' => (int) ($input['confidence'] ?? 3),
        ]);

        $v1 = $supabase->select('edu_hypothesis_versions', 'session_id=eq.' . $sessionId . '&version=eq.1', 1);
        $counter = $supabase->select('edu_counter_logs', 'session_id=eq.' . $sessionId, 1);

        $reflection = new Reflection($llm);
        $summary = $reflection->summarize(
            $v1[0]['stance'] ?? $session['stance'],
            $v1[0]['reason'] ?? '',
            $counter[0]['counter_argument'] ?? '',
            $rebuttal,
            $finalStance,
            $quest
        );

        $supabase->insert('edu_reflections', [
            'session_id' => $sessionId,
            'student_id' => $student['id'],
            'summary_lines' => $summary['summary_lines'],
            'key_insight' => $summary['key_insight'] ?? '',
            'stance_change_reason' => $stanceChanged ? $rebuttal : null,
        ]);

        $supabase->update('edu_quest_sessions', 'id=eq.' . $sessionId, [
            'stage' => 'reflection',
            'updated_at' => date('c'),
        ]);

        $response = [
            'turn' => 4,
            'stage' => 'reflection',
            'summary_lines' => $summary['summary_lines'],
            'stance_changed' => $stanceChanged,
            'final_stance' => $finalStance,
            'prompt' => '좋아! 이제 네 생각을 5문장으로 정리해볼까?',
            'ui_label' => '3줄 정리',
        ];
        break;

    case 4:
        $v2 = $supabase->select('edu_hypothesis_versions', 'session_id=eq.' . $sessionId . '&version=eq.2', 1);
        $reflections = $supabase->select('edu_reflections', 'session_id=eq.' . $sessionId, 1);

        $newsIds = [];
        foreach ($quest['articles'] as $a) {
            $nid = (int) ($a['news_id'] ?? 0);
            if ($nid > 0) {
                $newsIds[] = $nid;
            }
        }
        $arcContext = $rag->findArcArticles($newsIds, (string) ($quest['conflict_summary'] ?? ''));
        if (!empty($arcContext['alignment'])) {
            $quest['alignment_summary'] = trim(
                ($quest['alignment_summary'] ?? '') . ' / ' . $arcContext['alignment']
            );
        }

        $writer = new WritingBuilder($llm);
        $outline = $writer->buildOutline(
            $v2[0]['stance'] ?? $session['stance'],
            $v2[0]['reason'] ?? '',
            $reflections[0]['summary_lines'] ?? [],
            $quest
        );

        $supabase->update('edu_quest_sessions', 'id=eq.' . $sessionId, [
            'stage' => 'writing',
            'updated_at' => date('c'),
        ]);

        $response = [
            'turn' => 5,
            'stage' => 'writing',
            'outline' => $outline['outline'],
            'prompt' => '각 문장을 채워서 5문장 글을 완성해봐!',
            'ui_label' => '5문장 쓰기',
        ];
        break;

    case 5:
        $sentences = $input['sentences'] ?? [];
        if (!is_array($sentences) || count($sentences) < 5) {
            eduSendError('5 sentences required');
        }

        $writer = new WritingBuilder($llm);
        $composed = $writer->composeFromParts([
            'situation' => $sentences[0] ?? '',
            'complication' => $sentences[1] ?? '',
            'question' => $sentences[2] ?? '',
            'answer' => $sentences[3] ?? '',
            'conclusion' => $sentences[4] ?? '',
        ]);

        $judgmentPatterns = '';
        if (eduJudgmentWritingEnabled()) {
            $patterns = $rag->getWritingPatterns((string) ($quest['quest_title'] ?? ''), 3);
            $judgmentPatterns = $rag->formatWritingPatterns($patterns);
        }

        $evaluation = $writer->evaluateWriting($composed['full_text'], $quest, $judgmentPatterns);

        $supabase->insert('edu_writing_versions', [
            'session_id' => $sessionId,
            'student_id' => $student['id'],
            'version' => 1,
            'scqa_situation' => $sentences[0] ?? '',
            'scqa_complication' => $sentences[1] ?? '',
            'scqa_question' => $sentences[2] ?? '',
            'scqa_answer' => $sentences[3] ?? '',
            'conclusion' => $sentences[4] ?? '',
            'word_count' => $composed['word_count'],
            'quality_score' => $evaluation['quality_score'] ?? 70,
            'ai_feedback' => $evaluation['feedback'] ?? '',
        ]);

        $hero = $evaluation['hero_sentence'] ?? eduExtractHeroSentence($sentences);
        $draftPayload = [
            'v1_sentences' => array_slice($sentences, 0, 5),
            'v2_sentences' => array_slice($sentences, 0, 5),
            'hero_sentence' => $hero,
            'stance_delta' => 'refined',
            'teacher_feedback' => $evaluation['feedback'] ?? '',
            'updated_at' => date('c'),
        ];
        $existingDrafts = $supabase->select('edu_writing_drafts', 'session_id=eq.' . $sessionId, 1);
        if (!empty($existingDrafts[0])) {
            $supabase->update('edu_writing_drafts', 'session_id=eq.' . $sessionId, $draftPayload);
        } else {
            $supabase->insert('edu_writing_drafts', array_merge($draftPayload, [
                'session_id' => $sessionId,
                'student_id' => $student['id'],
            ]));
        }

        $xpQuest = 80;
        $xpWriting = 40;
        eduAwardXp($supabase, $student['id'], $xpQuest, 'quest_complete', $sessionId, ['quest_complete' => true]);
        $tierRow = eduAwardXp($supabase, $student['id'], $xpWriting, 'writing_v2', $sessionId, ['writing_v2' => true]);

        $supabase->update('edu_quest_sessions', 'id=eq.' . $sessionId, [
            'stage' => 'completed',
            'completed_at' => date('c'),
            'updated_at' => date('c'),
        ]);

        $response = [
            'turn' => 'completed',
            'stage' => 'completed',
            'full_text' => $composed['full_text'],
            'scqa_parts' => $composed['scqa_parts'] ?? [],
            'quality_score' => $evaluation['quality_score'] ?? 70,
            'feedback' => $evaluation['feedback'] ?? '잘 정리했어요!',
            'hero_sentence' => $hero,
            'xp_gained' => $xpQuest + $xpWriting,
            'tier' => eduTierProgressPayload($tierRow),
            'ui_label' => '완료!',
        ];
        break;

    default:
        eduSendError('Invalid turn');
}

eduSendJson(array_merge([
    'success' => true,
    'session_id' => $sessionId,
], $response));
