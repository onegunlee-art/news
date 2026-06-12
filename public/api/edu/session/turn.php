<?php
/**
 * POST /api/edu/session/turn — 턴 기반 세션 진행
 * 
 * 상태 머신:
 * Turn 0: 입장 선택 → v1 저장
 * Turn 1: "왜?" 질문 → 이유 답변
 * Turn 2: 기사 요약 노출 → 근거 답변
 * Turn 3: 반론 생성 (Hammer)
 * Turn 4: 재답변 → v2 저장 (수정 시)
 * Turn 5: 5문장 글 완성
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/eduAuth.php';
require_once __DIR__ . '/../lib/eduQuest.php';
require_once __DIR__ . '/../lib/_llm.php';

handleOptionsRequest();
setCorsHeaders();
eduRequirePost();

$student = eduRequireStudent();
$supabase = eduSupabase();
$body = eduJsonBody();

$sessionId = trim((string)($body['session_id'] ?? ''));
$turn = (int)($body['turn'] ?? 0);
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
            'confidence_level' => (int)($input['confidence'] ?? 3),
        ]);
        
        $supabase->update('edu_quest_sessions', 'id=eq.' . $sessionId, [
            'stance' => $stance,
            'updated_at' => date('c'),
        ]);
        
        require_once dirname(__DIR__, 4) . '/src/backend/services/edu/Agents/SocraticCoach.php';
        $coach = new \Services\Edu\Agents\SocraticCoach($llm);
        $question = $coach->askWhy($quest, $stance);
        
        $response = [
            'turn' => 1,
            'stage' => 'reasoning',
            'prompt' => $question['question'],
            'ui_label' => '이유 말하기',
        ];
        break;

    case 1:
        $reason = trim((string)($input['reason'] ?? ''));
        if ($reason === '') {
            eduSendError('reason required');
        }
        
        $supabase->insert('edu_thinking_logs', [
            'session_id' => $sessionId,
            'student_id' => $student['id'],
            'turn_number' => 1,
            'agent_role' => 'socratic',
            'student_response' => $reason,
        ]);
        
        $supabase->update('edu_hypothesis_versions', 'session_id=eq.' . $sessionId . '&version=eq.1', [
            'reason' => $reason,
        ]);
        
        $articleSummaries = [];
        foreach ($quest['articles'] as $a) {
            $articleSummaries[] = "- {$a['title']}";
        }
        $summaryText = implode("\n", $articleSummaries);
        
        $response = [
            'turn' => 2,
            'stage' => 'evidence',
            'prompt' => "기사들을 참고해서 네 주장을 뒷받침하는 근거를 찾아봐.",
            'articles_summary' => $summaryText,
            'articles' => $quest['articles'],
            'ui_label' => '근거 찾기',
        ];
        break;

    case 2:
        $evidence = trim((string)($input['evidence'] ?? ''));
        
        $supabase->insert('edu_evidence_logs', [
            'session_id' => $sessionId,
            'student_id' => $student['id'],
            'evidence_text' => $evidence,
            'source_type' => 'student',
        ]);
        
        require_once dirname(__DIR__, 4) . '/src/backend/services/edu/Agents/StanceScorer.php';
        require_once dirname(__DIR__, 4) . '/src/backend/services/edu/Agents/Hammer.php';
        
        $v1 = $supabase->select('edu_hypothesis_versions', 'session_id=eq.' . $sessionId . '&version=eq.1', 1);
        $stance = $v1[0]['stance'] ?? $session['stance'];
        $reason = $v1[0]['reason'] ?? '';
        
        $scorer = new \Services\Edu\Agents\StanceScorer($llm);
        $analysis = $scorer->scoreStance($stance, $reason . ' ' . $evidence, $quest);
        
        $hammer = new \Services\Edu\Agents\Hammer($llm);
        $strike = $hammer->strike($stance, $reason, $quest, $analysis['recommended_hammer_intensity'] ?? 'medium', $analysis);
        
        $supabase->insert('edu_counter_logs', [
            'session_id' => $sessionId,
            'student_id' => $student['id'],
            'counter_argument' => $strike['counter_argument'],
        ]);
        
        $supabase->update('edu_quest_sessions', 'id=eq.' . $sessionId, [
            'stage' => 'hammer',
            'hammer_payload' => $strike,
            'updated_at' => date('c'),
        ]);
        
        $response = [
            'turn' => 3,
            'stage' => 'hammer',
            'counter_argument' => $strike['counter_argument'],
            'counter_stance' => $strike['counter_stance'],
            'prompt' => '이 반론에 대해 어떻게 생각해? 네 입장이 바뀌었어, 아니면 유지해?',
            'ui_label' => '반론 듣기',
        ];
        break;

    case 3:
        $rebuttal = trim((string)($input['rebuttal'] ?? ''));
        $stanceChanged = (bool)($input['stance_changed'] ?? false);
        $newStance = $input['new_stance'] ?? $session['stance'];
        
        $supabase->update('edu_counter_logs', 'session_id=eq.' . $sessionId, [
            'student_rebuttal' => $rebuttal,
            'led_to_stance_change' => $stanceChanged,
        ]);
        
        $finalStance = $stanceChanged ? $newStance : $session['stance'];
        
        $supabase->insert('edu_hypothesis_versions', [
            'session_id' => $sessionId,
            'student_id' => $student['id'],
            'version' => 2,
            'stance' => $finalStance,
            'reason' => $rebuttal,
            'confidence_level' => (int)($input['confidence'] ?? 3),
        ]);
        
        require_once dirname(__DIR__, 4) . '/src/backend/services/edu/Agents/Reflection.php';
        
        $v1 = $supabase->select('edu_hypothesis_versions', 'session_id=eq.' . $sessionId . '&version=eq.1', 1);
        $counter = $supabase->select('edu_counter_logs', 'session_id=eq.' . $sessionId, 1);
        
        $reflection = new \Services\Edu\Agents\Reflection($llm);
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
        require_once dirname(__DIR__, 4) . '/src/backend/services/edu/Agents/WritingBuilder.php';
        
        $v2 = $supabase->select('edu_hypothesis_versions', 'session_id=eq.' . $sessionId . '&version=eq.2', 1);
        $reflections = $supabase->select('edu_reflections', 'session_id=eq.' . $sessionId, 1);
        
        $writer = new \Services\Edu\Agents\WritingBuilder($llm);
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
        
        require_once dirname(__DIR__, 4) . '/src/backend/services/edu/Agents/WritingBuilder.php';
        
        $writer = new \Services\Edu\Agents\WritingBuilder($llm);
        $composed = $writer->composeFromParts([
            'situation' => $sentences[0] ?? '',
            'complication' => $sentences[1] ?? '',
            'question' => $sentences[2] ?? '',
            'answer' => $sentences[3] ?? '',
            'conclusion' => $sentences[4] ?? '',
        ]);
        
        $evaluation = $writer->evaluateWriting($composed['full_text'], $quest);
        
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
        
        $supabase->update('edu_quest_sessions', 'id=eq.' . $sessionId, [
            'stage' => 'completed',
            'completed_at' => date('c'),
            'updated_at' => date('c'),
        ]);
        
        $response = [
            'turn' => 'completed',
            'stage' => 'completed',
            'full_text' => $composed['full_text'],
            'quality_score' => $evaluation['quality_score'] ?? 70,
            'feedback' => $evaluation['feedback'] ?? '잘 정리했어요!',
            'hero_sentence' => $evaluation['hero_sentence'] ?? '',
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
